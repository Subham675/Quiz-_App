<?php
$pageTitle = 'Manage Quizzes';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db    = getDB();
$error = '';
$success = '';

$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// ── Handle create/update ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quiz'])) {
    verifyCsrf();

    $id          = (int)($_POST['id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $timeLimit   = max(60, (int)($_POST['time_limit_minutes'] ?? 10) * 60);
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '' || $categoryId <= 0) {
        $error = 'Title and category are required.';
    } else {
        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE quizzes SET title=?, category_id=?, description=?, time_limit_seconds=?, is_active=?
                WHERE id=?
            ");
            $stmt->execute([$title, $categoryId, $description, $timeLimit, $isActive, $id]);
            $success = 'Quiz updated.';
        } else {
            $stmt = $db->prepare("
                INSERT INTO quizzes (category_id, title, description, time_limit_seconds, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$categoryId, $title, $description, $timeLimit, $isActive]);
            $newQuizId = $db->lastInsertId();
            $success = 'Quiz created. Now add questions to it.';

            // Notify all verified students if quiz is active
            if ($isActive) {
                require_once __DIR__ . '/../config/mailer.php';
                $students = $db->query("SELECT name, email FROM users WHERE role = 'user' AND is_verified = 1 AND is_banned = 0")->fetchAll();
                foreach ($students as $s) {
                    $body = "Hi {$s['name']},\n\nA new quiz is available on QuizApp: \"{$title}\"\n\nLog in to take it: http://localhost/Quiz_app/public/quiz-list.php\n\nGood luck!";
                    sendMail($s['email'], $s['name'], "New quiz available: {$title}", $body);
                }
            }
        }
    }
}

// ── Handle delete ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    verifyCsrf();
    $stmt = $db->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_quiz']]);
    $success = 'Quiz deleted.';
}

// ── Editing? load existing values ─────────────────────
$editQuiz = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editQuiz = $stmt->fetch();
}

$quizzes = $db->query("
    SELECT q.*, c.name AS category, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS q_count
    FROM quizzes q JOIN categories c ON c.id = q.category_id
    ORDER BY q.created_at DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Manage Quizzes</div>
    <div class="page-subtitle">Create and edit quizzes</div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="two-col" style="grid-template-columns: 380px 1fr">
    <div class="card">
        <div class="card-title"><?= $editQuiz ? 'Edit quiz' : 'Create new quiz' ?></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <?php if ($editQuiz): ?><input type="hidden" name="id" value="<?= $editQuiz['id'] ?>"><?php endif; ?>

            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($editQuiz['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($editQuiz['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($editQuiz['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Time limit (minutes)</label>
                <input type="number" name="time_limit_minutes" min="1" value="<?= isset($editQuiz['time_limit_seconds']) ? round($editQuiz['time_limit_seconds'] / 60) : 10 ?>">
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="is_active" style="width:auto" <?= !isset($editQuiz) || $editQuiz['is_active'] ? 'checked' : '' ?>>
                    Active (visible to students)
                </label>
            </div>

            <button type="submit" name="save_quiz" class="btn btn-primary" style="width:100%;justify-content:center">
                <?= $editQuiz ? 'Update quiz' : 'Create quiz' ?>
            </button>
            <?php if ($editQuiz): ?>
                <a href="manage-quizzes.php" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:8px">Cancel edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-title">All quizzes</div>
        <?php if (empty($quizzes)): ?>
            <p style="color:var(--muted);font-size:13.5px">No quizzes yet. Create one on the left.</p>
        <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Title</th><th>Category</th><th>Questions</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($quizzes as $q): ?>
                <tr>
                    <td><?= htmlspecialchars($q['title']) ?></td>
                    <td style="color:var(--muted)"><?= htmlspecialchars($q['category']) ?></td>
                    <td><?= $q['q_count'] ?></td>
                    <td>
                        <span class="badge <?= $q['is_active'] ? 'badge-success' : 'badge-warning' ?>">
                            <?= $q['is_active'] ? 'Active' : 'Hidden' ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap">
                        <a href="manage-questions.php?quiz_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline">Questions</a>
                        <a href="manage-quizzes.php?edit=<?= $q['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this quiz and all its questions/attempts?');">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit" name="delete_quiz" value="<?= $q['id'] ?>" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>