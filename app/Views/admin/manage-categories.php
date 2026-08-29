<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Manage Categories</h1>
        <p class="page-subtitle">Organize quizzes into structured categories</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
        <i class="bi bi-plus-lg me-1"></i>Add Category
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
                    <th>Category Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Quizzes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No categories created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($cat['name']) ?></td>
                        <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                        <td class="text-muted small"><?= htmlspecialchars($cat['description'] ?? '—') ?></td>
                        <td><span class="badge bg-light text-dark border"><?= (int)($cat['quiz_count'] ?? 0) ?> Quizzes</span></td>
                        <td class="text-end">
                            <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/categories/delete/<?= $cat['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this category?');">
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

<!-- Create Category Modal -->
<div class="modal fade" id="createCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/categories">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Web Development" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Slug (Optional)</label>
                        <input type="text" name="slug" class="form-control" placeholder="web-development">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
