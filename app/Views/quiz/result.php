<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Quiz Result</h1>
            <p class="page-subtitle"><?= htmlspecialchars($attempt['quiz_title']) ?></p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-1"></i>Back to Quizzes
        </a>
    </div>
</div>

<?php if ($timedOut): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
        <div><strong>Time Expired:</strong> Your time limit ended and the quiz was automatically submitted.</div>
    </div>
<?php endif; ?>

<!-- Summary Score Card -->
<div class="card shadow-sm border-0 mb-4 text-center p-4">
    <div class="row align-items-center">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="display-3 fw-bold <?= $passed ? 'text-success' : 'text-danger' ?>"><?= $pct ?>%</div>
            <div class="fw-semibold text-muted">Final Score</div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0 border-start border-end">
            <div class="fs-4 fw-bold text-dark"><?= number_format($attempt['score'], 2) ?> / <?= $attempt['total_marks'] ?></div>
            <div class="text-muted small">Total Marks Obtained</div>
            <div class="mt-2">
                <span class="badge <?= $passed ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-2">
                    <?= $passed ? '🎉 Passed' : 'Needs Improvement' ?>
                </span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-muted small mb-1">Time Taken: <strong><?= gmdate('i:s', (int)$attempt['time_taken_seconds']) ?></strong></div>
            <?php if (!empty($attempt['tab_switch_count'])): ?>
                <div class="text-warning small mb-3"><i class="bi bi-exclamation-circle me-1"></i>Tab switches: <?= (int)$attempt['tab_switch_count'] ?></div>
            <?php endif; ?>
            
            <?php if ($passed && !empty($certificate)): ?>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/<?= htmlspecialchars($certificate['cert_path']) ?>" class="btn btn-success w-100" download>
                    <i class="bi bi-award me-1"></i>Download Certificate (PDF)
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Detailed Question Answers -->
<h5 class="fw-bold mb-3">Detailed Answers & Explanations</h5>
<div class="space-y-3">
    <?php foreach ($details as $idx => $d): ?>
    <div class="card shadow-sm border-0 mb-3 <?= $d['is_correct'] ? 'border-start border-success border-4' : 'border-start border-danger border-4' ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted small">Question <?= $idx + 1 ?></span>
                <?php if ($d['is_correct']): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Correct (+<?= $d['marks'] ?>)</span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Incorrect</span>
                <?php endif; ?>
            </div>
            <h6 class="fw-bold mb-3"><?= htmlspecialchars($d['question_text']) ?></h6>

            <div class="p-3 bg-light rounded mb-2 small">
                <div><strong>Your Answer:</strong> <span class="<?= $d['is_correct'] ? 'text-success fw-semibold' : 'text-danger' ?>"><?= htmlspecialchars($d['selected_text'] ?? 'No answer selected') ?></span></div>
                <?php if (!$d['is_correct'] && !empty($d['correct_text'])): ?>
                    <div class="mt-1 text-success"><strong>Correct Answer:</strong> <?= htmlspecialchars($d['correct_text']) ?></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($d['explanation'])): ?>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle me-1"></i><strong>Explanation:</strong> <?= htmlspecialchars($d['explanation']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
