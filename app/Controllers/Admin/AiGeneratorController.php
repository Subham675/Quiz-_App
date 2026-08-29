<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Quiz;
use App\Models\Question;

class AiGeneratorController extends Controller
{
    public function index(Request $request): void
    {
        $quizzes = Quiz::allActive();

        $this->render('admin/ai-generator', [
            'pageTitle' => 'AI Quiz & Question Generator',
            'activeNav' => 'ai-generator',
            'quizzes'   => $quizzes,
            'error'     => $_SESSION['admin_ai_error'] ?? '',
            'success'   => $_SESSION['admin_ai_success'] ?? '',
        ], 'admin');
        unset($_SESSION['admin_ai_error'], $_SESSION['admin_ai_success']);
    }

    public function generate(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_ai_error'] = 'Session expired.';
            $this->redirect('/admin/ai-generator');
        }

        $quizId     = (int)$request->input('quiz_id');
        $topic      = trim($request->input('topic', ''));
        $count      = min(20, max(1, (int)$request->input('count', 5)));
        $difficulty = $request->input('difficulty', 'medium');
        $apiKey     = $_ENV['GEMINI_API_KEY'] ?? '';

        if (empty($topic) || $quizId <= 0) {
            $_SESSION['admin_ai_error'] = 'Please select a quiz and enter a topic.';
            $this->redirect('/admin/ai-generator');
        }

        if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
            $_SESSION['admin_ai_error'] = 'Gemini API key is not configured in .env file.';
            $this->redirect('/admin/ai-generator');
        }

        $prompt = "Generate {$count} multiple-choice questions on the topic '{$topic}' at '{$difficulty}' difficulty level.
Return ONLY valid raw JSON in this exact structure without markdown backticks:
[
  {
    \"question\": \"Question text here?\",
    \"options\": [
      {\"text\": \"Option A\", \"is_correct\": false},
      {\"text\": \"Option B\", \"is_correct\": true},
      {\"text\": \"Option C\", \"is_correct\": false},
      {\"text\": \"Option D\", \"is_correct\": false}
    ],
    \"marks\": 1,
    \"tag\": \"{$topic}\"
  }
]";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseMimeType' => 'application/json',
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            $_SESSION['admin_ai_error'] = "AI generation failed (HTTP {$httpCode}): {$curlErr}";
            $this->redirect('/admin/ai-generator');
        }

        $result = json_decode($response, true);
        $rawJson = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $rawJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawJson));
        $questions = json_decode($rawJson, true);

        if (!is_array($questions) || empty($questions)) {
            $_SESSION['admin_ai_error'] = 'Failed to parse AI question output.';
            $this->redirect('/admin/ai-generator');
        }

        $insertedCount = 0;
        foreach ($questions as $q) {
            if (!empty($q['question']) && !empty($q['options']) && is_array($q['options'])) {
                $qId = Question::create($quizId, $q['question'], (int)($q['marks'] ?? 1), $difficulty, $q['tag'] ?? $topic);
                foreach ($q['options'] as $opt) {
                    Question::saveOption($qId, $opt['text'], !empty($opt['is_correct']));
                }
                $insertedCount++;
            }
        }

        $_SESSION['admin_ai_success'] = "Successfully generated and added {$insertedCount} questions!";
        $this->redirect('/admin/questions?quiz_id=' . $quizId);
    }
}
