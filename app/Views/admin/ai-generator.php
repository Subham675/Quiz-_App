<div class="mb-4">
    <h1 class="page-title">AI Quiz & Question Generator</h1>
    <p class="page-subtitle">Instantly generate high quality multiple choice questions using Google Gemini</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/ai-generator">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Target Quiz</label>
                    <select name="quiz_id" class="form-select" required>
                        <option value="">Select Target Quiz...</option>
                        <?php foreach ($quizzes as $qz): ?>
                            <option value="<?= $qz['id'] ?>" <?= (isset($_GET['quiz_id']) && $_GET['quiz_id'] == $qz['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($qz['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Topic / Concept</label>
                    <input type="text" name="topic" class="form-control" placeholder="e.g. Asynchronous JavaScript, CSS Grid, React Hooks" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Number of Questions</label>
                    <select name="count" class="form-select">
                        <option value="3">3 Questions</option>
                        <option value="5" selected>5 Questions</option>
                        <option value="10">10 Questions</option>
                        <option value="15">15 Questions</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Difficulty Level</label>
                    <select name="difficulty" class="form-select">
                        <option value="easy">Beginner / Easy</option>
                        <option value="medium" selected>Intermediate / Medium</option>
                        <option value="hard">Advanced / Hard</option>
                    </select>
                </div>
                <div class="col-12 mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-stars me-1"></i>Generate & Insert Questions
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
