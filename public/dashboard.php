<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

// Streak
$streak = getUserStreak($userId, $db);

$totalAttempts = $db->prepare("SELECT COUNT(*) FROM attempts WHERE user_id=? AND is_completed=1");
$totalAttempts->execute([$userId]);
$totalAttempts = $totalAttempts->fetchColumn();

$bestScore = $db->prepare("SELECT MAX(ROUND(score*100/NULLIF(total_marks,0))) FROM attempts WHERE user_id=? AND is_completed=1");
$bestScore->execute([$userId]);
$bestScore = $bestScore->fetchColumn() ?? 0;

$avgScore = $db->prepare("SELECT ROUND(AVG(score*100/NULLIF(total_marks,0))) FROM attempts WHERE user_id=? AND is_completed=1");
$avgScore->execute([$userId]);
$avgScore = $avgScore->fetchColumn() ?? 0;

$totalCerts = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id=?");
$totalCerts->execute([$userId]);
$totalCerts = $totalCerts->fetchColumn();

$suggested = $db->prepare("
    SELECT q.id, q.title, q.time_limit_seconds, c.name AS category,
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS q_count
    FROM quizzes q
    JOIN categories c ON c.id = q.category_id
    WHERE q.is_active = 1
      AND q.id NOT IN (SELECT quiz_id FROM attempts WHERE user_id=? AND is_completed=1)
    LIMIT 4
");
$suggested->execute([$userId]);
$suggested = $suggested->fetchAll();

$recentAttempts = $db->prepare("
    SELECT a.id, q.title, ROUND(a.score*100/NULLIF(a.total_marks,0)) AS pct,
           a.submitted_at
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.user_id=? AND a.is_completed=1
    ORDER BY a.submitted_at DESC LIMIT 5
");
$recentAttempts->execute([$userId]);
$recentAttempts = $recentAttempts->fetchAll();

// Progress trend — last 10 attempts in chronological order, for the chart
$trendStmt = $db->prepare("
    SELECT ROUND(score*100/NULLIF(total_marks,0)) AS pct, submitted_at
    FROM attempts
    WHERE user_id = ? AND is_completed = 1
    ORDER BY submitted_at DESC
    LIMIT 10
");
$trendStmt->execute([$userId]);
$trend = array_reverse($trendStmt->fetchAll());

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="page-title">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!</h1>
    <p class="page-subtitle">Here's your progress at a glance</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Quizzes taken</div>
                <div class="stat-value"><?= $totalAttempts ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Best score</div>
                <div class="stat-value"><?= $bestScore ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Avg score</div>
                <div class="stat-value"><?= $avgScore ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Certificates</div>
                <div class="stat-value"><?= $totalCerts ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">🔥 Daily Streak</div>
                <div class="stat-value" style="color:<?= $streak > 0 ? '#f59e0b' : 'var(--qa-muted)' ?>"><?= $streak ?></div>
                <?php if ($streak > 0): ?>
                <div class="stat-sub"><?= $streak === 1 ? 'Day started!' : 'Days in a row!' ?></div>
                <?php else: ?>
                <div class="stat-sub">Take a quiz to start!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (count($trend) >= 2): ?>
<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title">Your progress</h6>
        <p class="text-muted small mb-3">Score trend across your last <?= count($trend) ?> quiz attempts</p>
        <div class="d-flex align-items-end gap-2" style="height:140px">
            <?php foreach ($trend as $t): ?>
            <div class="flex-fill d-flex flex-column align-items-center gap-1">
                <small class="text-muted"><?= (int)$t['pct'] ?>%</small>
                <div style="width:100%;background:<?= $t['pct'] >= 60 ? 'var(--bs-success)' : 'var(--bs-danger)' ?>;border-radius:4px 4px 0 0;height:<?= max(4, $t['pct']) ?>%;min-height:4px"></div>
                <small class="text-muted" style="font-size:10px"><?= date('d M', strtotime($t['submitted_at'])) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title">Quizzes to try</h6>
                <?php if (empty($suggested)): ?>
                    <p class="text-muted small">You've completed all available quizzes!</p>
                <?php else: ?>
                    <?php foreach ($suggested as $q): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-medium small"><?= htmlspecialchars($q['title']) ?></div>
                            <small class="text-muted">
                                <?= $q['q_count'] ?> questions · <?= round($q['time_limit_seconds']/60) ?> min · <?= htmlspecialchars($q['category']) ?>
                            </small>
                        </div>
                        <a href="take-quiz.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-primary">Start</a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title">Recent attempts</h6>
                <?php if (empty($recentAttempts)): ?>
                    <p class="text-muted small">No attempts yet. Take your first quiz!</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Quiz</th><th>Score</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentAttempts as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['title']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= $a['pct'] >= 60 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $a['pct'] ?>%
                                </span>
                            </td>
                            <td class="text-muted small"><?= date('d M', strtotime($a['submitted_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>