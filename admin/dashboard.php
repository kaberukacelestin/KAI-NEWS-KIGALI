<?php
require_once 'auth.php';

$stats = [
    'total_news' => db()->query("SELECT COUNT(*) as n FROM news WHERE status='published'")->fetch_assoc()['n'],
    'total_views' => db()->query("SELECT SUM(views) as n FROM news")->fetch_assoc()['n'] ?? 0,
    'total_ads'   => db()->query("SELECT COUNT(*) as n FROM advertisements WHERE is_active=1")->fetch_assoc()['n'],
    'journalists'  => db()->query("SELECT COUNT(*) as n FROM admins WHERE role='journalist'")->fetch_assoc()['n'],
    'today_news'  => db()->query("SELECT COUNT(*) as n FROM news WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['n'],
];

$recent = db()->query("SELECT n.id, n.title_rw, n.thumbnail, n.views, n.created_at, n.status, c.name_rw as cat_name
    FROM news n JOIN categories c ON n.category_id=c.id ORDER BY n.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

adminHeader("Dashboard");
?>
<?php if (($_GET['err']??'')==='access'): ?>
<div class="alert alert-error" style="margin-bottom:20px">
  <i class="fa fa-lock"></i> Access denied — your journalist account does not have permission to do that.
</div>
<?php endif; ?>
<div class="dash-stats">
  <div class="stat-card blue">
    <i class="fa fa-newspaper"></i>
    <div><span><?= number_format($stats['total_news']) ?></span><small>Published News</small></div>
  </div>
  <div class="stat-card red">
    <i class="fa fa-eye"></i>
    <div><span><?= number_format($stats['total_views']) ?></span><small>Total Views</small></div>
  </div>
  <div class="stat-card green">
    <i class="fa fa-ad"></i>
    <div><span><?= number_format($stats['total_ads']) ?></span><small>Active Ads</small></div>
  </div>
  <div class="stat-card orange">
    <i class="fa fa-calendar-day"></i>
    <div><span><?= number_format($stats['today_news']) ?></span><small>Today's News</small></div>
  </div>
</div>

<div class="dash-actions">
  <a href="news-add.php" class="big-btn"><i class="fa fa-plus"></i> Add News</a>
  <a href="profile.php" class="big-btn secondary"><i class="fa fa-user-circle"></i> My Profile</a>
  <a href="ads.php" class="big-btn secondary"><i class="fa fa-ad"></i> Manage Ads</a>
  <a href="../index.php" target="_blank" class="big-btn secondary"><i class="fa fa-external-link-alt"></i> View Site</a>
</div>

<div class="admin-section">
  <h3>Recent News</h3>
  <table class="admin-table">
    <thead>
      <tr><th>Thumbnail</th><th>Title</th><th>Category</th><th>Views</th><th>Date</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td><img src="<?= UPLOAD_URL . e($r['thumbnail']) ?>" class="table-thumb" alt=""></td>
        <td><?= e(mb_substr($r['title_rw'],0,60)) ?><?= strlen($r['title_rw'])>60?'...':'' ?></td>
        <td><?= e($r['cat_name']) ?></td>
        <td><?= number_format($r['views']) ?></td>
        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
        <td><span class="badge <?= $r['status'] ?>"><?= $r['status'] ?></span></td>
        <td>
          <a href="news-edit.php?id=<?= $r['id'] ?>" class="btn-sm edit"><i class="fa fa-edit"></i></a>
          <a href="news-list.php?delete=<?= $r['id'] ?>" onclick="return confirm('Delete this article?')" class="btn-sm del"><i class="fa fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <a href="news-list.php" class="view-all-link">View all news →</a>
</div>
<?php adminFooter(); ?>
