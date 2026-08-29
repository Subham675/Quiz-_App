<div class="mb-4">
    <h1 class="page-title">Practice Mode</h1>
    <p class="page-subtitle">Test yourself without timer pressure and see instant explanations</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 text-center p-4">
            <div class="display-5 text-primary mb-2"><i class="bi bi-calendar-check"></i></div>
            <h5 class="fw-bold">Daily Challenge</h5>
            <p class="text-muted small flex-grow-1">Keep your streak alive with 5 fresh daily questions.</p>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/daily-quiz" class="btn btn-outline-primary">Start Daily</a>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 text-center p-4">
            <div class="display-5 text-warning mb-2"><i class="bi bi-lightning-charge"></i></div>
            <h5 class="fw-bold">Weak Topics</h5>
            <p class="text-muted small flex-grow-1">Focus on concepts and tags where you scored below 70%.</p>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/weak-topics" class="btn btn-outline-warning">Review Weak Areas</a>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 text-center p-4">
            <div class="display-5 text-success mb-2"><i class="bi bi-bullseye"></i></div>
            <h5 class="fw-bold">Adaptive Quiz</h5>
            <p class="text-muted small flex-grow-1">Difficulty scales automatically based on your real-time answers.</p>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/adaptive-quiz" class="btn btn-outline-success">Launch Adaptive</a>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 text-center p-4">
            <div class="display-5 text-info mb-2"><i class="bi bi-robot"></i></div>
            <h5 class="fw-bold">AI Practice</h5>
            <p class="text-muted small flex-grow-1">Generate unlimited practice questions on any topic with AI.</p>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/ai-practice" class="btn btn-outline-info">Try AI Practice</a>
        </div>
    </div>
</div>
