<?php
/**
 * Gemini API integration.
 * Requires GEMINI_API_KEY in .env (free tier: https://aistudio.google.com/apikey)
 */

define('GEMINI_MODEL', 'gemini-2.5-flash-lite');
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

/**
 * Low-level call to Gemini. Returns the raw text response or throws on failure.
 */
function callGemini(string $prompt, bool $jsonMode = true, int $maxTokens = 2048): string
{
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    if (empty($apiKey) || $apiKey === 'paste_your_key_here') {
        throw new Exception('Gemini API key is not set in .env (GEMINI_API_KEY).');
    }

    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature'     => 0.6,
            'maxOutputTokens' => $maxTokens,
        ],
    ];

    if ($jsonMode) {
        $body['generationConfig']['responseMimeType'] = 'application/json';
    }

    // Transient errors (model overloaded / rate-limited / momentary server fault)
    // are common on the free tier and usually clear up within a second or two,
    // so retry a couple of times before giving up. Anything else (bad request,
    // invalid key, model not found) won't be fixed by retrying.
    $maxAttempts   = 3;
    $retryableCodes = [429, 500, 503];
    $lastError     = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init(GEMINI_ENDPOINT . '?key=' . urlencode($apiKey));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TCP_NODELAY    => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2TLS,
            CURLOPT_ENCODING       => '', // accept gzip/deflate — smaller payload, faster transfer
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $lastError = new Exception('Network error calling Gemini: ' . $curlErr);
        } elseif ($httpCode !== 200) {
            $data = json_decode($response, true);
            $msg  = $data['error']['message'] ?? 'Unknown error';
            $lastError = new Exception("Gemini API error ({$httpCode}): {$msg}");
            if (!in_array($httpCode, $retryableCodes, true)) {
                throw $lastError; // not worth retrying, fail fast
            }
        } else {
            $data = json_decode($response, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text === null) {
                throw new Exception('Gemini returned no content. It may have blocked the response.');
            }
            return $text;
        }

        if ($attempt < $maxAttempts) {
            usleep(500000 * $attempt); // 0.5s, then 1s backoff before retrying
        }
    }

    throw $lastError;
}

/**
 * Generate MCQ questions for a topic.
 * Returns array of: ['question' => ..., 'marks' => 1, 'options' => [['text'=>.., 'correct'=>bool], ...]]
 */
function generateQuizQuestions(string $topic, int $count, string $difficulty): array
{
    $count = max(1, min($count, 20)); // safety cap

    $prompt = <<<PROMPT
Generate exactly {$count} multiple-choice quiz questions about "{$topic}" at {$difficulty} difficulty.
Each question: exactly 4 options, exactly 1 correct. Be concise. No explanations, no markdown.
JSON array only, this exact shape:
[{"question":"...","options":["A","B","C","D"],"correct_index":0}]
PROMPT;

    $maxTokens = min(4096, 150 * $count + 200);
    $raw = callGemini($prompt, true, $maxTokens);
    $parsed = json_decode($raw, true);

    if (!is_array($parsed)) {
        throw new Exception('Gemini returned invalid JSON for questions.');
    }

    $questions = [];
    foreach ($parsed as $item) {
        if (empty($item['question']) || empty($item['options']) || count($item['options']) < 2) {
            continue;
        }
        $correctIndex = (int)($item['correct_index'] ?? 0);
        $options = [];
        foreach ($item['options'] as $i => $optText) {
            $options[] = ['text' => trim($optText), 'correct' => $i === $correctIndex];
        }
        $questions[] = [
            'question' => trim($item['question']),
            'marks'    => 1,
            'options'  => $options,
        ];
    }

    if (empty($questions)) {
        throw new Exception('Gemini returned no usable questions. Try a different topic or fewer questions.');
    }

    return $questions;
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