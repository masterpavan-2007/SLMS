<?php
/**
 * Renders the top bar. Expects $pageTitle to be set by the calling page.
 * Uses $pdo (already available since header.php is included after DB connect).
 */
$unreadCount = 0;
if (isset($pdo, $_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadCount = (int)$stmt->fetchColumn();
}
$initials = '';
foreach (explode(' ', $_SESSION['name'] ?? 'U') as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
$notifLink = match($_SESSION['role'] ?? '') {
    'student' => basePath() . '/student/notifications.php',
    default   => '#',
};
?>
<div class="topbar">
    <div class="flex items-center gap-2">
        <button class="menu-toggle"><i class="fa-solid fa-bars"></i></button>
        <div class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
    </div>
    <div class="topbar-right">
        <a href="<?= e($notifLink) ?>" class="bell" title="Notifications">
            <i class="fa-solid fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
                <span class="dot"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <div class="user-chip">
            <div class="avatar"><?= e($initials) ?></div>
            <div>
                <div style="font-weight:600;"><?= e($_SESSION['name'] ?? '') ?></div>
                <div class="text-muted" style="font-size:12px;"><?= e(ucfirst($_SESSION['role'] ?? '')) ?></div>
            </div>
        </div>
    </div>
</div>
