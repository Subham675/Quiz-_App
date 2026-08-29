<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Adaptive Quiz Engine</h1>
            <p class="page-subtitle">Dynamic difficulty scaling based on your live answer accuracy</p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/practice" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Practice
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 p-5 text-center">
    <i class="bi bi-bullseye text-primary display-3 mb-3"></i>
    <h3 class="fw-bold mb-2">Adaptive Skill Assessment</h3>
    <p class="text-muted mx-auto mb-4" style="max-width: 540px;">
        The system starts at medium difficulty. Each correct answer increases the difficulty tier to hard, and incorrect answers adjust back to reinforce core concepts.
    </p>
    <div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="btn btn-primary btn-lg px-5">
            Select a Subject to Start
        </a>
    </div>
</div>
