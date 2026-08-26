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

            <div class="form-group" style="position:relative">
                <label>Category <span style="font-size:12px;color:var(--muted)">(Search or select from list)</span></label>
                <input type="text" id="categoryTypeahead" placeholder="Type to search category (e.g. Politics, Science)..." autocomplete="off" value="<?= htmlspecialchars($editQuiz ? ($db->query("SELECT name FROM categories WHERE id = " . (int)$editQuiz['category_id'])->fetchColumn() ?: '') : '') ?>">
                <input type="hidden" name="category_id" id="selectedCategoryId" required value="<?= $editQuiz['category_id'] ?? '' ?>">
                <div id="catTypeaheadList" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:99;background:var(--surface,#ffffff);border:1px solid var(--border,#E4E6EA);border-radius:var(--radius-sm,6px);max-height:200px;overflow-y:auto;box-shadow:0 6px 18px rgba(0,0,0,.08);margin-top:4px"></div>
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
                <label>Negative Marking <span style="color:var(--muted);font-size:12px">(marks deducted per wrong answer, 0 = disabled)</span></label>
                <input type="number" name="negative_marking" min="0" max="5" step="0.25" value="<?= number_format((float)($editQuiz['negative_marking'] ?? 0), 2) ?>" style="max-width:140px">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Opens at <span style="color:var(--muted);font-size:12px">(optional)</span></label>
                    <input type="datetime-local" name="starts_at" value="<?= !empty($editQuiz['starts_at']) ? date('Y-m-d\TH:i', strtotime($editQuiz['starts_at'])) : '' ?>">
                </div>
                <div class="form-group">
                    <label>Closes at <span style="color:var(--muted);font-size:12px">(optional)</span></label>
                    <input type="datetime-local" name="ends_at" value="<?= !empty($editQuiz['ends_at']) ? date('Y-m-d\TH:i', strtotime($editQuiz['ends_at'])) : '' ?>">
                </div>
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
            <thead><tr><th>Title</th><th>Category</th><th>Questions</th><th>Neg. Mark</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($quizzes as $q): ?>
                <tr>
                    <td><?= htmlspecialchars($q['title']) ?></td>
                    <td style="color:var(--muted)"><?= htmlspecialchars($q['category']) ?></td>
                    <td><?= $q['q_count'] ?></td>
                    <td><?= (float)($q['negative_marking'] ?? 0) > 0 ? '<span style="color:var(--danger)">−' . number_format($q['negative_marking'], 2) . '</span>' : '<span style="color:var(--muted)">None</span>' ?></td>
                    <td style="font-size:12px;color:var(--muted)">
                        <?php
                            $now = new DateTime();
                            $sa = !empty($q['starts_at']) ? new DateTime($q['starts_at']) : null;
                            $ea = !empty($q['ends_at'])   ? new DateTime($q['ends_at'])   : null;
                            if ($sa && $now < $sa) echo '<span style="color:var(--warning,#f59e0b)">⏳ ' . date('d M, H:i', $sa->getTimestamp()) . '</span>';
                            elseif ($ea && $now > $ea) echo '<span style="color:var(--danger)">🔒 Expired</span>';
                            elseif ($sa || $ea) echo '<span style="color:var(--success)">Active window</span>';
                            else echo 'Always open';
                        ?>
                    </td>
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
            list.innerHTML = '<div style="padding:10px;font-size:12.5px;color:var(--muted)">No matching category found</div>';
            list.style.display = 'block';
            return;
        }

        list.innerHTML = '';
        matches.forEach(c => {
            const div = document.createElement('div');
            div.style.padding = '9px 14px';
            div.style.cursor = 'pointer';
            div.style.borderBottom = '1px solid var(--border,#E4E6EA)';
            div.style.fontSize = '13.5px';
            div.style.color = 'var(--text,#111318)';
            div.style.background = 'var(--surface,#ffffff)';
            div.textContent = c.name;

            div.addEventListener('mouseenter', () => {
                div.style.background = 'var(--accent-light,#E6F1FB)';
                div.style.color = 'var(--accent,#185FA5)';
            });
            div.addEventListener('mouseleave', () => {
                div.style.background = 'var(--surface,#ffffff)';
                div.style.color = 'var(--text,#111318)';
            });
            div.addEventListener('mousedown', (e) => {
                e.preventDefault();
                input.value = c.name;
                hiddenId.value = c.id;
                list.style.display = 'none';
            });

            list.appendChild(div);
        });

        list.style.display = 'block';
    }

    input.addEventListener('input', function () {
        hiddenId.value = ''; // reset until valid category is picked
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
            // Auto-snap to exact match if typed
            const match = categories.find(c => c.name.toLowerCase() === input.value.trim().toLowerCase());
            if (match) {
                input.value = match.name;
                hiddenId.value = match.id;
            } else if (!hiddenId.value) {
                // If invalid input, highlight first match
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