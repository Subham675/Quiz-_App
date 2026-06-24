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
            $db->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
            $success = 'Category added.';
        }
    }
}

// ── Delete ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    verifyCsrf();
    $id = (int)$_POST['delete_category'];
    $count = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE category_id = ?");
    $count->execute([$id]);
    if ($count->fetchColumn() > 0) {
        $error = 'Cannot delete — this category has quizzes assigned to it.';
    } else {
        $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        $success = 'Category deleted.';
    }
}

// ── Edit mode ───────────────────────────────────────────
$editCat = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCat = $stmt->fetch();
}

$categories = $db->query("
    SELECT c.*, (SELECT COUNT(*) FROM quizzes WHERE category_id = c.id) AS quiz_count
    FROM categories c ORDER BY c.name
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Manage Categories</div>
    <div class="page-subtitle">Categories group your quizzes for students to filter by</div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="two-col" style="grid-template-columns:320px 1fr">
    <div class="card">
        <div class="card-title"><?= $editCat ? 'Edit category' : 'Add new category' ?></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <?php if ($editCat): ?><input type="hidden" name="id" value="<?= $editCat['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label>Category name</label>
                <input type="text" name="name" required placeholder="e.g. Science" value="<?= htmlspecialchars($editCat['name'] ?? '') ?>">
            </div>
            <button type="submit" name="save_category" class="btn btn-primary" style="width:100%;justify-content:center">
                <?= $editCat ? 'Update' : 'Add category' ?>
            </button>
            <?php if ($editCat): ?>
                <a href="manage-categories.php" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:8px">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-title">All categories</div>
        <?php if (empty($categories)): ?>
            <p style="color:var(--muted);font-size:13.5px">No categories yet.</p>
        <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Quizzes</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= $c['quiz_count'] ?></td>
                <td style="white-space:nowrap">
                    <a href="manage-categories.php?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                    <?php if ($c['quiz_count'] == 0): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this category?')">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button type="submit" name="delete_category" value="<?= $c['id'] ?>" class="btn btn-sm btn-danger">Delete</button>
                    </form>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>