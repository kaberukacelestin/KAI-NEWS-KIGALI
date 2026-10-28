<?php
require_once 'includes/config.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') { header('Location: index.php'); exit; }
$stmt = db()->prepare('SELECT * FROM categories WHERE slug = ?');
$stmt->bind_param('s', $slug);
$stmt->execute();
$cat = $stmt->get_result()->fetch_assoc();
if (!$cat) { header('Location: index.php'); exit; }

$page = max(1, intval($_GET['p'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;
$cat_id = $cat['id'];

$total = db()->query("SELECT COUNT(*) as n FROM news n WHERE n.status='published' AND " . languageContentFilter('n') . " AND n.category_id=$cat_id")->fetch_assoc()['n'];
$pages = ceil($total / $per_page);

$news = db()->query(
    "SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug"
  . " FROM news n JOIN categories c ON n.category_id=c.id"
  . " WHERE n.status='published' AND " . languageContentFilter('n') . " AND n.category_id=$cat_id"
  . " ORDER BY n.created_at DESC LIMIT $per_page OFFSET $offset"
)->fetch_all(MYSQLI_ASSOC);
$show_views = canSeeViews();
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($cat['name_'.$LANG]) ?> — KAI News Kigali</title>
<link rel="stylesheet" href="assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container article-layout">
  <main class="article-main">
    <div class="cat-page-header">
      <h1><?= e($cat['name_'.$LANG]) ?></h1>
      <span><?= $total ?> <?= $LANG==='en'?'articles':'inkuru' ?></span>
    </div>
    <div class="news-grid">
      <?php foreach ($news as $n): ?>
      <article class="news-card">
        <a href="article.php?id=<?= $n['id'] ?>&lang=<?= $LANG ?>" class="card-img-wrap">
          <img src="<?= newsThumbnail($n['thumbnail']) ?>" alt="<?= e(t($n,'title',$LANG)) ?>" loading="lazy">
          <span class="card-cat"><?= e($n['cat_name']) ?></span>
        </a>
        <div class="card-body">
          <h3 class="card-title">
            <a href="article.php?id=<?= $n['id'] ?>&lang=<?= $LANG ?>"><?= e(t($n,'title',$LANG)) ?></a>
          </h3>
          <p class="card-excerpt"><?= e(mb_substr(strip_tags(t($n,'description',$LANG)),0,110)) ?>...</p>
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
    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="category.php?slug=<?= urlencode($slug) ?>&p=<?= $i ?>&lang=<?= $LANG ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </main>
  <aside class="sidebar">
    <div class="widget search-widget">
      <form action="search.php" method="GET">
        <input type="hidden" name="lang" value="<?= $LANG ?>">
        <div class="search-box">
          <input type="text" name="q" placeholder="<?= L('search') ?>...">
          <button type="submit"><i class="fa fa-search"></i></button>
        </div>
      </form>
    </div>
    <div class="widget">
      <div class="widget-title"><?= $LANG==='en'?'Categories':'Indinganizo' ?></div>
      <?php foreach (getCategories() as $c): ?>
      <a href="category.php?slug=<?= urlencode($c['slug']) ?>&lang=<?= $LANG ?>" class="cat-link <?= $c['id']===$cat_id?'active':'' ?>">
        <i class="fa fa-chevron-right"></i> <?= e($c['name_'.$LANG]) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
<?php include 'includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
