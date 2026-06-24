<?php
$pageTitle = 'Quiz Result';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db        = getDB();
$userId    = $_SESSION['user_id'];
$attemptId = (int)($_GET['attempt'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, q.title AS quiz_title, q.id AS quiz_id
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.id = ? AND a.user_id = ?
");
$stmt->execute([$attemptId, $userId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header('Location: my-attempts.php');
    exit;
}

$pct = $attempt['total_marks'] > 0
    ? round($attempt['score'] * 100 / $attempt['total_marks'])
    : 0;
$passed = $pct >= 60;

$certificate = null;
if ($passed) {
    require_once __DIR__ . '/../includes/certificate.php';
    $certificate = generateCertificateIfEligible($attemptId);
}

$detailsStmt = $db->prepare("
    SELECT q.question_text, q.marks,
           aa.selected_option_id, aa.is_correct,
           o_sel.option_text AS selected_text,
           o_correct.option_text AS correct_text
    FROM attempt_answers aa
    JOIN questions q ON q.id = aa.question_id
    LEFT JOIN options o_sel     ON o_sel.id = aa.selected_option_id
    LEFT JOIN options o_correct ON o_correct.question_id = q.id AND o_correct.is_correct = 1
    WHERE aa.attempt_id = ?
    ORDER BY q.order_index ASC, q.id ASC
");
$detailsStmt->execute([$attemptId]);
$details = $detailsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="quiz-wrapper">
    <?php if (isset($_GET['already'])): ?>
        <div class="alert alert-warning">You've already completed this quiz. Each quiz can only be attempted once — here's your result from your first attempt.</div>
    <?php endif; ?>
    <div class="card" style="text-align:center;padding:36px">
        <div class="page-subtitle"><?= htmlspecialchars($attempt['quiz_title']) ?></div>
        <div class="result-score <?= $passed ? 'result-passed' : 'result-failed' ?>"><?= $pct ?>%</div>
        <span class="badge <?= $passed ? 'badge-success' : 'badge-danger' ?>">
            <?= $passed ? 'Passed' : 'Not passed' ?>
        </span>
        <p style="margin-top:14px;color:var(--muted);font-size:13.5px">
            Score: <?= $attempt['score'] ?> / <?= $attempt['total_marks'] ?> marks
            · Time taken: <?= gmdate('i:s', $attempt['time_taken_seconds']) ?>
        </p>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:20px">
            <a href="quiz-list.php" class="btn btn-outline btn-sm">Browse more quizzes</a>
            <a href="download-result.php?attempt=<?= $attemptId ?>" class="btn btn-outline btn-sm">Download result (PDF)</a>
            <?php if ($certificate): ?>
                <a href="/Quiz_app/<?= htmlspecialchars($certificate['cert_path']) ?>" target="_blank" class="btn btn-sm" style="background:var(--success);color:#fff;border-color:var(--success)">
                    Download certificate
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-title" style="margin:24px 0 12px">Answer breakdown</div>

    <?php foreach ($details as $i => $d): ?>
    <div class="question-card">
        <div class="question-number">Question <?= $i + 1 ?> · <?= $d['marks'] ?> mark<?= $d['marks'] > 1 ? 's' : '' ?></div>
        <div class="question-text"><?= htmlspecialchars($d['question_text']) ?></div>

        <div class="option-label <?= $d['is_correct'] ? 'correct' : 'wrong' ?>">
            Your answer: <?= htmlspecialchars($d['selected_text'] ?? 'Skipped') ?>
        </div>

        <?php if (!$d['is_correct']): ?>
        <div class="option-label correct">
            Correct answer: <?= htmlspecialchars($d['correct_text'] ?? '—') ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>