<?php
/**
 * Gemini API integration.
 * Requires GEMINI_API_KEY in .env (free tier: https://aistudio.google.com/apikey)
 */

// Standard Google Gemini models in priority order for robust fallback
const GEMINI_MODELS = [
    'gemini-2.5-flash',
    'gemini-2.0-flash',
    'gemini-1.5-flash',
    'gemini-2.5-flash-lite',
    'gemini-1.5-flash-8b'
];

/**
 * Low-level call to Gemini with automatic model fallback. Returns raw text response or throws on failure.
 */
function callGemini(string $prompt, bool $jsonMode = true, int $maxTokens = 4096): string
{
    $apiKey = trim($_ENV['GEMINI_API_KEY'] ?? '');
    if (empty($apiKey) || $apiKey === 'paste_your_key_here' || $apiKey === 'your_gemini_api_key_here') {
        throw new Exception('Gemini API key is not configured in .env file.');
    }

    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature'     => 0.5,
            'maxOutputTokens' => $maxTokens,
        ],
    ];

    if ($jsonMode) {
        $body['generationConfig']['responseMimeType'] = 'application/json';
    }

    $modelsToTry = GEMINI_MODELS;
    $lastError   = null;

    foreach ($modelsToTry as $modelName) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent";

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $ch = curl_init($endpoint . '?key=' . urlencode($apiKey));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode($body),
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TCP_NODELAY    => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_ENCODING       => '',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $lastError = new Exception('Network error calling Gemini: ' . $curlErr);
                break; // try next model or retry
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text !== null && trim($text) !== '') {
                    return $text;
                }
                $lastError = new Exception('Gemini returned empty content.');
            } else {
                $data = json_decode($response, true);
                $msg  = $data['error']['message'] ?? 'HTTP ' . $httpCode;
                $lastError = new Exception("Gemini API error ({$httpCode} with {$modelName}): {$msg}");
                
                // If 404 (model not found/available), break to try next model immediately
                if ($httpCode === 404) {
                    break;
                }
                // If rate limited or transient error, retry briefly
                if (in_array($httpCode, [429, 500, 503], true) && $attempt < 2) {
                    usleep(600000);
                } else {
                    break; // try next fallback model
                }
            }
        }
    }

    throw $lastError ?? new Exception('Failed to communicate with Gemini API.');
}

/**
 * Generate MCQ questions for a topic.
 * Returns array of: ['question' => ..., 'marks' => 1, 'options' => [['text'=>.., 'correct'=>bool], ...]]
 */
function generateQuizQuestions(string $topic, int $count, string $difficulty): array
{
    $count = max(1, min($count, 50)); // Allow up to 50 questions

    $prompt = <<<PROMPT
You are an expert educational quiz creator. Generate exactly {$count} multiple-choice questions on the topic "{$topic}" at "{$difficulty}" difficulty.
Return ONLY a valid raw JSON array of objects with no markdown backticks and no introductory text.

JSON Schema format:
[
  {
    "question": "Question text here?",
    "options": [
      {"text": "Option 1", "is_correct": true},
      {"text": "Option 2", "is_correct": false},
      {"text": "Option 3", "is_correct": false},
      {"text": "Option 4", "is_correct": false}
    ],
    "marks": 1
  }
]
PROMPT;

    $maxTokens = min(8192, max(2048, 300 * $count + 500));
    $raw = callGemini($prompt, true, $maxTokens);

    // Strip markdown formatting fences if present
    $cleanJson = trim($raw);
    $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
    $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
    $cleanJson = trim($cleanJson);

    $parsed = json_decode($cleanJson, true);

    // If direct decode failed, try regex extracting bracketed JSON
    if (!is_array($parsed)) {
        if (preg_match('/\[\s*\{.*\}\s*\]/s', $cleanJson, $matches)) {
            $parsed = json_decode($matches[0], true);
        }
    }

    // If response was wrapped in an object like {"questions": [...]}
    if (is_array($parsed) && isset($parsed['questions']) && is_array($parsed['questions'])) {
        $parsed = $parsed['questions'];
    }

    if (!is_array($parsed) || empty($parsed)) {
        throw new Exception('Gemini returned invalid JSON for questions. Please try again.');
    }

    $questions = [];
    foreach ($parsed as $item) {
        if (empty($item['question']) || empty($item['options']) || !is_array($item['options'])) {
            continue;
        }

        $options = [];
        // Handle options whether array of strings or array of objects
        if (isset($item['options'][0]) && is_string($item['options'][0])) {
            $correctIdx = (int)($item['correct_index'] ?? 0);
            foreach ($item['options'] as $i => $optText) {
                $options[] = ['text' => trim((string)$optText), 'correct' => ($i === $correctIdx)];
            }
        } else {
            foreach ($item['options'] as $opt) {
                if (is_array($opt) && isset($opt['text'])) {
                    $isCorr = !empty($opt['is_correct']) || !empty($opt['correct']);
                    $options[] = ['text' => trim((string)$opt['text']), 'correct' => $isCorr];
                }
            }
        }

        // Ensure at least one option is marked correct
        $hasCorrect = false;
        foreach ($options as $opt) {
            if ($opt['correct']) {
                $hasCorrect = true;
                break;
            }
        }
        if (!$hasCorrect && !empty($options)) {
            $options[0]['correct'] = true;
        }

        if (count($options) >= 2) {
            $questions[] = [
                'question' => trim((string)$item['question']),
                'marks'    => (int)($item['marks'] ?? 1),
                'options'  => $options,
            ];
        }
    }

    if (empty($questions)) {
        throw new Exception('Could not parse valid questions from the AI output. Please try again.');
    }

    return $questions;
}

/**
 * Generate related learning concepts / subtopics for a searched topic using Gemini.
 * Returns array of strings: ['Concept 1', 'Concept 2', ...]
 */
function generateRelatedConcepts(string $topic, string $quizContext = ''): array
{
    $prompt = "For the search topic '{$topic}'" . ($quizContext ? " in the subject/quiz context of '{$quizContext}'" : "") . ", generate 6 to 8 concise, specific key learning concepts or sub-topics for multiple choice quiz questions.
Return ONLY a valid raw JSON array of strings, like [\"Concept A\", \"Concept B\", \"Concept C\"]. No markdown backticks, no explanations.";

    try {
        $raw = callGemini($prompt, true, 600);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
        $parsed = json_decode($raw, true);

        if (is_array($parsed)) {
            $cleaned = [];
            foreach ($parsed as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $cleaned[] = trim($item);
                } elseif (is_array($item) && isset($item['concept'])) {
                    $cleaned[] = trim($item['concept']);
                }
            }
            if (!empty($cleaned)) {
                return array_slice($cleaned, 0, 8);
            }
        }
    } catch (\Exception $e) {
        error_log('Concept suggestion error: ' . $e->getMessage());
    }

    return [];
}


/**
 * Evaluate a student's free-text/descriptive answer against a model answer using AI.
 * Returns: ['score_percent' => int, 'feedback' => string, 'strengths' => string, 'improvements' => string]
 */
function evaluateDescriptiveAnswer(string $question, string $modelAnswer, string $studentAnswer): array
{
    $prompt = <<<PROMPT
Grade this student answer. Be concise, no markdown.
Question: {$question}
Model answer: {$modelAnswer}
Student answer: {$studentAnswer}
JSON only, this exact shape:
{"score_percent":0,"feedback":"...","strengths":"...","improvements":"..."}
PROMPT;

    $raw = callGemini($prompt, true, 600);
    $parsed = json_decode($raw, true);

    if (!is_array($parsed) || !isset($parsed['score_percent'])) {
        throw new Exception('Gemini returned invalid JSON for evaluation.');
    }

    return [
        'score_percent' => max(0, min(100, (int)$parsed['score_percent'])),
        'feedback'      => trim($parsed['feedback'] ?? ''),
        'strengths'     => trim($parsed['strengths'] ?? ''),
        'improvements'  => trim($parsed['improvements'] ?? ''),
    ];
}

/**
 * Generate clear and concise explanations for quiz questions (especially wrong answers).
 * $items: array of ['id' => int/string, 'question' => string, 'correct_answer' => string, 'user_answer' => string]
 * Returns: array of [id => explanation_string]
 */
function generateAnswerExplanations(array $items): array
{
    if (empty($items)) return [];

    $itemList = [];
    foreach ($items as $item) {
        $id = $item['id'];
        $q = $item['question'];
        $c = $item['correct_answer'];
        $u = $item['user_answer'] ?? 'None / Skipped';
        $itemList[] = [
            'id' => $id,
            'question' => $q,
            'correct_answer' => $c,
            'user_answer' => $u
        ];
    }

    $jsonInput = json_encode($itemList);
    $prompt = <<<PROMPT
For each quiz question item below, provide a short, accurate, 1-2 sentence explanation of why the correct answer is right and why the user's answer was incorrect.
Return ONLY a valid JSON array of objects with keys "id" and "explanation". No markdown formatting or extra text.

Items:
{$jsonInput}
PROMPT;

    try {
        $raw = callGemini($prompt, true, min(4096, count($items) * 200 + 300));
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) return [];

        $result = [];
        foreach ($parsed as $entry) {
            if (isset($entry['id'], $entry['explanation'])) {
                $result[$entry['id']] = trim($entry['explanation']);
            }
        }
        return $result;
    } catch (Exception $e) {
        error_log('Failed to generate AI explanations: ' . $e->getMessage());
        return [];
    }
}