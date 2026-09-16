<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
requireRole(['student']);
$pageTitle = 'Notifications';
$currentPage = 'notifications.php';

// Mark all as read when the page is viewed
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);

$notifications = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50");
$notifications->execute([$_SESSION['user_id']]);
$notifications = $notifications->fetchAll();

$icons = [
    'Book Issued' => 'fa-right-from-bracket', 'Book Returned' => 'fa-right-to-bracket',
    'Due Soon' => 'fa-clock', 'Reservation Ready' => 'fa-bookmark', 'Payment Completed' => 'fa-credit-card',
];

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <?php if (!$notifications): ?>
        <div class="empty-state"><i class="fa-solid fa-bell-slash"></i>No notifications yet.</div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div style="display:flex; gap:12px; padding:14px 0; border-bottom:1px solid var(--border);">
                <div style="width:36px; height:36px; border-radius:50%; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fa-solid <?= $icons[$n['title']] ?? 'fa-bell' ?>"></i>
                </div>
                <div>
                    <strong><?= e($n['title']) ?></strong>
                    <div style="font-size:13.5px;"><?= e($n['message']) ?></div>
                    <div class="text-muted" style="font-size:12px;"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
