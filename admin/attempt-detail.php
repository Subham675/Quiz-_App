<?php
$pageTitle = 'Attempt Detail';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db        = getDB();
$attemptId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, q.title AS quiz_title, q.id AS quiz_id, q.negative_marking,
           u.name AS user_name, u.email AS user_email
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    JOIN users u   ON u.id = a.user_id
    WHERE a.id = ?
");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header('Location: index.php');
    exit;
}

$pct    = $attempt['total_marks'] > 0
        ? round($attempt['score'] * 100 / $attempt['total_marks'])
        : 0;
$passed = $pct >= 60;

// Fetch answer breakdown
$detailsStmt = $db->prepare("
    SELECT q.question_text, q.marks,
           aa.selected_option_id, aa.is_correct, aa.explanation,
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

$negativeMarking = (float)($attempt['negative_marking'] ?? 0);
$correctCount    = array_sum(array_column($details, 'is_correct'));
$wrongCount      = count(array_filter($details, fn($d) => $d['selected_option_id'] && !$d['is_correct']));
$skippedCount    = count(array_filter($details, fn($d) => !$d['selected_option_id']));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="page-title">Attempt Detail</h1>
    <p class="page-subtitle">
        <a href="index.php" class="text-decoration-none">&larr; Back to dashboard</a> · Student: <strong><?= htmlspecialchars($attempt['user_name']) ?></strong> (<?= htmlspecialchars($attempt['user_email']) ?>)
    </p>
</div>

<div class="card mb-4 text-center">
    <div class="card-body p-4 p-md-5">
        <p class="text-muted small mb-1"><?= htmlspecialchars($attempt['quiz_title']) ?></p>
        <div class="display-3 fw-bold mb-2 <?= $passed ? 'text-success' : 'text-danger' ?>"><?= $pct ?>%</div>
        <div>
            <span class="badge rounded-pill <?= $passed ? 'bg-success' : 'bg-danger' ?> px-3 py-2 fs-6">
                <?= $passed ? '🎉 Passed' : '❌ Failed' ?>
            </span>
        </div>

        <p class="text-muted small mt-3 mb-3">
            Score: <strong><?= $attempt['score'] ?> / <?= $attempt['total_marks'] ?></strong> marks
            · Time taken: <strong><?= gmdate('i:s', $attempt['time_taken_seconds']) ?></strong>
            · Submitted: <strong><?= date('d M Y, h:i A', strtotime($attempt['submitted_at'])) ?></strong>
        </p>

        <!-- Score breakdown -->
        <div class="d-flex gap-2 justify-content-center flex-wrap my-3">
            <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 fw-medium">
                ✅ Correct: <strong><?= $correctCount ?></strong>
            </div>
            <div class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 fw-medium">
                ❌ Wrong: <strong><?= $wrongCount ?></strong>
            </div>
            <div class="badge bg-light text-dark border px-3 py-2 fw-medium">
                ⏭️ Skipped: <strong><?= $skippedCount ?></strong>
            </div>
            <?php if ((int)($attempt['tab_switch_count'] ?? 0) > 0): ?>
            <div class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25 px-3 py-2 fw-medium">
                ⚠️ Tab switches: <strong><?= (int)$attempt['tab_switch_count'] ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<h5 class="fw-semibold mb-3">Questions & Answers Breakdown</h5>

<?php foreach ($details as $i => $d): ?>
<div class="question-card mb-3">
    <div class="question-number">Question <?= $i + 1 ?> · <?= $d['marks'] ?> mark<?= $d['marks'] > 1 ? 's' : '' ?></div>
    <div class="question-text"><?= htmlspecialchars($d['question_text']) ?></div>

    <div class="option-label <?= $d['is_correct'] ? 'correct' : 'wrong' ?>">
        Student answer: <?= htmlspecialchars($d['selected_text'] ?? 'Skipped') ?>
    </div>

    <?php if (!$d['is_correct']): ?>
    <div class="option-label correct">
        Correct answer: <?= htmlspecialchars($d['correct_text'] ?? '—') ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($d['explanation'])): ?>
    <div class="p-3 bg-light border-start border-3 border-primary rounded-end small mt-2">
        <strong class="text-primary text-uppercase d-block mb-1">💡 Explanation</strong>
        <?= htmlspecialchars($d['explanation']) ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>