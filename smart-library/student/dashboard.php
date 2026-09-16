<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
requireRole(['student']);
$pageTitle = 'My Dashboard';
$currentPage = 'dashboard.php';

$stu = $pdo->prepare("SELECT * FROM students WHERE user_id=?");
$stu->execute([$_SESSION['user_id']]);
$student = $stu->fetch();
$studentId = $student['id'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE student_id=? AND status IN ('issued','overdue')");
$stmt->execute([$studentId]); $activeBooks = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE student_id=?");
$stmt->execute([$studentId]); $totalBorrowed = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE student_id=? AND status='pending'");
$stmt->execute([$studentId]); $pendingFines = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE student_id=? AND status IN ('pending','approved','ready')");
$stmt->execute([$studentId]); $activeReservations = (int)$stmt->fetchColumn();

// Reading statistics: favourite category
$stmt = $pdo->prepare("
    SELECT c.name, COUNT(*) c FROM book_issues bi
    JOIN books b ON b.id=bi.book_id LEFT JOIN categories c ON c.id=b.category_id
    WHERE bi.student_id=? GROUP BY c.id ORDER BY c DESC LIMIT 1");
$stmt->execute([$studentId]);
$favCategory = $stmt->fetch();

// Recommendations based on favourite category (simple "smart" feature)
$recommendations = [];
if ($favCategory) {
    $stmt = $pdo->prepare("
        SELECT b.* FROM books b
        JOIN categories c ON c.id = b.category_id
        WHERE c.name = ? AND b.available_copies > 0
        AND b.id NOT IN (SELECT book_id FROM book_issues WHERE student_id = ?)
        ORDER BY RAND() LIMIT 4");
    $stmt->execute([$favCategory['name'], $studentId]);
    $recommendations = $stmt->fetchAll();
}
if (!$recommendations) {
    $recommendations = $pdo->query("SELECT * FROM books WHERE available_copies > 0 ORDER BY RAND() LIMIT 4")->fetchAll();
}

$myIssued = $pdo->prepare("
    SELECT bi.*, b.title FROM book_issues bi JOIN books b ON b.id=bi.book_id
    WHERE bi.student_id=? AND bi.status IN ('issued','overdue') ORDER BY bi.due_date ASC LIMIT 5");
$myIssued->execute([$studentId]);
$myIssued = $myIssued->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-4">
    <div class="card stat-card"><div class="icon blue"><i class="fa-solid fa-book"></i></div><div><div class="num"><?= $activeBooks ?></div><div class="label">Books with Me</div></div></div>
    <div class="card stat-card"><div class="icon green"><i class="fa-solid fa-book-open-reader"></i></div><div><div class="num"><?= $totalBorrowed ?></div><div class="label">Total Borrowed</div></div></div>
    <div class="card stat-card"><div class="icon yellow"><i class="fa-solid fa-bookmark"></i></div><div><div class="num"><?= $activeReservations ?></div><div class="label">Active Reservations</div></div></div>
    <div class="card stat-card"><div class="icon red"><i class="fa-solid fa-coins"></i></div><div><div class="num">&#8377;<?= number_format($pendingFines,2) ?></div><div class="label">Pending Fines</div></div></div>
</div>

<div class="grid grid-2" style="margin-top:18px;">
    <div class="card">
        <h3>My Current Books</h3>
        <?php if (!$myIssued): ?>
            <div class="empty-state"><i class="fa-solid fa-book"></i>You don't have any books issued right now.<br><a href="books.php">Browse the catalog &rarr;</a></div>
        <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Book</th><th>Due Date</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($myIssued as $r):
                $displayStatus = strtotime($r['due_date']) < strtotime(date('Y-m-d')) ? 'overdue' : 'issued';
            ?>
                <tr><td><?= e($r['title']) ?></td><td><?= fmtDate($r['due_date']) ?></td><td><?= statusBadge($displayStatus) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Recommended for You</h3>
        <?php if ($favCategory): ?><p class="text-muted" style="margin-top:-8px; font-size:13px;">Based on your interest in <?= e($favCategory['name']) ?></p><?php endif; ?>
        <?php if (!$recommendations): ?>
            <div class="empty-state"><i class="fa-solid fa-wand-magic-sparkles"></i>No recommendations available yet.</div>
        <?php else: ?>
        <?php foreach ($recommendations as $b): ?>
            <div style="padding:8px 0; border-bottom:1px solid var(--border);">
                <strong><?= e($b['title']) ?></strong>
                <div class="text-muted" style="font-size:12.5px;">Shelf <?= e($b['shelf_number']) ?> &middot; <?= (int)$b['available_copies'] ?> available</div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
