<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// ── Overview stats ──────────────────────────────────────
$totalUsers    = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalQuizzes  = $db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalAttempts = $db->query("SELECT COUNT(*) FROM attempts WHERE is_completed = 1")->fetchColumn();
$avgScore      = $db->query("SELECT COALESCE(AVG(score * 100 / NULLIF(total_marks,0)), 0) FROM attempts WHERE is_completed = 1")->fetchColumn();
$passRate      = $db->query("SELECT COALESCE(AVG(CASE WHEN score * 100 / NULLIF(total_marks,0) >= 60 THEN 100 ELSE 0 END), 0) FROM attempts WHERE is_completed = 1")->fetchColumn();
$totalCerts    = $db->query("SELECT COUNT(*) FROM certificates")->fetchColumn();

// ── Per-quiz performance ────────────────────────────────
$quizPerf = $db->query("
    SELECT q.title,
           COUNT(a.id) AS attempts,
           COALESCE(AVG(a.score * 100 / NULLIF(a.total_marks,0)), 0) AS avg_pct
    FROM quizzes q
    LEFT JOIN attempts a ON a.quiz_id = q.id AND a.is_completed = 1
    GROUP BY q.id, q.title
    ORDER BY attempts DESC
    LIMIT 10
")->fetchAll();

// ── Category breakdown ──────────────────────────────────
$categoryPerf = $db->query("
    SELECT c.name, COUNT(a.id) AS attempts
    FROM categories c
    LEFT JOIN quizzes q ON q.category_id = c.id
    LEFT JOIN attempts a ON a.quiz_id = q.id AND a.is_completed = 1
    GROUP BY c.id, c.name
    ORDER BY attempts DESC
")->fetchAll();
$maxCatAttempts = max(array_column($categoryPerf, 'attempts') ?: [1]);

// ── Last 7 days trend ────────────────────────────────────
$trend = $db->query("
    SELECT DATE(submitted_at) AS d, COUNT(*) AS c
    FROM attempts
    WHERE is_completed = 1 AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(submitted_at)
")->fetchAll(PDO::FETCH_KEY_PAIR);

$trendDays = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trendDays[$d] = $trend[$d] ?? 0;
}
$maxTrend = max(array_values($trendDays) ?: [1]) ?: 1;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Reports</div>
    <div class="page-subtitle">Performance overview across the platform</div>
</div>

<div class="stat-grid" style="margin-bottom:24px">
    <div class="stat-card"><div class="stat-label">Total students</div><div class="stat-value"><?= $totalUsers ?></div></div>
    <div class="stat-card"><div class="stat-label">Total quizzes</div><div class="stat-value"><?= $totalQuizzes ?></div></div>
    <div class="stat-card"><div class="stat-label">Total attempts</div><div class="stat-value"><?= $totalAttempts ?></div></div>
    <div class="stat-card"><div class="stat-label">Avg score</div><div class="stat-value"><?= round($avgScore) ?>%</div></div>
    <div class="stat-card"><div class="stat-label">Pass rate</div><div class="stat-value"><?= round($passRate) ?>%</div></div>
    <div class="stat-card"><div class="stat-label">Certificates issued</div><div class="stat-value"><?= $totalCerts ?></div></div>
</div>

<div class="two-col" style="grid-template-columns: 1fr 1fr;margin-bottom:24px">
    <div class="card">
        <div class="card-title">Attempts — last 7 days</div>
        <div style="display:flex;align-items:flex-end;gap:10px;height:140px;margin-top:16px">
            <?php foreach ($trendDays as $date => $count):
                $heightPct = $maxTrend > 0 ? max(4, round($count / $maxTrend * 100)) : 4;
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
                <div style="font-size:11px;color:var(--muted)"><?= $count ?></div>
                <div style="width:100%;background:var(--accent);border-radius:4px 4px 0 0;height:<?= $heightPct ?>%;min-height:4px"></div>
                <div style="font-size:10.5px;color:var(--muted)"><?= date('D', strtotime($date)) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Attempts by category</div>
        <?php if (empty($categoryPerf)): ?>
            <p style="color:var(--muted);font-size:13.5px">No categories yet.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;margin-top:14px">
            <?php foreach ($categoryPerf as $c):
                $w = $maxCatAttempts > 0 ? round($c['attempts'] / $maxCatAttempts * 100) : 0;
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
                    <span><?= htmlspecialchars($c['name']) ?></span>
                    <span style="color:var(--muted)"><?= $c['attempts'] ?></span>
                </div>
                <div style="background:var(--bg);border-radius:6px;height:8px;overflow:hidden">
                    <div style="background:var(--accent);height:100%;width:<?= max(2, $w) ?>%;border-radius:6px"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-title">Quiz performance</div>
    <?php if (empty($quizPerf)): ?>
        <p style="color:var(--muted);font-size:13.5px">No quizzes yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Quiz</th><th>Attempts</th><th>Avg score</th></tr></thead>
        <tbody>
        <?php foreach ($quizPerf as $q): ?>
            <tr>
                <td><?= htmlspecialchars($q['title']) ?></td>
                <td><?= $q['attempts'] ?></td>
                <td>
                    <?php if ($q['attempts'] > 0): ?>
                        <span class="badge <?= $q['avg_pct'] >= 60 ? 'badge-success' : 'badge-warning' ?>">
                            <?= round($q['avg_pct']) ?>%
                        </span>
                    <?php else: ?>
                        <span style="color:var(--muted);font-size:12.5px">No attempts</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>