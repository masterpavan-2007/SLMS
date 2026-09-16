<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
requireRole(['student']);
$pageTitle = 'Browse Books';
$currentPage = 'books.php';
$settings = getSettings($pdo);

$stu = $pdo->prepare("SELECT * FROM students WHERE user_id=?");
$stu->execute([$_SESSION['user_id']]);
$student = $stu->fetch();
$studentId = $student['id'];

// ---------- Reserve a book ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $bookId = (int)($_POST['book_id'] ?? 0);
    $bk = $pdo->prepare("SELECT * FROM books WHERE id=?");
    $bk->execute([$bookId]);
    $book = $bk->fetch();

    $existing = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE student_id=? AND book_id=? AND status IN ('pending','approved','ready')");
    $existing->execute([$studentId, $bookId]);

    if (!$book) {
        setFlash('error', 'Book not found.');
    } elseif ($existing->fetchColumn() > 0) {
        setFlash('error', 'You already have an active reservation for this book.');
    } else {
        $expiry = date('Y-m-d', strtotime("+{$settings['reservation_valid_days']} days"));
        $pdo->prepare("INSERT INTO reservations (student_id, book_id, reservation_date, expiry_date, status) VALUES (?,?,CURDATE(),?, 'pending')")
            ->execute([$studentId, $bookId, $expiry]);
        setFlash('success', 'Book reserved. We will notify you when it becomes available.');
    }
    redirect('books.php');
}

$search = trim($_GET['q'] ?? '');
$catId = $_GET['category'] ?? '';
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$where = []; $params = [];
if ($search !== '') {
    $where[] = "(b.title LIKE ? OR b.isbn LIKE ? OR a.name LIKE ? OR c.name LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($catId !== '') { $where[] = "b.category_id = ?"; $params[] = $catId; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT b.*, a.name author_name, c.name category_name
    FROM books b LEFT JOIN authors a ON a.id=b.author_id LEFT JOIN categories c ON c.id=b.category_id
    $whereSql ORDER BY b.title LIMIT 60");
$stmt->execute($params);
$books = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="table-toolbar">
    <form method="GET" class="flex gap-2" style="flex-wrap:wrap;">
        <input type="text" name="q" class="form-control" style="width:260px;" placeholder="Search title, author, category..." value="<?= e($search) ?>">
        <select name="category" class="form-control" style="width:170px;">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    </form>
</div>

<?php if (!$books): ?>
    <div class="card"><div class="empty-state"><i class="fa-solid fa-book"></i>No books match your search.</div></div>
<?php else: ?>
<div class="grid grid-3">
    <?php foreach ($books as $b): ?>
        <div class="card">
            <div style="display:flex; gap:12px;">
                <div style="width:52px; height:70px; background:var(--primary-light); border-radius:6px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fa-solid fa-book" style="color:var(--primary); font-size:20px;"></i>
                </div>
                <div>
                    <strong><?= e($b['title']) ?></strong>
                    <div class="text-muted" style="font-size:12.5px;"><?= e($b['author_name'] ?? 'Unknown author') ?></div>
                    <div class="text-muted" style="font-size:12.5px;"><?= e($b['category_name'] ?? '') ?> &middot; Shelf <?= e($b['shelf_number']) ?></div>
                </div>
            </div>
            <div class="flex justify-between items-center" style="margin-top:14px;">
                <?= statusBadge($b['available_copies'] > 0 ? 'available' : 'issued') ?>
                <?php if ($b['available_copies'] > 0): ?>
                    <span class="text-muted" style="font-size:12.5px;"><?= (int)$b['available_copies'] ?> copies left</span>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfField() ?><input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                        <button class="btn btn-sm btn-outline" type="submit"><i class="fa-solid fa-bookmark"></i> Reserve</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
