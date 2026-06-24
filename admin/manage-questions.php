<?php
$pageTitle = 'Manage Questions';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db    = getDB();
$error = '';
$success = '';

$quizId = (int)($_GET['quiz_id'] ?? $_POST['quiz_id'] ?? 0);

// No quiz specified (e.g. sidebar link) — auto-pick the most recently created quiz instead of bouncing
if ($quizId <= 0) {
    $fallback = $db->query("SELECT id FROM quizzes ORDER BY created_at DESC LIMIT 1")->fetchColumn();
    if ($fallback) {
        header('Location: manage-questions.php?quiz_id=' . $fallback);
        exit;
    }
    header('Location: manage-quizzes.php');
    exit;
}

$quizStmt = $db->prepare("SELECT * FROM quizzes WHERE id = ?");
$quizStmt->execute([$quizId]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    header('Location: manage-quizzes.php');
    exit;
}

// ── Add question ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_question'])) {
    verifyCsrf();

    $questionText = trim($_POST['question_text'] ?? '');
    $marks        = max(1, (int)($_POST['marks'] ?? 1));
    $options      = $_POST['options'] ?? [];      // array of 4 strings
    $correctIndex = (int)($_POST['correct_index'] ?? -1);

    $options = array_map('trim', $options);
    $filledOptions = array_filter($options, fn($o) => $o !== '');

    if ($questionText === '') {
        $error = 'Question text is required.';
    } elseif (count($filledOptions) < 2) {
        $error = 'Provide at least 2 options.';
    } elseif ($correctIndex < 0 || empty($options[$correctIndex])) {
        $error = 'Select which option is correct.';
    } else {
        $db->beginTransaction();

        $nextOrderStmt = $db->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 FROM questions WHERE quiz_id = ?");
        $nextOrderStmt->execute([$quizId]);
        $nextOrder = $nextOrderStmt->fetchColumn();

        $qStmt = $db->prepare("INSERT INTO questions (quiz_id, question_text, marks, order_index) VALUES (?, ?, ?, ?)");
        $qStmt->execute([$quizId, $questionText, $marks, $nextOrder]);
        $questionId = $db->lastInsertId();

        $oStmt = $db->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
        foreach ($options as $i => $optText) {
            if ($optText === '') continue;
            $oStmt->execute([$questionId, $optText, $i === $correctIndex ? 1 : 0]);
        }

        // Recalculate quiz total marks
        $db->prepare("UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id = ?) WHERE id = ?")
           ->execute([$quizId, $quizId]);

        $db->commit();
        $success = 'Question added.';
    }
}

// ── Delete question ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    verifyCsrf();
    $db->prepare("DELETE FROM questions WHERE id = ? AND quiz_id = ?")->execute([(int)$_POST['delete_question'], $quizId]);
    $db->prepare("UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id = ?) WHERE id = ?")
       ->execute([$quizId, $quizId]);
    $success = 'Question deleted.';
}

$questions = $db->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY order_index ASC, id ASC");
$questions->execute([$quizId]);
$questions = $questions->fetchAll();

$optStmt = $db->prepare("SELECT * FROM options WHERE question_id = ?");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Questions — <?= htmlspecialchars($quiz['title']) ?></div>
    <div class="page-subtitle"><a href="manage-quizzes.php">&larr; Back to quizzes</a> · <?= count($questions) ?> question<?= count($questions) !== 1 ? 's' : '' ?></div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="two-col" style="grid-template-columns: 420px 1fr">
    <div class="card">
        <div class="card-title">Add a question</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

            <div class="form-group">
                <label>Question text</label>
                <textarea name="question_text" rows="2" required></textarea>
            </div>

            <div class="form-group">
                <label>Marks</label>
                <input type="number" name="marks" min="1" value="1" style="max-width:100px">
            </div>

            <div class="form-group">
                <label>Options (mark the correct one)</label>
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                    <input type="radio" name="correct_index" value="<?= $i ?>" required style="width:auto;flex-shrink:0">
                    <input type="text" name="options[]" placeholder="Option <?= $i + 1 ?>" <?= $i < 2 ? 'required' : '' ?>>
                </div>
                <?php endfor; ?>
                <div class="form-hint">First two options are required; last two are optional.</div>
            </div>

            <button type="submit" name="save_question" class="btn btn-primary" style="width:100%;justify-content:center">
                Add question
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Existing questions</div>
        <?php if (empty($questions)): ?>
            <p style="color:var(--muted);font-size:13.5px">No questions yet — add the first one on the left.</p>
        <?php else: ?>
            <?php foreach ($questions as $i => $q):
                $optStmt->execute([$q['id']]);
                $opts = $optStmt->fetchAll();
            ?>
            <div class="question-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div class="question-number">Q<?= $i + 1 ?> · <?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?></div>
                    <div style="display:flex;gap:6px">
                        <a href="edit-question.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this question?');">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
                            <button type="submit" name="delete_question" value="<?= $q['id'] ?>" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
                <div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>
                <?php foreach ($opts as $o): ?>
                    <div class="option-label <?= $o['is_correct'] ? 'correct' : '' ?>" style="cursor:default">
                        <?= htmlspecialchars($o['option_text']) ?>
                        <?= $o['is_correct'] ? ' ✓' : '' ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>