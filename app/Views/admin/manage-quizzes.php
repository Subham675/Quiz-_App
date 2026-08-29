<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Manage Quizzes</h1>
        <p class="page-subtitle">Create, update, and manage assessment quizzes</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createQuizModal">
        <i class="bi bi-plus-lg me-1"></i>Create Quiz
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Questions</th>
                    <th>Time Limit</th>
                    <th>Negative Mark</th>
                    <th>Attempts</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quizzes)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No quizzes created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($quizzes as $q): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($q['title']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($q['description'] ?? '') ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($q['category_name'] ?? 'General') ?></span></td>
                        <td>
                            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions?quiz_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <?= (int)$q['question_count'] ?> Questions
                            </a>
                        </td>
                        <td><?= (int)$q['time_limit_minutes'] ?> mins</td>
                        <td><?= (float)$q['negative_marking'] > 0 ? (float)$q['negative_marking'] : 'None' ?></td>
                        <td><?= (int)$q['attempt_count'] ?></td>
                        <td class="text-end">
                            <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/quizzes/delete/<?= $q['id'] ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this quiz?');">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Quiz Modal -->
<div class="modal fade" id="createQuizModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/quizzes">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create New Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Quiz Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. JavaScript Fundamentals" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of quiz coverage..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Time Limit (Minutes)</label>
                        <input type="number" name="time_limit_minutes" class="form-control" value="10" min="1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Negative Marking (e.g. 0.25)</label>
                        <input type="number" step="0.05" name="negative_marking" class="form-control" value="0.00" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Quiz</button>
                </div>
            </form>
        </div>
    </div>
</div>
