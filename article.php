<?php
require_once 'includes/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

$stmt = db()->prepare("SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug, a.full_name as author_name, a.profile_picture AS author_photo
    FROM news n JOIN categories c ON n.category_id=c.id JOIN admins a ON n.admin_id=a.id
    WHERE n.id=? AND n.status='published'");
$stmt->bind_param("i", $id);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
if (!$article) { header("Location: index.php"); exit; }

// Increment views
db()->query("UPDATE news SET views=views+1 WHERE id=$id");

// Gallery
$gallery = db()->query("SELECT * FROM news_gallery WHERE news_id=$id ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// Related
$cat_id = $article['category_id'];
$related = db()->query(
    "SELECT n.*, c.name_{$LANG} as cat_name FROM news n JOIN categories c ON n.category_id=c.id"
  . " WHERE n.status='published' AND " . languageContentFilter('n') . " AND n.category_id=$cat_id AND n.id<>$id"
  . " ORDER BY RAND() LIMIT 4"
)->fetch_all(MYSQLI_ASSOC);

$share_url = urlencode(SITE_URL . "/article.php?id=$id&lang=$LANG");
$share_title = urlencode(t($article,'title',$LANG));
$yt_channel = getSetting('youtube_url');

function renderInlineImagesHtml(array $images): string {
    $html = '';
    foreach ($images as $img) {
        $align = $img['align'] ?? 'center';
        $style = match ($align) {
            'left'  => 'float:left;margin:12px 20px 12px 0;max-width:45%;',
            'right' => 'float:right;margin:12px 0 12px 20px;max-width:45%;',
            default => 'display:block;margin:20px auto;max-width:90%;clear:both;',
        };
        $html .= '<figure class="inline-article-img" style="' . $style . '">';
        $html .= '<img src="' . UPLOAD_URL . e($img['url']) . '" alt="' . e($img['caption'] ?? '') . '" loading="lazy" style="width:100%;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.15)">';
        if (!empty($img['caption'])) {
            $html .= '<figcaption style="text-align:center;font-size:.8rem;color:#888;margin-top:6px;font-style:italic">' . e($img['caption']) . '</figcaption>';
        }
        $html .= '</figure>';
    }
    return $html;
}

function renderArticleContent(string $raw_desc, array $inline_imgs): string {
    if (empty($inline_imgs)) {
        return nl2br(e($raw_desc));
    }

    $raw_desc = str_replace(["\r\n", "\r"], "\n", $raw_desc);
    $image_map = [];
    foreach ($inline_imgs as $img) {
        $image_map[intval($img['after_p'])][] = $img;
    }

    if (stripos($raw_desc, '</p>') !== false) {
        $parts = preg_split('/(<\/p>)/i', $raw_desc, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        $pIndex = 0;

        if (isset($image_map[0])) {
            $html .= renderInlineImagesHtml($image_map[0]);
        }

        for ($i = 0; $i < count($parts); $i += 2) {
            $html .= $parts[$i];
            if (isset($parts[$i + 1])) {
                $html .= $parts[$i + 1];
                $pIndex++;
                if (isset($image_map[$pIndex])) {
                    $html .= renderInlineImagesHtml($image_map[$pIndex]);
                }
            }
        }

        $max = $pIndex;
        foreach ($image_map as $pos => $images) {
            if ($pos > $max) {
                $html .= renderInlineImagesHtml($images);
            }
        }

        return $html;
    }

    $paragraphs = preg_split('/\n{2,}/', trim($raw_desc));
    if (!$paragraphs) {
        return nl2br(e($raw_desc));
    }

    $html = '';
    if (isset($image_map[0])) {
        $html .= renderInlineImagesHtml($image_map[0]);
    }

    foreach ($paragraphs as $index => $para) {
        $html .= '<p>' . nl2br(e(trim($para))) . '</p>';
        $pIndex = $index + 1;
        if (isset($image_map[$pIndex])) {
            $html .= renderInlineImagesHtml($image_map[$pIndex]);
        }
    }

    $max = count($paragraphs);
    foreach ($image_map as $pos => $images) {
        if ($pos > $max) {
            $html .= renderInlineImagesHtml($images);
        }
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(t($article,'title',$LANG)) ?> — KAI News Kigali</title>
<meta name="description" content="<?= e(mb_substr(strip_tags(t($article,'description',$LANG)),0,160)) ?>">
<meta property="og:image" content="<?= UPLOAD_URL . e($article['thumbnail']) ?>">
<link rel="stylesheet" href="assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container article-layout">
  <main class="article-main">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <a href="index.php?lang=<?= $LANG ?>"><?= L('home') ?></a> &rsaquo;
      <a href="category.php?slug=<?= urlencode($article['cat_slug']) ?>&lang=<?= $LANG ?>"><?= e($article['cat_name']) ?></a> &rsaquo;
      <span><?= e(mb_substr(t($article,'title',$LANG),0,60)) ?>...</span>
    </nav>

    <article class="article-body">
      <div class="article-cat-badge"><?= e($article['cat_name']) ?></div>
      <h1 class="article-title"><?= e(t($article,'title',$LANG)) ?></h1>

      <div class="article-meta article-author-meta">
        <div class="author-card">
          <img src="<?= profileThumbnail($article['author_photo']) ?>" alt="<?= e($article['author_name']) ?>" class="author-avatar">
          <div class="author-info">
            <div class="author-label"><?= L('written_by') ?? 'Written by' ?></div>
            <div class="author-name"><?= e($article['author_name']) ?></div>
          </div>
        </div>
        <span class="meta-item"><i class="fa fa-calendar"></i> <?= date('d F Y, H:i', strtotime($article['created_at'])) ?></span>
        <span class="meta-item"><i class="fa fa-eye"></i> <?= number_format($article['views']) ?> <?= L('views') ?></span>
      </div>

      <!-- Share Buttons -->
      <div class="share-bar">
        <span><?= L('share') ?>:</span>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>" target="_blank" class="share-btn fb"><i class="fab fa-facebook-f"></i></a>
        <a href="https://twitter.com/intent/tweet?url=<?= $share_url ?>&text=<?= $share_title ?>" target="_blank" class="share-btn tw"><i class="fab fa-x-twitter"></i></a>
        <a href="https://wa.me/?text=<?= $share_title ?>%20<?= $share_url ?>" target="_blank" class="share-btn wa"><i class="fab fa-whatsapp"></i></a>
        <a href="https://t.me/share/url?url=<?= $share_url ?>&text=<?= $share_title ?>" target="_blank" class="share-btn tg"><i class="fab fa-telegram-plane"></i></a>
      </div>
      <?php if ($yt_channel): ?>
      <div class="article-follow-us">
        <a href="watch.php?src=youtube&lang=<?= $LANG ?>" class="btn btn-youtube">
          <i class="fab fa-youtube"></i> <?= $LANG==='rw'?'Subscribe on YouTube':'Subscribe on YouTube' ?>
        </a>
      </div>
      <?php endif; ?>

      <!-- Thumbnail -->
      <div class="article-thumbnail">
        <img src="<?= UPLOAD_URL . e($article['thumbnail']) ?>" alt="<?= e(t($article,'title',$LANG)) ?>">
      </div>

      <!-- Content with Inline Images -->
      <div class="article-content">
        <?php
        $raw_desc = t($article,'description',$LANG);
        $inline_imgs = !empty($article['inline_images']) ? json_decode($article['inline_images'], true) : [];
        echo renderArticleContent($raw_desc, $inline_imgs);
        ?>
      </div>
      <div style="clear:both"></div>

      <!-- Photo Gallery -->
      <?php if (!empty($gallery)): ?>
      <div class="article-gallery">
        <h3 class="gallery-title"><i class="fa fa-images"></i> <?= L('gallery') ?></h3>
        <div class="gallery-grid">
          <?php foreach ($gallery as $img): ?>
          <div class="gallery-item" onclick="openLightbox('<?= UPLOAD_URL . e($img['image_path']) ?>')">
            <img src="<?= UPLOAD_URL . e($img['image_path']) ?>" alt="<?= e($img['caption']) ?>" loading="lazy">
            <?php if ($img['caption']): ?><span class="gallery-caption"><?= e($img['caption']) ?></span><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Language Versions -->
      <div class="lang-versions">
        <strong><?= $LANG==='rw'?'Soma mu zindi ndimi:':($LANG==='en'?'Read in other languages:':'') ?></strong>
        <?php foreach (['rw'=>'Kinyarwanda','en'=>'English','sw'=>'Swahili','fr'=>'Français'] as $lc=>$ln): ?>
          <?php if ($lc !== $LANG && !empty($article['title_'.$lc])): ?>
          <a href="article.php?id=<?= $id ?>&lang=<?= $lc ?>" class="lang-ver-btn"><?= $ln ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

    </article>

    <!-- Related News -->
    <?php if (!empty($related)): ?>
    <section class="related-news">
      <div class="section-header">
        <h2><?= L('related') ?></h2>
        <div class="section-line"></div>
      </div>
      <div class="news-grid cat-grid-4">
        <?php foreach ($related as $r): ?>
        <article class="news-card mini-card">
          <a href="article.php?id=<?= $r['id'] ?>&lang=<?= $LANG ?>" class="card-img-wrap">
            <img src="<?= UPLOAD_URL . e($r['thumbnail']) ?>" alt="<?= e(t($r,'title',$LANG)) ?>" loading="lazy">
          </a>
          <div class="card-body">
            <h3 class="card-title">
              <a href="article.php?id=<?= $r['id'] ?>&lang=<?= $LANG ?>"><?= e(t($r,'title',$LANG)) ?></a>
            </h3>
            <div class="card-meta">
              <span><i class="fa fa-clock"></i> <?= date('d M Y', strtotime($r['created_at'])) ?></span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </main>

  <!-- Sidebar -->
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
    <?php
    $ads_sidebar = db()->query("SELECT * FROM advertisements WHERE position='sidebar' AND is_active=1 ORDER BY created_at DESC LIMIT 2")->fetch_all(MYSQLI_ASSOC);
    foreach ($ads_sidebar as $ad):
    ?>
    <div class="widget ad-widget">
      <a href="<?= e($ad['link_url']) ?>" target="_blank">
        <?php if (!empty($ad['video'])): ?>
          <video src="<?= UPLOAD_URL . e($ad['video']) ?>" muted playsinline loop style="width:100%;height:auto;display:block;object-fit:cover;"></video>
        <?php else: ?>
          <img src="<?= UPLOAD_URL . e($ad['image']) ?>" alt="<?= e($ad['title']) ?>">
        <?php endif; ?>
      </a>
    </div>
    <?php endforeach; ?>
    <div class="widget">
      <div class="widget-title"><?= $LANG==='rw'?'Byasomwe Cyane':'Most Read' ?></div>
      <?php
      $most_read = db()->query("SELECT id, title_rw, title_en, title_sw, title_fr, views FROM news WHERE status='published' ORDER BY views DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
      foreach ($most_read as $i => $mr):
      ?>
      <div class="most-read-item">
        <span class="mr-num"><?= $i+1 ?></span>
        <div class="mr-info">
          <a href="article.php?id=<?= $mr['id'] ?>&lang=<?= $LANG ?>"><?= e(t($mr,'title',$LANG)) ?></a>
          <span><i class="fa fa-eye"></i> <?= number_format($mr['views']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lightbox-close">&times;</button>
  <img id="lightboxImg" src="" alt="">
</div>

<?php include 'includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
<script>
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('active');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('active');
}
</script>
</body>
</html>
