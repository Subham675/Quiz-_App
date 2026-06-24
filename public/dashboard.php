<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

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

<div class="page-header">
    <div class="page-title">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!</div>
    <div class="page-subtitle">Here's your progress at a glance</div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Quizzes taken</div>
        <div class="stat-value"><?= $totalAttempts ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Best score</div>
        <div class="stat-value"><?= $bestScore ?>%</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avg score</div>
        <div class="stat-value"><?= $avgScore ?>%</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Certificates</div>
        <div class="stat-value"><?= $totalCerts ?></div>
    </div>
</div>

<?php if (count($trend) >= 2): ?>
<div class="card" style="margin-top:20px">
    <div class="card-title">Your progress</div>
    <p style="font-size:12.5px;color:var(--muted);margin-bottom:16px">Score trend across your last <?= count($trend) ?> quiz attempts</p>
    <div style="display:flex;align-items:flex-end;gap:10px;height:140px">
        <?php foreach ($trend as $t): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
            <div style="font-size:11px;color:var(--muted)"><?= (int)$t['pct'] ?>%</div>
            <div style="width:100%;background:<?= $t['pct'] >= 60 ? 'var(--success)' : 'var(--danger)' ?>;border-radius:4px 4px 0 0;height:<?= max(4, $t['pct']) ?>%;min-height:4px"></div>
            <div style="font-size:10px;color:var(--muted)"><?= date('d M', strtotime($t['submitted_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="two-col">
    <div class="card">
        <div class="card-title">Quizzes to try</div>
        <?php if (empty($suggested)): ?>
            <p style="color:var(--muted);font-size:13px">You've completed all available quizzes!</p>
        <?php else: ?>
            <?php foreach ($suggested as $q): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
                <div>
                    <div style="font-size:13.5px;font-weight:500"><?= htmlspecialchars($q['title']) ?></div>
                    <div style="font-size:11px;color:var(--muted);margin-top:2px">
                        <?= $q['q_count'] ?> questions · <?= round($q['time_limit_seconds']/60) ?> min · <?= htmlspecialchars($q['category']) ?>
                    </div>
                </div>
                <a href="take-quiz.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-primary">Start</a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Recent attempts</div>
        <?php if (empty($recentAttempts)): ?>
            <p style="color:var(--muted);font-size:13px">No attempts yet. Take your first quiz!</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Quiz</th><th>Score</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentAttempts as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td>
                        <span class="badge <?= $a['pct'] >= 60 ? 'badge-success' : 'badge-danger' ?>">
                            <?= $a['pct'] ?>%
                        </span>
                    </td>
                    <td style="color:var(--muted);font-size:12px"><?= date('d M', strtotime($a['submitted_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>