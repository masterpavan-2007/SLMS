<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
requireRole(['admin']);

$reportType = $_GET['type'] ?? 'issued';

$reportQueries = [
    'total_books'    => "SELECT b.title, a.name author, c.name category, b.total_copies, b.available_copies FROM books b LEFT JOIN authors a ON a.id=b.author_id LEFT JOIN categories c ON c.id=b.category_id ORDER BY b.title",
    'available'      => "SELECT b.title, a.name author, b.available_copies, b.shelf_number FROM books b LEFT JOIN authors a ON a.id=b.author_id WHERE b.available_copies > 0 ORDER BY b.title",
    'issued'         => "SELECT u.name student, s.roll_number, b.title, bi.issue_date, bi.due_date FROM book_issues bi JOIN students s ON s.id=bi.student_id JOIN users u ON u.id=s.user_id JOIN books b ON b.id=bi.book_id WHERE bi.status IN ('issued','overdue') ORDER BY bi.due_date",
    'returned'       => "SELECT u.name student, s.roll_number, b.title, br.return_date, br.late_days, br.fine_amount FROM book_returns br JOIN book_issues bi ON bi.id=br.issue_id JOIN students s ON s.id=bi.student_id JOIN users u ON u.id=s.user_id JOIN books b ON b.id=bi.book_id ORDER BY br.return_date DESC",
    'overdue'        => "SELECT u.name student, s.roll_number, b.title, bi.due_date, DATEDIFF(CURDATE(), bi.due_date) late_days FROM book_issues bi JOIN students s ON s.id=bi.student_id JOIN users u ON u.id=s.user_id JOIN books b ON b.id=bi.book_id WHERE bi.status='issued' AND bi.due_date < CURDATE() ORDER BY late_days DESC",
    'fines'          => "SELECT u.name student, s.roll_number, f.amount, f.reason, f.status, f.created_at FROM fines f JOIN students s ON s.id=f.student_id JOIN users u ON u.id=s.user_id ORDER BY f.created_at DESC",
    'payments'       => "SELECT u.name student, s.roll_number, p.amount, p.payment_method, p.payment_date FROM payments p JOIN students s ON s.id=p.student_id JOIN users u ON u.id=s.user_id ORDER BY p.payment_date DESC",
    'student_borrow' => "SELECT u.name student, s.roll_number, COUNT(bi.id) total_borrowed FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN book_issues bi ON bi.student_id=s.id GROUP BY s.id ORDER BY total_borrowed DESC",
    'most_borrowed'  => "SELECT b.title, COUNT(bi.id) times_borrowed FROM books b LEFT JOIN book_issues bi ON bi.book_id=b.id GROUP BY b.id ORDER BY times_borrowed DESC LIMIT 20",
    'category_wise'  => "SELECT c.name category, COUNT(b.id) total_books FROM categories c LEFT JOIN books b ON b.category_id=c.id GROUP BY c.id ORDER BY total_books DESC",
    'monthly'        => "SELECT DATE_FORMAT(issue_date,'%Y-%m') month, COUNT(*) total_issued FROM book_issues GROUP BY month ORDER BY month DESC LIMIT 12",
];

$labels = [
    'total_books' => 'Total Books Report', 'available' => 'Available Books Report', 'issued' => 'Issued Books Report',
    'returned' => 'Returned Books Report', 'overdue' => 'Overdue Books Report', 'fines' => 'Fine Report',
    'payments' => 'Payment Report', 'student_borrow' => 'Student Borrowing Report', 'most_borrowed' => 'Most Borrowed Books',
    'category_wise' => 'Category-wise Books', 'monthly' => 'Monthly Issue Report',
];

if (!isset($reportQueries[$reportType])) { $reportType = 'issued'; }
$rows = $pdo->query($reportQueries[$reportType])->fetchAll();
$columns = $rows ? array_keys($rows[0]) : [];

// ---------- CSV export ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $reportType . '_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_map(fn($c) => ucwords(str_replace('_', ' ', $c)), $columns));
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

$pageTitle = 'Reports';
$currentPage = 'reports.php';
include __DIR__ . '/../includes/header.php';
?>
<div class="table-toolbar no-print">
    <select class="form-control" style="width:260px;" onchange="window.location='reports.php?type='+this.value">
        <?php foreach ($labels as $key => $label): ?>
            <option value="<?= $key ?>" <?= $reportType === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <div class="flex gap-2">
        <a class="btn btn-outline" href="reports.php?type=<?= $reportType ?>&export=csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    </div>
</div>

<div class="card">
    <h3><?= e($labels[$reportType]) ?> <span class="text-muted" style="font-weight:400; font-size:13px;">(generated <?= date('d M Y, h:i A') ?>)</span></h3>
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-solid fa-chart-column"></i>No data available for this report.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><?php foreach ($columns as $c): ?><th><?= e(ucwords(str_replace('_',' ',$c))) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr><?php foreach ($r as $col => $val): ?>
                    <td><?= e(is_null($val) ? '—' : $val) ?></td>
                <?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
