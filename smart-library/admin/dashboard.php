<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
requireRole(['admin']);

$pageTitle = 'Admin Dashboard';
$currentPage = 'dashboard.php';

// ---- Stat cards ----
$totalBooks     = (int)$pdo->query("SELECT COALESCE(SUM(total_copies),0) FROM books")->fetchColumn();
$availableBooks = (int)$pdo->query("SELECT COALESCE(SUM(available_copies),0) FROM books")->fetchColumn();
$issuedBooks    = (int)$pdo->query("SELECT COUNT(*) FROM book_issues WHERE status IN ('issued','overdue')")->fetchColumn();
$totalStudents  = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$overdueBooks   = (int)$pdo->query("SELECT COUNT(*) FROM book_issues WHERE status='issued' AND due_date < CURDATE()")->fetchColumn();
$pendingFines   = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status='pending'")->fetchColumn();

// ---- Chart: monthly issues vs returns (last 6 months) ----
$issueStmt = $pdo->query("
    SELECT DATE_FORMAT(issue_date, '%Y-%m') ym, COUNT(*) c
    FROM book_issues
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym");
$issuesByMonth = $issueStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$returnStmt = $pdo->query("
    SELECT DATE_FORMAT(return_date, '%Y-%m') ym, COUNT(*) c
    FROM book_returns
    WHERE return_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym");
$returnsByMonth = $returnStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}
$issueSeries = array_map(fn($m) => (int)($issuesByMonth[$m] ?? 0), $months);
$returnSeries = array_map(fn($m) => (int)($returnsByMonth[$m] ?? 0), $months);
$monthLabels = array_map(fn($m) => date('M Y', strtotime($m . '-01')), $months);

// ---- Chart: most borrowed books (top 5) ----
$topBooks = $pdo->query("
    SELECT b.title, COUNT(*) c
    FROM book_issues bi JOIN books b ON b.id = bi.book_id
    GROUP BY b.id ORDER BY c DESC LIMIT 5")->fetchAll();

// ---- Chart: category distribution ----
$catDist = $pdo->query("
    SELECT c.name, COUNT(b.id) c
    FROM categories c LEFT JOIN books b ON b.category_id = c.id
    GROUP BY c.id HAVING c > 0 ORDER BY c DESC")->fetchAll();

// ---- Recent activity ----
$recentIssues = $pdo->query("
    SELECT bi.issue_date, b.title, u.name student_name
    FROM book_issues bi
    JOIN books b ON b.id = bi.book_id
    JOIN students s ON s.id = bi.student_id
    JOIN users u ON u.id = s.user_id
    ORDER BY bi.id DESC LIMIT 5")->fetchAll();

$recentReturns = $pdo->query("
    SELECT br.return_date, b.title, u.name student_name
    FROM book_returns br
    JOIN book_issues bi ON bi.id = br.issue_id
    JOIN books b ON b.id = bi.book_id
    JOIN students s ON s.id = bi.student_id
    JOIN users u ON u.id = s.user_id
    ORDER BY br.id DESC LIMIT 5")->fetchAll();

$recentStudents = $pdo->query("
    SELECT u.name, s.registration_date
    FROM students s JOIN users u ON u.id = s.user_id
    ORDER BY s.id DESC LIMIT 5")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-4">
    <div class="card stat-card">
        <div class="icon blue"><i class="fa-solid fa-book"></i></div>
        <div><div class="num"><?= $totalBooks ?></div><div class="label">Total Books</div></div>
    </div>
    <div class="card stat-card">
        <div class="icon green"><i class="fa-solid fa-book-open"></i></div>
        <div><div class="num"><?= $availableBooks ?></div><div class="label">Available Books</div></div>
    </div>
    <div class="card stat-card">
        <div class="icon yellow"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
        <div><div class="num"><?= $issuedBooks ?></div><div class="label">Issued Books</div></div>
    </div>
    <div class="card stat-card">
        <div class="icon blue"><i class="fa-solid fa-user-graduate"></i></div>
        <div><div class="num"><?= $totalStudents ?></div><div class="label">Total Students</div></div>
    </div>
</div>

<div class="grid grid-4" style="margin-top:18px;">
    <div class="card stat-card">
        <div class="icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><div class="num"><?= $overdueBooks ?></div><div class="label">Overdue Books</div></div>
    </div>
    <div class="card stat-card">
        <div class="icon red"><i class="fa-solid fa-coins"></i></div>
        <div><div class="num">&#8377;<?= number_format($pendingFines, 2) ?></div><div class="label">Pending Fines</div></div>
    </div>
    <a href="books.php?action=add" class="card stat-card" style="text-decoration:none;">
        <div class="icon blue"><i class="fa-solid fa-plus"></i></div>
        <div><div class="num" style="font-size:15px;">Add Book</div><div class="label">Quick action</div></div>
    </a>
    <a href="students.php?action=add" class="card stat-card" style="text-decoration:none;">
        <div class="icon green"><i class="fa-solid fa-user-plus"></i></div>
        <div><div class="num" style="font-size:15px;">Add Student</div><div class="label">Quick action</div></div>
    </a>
</div>

<div class="grid grid-2" style="margin-top:18px;">
    <div class="card">
        <h3>Monthly Issues vs Returns</h3>
        <canvas id="issueReturnChart" height="220"></canvas>
    </div>
    <div class="card">
        <h3>Book Category Distribution</h3>
        <canvas id="categoryChart" height="220"></canvas>
    </div>
</div>

<div class="grid grid-2" style="margin-top:18px;">
    <div class="card">
        <h3>Most Borrowed Books</h3>
        <canvas id="topBooksChart" height="220"></canvas>
    </div>
    <div class="card">
        <h3>Recent Activity</h3>
        <?php if (!$recentIssues && !$recentReturns && !$recentStudents): ?>
            <div class="empty-state"><i class="fa-solid fa-inbox"></i>No recent activity yet.</div>
        <?php else: ?>
            <div style="max-height:260px; overflow-y:auto;">
            <?php foreach ($recentIssues as $r): ?>
                <div style="padding:8px 0; border-bottom:1px solid var(--border); font-size:13.5px;">
                    <i class="fa-solid fa-arrow-right-from-bracket text-muted"></i>
                    <strong><?= e($r['student_name']) ?></strong> issued <em><?= e($r['title']) ?></em>
                    <span class="text-muted"> &middot; <?= fmtDate($r['issue_date']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ($recentReturns as $r): ?>
                <div style="padding:8px 0; border-bottom:1px solid var(--border); font-size:13.5px;">
                    <i class="fa-solid fa-arrow-right-to-bracket text-muted"></i>
                    <strong><?= e($r['student_name']) ?></strong> returned <em><?= e($r['title']) ?></em>
                    <span class="text-muted"> &middot; <?= fmtDate($r['return_date']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ($recentStudents as $r): ?>
                <div style="padding:8px 0; border-bottom:1px solid var(--border); font-size:13.5px;">
                    <i class="fa-solid fa-user-plus text-muted"></i>
                    <strong><?= e($r['name']) ?></strong> registered as a new student
                    <span class="text-muted"> &middot; <?= fmtDate($r['registration_date']) ?></span>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const chartColors = ['#4f46e5','#16a34a','#ca8a04','#dc2626','#0891b2','#9333ea','#db2777','#65a30d'];

new Chart(document.getElementById('issueReturnChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthLabels) ?>,
        datasets: [
            { label: 'Issued', data: <?= json_encode($issueSeries) ?>, borderColor: '#4f46e5', backgroundColor: '#4f46e5', tension: 0.3 },
            { label: 'Returned', data: <?= json_encode($returnSeries) ?>, borderColor: '#16a34a', backgroundColor: '#16a34a', tension: 0.3 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($catDist, 'name')) ?>,
        datasets: [{ data: <?= json_encode(array_column($catDist, 'c')) ?>, backgroundColor: chartColors }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('topBooksChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($topBooks, 'title')) ?>,
        datasets: [{ label: 'Times Borrowed', data: <?= json_encode(array_column($topBooks, 'c')) ?>, backgroundColor: '#4f46e5', borderRadius: 6 }]
    },
    options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
