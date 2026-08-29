<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Edit Question</h1>
            <p class="page-subtitle">Update question content and options</p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions?quiz_id=<?= $question['quiz_id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Questions
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions/update/<?= $question['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">Quiz</label>
                    <select name="quiz_id" class="form-select" required>
                        <?php foreach ($quizzes as $qz): ?>
                            <option value="<?= $qz['id'] ?>" <?= $question['quiz_id'] == $qz['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($qz['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Marks</label>
                    <input type="number" name="marks" class="form-control" value="<?= (int)$question['marks'] ?>" min="1" required>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Question Text</label>
                    <textarea name="question_text" class="form-control" rows="3" required><?= htmlspecialchars($question['question_text']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Tag / Topic</label>
                    <input type="text" name="tag" class="form-control" value="<?= htmlspecialchars($question['tag'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Difficulty</label>
                    <select name="difficulty" class="form-select">
                        <option value="easy" <?= ($question['difficulty'] ?? '') === 'easy' ? 'selected' : '' ?>>Easy</option>
                        <option value="medium" <?= ($question['difficulty'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="hard" <?= ($question['difficulty'] ?? '') === 'hard' ? 'selected' : '' ?>>Hard</option>
                    </select>
                </div>

                <div class="col-12"><hr class="my-3"><label class="form-label small fw-bold">Options</label></div>
                <?php 
                $options = $question['options'] ?? [];
                for ($i = 0; $i < 4; $i++): 
                    $opt = $options[$i] ?? null;
                ?>
                <div class="col-md-6">
                    <div class="input-group">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="radio" name="correct_option" value="<?= $i ?>" <?= ($opt && $opt['is_correct']) ? 'checked' : ($i === 0 && empty($options) ? 'checked' : '') ?>>
                        </div>
                        <input type="text" name="options[]" class="form-control" value="<?= htmlspecialchars($opt['option_text'] ?? '') ?>" placeholder="Option <?= chr(65 + $i) ?>" required>
                    </div>
                </div>
                <?php endfor; ?>

                <div class="col-12 mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4">Update Question</button>
                </div>
            </div>
        </form>
    </div>
</div>
