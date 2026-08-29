<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> — Quiz</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/style.css?v=6">
    <style>
        body { background-color: #f8fafc; }
        .quiz-header { background: #fff; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 100; }
        .timer-badge { font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .question-card { border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; margin-bottom: 24px; padding: 24px; }
        .option-label { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border: 1.5px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s; margin-bottom: 10px; }
        .option-label:hover { border-color: #3b82f6; background: #f0f7ff; }
        .option-input:checked + .option-label { border-color: #185FA5; background: #ebf5ff; font-weight: 600; }
        .option-input { display: none; }
    </style>
</head>
<body>

<header class="quiz-header py-3 px-4 shadow-sm mb-4">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($quiz['title']) ?></h5>
            <small class="text-muted"><?= count($questions) ?> Questions · Total Marks: <?= array_sum(array_column($questions, 'marks')) ?></small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2 bg-light px-3 py-1.5 rounded-pill border">
                <i class="bi bi-clock-history text-primary"></i>
                <span id="timerDisplay" class="timer-badge text-dark"><?= sprintf('%02d:%02d', floor($remainingSeconds / 60), $remainingSeconds % 60) ?></span>
            </div>
            <button type="button" class="btn btn-primary" onclick="confirmSubmit()">Submit Exam</button>
        </div>
    </div>
</header>

<div class="container mb-5">
    <form id="quizForm" method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quiz/submit/<?= $attemptId ?>">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <?php foreach ($questions as $idx => $q): ?>
        <div class="question-card shadow-sm" id="q_<?= $q['id'] ?>">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6">Question <?= $idx + 1 ?> of <?= count($questions) ?></span>
                <span class="badge bg-light text-muted border"><?= $q['marks'] ?> Mark<?= $q['marks'] > 1 ? 's' : '' ?></span>
            </div>
            <h5 class="fw-semibold mb-4" style="line-height: 1.5;"><?= nl2br(htmlspecialchars($q['question_text'])) ?></h5>

            <div class="options-container">
                <?php foreach ($q['options'] as $opt): ?>
                <div>
                    <input type="radio" class="option-input" name="answers[<?= $q['id'] ?>]" id="opt_<?= $opt['id'] ?>" value="<?= $opt['id'] ?>">
                    <label class="option-label" for="opt_<?= $opt['id'] ?>">
                        <span class="radio-custom"></span>
                        <span><?= htmlspecialchars($opt['option_text']) ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="text-center mt-4">
            <button type="button" class="btn btn-lg btn-success px-5" onclick="confirmSubmit()">
                <i class="bi bi-check-circle me-2"></i>Complete & Submit Quiz
            </button>
        </div>
    </form>
</div>

<script>
let remaining = <?= $remainingSeconds ?>;
const timerEl = document.getElementById('timerDisplay');
const form    = document.getElementById('quizForm');

const interval = setInterval(() => {
    remaining--;
    if (remaining <= 0) {
        clearInterval(interval);
        timerEl.textContent = "00:00";
        alert("Time is up! Your quiz is being submitted automatically.");
        form.submit();
        return;
    }
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    timerEl.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    if (remaining < 120) {
        timerEl.classList.add('text-danger');
    }
}, 1000);

// Tab switch detection (anti-cheat)
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        fetch('<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quiz/tab-switch/<?= $attemptId ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }
});

function confirmSubmit() {
    if (confirm("Are you sure you want to finish and submit your quiz answers?")) {
        form.submit();
    }
}
</script>
</body>
</html>
