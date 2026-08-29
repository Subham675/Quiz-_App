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
    $negativeMarking = max(0, (float)($_POST['negative_marking'] ?? 0));
    $startsAt    = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
    $endsAt      = !empty($_POST['ends_at'])   ? $_POST['ends_at']   : null;

    if ($title === '' || $categoryId <= 0) {
        $error = 'Title and category are required.';
    } else {
        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE quizzes SET title=?, category_id=?, description=?, time_limit_seconds=?, is_active=?,
                    negative_marking=?, starts_at=?, ends_at=?
                WHERE id=?
            ");
            $stmt->execute([$title, $categoryId, $description, $timeLimit, $isActive, $negativeMarking, $startsAt, $endsAt, $id]);
            $success = 'Quiz updated.';
        } else {
            $stmt = $db->prepare("
                INSERT INTO quizzes (category_id, title, description, time_limit_seconds, is_active, negative_marking, starts_at, ends_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$categoryId, $title, $description, $timeLimit, $isActive, $negativeMarking, $startsAt, $endsAt]);
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

// ── Handle soft delete ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    verifyCsrf();
    $stmt = $db->prepare("UPDATE quizzes SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([(int)$_POST['delete_quiz']]);
    $success = 'Quiz moved to trash (soft-deleted).';
}

// ── Handle restore ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_quiz'])) {
    verifyCsrf();
    $stmt = $db->prepare("UPDATE quizzes SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([(int)$_POST['restore_quiz']]);
    $success = 'Quiz restored successfully.';
}

// ── Editing? load existing values ─────────────────────
$editQuiz = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editQuiz = $stmt->fetch();
}

$showTrash = isset($_GET['trash']);

if ($showTrash) {
    $quizzes = $db->query("
        SELECT q.*, c.name AS category, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS q_count
        FROM quizzes q JOIN categories c ON c.id = q.category_id
        WHERE q.deleted_at IS NOT NULL
        ORDER BY q.created_at DESC
    ")->fetchAll();
} else {
    $quizzes = $db->query("
        SELECT q.*, c.name AS category, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND deleted_at IS NULL) AS q_count
        FROM quizzes q JOIN categories c ON c.id = q.category_id
        WHERE q.deleted_at IS NULL
        ORDER BY q.created_at DESC
    ")->fetchAll();
}

$trashCount = (int)$db->query("SELECT COUNT(*) FROM quizzes WHERE deleted_at IS NOT NULL")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title">Manage Quizzes</h1>
        <p class="page-subtitle"><?= $showTrash ? 'Deleted Quizzes (Trash)' : 'Create, edit, and schedule quizzes' ?></p>
    </div>
    <div>
        <?php if ($showTrash): ?>
            <a href="manage-quizzes.php" class="btn btn-primary btn-sm">Active Quizzes</a>
        <?php else: ?>
            <a href="manage-quizzes.php?trash=1" class="btn btn-outline-secondary btn-sm">
                🗑️ Trash <?= $trashCount > 0 ? "({$trashCount})" : '' ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title fw-semibold mb-3"><?= $editQuiz ? 'Edit quiz' : 'Create new quiz' ?></h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <?php if ($editQuiz): ?><input type="hidden" name="id" value="<?= $editQuiz['id'] ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Title</label>
                        <input type="text" class="form-control" name="title" required value="<?= htmlspecialchars($editQuiz['title'] ?? '') ?>">
                    </div>

                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-medium">Category <span class="text-muted fw-normal">(Search or select)</span></label>
                        <input type="text" class="form-control" id="categoryTypeahead" placeholder="Type to search category..." autocomplete="off" value="<?= htmlspecialchars($editQuiz ? ($db->query("SELECT name FROM categories WHERE id = " . (int)$editQuiz['category_id'])->fetchColumn() ?: '') : '') ?>">
                        <input type="hidden" name="category_id" id="selectedCategoryId" required value="<?= $editQuiz['category_id'] ?? '' ?>">
                        <div id="catTypeaheadList" class="dropdown-menu w-100 shadow" style="display:none;max-height:200px;overflow-y:auto"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($editQuiz['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Time limit (minutes)</label>
                        <input type="number" class="form-control" name="time_limit_minutes" min="1" value="<?= isset($editQuiz['time_limit_seconds']) ? round($editQuiz['time_limit_seconds'] / 60) : 10 ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Negative Marking <span class="text-muted fw-normal">(deduction per wrong answer)</span></label>
                        <input type="number" class="form-control" name="negative_marking" min="0" max="5" step="0.25" value="<?= number_format((float)($editQuiz['negative_marking'] ?? 0), 2) ?>" style="max-width:140px">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Opens at <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="datetime-local" class="form-control form-control-sm" name="starts_at" value="<?= !empty($editQuiz['starts_at']) ? date('Y-m-d\TH:i', strtotime($editQuiz['starts_at'])) : '' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Closes at <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="datetime-local" class="form-control form-control-sm" name="ends_at" value="<?= !empty($editQuiz['ends_at']) ? date('Y-m-d\TH:i', strtotime($editQuiz['ends_at'])) : '' ?>">
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active_chk" <?= !isset($editQuiz) || $editQuiz['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="is_active_chk">
                            Active (visible to students)
                        </label>
                    </div>

                    <button type="submit" name="save_quiz" class="btn btn-primary w-100">
                        <?= $editQuiz ? 'Update quiz' : 'Create quiz' ?>
                    </button>
                    <?php if ($editQuiz): ?>
                        <a href="manage-quizzes.php" class="btn btn-outline-secondary w-100 mt-2">Cancel edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title fw-semibold mb-3">All quizzes</h6>
                <?php if (empty($quizzes)): ?>
                    <p class="text-muted small mb-0">No quizzes yet. Create one on the left.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Title</th><th>Category</th><th>Questions</th><th>Neg. Mark</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($quizzes as $q): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($q['title']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($q['category']) ?></td>
                                <td><?= $q['q_count'] ?></td>
                                <td><?= (float)($q['negative_marking'] ?? 0) > 0 ? '<span class="text-danger">−' . number_format($q['negative_marking'], 2) . '</span>' : '<span class="text-muted small">None</span>' ?></td>
                                <td class="small text-muted">
                                    <?php
                                        $now = new DateTime();
                                        $sa = !empty($q['starts_at']) ? new DateTime($q['starts_at']) : null;
                                        $ea = !empty($q['ends_at'])   ? new DateTime($q['ends_at'])   : null;
                                        if ($sa && $now < $sa) echo '<span class="text-warning">⏳ ' . date('d M, H:i', $sa->getTimestamp()) . '</span>';
                                        elseif ($ea && $now > $ea) echo '<span class="text-danger">🔒 Expired</span>';
                                        elseif ($sa || $ea) echo '<span class="text-success">Active window</span>';
                                        else echo 'Always open';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill <?= $q['is_active'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= $q['is_active'] ? 'Active' : 'Hidden' ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($showTrash): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <button type="submit" name="restore_quiz" value="<?= $q['id'] ?>" class="btn btn-sm btn-primary">Restore</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="manage-questions.php?quiz_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-secondary">Questions</a>
                                        <a href="manage-quizzes.php?edit=<?= $q['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Move this quiz to trash?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <button type="submit" name="delete_quiz" value="<?= $q['id'] ?>" class="btn btn-sm btn-danger">Delete</button>
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
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('categoryTypeahead');
    const hiddenId = document.getElementById('selectedCategoryId');
    const list = document.getElementById('catTypeaheadList');
    if (!input || !list) return;

    const categories = <?= json_encode($categories) ?>;

    function render(query) {
        const q = (query || '').trim().toLowerCase();
        const matches = categories.filter(c => c.name.toLowerCase().includes(q));

        if (matches.length === 0) {
            list.innerHTML = '<div class="p-2 small text-muted">No matching category found</div>';
            list.style.display = 'block';
            return;
        }

        list.innerHTML = '';
        matches.forEach(c => {
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';
            item.textContent = c.name;

            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                input.value = c.name;
                hiddenId.value = c.id;
                list.style.display = 'none';
            });

            list.appendChild(item);
        });

        list.style.display = 'block';
    }

    input.addEventListener('input', function () {
        hiddenId.value = '';
        const match = categories.find(c => c.name.toLowerCase() === this.value.trim().toLowerCase());
        if (match) hiddenId.value = match.id;
        render(this.value);
    });

    input.addEventListener('focus', function () {
        render(this.value);
    });

    input.addEventListener('blur', function () {
        setTimeout(() => {
            list.style.display = 'none';
            const match = categories.find(c => c.name.toLowerCase() === input.value.trim().toLowerCase());
            if (match) {
                input.value = match.name;
                hiddenId.value = match.id;
            } else if (!hiddenId.value) {
                const first = categories.find(c => c.name.toLowerCase().includes(input.value.trim().toLowerCase()));
                if (first) {
                    input.value = first.name;
                    hiddenId.value = first.id;
                }
            }
        }, 200);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>