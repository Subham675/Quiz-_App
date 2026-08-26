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
    $difficulty   = in_array($_POST['difficulty'] ?? '', ['easy','medium','hard']) ? $_POST['difficulty'] : 'medium';
    $tag          = trim($_POST['tag'] ?? '');
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

        $qStmt = $db->prepare("INSERT INTO questions (quiz_id, question_text, marks, difficulty, tag, order_index) VALUES (?, ?, ?, ?, ?, ?)");
        $qStmt->execute([$quizId, $questionText, $marks, $difficulty, $tag ?: null, $nextOrder]);
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

// ── CSV Sample Download ────────────────────────────────
if (isset($_GET['sample_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_questions.csv"');
    echo "question_text,marks,difficulty,tag,option_a,option_b,option_c,option_d,correct_index\n";
    echo "\"What is the capital of France?\",1,easy,geography,Paris,London,Berlin,Madrid,0\n";
    echo "\"What is 2+2?\",1,easy,math,3,4,5,6,1\n";
    exit;
}

// ── CSV Bulk Import ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csv_import'])) {
    verifyCsrf();
    $file = $_FILES['csv_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid CSV file.';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // skip header row
        $imported = 0;
        $importErrors = 0;

        $db->beginTransaction();
        $nextOrderStmt = $db->prepare("SELECT COALESCE(MAX(order_index), 0) FROM questions WHERE quiz_id = ?");
        $nextOrderStmt->execute([$quizId]);
        $nextOrder = (int)$nextOrderStmt->fetchColumn();

        $qStmt = $db->prepare("INSERT INTO questions (quiz_id, question_text, marks, difficulty, tag, order_index) VALUES (?, ?, ?, ?, ?, ?)");
        $oStmt = $db->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 9) { $importErrors++; continue; }
            [$questionText, $marks, $difficulty, $tag, $optA, $optB, $optC, $optD, $correctIdx] = $row;
            $questionText = trim($questionText);
            if (empty($questionText)) { $importErrors++; continue; }

            $difficulty = in_array(trim($difficulty), ['easy','medium','hard']) ? trim($difficulty) : 'medium';
            $marks = max(1, (int)$marks);
            $correctIdx = (int)$correctIdx; // 0=A,1=B,2=C,3=D
            $opts = array_map('trim', [$optA, $optB, $optC, $optD]);
            $filled = array_filter($opts, fn($o) => $o !== '');
            if (count($filled) < 2) { $importErrors++; continue; }
            if ($correctIdx < 0 || $correctIdx > 3 || empty($opts[$correctIdx])) { $importErrors++; continue; }

            $nextOrder++;
            $qStmt->execute([$quizId, $questionText, $marks, $difficulty, trim($tag) ?: null, $nextOrder]);
            $questionId = $db->lastInsertId();

            foreach ($opts as $i => $optText) {
                if ($optText === '') continue;
                $oStmt->execute([$questionId, $optText, $i === $correctIdx ? 1 : 0]);
            }
            $imported++;
        }
        fclose($handle);

        $db->prepare("UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id = ?) WHERE id = ?")
           ->execute([$quizId, $quizId]);
        $db->commit();

        $success = "{$imported} question(s) imported successfully." . ($importErrors > 0 ? " {$importErrors} row(s) skipped (invalid format)." : '');
    }
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

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>Marks</label>
                    <input type="number" name="marks" min="1" value="1">
                </div>
                <div class="form-group">
                    <label>Difficulty</label>
                    <select name="difficulty">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Tag <span style="color:var(--muted);font-size:12px">(optional, e.g. topic/chapter)</span></label>
                <input type="text" name="tag" placeholder="e.g. Algebra, Chapter 3">
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

        <hr style="margin:20px 0;border-color:var(--border)">
        <div class="card-title" style="font-size:13.5px">📥 Bulk Import via CSV</div>
        <p style="font-size:12.5px;color:var(--muted);margin-bottom:12px">
            Upload a CSV with columns: <code>question_text, marks, difficulty, tag, option_a, option_b, option_c, option_d, correct_index</code><br>
            correct_index: 0=A, 1=B, 2=C, 3=D. <a href="?quiz_id=<?= $quizId ?>&sample_csv=1" style="color:var(--accent)">Download sample CSV</a>
        </p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
            <div class="form-group">
                <label>CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>
            <button type="submit" name="csv_import" class="btn btn-outline" style="width:100%;justify-content:center">
                Import from CSV
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