<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// ── CSV Export ──────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $db->query("
        SELECT u.name AS student, u.email,
               q.title AS quiz, c.name AS category,
               a.score, a.total_marks,
               ROUND(a.score * 100 / NULLIF(a.total_marks,0),1) AS pct,
               a.tab_switch_count,
               q.negative_marking,
               a.time_taken_seconds,
               a.submitted_at
        FROM attempts a
        JOIN users u   ON u.id = a.user_id
        JOIN quizzes q ON q.id = a.quiz_id
        JOIN categories c ON c.id = q.category_id
        WHERE a.is_completed = 1
        ORDER BY a.submitted_at DESC
    ")->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="quiz_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'Email', 'Quiz', 'Category', 'Score', 'Total Marks', 'Percentage', 'Tab Switches', 'Neg. Marking', 'Time (s)', 'Submitted At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['student'], $r['email'], $r['quiz'], $r['category'],
            $r['score'], $r['total_marks'], $r['pct'] . '%',
            $r['tab_switch_count'], $r['negative_marking'],
            $r['time_taken_seconds'], $r['submitted_at']
        ]);
    }
    fclose($out);
    exit;
}

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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Performance overview across the platform</p>
    </div>
    <a href="reports.php?export=csv" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i> Export CSV
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="stat-label">Total students</div><div class="stat-value"><?= $totalUsers ?></div></div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="stat-label">Total quizzes</div><div class="stat-value"><?= $totalQuizzes ?></div></div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="stat-label">Total attempts</div><div class="stat-value"><?= $totalAttempts ?></div></div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="stat-label">Avg score</div><div class="stat-value"><?= round($avgScore) ?>%</div></div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="stat-label">Pass rate</div><div class="stat-value"><?= round($passRate) ?>%</div></div></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="stat-label">Certs issued</div><div class="stat-value"><?= $totalCerts ?></div></div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title fw-semibold mb-3">Attempts — last 7 days</h6>
                <div class="d-flex align-items-end gap-2" style="height:140px">
                    <?php foreach ($trendDays as $date => $count):
                        $heightPct = $maxTrend > 0 ? max(4, round($count / $maxTrend * 100)) : 4;
                    ?>
                    <div class="flex-fill d-flex flex-column align-items-center gap-1">
                        <small class="text-muted"><?= $count ?></small>
                        <div class="bg-primary rounded-top" style="width:100%;height:<?= $heightPct ?>%;min-height:4px"></div>
                        <small class="text-muted" style="font-size:10px"><?= date('D', strtotime($date)) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title fw-semibold mb-3">Attempts by category</h6>
                <?php if (empty($categoryPerf)): ?>
                    <p class="text-muted small mb-0">No categories yet.</p>
                <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($categoryPerf as $c):
                        $w = $maxCatAttempts > 0 ? round($c['attempts'] / $maxCatAttempts * 100) : 0;
                    ?>
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-medium"><?= htmlspecialchars($c['name']) ?></span>
                            <span class="text-muted"><?= $c['attempts'] ?></span>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-primary" role="progressbar" style="width:<?= max(2, $w) ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="card-title fw-semibold mb-3">Quiz performance</h6>
        <?php if (empty($quizPerf)): ?>
            <p class="text-muted small mb-0">No quizzes yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Quiz</th><th>Attempts</th><th>Avg score</th></tr></thead>
                <tbody>
                <?php foreach ($quizPerf as $q): ?>
                    <tr>
                        <td class="fw-medium"><?= htmlspecialchars($q['title']) ?></td>
                        <td><?= $q['attempts'] ?></td>
                        <td>
                            <?php if ($q['attempts'] > 0): ?>
                                <span class="badge rounded-pill <?= $q['avg_pct'] >= 60 ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= round($q['avg_pct']) ?>%
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">No attempts</span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>