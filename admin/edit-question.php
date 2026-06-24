<?php
$pageTitle = 'Edit Question';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db    = getDB();
$error = '';
$success = '';

$questionId = (int)($_GET['id'] ?? $_POST['question_id'] ?? 0);

$qStmt = $db->prepare("
    SELECT q.*, quiz.title AS quiz_title
    FROM questions q
    JOIN quizzes quiz ON quiz.id = q.quiz_id
    WHERE q.id = ?
");
$qStmt->execute([$questionId]);
$question = $qStmt->fetch();

if (!$question) {
    header('Location: manage-quizzes.php');
    exit;
}

$quizId = $question['quiz_id'];

// ── Handle update ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
    verifyCsrf();

    $questionText = trim($_POST['question_text'] ?? '');
    $marks        = max(1, (int)($_POST['marks'] ?? 1));
    $optionIds    = $_POST['option_id'] ?? [];   // existing option IDs, parallel array
    $optionTexts  = array_map('trim', $_POST['options'] ?? []);
    $correctIndex = (int)($_POST['correct_index'] ?? -1);

    $filled = array_filter($optionTexts, fn($o) => $o !== '');

    if ($questionText === '') {
        $error = 'Question text is required.';
    } elseif (count($filled) < 2) {
        $error = 'Provide at least 2 options.';
    } elseif ($correctIndex < 0 || empty($optionTexts[$correctIndex])) {
        $error = 'Select which option is correct.';
    } else {
        $db->beginTransaction();

        $db->prepare("UPDATE questions SET question_text = ?, marks = ? WHERE id = ?")
           ->execute([$questionText, $marks, $questionId]);

        $updateOpt = $db->prepare("UPDATE options SET option_text = ?, is_correct = ? WHERE id = ? AND question_id = ?");
        $insertOpt = $db->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
        $deleteOpt = $db->prepare("DELETE FROM options WHERE id = ? AND question_id = ?");

        foreach ($optionTexts as $i => $text) {
            $existingId = $optionIds[$i] ?? '';
            $isCorrect  = $i === $correctIndex ? 1 : 0;

            if ($text === '') {
                if ($existingId) {
                    $deleteOpt->execute([(int)$existingId, $questionId]);
                }
                continue;
            }

            if ($existingId) {
                $updateOpt->execute([$text, $isCorrect, (int)$existingId, $questionId]);
            } else {
                $insertOpt->execute([$questionId, $text, $isCorrect]);
            }
        }

        $db->prepare("UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id = ?) WHERE id = ?")
           ->execute([$quizId, $quizId]);

        $db->commit();
        $success = 'Question updated.';

        // Refresh question data
        $qStmt->execute([$questionId]);
        $question = $qStmt->fetch();
    }
}

$optStmt = $db->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY id ASC");
$optStmt->execute([$questionId]);
$options = $optStmt->fetchAll();

// Pad to 4 slots for the form
while (count($options) < 4) {
    $options[] = ['id' => '', 'option_text' => '', 'is_correct' => 0];
}

$correctIdx = 0;
foreach ($options as $i => $o) {
    if (!empty($o['is_correct'])) { $correctIdx = $i; break; }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Edit Question</div>
    <div class="page-subtitle">
        <a href="manage-questions.php?quiz_id=<?= $quizId ?>">&larr; Back to <?= htmlspecialchars($question['quiz_title']) ?></a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card" style="max-width:560px">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="question_id" value="<?= $questionId ?>">

        <div class="form-group">
            <label>Question text</label>
            <textarea name="question_text" rows="2" required><?= htmlspecialchars($question['question_text']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Marks</label>
            <input type="number" name="marks" min="1" value="<?= (int)$question['marks'] ?>" style="max-width:100px">
        </div>

        <div class="form-group">
            <label>Options (mark the correct one)</label>
            <?php foreach ($options as $i => $o): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                <input type="radio" name="correct_index" value="<?= $i ?>" <?= $i === $correctIdx ? 'checked' : '' ?> required style="width:auto;flex-shrink:0">
                <input type="hidden" name="option_id[]" value="<?= htmlspecialchars((string)$o['id']) ?>">
                <input type="text" name="options[]" placeholder="Option <?= $i + 1 ?>" value="<?= htmlspecialchars($o['option_text']) ?>" <?= $i < 2 ? 'required' : '' ?>>
            </div>
            <?php endforeach; ?>
            <div class="form-hint">Clearing an option's text will remove it (min. 2 required).</div>
        </div>

        <button type="submit" name="update_question" class="btn btn-primary" style="width:100%;justify-content:center">
            Save changes
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>