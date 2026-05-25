<?php
require_once 'auth.php';

// Handle delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $article = db()->query("SELECT thumbnail, admin_id FROM news WHERE id=$del_id")->fetch_assoc();
    
    // SECURITY: Ownership enforcement - journalists can only delete their own articles
    if ($article && $admin_role === 'journalist' && $article['admin_id'] != $admin_id) {
        header("Location: news-list.php?err=own");
        exit;
    }
    
    if ($article) {
        // Delete gallery images from disk
        $gallery = db()->query("SELECT image_path FROM news_gallery WHERE news_id=$del_id")->fetch_all(MYSQLI_ASSOC);
        foreach ($gallery as $g) {
            $f = UPLOAD_PATH . $g['image_path'];
            if (file_exists($f)) unlink($f);
        }
        // Delete thumbnail
        $tf = UPLOAD_PATH . $article['thumbnail'];
        if (file_exists($tf)) unlink($tf);
        db()->query("DELETE FROM news WHERE id=$del_id");
        
        // Log this activity for audit trail
        logActivity($_SESSION['admin_id'], 'delete_article', 'article', $del_id, json_encode($article), null);
    }
    header("Location: news-list.php?msg=deleted");
    exit;
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = "WHERE 1=1";
if ($search) {
    $s = db()->real_escape_string($search);
    $where .= " AND (n.title_rw LIKE '%$s%' OR n.title_en LIKE '%$s%')";
}

// SECURITY: Journalists only see their own articles
if ($admin_role === 'journalist') {
    $where .= " AND n.admin_id = $admin_id";
}

$total = db()->query("SELECT COUNT(*) as n FROM news n $where")->fetch_assoc()['n'];
$pages = ceil($total / $per_page);

$news = db()->query("SELECT n.*, c.name_rw as cat_name
    FROM news n JOIN categories c ON n.category_id=c.id $where
    ORDER BY n.created_at DESC LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

adminHeader("All News");
?>
<div class="admin-section">
<?php if ($msg === 'deleted'): ?><div class="alert alert-success">Article deleted successfully.</div><?php endif; ?>
<?php if ($msg === 'updated'): ?><div class="alert alert-success">Article updated successfully.</div><?php endif; ?>
<?php if ($err === 'own'): ?><div class="alert alert-error">You can only edit/delete your own articles.</div><?php endif; ?>

  <div class="list-header">
    <form method="GET" class="search-form">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search news...">
      <button type="submit" class="btn-sm edit"><i class="fa fa-search"></i></button>
    </form>
    <a href="news-add.php" class="btn-publish"><i class="fa fa-plus"></i> Add News</a>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th><th>Thumb</th><th>Title (RW)</th><th>Category</th>
        <th>HL</th><th>Views</th><th>Status</th><th>Date</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($news as $n): ?>
      <tr>
        <td><?= $n['id'] ?></td>
        <td><img src="<?= UPLOAD_URL . e($n['thumbnail']) ?>" class="table-thumb" alt=""></td>
        <td><?= e(mb_substr($n['title_rw'],0,55)) ?><?= mb_strlen($n['title_rw'])>55?'...':'' ?></td>
        <td><?= e($n['cat_name']) ?></td>
        <td><?= $n['is_headline'] ? '⭐' : '' ?></td>
        <td><?= number_format($n['views']) ?></td>
        <td><span class="badge <?= $n['status'] ?>"><?= $n['status'] ?></span></td>
        <td><?= date('d/m/Y', strtotime($n['created_at'])) ?></td>
        <td>
          <a href="news-edit.php?id=<?= $n['id'] ?>" class="btn-sm edit"><i class="fa fa-edit"></i></a>
          <a href="../article.php?id=<?= $n['id'] ?>" target="_blank" class="btn-sm view"><i class="fa fa-eye"></i></a>
          <a href="news-list.php?delete=<?= $n['id'] ?>" class="btn-sm del" onclick="return confirm('Delete this article?')"><i class="fa fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="news-list.php?p=<?= $i ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php adminFooter(); ?>
