<?php
$pageTitle = 'Manage Categories';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$error = $success = '';

// ── Add / Edit ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    verifyCsrf();
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        if ($id > 0) {
            $db->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$name, $id]);
            $success = 'Category updated.';
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
            $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
            $success = 'Category added.';
        }
    }
}

// ── Soft Delete ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    verifyCsrf();
    $id = (int)$_POST['delete_category'];
    $count = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE category_id = ? AND deleted_at IS NULL");
    $count->execute([$id]);
    if ($count->fetchColumn() > 0) {
        $error = 'Cannot delete — this category has active quizzes assigned to it. Move quizzes first or delete them.';
    } else {
        $db->prepare("UPDATE categories SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        $success = 'Category moved to trash (soft-deleted).';
    }
}

// ── Restore ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_category'])) {
    verifyCsrf();
    $id = (int)$_POST['restore_category'];
    $db->prepare("UPDATE categories SET deleted_at = NULL WHERE id = ?")->execute([$id]);
    $success = 'Category restored successfully.';
}

// ── Edit mode ───────────────────────────────────────────
$editCat = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCat = $stmt->fetch();
}

$showTrash = isset($_GET['trash']);

if ($showTrash) {
    $categories = $db->query("
        SELECT c.*, (SELECT COUNT(*) FROM quizzes WHERE category_id = c.id AND deleted_at IS NULL) AS quiz_count
        FROM categories c
        WHERE c.deleted_at IS NOT NULL
        ORDER BY c.name
    ")->fetchAll();
} else {
    $categories = $db->query("
        SELECT c.*, (SELECT COUNT(*) FROM quizzes WHERE category_id = c.id AND deleted_at IS NULL) AS quiz_count
        FROM categories c
        WHERE c.deleted_at IS NULL
        ORDER BY c.name
    ")->fetchAll();
}

$trashCount = (int)$db->query("SELECT COUNT(*) FROM categories WHERE deleted_at IS NOT NULL")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title">Manage Categories</h1>
        <p class="page-subtitle"><?= $showTrash ? 'Deleted Categories (Trash)' : 'Categories group your quizzes for students to filter by' ?></p>
    </div>
    <div>
        <?php if ($showTrash): ?>
            <a href="manage-categories.php" class="btn btn-sm btn-primary">Active Categories</a>
        <?php else: ?>
            <a href="manage-categories.php?trash=1" class="btn btn-sm btn-outline-secondary">
                🗑️ Trash <?= $trashCount > 0 ? "({$trashCount})" : '' ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><?= $editCat ? 'Edit category' : 'Add new category' ?></h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <?php if ($editCat): ?><input type="hidden" name="id" value="<?= $editCat['id'] ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Category name</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. Science" value="<?= htmlspecialchars($editCat['name'] ?? '') ?>">
                    </div>
                    <button type="submit" name="save_category" class="btn btn-primary w-100">
                        <?= $editCat ? 'Update' : 'Add category' ?>
                    </button>
                    <?php if ($editCat): ?>
                        <a href="manage-categories.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><?= $showTrash ? 'Deleted categories (Trash)' : 'All categories' ?></h6>
                <?php if (empty($categories)): ?>
                    <p class="text-muted small"><?= $showTrash ? 'Trash is empty.' : 'No categories yet.' ?></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Name</th><th>Quizzes</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= $c['quiz_count'] ?></td>
                            <td class="text-nowrap">
                                <?php if ($showTrash): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <button type="submit" name="restore_category" value="<?= $c['id'] ?>" class="btn btn-sm btn-primary">Restore</button>
                                    </form>
                                <?php else: ?>
                                    <a href="manage-categories.php?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <?php if ($c['quiz_count'] == 0): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Move this category to trash?')">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <button type="submit" name="delete_category" value="<?= $c['id'] ?>" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>