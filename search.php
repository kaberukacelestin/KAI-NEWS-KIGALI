<?php
require_once 'includes/config.php';

$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$per_page = 10;
$offset = ($page-1) * $per_page;
$news = [];
$total = 0;

if ($q) {
    $sq = db()->real_escape_string($q);
    $titleField = languageTitleField('n');
    $descField = languageDescriptionField('n');
    $where = "WHERE n.status='published' AND " . languageContentFilter('n') . " AND (" . $titleField . " LIKE '%$sq%' OR " . $descField . " LIKE '%$sq%')";
    $total = db()->query("SELECT COUNT(*) as n FROM news n $where")->fetch_assoc()['n'];
    $pages = ceil($total / $per_page);
    $news = db()->query(
        "SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug"
      . " FROM news n JOIN categories c ON n.category_id=c.id " . $where
      . " ORDER BY n.created_at DESC LIMIT $per_page OFFSET $offset"
    )->fetch_all(MYSQLI_ASSOC);
}
$show_views = canSeeViews();
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= L('search') ?> — KAI News Kigali</title>
<link rel="stylesheet" href="assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container article-layout">
  <main class="article-main">
    <div class="cat-page-header">
      <h1><?= L('search') ?>: <?= e($q) ?></h1>
      <?php if ($q): ?><span><?= $total ?> <?= $LANG==='en'?'results found':'ibyabonetse' ?></span><?php endif; ?>
    </div>
    <form action="search.php" method="GET" style="margin-bottom:24px">
      <input type="hidden" name="lang" value="<?= $LANG ?>">
      <div style="display:flex;gap:0">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= L('search') ?>..."
               style="flex:1;padding:12px 18px;border:2px solid #dde3ed;border-right:none;border-radius:6px 0 0 6px;font-size:15px;outline:none">
        <button type="submit" style="background:#c8102e;color:white;padding:12px 24px;border:none;border-radius:0 6px 6px 0;cursor:pointer;font-size:15px">
          <i class="fa fa-search"></i>
        </button>
      </div>
    </form>
    <?php if ($q && empty($news)): ?>
      <p style="color:#6b7280;font-size:15px">No results found for "<?= e($q) ?>".</p>
    <?php endif; ?>
    <div class="news-grid">
      <?php foreach ($news as $n): ?>
      <article class="news-card">
        <a href="article.php?id=<?= $n['id'] ?>&lang=<?= $LANG ?>" class="card-img-wrap">
          <img src="<?= newsThumbnail($n['thumbnail']) ?>" alt="<?= e(t($n,'title',$LANG)) ?>" loading="lazy">
          <span class="card-cat"><?= e($n['cat_name']) ?></span>
        </a>
        <div class="card-body">
          <h3 class="card-title"><a href="article.php?id=<?= $n['id'] ?>&lang=<?= $LANG ?>"><?= e(t($n,'title',$LANG)) ?></a></h3>
          <div class="card-meta">
            <span><i class="fa fa-clock"></i> <?= date('d M Y', strtotime($n['created_at'])) ?></span>
            <?php if ($show_views): ?>
            <span><i class="fa fa-eye"></i> <?= number_format($n['views']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($pages) && $pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="search.php?q=<?= urlencode($q) ?>&p=<?= $i ?>&lang=<?= $LANG ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </main>
  <aside class="sidebar">
    <div class="widget">
      <div class="widget-title"><?= $LANG==='en'?'Categories':'Indinganizo' ?></div>
      <?php foreach (getCategories() as $cat): ?>
      <a href="category.php?slug=<?= urlencode($cat['slug']) ?>&lang=<?= $LANG ?>" class="cat-link">
        <i class="fa fa-chevron-right"></i> <?= e($cat['name_'.$LANG]) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
<?php include 'includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
