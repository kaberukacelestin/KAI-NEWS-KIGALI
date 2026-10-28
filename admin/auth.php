<?php
require_once '../includes/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

// Suspended accounts are kicked out
if ($admin_role === 'suspended') {
    session_destroy();
    header("Location: login.php?err=suspended");
    exit;
}

/**
 * Role permission helper
 * Roles: superadmin > editor > journalist
 */
function canDo(string $action): bool {
    global $admin_role;
    $permissions = [
        'journalist' => ['add_news','edit_news'],
        'editor'     => ['add_news','edit_news','delete_news','manage_ads'],
        'superadmin' => ['add_news','edit_news','delete_news','manage_ads','manage_settings','manage_journalists','manage_ai_news'],
        'notifier'   => [],
        'suspended'  => [],
    ];
    return in_array($action, $permissions[$admin_role] ?? []);
}

function requirePermission(string $action): void {
    if (!canDo($action)) {
        header("Location: dashboard.php?err=access");
        exit;
    }
}

function adminHeader($page_title) {
    global $admin_name, $admin_role;
    $roleColor = match($admin_role) {
        'superadmin' => '#c0392b',
        'editor'     => '#2980b9',
        'notifier'   => '#8e44ad',
        'suspended'  => '#7f8c8d',
        default      => '#27ae60', // journalist
    };
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — KAI News Kigali Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    </head>
    <body class="admin-body">
    <div class="admin-layout">
    <aside class="admin-sidebar">
      <div class="sidebar-logo">
        <img src="../assets/images/logo.png" alt="KAI News Kigali">
        <span>Admin Panel</span>
      </div>
      <nav class="sidebar-nav">
        <a href="dashboard.php"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
        <?php if (canDo('add_news')): ?>
        <a href="news-add.php"><i class="fa fa-plus-circle"></i> Add News</a>
        <?php endif; ?>
        <?php if (canDo('edit_news')): ?>
        <a href="news-list.php"><i class="fa fa-newspaper"></i> All News</a>
        <?php endif; ?>
        <?php if (canDo('manage_ai_news')): ?>
        <a href="ai-news.php"><i class="fa fa-wand-magic-sparkles"></i> AI News Desk</a>
        <?php endif; ?>
        <?php if (canDo('manage_ads')): ?>
        <a href="ads.php"><i class="fa fa-ad"></i> Advertisements</a>
        <?php endif; ?>
        <?php if (canDo('manage_journalists')): ?>
        <a href="manage-journalists.php"><i class="fa fa-users"></i> Journalists
          <?php
          // Show badge for pending applications
          $pending = db()->query("SELECT COUNT(*) as n FROM journalist_requests WHERE status='pending'")->fetch_assoc()['n'];
          if ($pending > 0): ?>
            <span style="background:#e74c3c;color:#fff;border-radius:10px;padding:1px 7px;font-size:.75rem;margin-left:6px"><?= $pending ?></span>
          <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (canDo('manage_settings')): ?>
        <a href="settings.php"><i class="fa fa-cog"></i> Settings</a>
        <?php endif; ?>
        <a href="notifications.php"><i class="fa fa-bell"></i> Notifications
          <?php if (isset($_SESSION['admin_id'])):
              $notifCount = getUnreadNotificationCount($_SESSION['admin_id']);
              if ($notifCount > 0): ?>
                <span class="notif-badge"><?= $notifCount ?></span>
          <?php endif; endif; ?>
        </a>
        <a href="logout.php" class="logout-link"><i class="fa fa-sign-out-alt"></i> Logout</a>
      </nav>
    </aside>
    <div class="admin-content">
      <header class="admin-topbar">
        <h1><?= htmlspecialchars($page_title) ?></h1>
        <div class="admin-user">
          <i class="fa fa-user-circle"></i>
          <span><?= htmlspecialchars($admin_name) ?></span>
          <span class="role-badge" style="background:<?= $roleColor ?>;color:#fff;padding:2px 10px;border-radius:12px;font-size:.75rem;font-weight:700;text-transform:uppercase">
            <?= htmlspecialchars($admin_role) ?>
          </span>
        </div>
      </header>
    <?php
}

function adminFooter() {
    echo '</div></div></body></html>';
}
?>
