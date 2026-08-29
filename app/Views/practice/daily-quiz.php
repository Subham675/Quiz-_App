<?php
$db     = \App\Core\Model::getDb();
$userId = $_SESSION['user_id'];
$today  = date('Y-m-d');

// Check today's session
$todaySession = null;
try {
    $chk = $db->prepare("SELECT * FROM daily_sessions WHERE user_id = ? AND session_date = ?");
    $chk->execute([$userId, $today]);
    $todaySession = $chk->fetch();
} catch (Exception $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS daily_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_date DATE NOT NULL,
        total_questions INT DEFAULT 0,
        total_correct INT DEFAULT 0,
        completed_at DATETIME DEFAULT NULL,
        UNIQUE KEY unique_user_day (user_id, session_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

$streakQ = $db->prepare("SELECT session_date FROM daily_sessions WHERE user_id = ? ORDER BY session_date DESC");
$streakQ->execute([$userId]);
$days   = $streakQ->fetchAll(PDO::FETCH_COLUMN);
$streak = 0;
$check  = new DateTime($today);
foreach ($days as $d) {
    if ($d === $check->format('Y-m-d')) { $streak++; $check->modify('-1 day'); }
    else break;
}

$totalAvailable = (int)$db->query("
    SELECT COUNT(q.id) FROM questions q
    JOIN quizzes qu ON qu.id = q.quiz_id WHERE qu.deleted_at IS NULL
")->fetchColumn();

$timeLeft = (new DateTime('tomorrow midnight'))->diff(new DateTime())->format('%Hh %Im');
?>

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Daily Challenge Quiz</h1>
            <p class="page-subtitle"><?= date('l, F j, Y') ?> · Keep your daily streak going</p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/practice" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Practice
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card bg-primary text-white p-4 shadow-sm border-0 rounded-4">
            <h3 class="fw-bold mb-2">🔥 Daily Streak: <?= $streak ?> Day<?= $streak !== 1 ? 's' : '' ?></h3>
            <p class="opacity-75 mb-3">Answer in batches of 5 questions. Score 3+ to unlock bonus batches!</p>
            <div class="d-flex gap-4">
                <div><small class="opacity-75">Next challenge</small><div class="fw-bold fs-5"><?= $timeLeft ?></div></div>
                <div><small class="opacity-75">Pool size</small><div class="fw-bold fs-5"><?= $totalAvailable ?>+ questions</div></div>
            </div>
        </div>
    </div>
</div>

<?php if ($todaySession): ?>
    <div class="card text-center p-5 shadow-sm border-0">
        <h3 class="fw-bold text-success mb-2">You crushed today's quiz! 🎉</h3>
        <div class="display-3 fw-bold text-primary my-3">
            <?= round($todaySession['total_correct'] / max($todaySession['total_questions'],1) * 100) ?>%
        </div>
        <p class="text-muted">You answered <?= $todaySession['total_correct'] ?> out of <?= $todaySession['total_questions'] ?> correctly. Come back tomorrow!</p>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0 p-4">
        <h4 class="fw-bold mb-2">Ready for today's challenge? 🚀</h4>
        <p class="text-muted">Test yourself across all subjects with 5 random questions.</p>
        <div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="btn btn-primary px-4 py-2">
                Start Daily Challenge →
            </a>
        </div>
    </div>
<?php endif; ?>
