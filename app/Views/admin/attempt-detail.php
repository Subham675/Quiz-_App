<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Attempt #<?= $attempt['id'] ?> Details</h1>
            <p class="page-subtitle"><?= htmlspecialchars($attempt['user_name']) ?> — <?= htmlspecialchars($attempt['quiz_title']) ?></p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/reports" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Reports
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 p-4 mb-4">
    <div class="row text-center">
        <div class="col-md-3">
            <div class="text-muted small">Score Obtained</div>
            <div class="fs-3 fw-bold text-primary"><?= number_format($attempt['score'], 2) ?> / <?= $attempt['total_marks'] ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">Percentage</div>
            <div class="fs-3 fw-bold <?= $attempt['score'] >= ($attempt['total_marks'] * 0.6) ? 'text-success' : 'text-danger' ?>">
                <?= $attempt['total_marks'] > 0 ? round($attempt['score'] * 100 / $attempt['total_marks']) : 0 ?>%
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">Time Taken</div>
            <div class="fs-3 fw-bold text-dark"><?= gmdate('i:s', (int)$attempt['time_taken_seconds']) ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">Tab Switches</div>
            <div class="fs-3 fw-bold <?= !empty($attempt['tab_switch_count']) ? 'text-warning' : 'text-success' ?>">
                <?= (int)$attempt['tab_switch_count'] ?>
            </div>
        </div>
    </div>
</div>

<h5 class="fw-bold mb-3">Student Answers Breakdown</h5>
<div class="space-y-3">
    <?php foreach ($details as $idx => $d): ?>
    <div class="card shadow-sm border-0 mb-3 <?= $d['is_correct'] ? 'border-start border-success border-4' : 'border-start border-danger border-4' ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted small">Question <?= $idx + 1 ?></span>
                <span class="badge <?= $d['is_correct'] ? 'bg-success' : 'bg-danger' ?>">
                    <?= $d['is_correct'] ? 'Correct' : 'Incorrect' ?>
                </span>
            </div>
            <h6 class="fw-bold mb-2"><?= htmlspecialchars($d['question_text']) ?></h6>
            <div class="p-3 bg-light rounded small">
                <div><strong>Selected Option:</strong> <?= htmlspecialchars($d['selected_text'] ?? 'No answer') ?></div>
                <?php if (!$d['is_correct'] && !empty($d['correct_text'])): ?>
                    <div class="text-success mt-1"><strong>Correct Option:</strong> <?= htmlspecialchars($d['correct_text']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
