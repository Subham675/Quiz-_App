<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Manage Questions</h1>
        <p class="page-subtitle">Add, edit, or remove questions and options</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createQuestionModal">
        <i class="bi bi-plus-lg me-1"></i>Add Question
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Quiz Filter -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions" class="row g-3 align-items-center">
            <div class="col-md-8">
                <select name="quiz_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Quizzes</option>
                    <?php foreach ($quizzes as $qz): ?>
                        <option value="<?= $qz['id'] ?>" <?= $selectedQuizId == $qz['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($qz['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/ai-generator<?= $selectedQuizId > 0 ? '?quiz_id=' . $selectedQuizId : '' ?>" class="btn btn-outline-primary w-100">
                    <i class="bi bi-stars me-1"></i>Generate with AI
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Questions List -->
<div class="space-y-3">
    <?php if (empty($questions)): ?>
        <div class="card text-center p-5 shadow-sm border-0">
            <i class="bi bi-patch-question text-muted display-4 mb-3"></i>
            <h5 class="fw-bold">No questions found</h5>
            <p class="text-muted">Select a quiz or create a question using the button above.</p>
        </div>
    <?php else: ?>
        <?php foreach ($questions as $idx => $q): ?>
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge bg-light text-primary border me-2"><?= htmlspecialchars($q['quiz_title']) ?></span>
                        <span class="badge bg-light text-muted border"><?= $q['marks'] ?> Mark<?= $q['marks'] > 1 ? 's' : '' ?></span>
                        <?php if (!empty($q['tag'])): ?>
                            <span class="badge bg-light text-secondary border ms-1">#<?= htmlspecialchars($q['tag']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions/edit/<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions/delete/<?= $q['id'] ?>" onsubmit="return confirm('Delete this question?');">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <h6 class="fw-bold mb-3"><?= htmlspecialchars($q['question_text']) ?></h6>

                <div class="row g-2">
                    <?php foreach ($q['options'] as $opt): ?>
                    <div class="col-md-6">
                        <div class="p-2 rounded border small <?= $opt['is_correct'] ? 'bg-success-subtle border-success text-success fw-semibold' : 'bg-light' ?>">
                            <?= $opt['is_correct'] ? '✓ ' : '○ ' ?><?= htmlspecialchars($opt['option_text']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Create Question Modal -->
<div class="modal fade" id="createQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Select Quiz</label>
                        <select name="quiz_id" class="form-select" required>
                            <option value="">Choose Quiz...</option>
                            <?php foreach ($quizzes as $qz): ?>
                                <option value="<?= $qz['id'] ?>" <?= $selectedQuizId == $qz['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($qz['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Marks</label>
                        <input type="number" name="marks" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Question Text</label>
                        <textarea name="question_text" class="form-control" rows="3" placeholder="Type your question..." required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Topic / Tag</label>
                        <input type="text" name="tag" class="form-control" placeholder="e.g. Arrays, OOP">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Difficulty</label>
                        <select name="difficulty" class="form-select">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-2"><label class="form-label small fw-bold">Options (Check the radio for the correct option)</label></div>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="col-md-6">
                        <div class="input-group">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            </div>
                            <input type="text" name="options[]" class="form-control" placeholder="Option <?= chr(65 + $i) ?>" required>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>
