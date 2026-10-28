<?php
require_once 'auth.php';
$admin_id = $_SESSION['admin_id'];

if (isset($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    markNotificationAsRead($notification_id);
    header('Location: notifications.php');
    exit;
}

if (isset($_GET['mark_all'])) {
    markAllNotificationsAsRead($admin_id);
    header('Location: notifications.php');
    exit;
}

$stmt = db()->prepare("SELECT n.*, a.full_name AS sender_name FROM notifications n LEFT JOIN admins a ON n.sender_id=a.id WHERE n.recipient_id=? ORDER BY n.created_at DESC");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

adminHeader("Notifications");
?>
<div class="admin-section">
  <div class="list-header" style="align-items:center; gap:12px;">
    <h2 style="margin:0;">Notifications</h2>
    <a href="notifications.php?mark_all=1" class="btn-sm edit" style="margin-left:auto;">Mark all read</a>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="alert alert-info">No notifications yet.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Status</th>
          <th>Title</th>
          <th>Message</th>
          <th>Sender</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notifications as $note): ?>
        <tr class="<?= $note['is_read'] ? '' : 'unread' ?>">
          <td><?= $note['is_read'] ? 'Read' : 'Unread' ?></td>
          <td><?= e($note['title']) ?></td>
          <td><?= nl2br(e($note['message'])) ?></td>
          <td><?= e($note['sender_name'] ?: 'System') ?></td>
          <td><?= date('d/m/Y H:i', strtotime($note['created_at'])) ?></td>
          <td>
            <?php if (!$note['is_read']): ?>
              <a href="notifications.php?mark_read=<?= $note['id'] ?>" class="btn-sm edit">Mark read</a>
            <?php else: ?>
              <span class="badge published">Read</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php adminFooter(); ?>
