<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/certificate.php';

$db        = getDB();
$userId    = $_SESSION['user_id'];
$attemptId = (int)($_GET['attempt'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, u.name AS user_name, q.title AS quiz_title
    FROM attempts a
    JOIN users u   ON u.id = a.user_id
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.id = ? AND a.user_id = ?
");
$stmt->execute([$attemptId, $userId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header('Location: my-attempts.php');
    exit;
}

$answersStmt = $db->prepare("
    SELECT q.question_text, aa.is_correct,
           o_sel.option_text AS selected_text,
           o_correct.option_text AS correct_text
    FROM attempt_answers aa
    JOIN questions q ON q.id = aa.question_id
    LEFT JOIN options o_sel     ON o_sel.id = aa.selected_option_id
    LEFT JOIN options o_correct ON o_correct.question_id = q.id AND o_correct.is_correct = 1
    WHERE aa.attempt_id = ?
    ORDER BY q.order_index ASC, q.id ASC
");
$answersStmt->execute([$attemptId]);
$answers = $answersStmt->fetchAll();

if (!is_dir(CERT_DIR)) {
    mkdir(CERT_DIR, 0775, true);
}

$tmpPath = sys_get_temp_dir() . '/result_' . $attemptId . '_' . uniqid() . '.pdf';
buildResultPdf($tmpPath, $attempt, $answers);

$fileName = 'Result_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $attempt['quiz_title']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($tmpPath));
readfile($tmpPath);
unlink($tmpPath);
exit;