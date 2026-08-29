<?php
$pageTitle = 'Quiz Result';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db        = getDB();
$userId    = $_SESSION['user_id'];
$attemptId = (int)($_GET['attempt'] ?? 0);

$stmt = $db->prepare("
    SELECT a.*, q.title AS quiz_title, q.id AS quiz_id, q.negative_marking
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

$pct    = $attempt['total_marks'] > 0
        ? round($attempt['score'] * 100 / $attempt['total_marks'])
        : 0;
$passed = $pct >= 60;

$certificate = null;
if ($passed) {
    require_once __DIR__ . '/../includes/certificate.php';
    $certificate = generateCertificateIfEligible($attemptId);
}

// Fetch answer breakdown including explanation
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

// Negative marking breakdown
$negativeMarking = (float)($attempt['negative_marking'] ?? 0);
$correctCount    = array_sum(array_column($details, 'is_correct'));
$wrongCount      = count(array_filter($details, fn($d) => $d['selected_option_id'] && !$d['is_correct']));
$skippedCount    = count(array_filter($details, fn($d) => !$d['selected_option_id']));
$totalDeductions = $negativeMarking > 0 ? $wrongCount * $negativeMarking : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="quiz-wrapper">
    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warning mb-4">⏱️ <strong>Time Expired!</strong> The quiz reached the server time limit and was automatically submitted.</div>
    <?php elseif (isset($_GET['already'])): ?>
        <div class="alert alert-warning mb-4">You've already completed this quiz. Each quiz can only be attempted once — here's your result from your first attempt.</div>
    <?php endif; ?>

    <div class="card mb-4 text-center">
        <div class="card-body p-4 p-md-5">
            <p class="text-muted small mb-1"><?= htmlspecialchars($attempt['quiz_title']) ?></p>
            <div class="result-score <?= $passed ? 'result-passed' : 'result-failed' ?> display-3 fw-bold mb-2"><?= $pct ?>%</div>
            <div>
                <span class="badge rounded-pill <?= $passed ? 'bg-success' : 'bg-danger' ?> px-3 py-2 fs-6">
                    <?= $passed ? '🎉 Passed' : '❌ Not Passed' ?>
                </span>
            </div>

            <p class="text-muted small mt-3 mb-3">
                Score: <strong><?= $attempt['score'] ?> / <?= $attempt['total_marks'] ?></strong> marks
                · Time taken: <strong><?= gmdate('i:s', $attempt['time_taken_seconds']) ?></strong>
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
                <?php if ($negativeMarking > 0): ?>
                <div class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 fw-medium">
                    ➖ Negative deduction: <strong>−<?= number_format($totalDeductions, 2) ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab switch warning -->
            <?php if ((int)($attempt['tab_switch_count'] ?? 0) > 0): ?>
            <div class="alert alert-warning small py-2 px-3 my-3 d-inline-block text-start">
                ⚠️ <strong><?= (int)$attempt['tab_switch_count'] ?> tab switch<?= $attempt['tab_switch_count'] > 1 ? 'es' : '' ?> detected</strong> during this attempt — this was logged.
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
                <a href="quiz-list.php" class="btn btn-outline-secondary btn-sm">Browse more quizzes</a>
                <a href="download-result.php?attempt=<?= $attemptId ?>" class="btn btn-outline-secondary btn-sm">Download result (PDF)</a>
                <?php if ($certificate): ?>
                    <a href="/Quiz_app/<?= htmlspecialchars($certificate['cert_path']) ?>" target="_blank" class="btn btn-success btn-sm">
                        🏆 Download Certificate
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h5 class="fw-semibold mb-3">Answer Breakdown</h5>

    <?php foreach ($details as $i => $d): ?>
    <div class="question-card mb-3">
        <div class="question-number">Question <?= $i + 1 ?> · <?= $d['marks'] ?> mark<?= $d['marks'] > 1 ? 's' : '' ?></div>
        <div class="question-text"><?= htmlspecialchars($d['question_text']) ?></div>

        <div class="option-label <?= $d['is_correct'] ? 'correct' : 'wrong' ?>">
            Your answer: <?= htmlspecialchars($d['selected_text'] ?? 'Skipped') ?>
        </div>

        <?php if (!$d['is_correct']): ?>
        <div class="option-label correct">
            Correct answer: <?= htmlspecialchars($d['correct_text'] ?? '—') ?>
        </div>

        <?php if (!empty($d['explanation'])): ?>
        <div class="p-3 bg-light border-start border-3 border-primary rounded-end small mt-2">
            <strong class="text-primary text-uppercase d-block mb-1">💡 AI Explanation</strong>
            <?= htmlspecialchars($d['explanation']) ?>
        </div>
        <?php elseif (!$d['selected_option_id']): ?>
        <div class="small text-muted mt-2">You skipped this question.</div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>