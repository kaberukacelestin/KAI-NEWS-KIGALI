<?php
require_once 'includes/config.php';
require_once 'includes/article-references.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

$stmt = db()->prepare("SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug, a.full_name as author_name, a.profile_picture AS author_photo
    FROM news n JOIN categories c ON n.category_id=c.id JOIN admins a ON n.admin_id=a.id
    WHERE n.id=? AND n.status='published'");
$stmt->bind_param("i", $id);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
if (!$article) { header("Location: index.php"); exit; }
$articleReferences = decodeArticleReferences($article['article_references'] ?? null);

$ai_source = null;
$ai_source_table = db()->query("SHOW TABLES LIKE 'ai_news_items'");
if ($ai_source_table && $ai_source_table->num_rows > 0) {
    $source_stmt = db()->prepare("SELECT source_name, source_url FROM ai_news_items WHERE news_id=? AND status='drafted' LIMIT 1");
    if (!$source_stmt) {
        throw new RuntimeException('Could not prepare AI source attribution lookup: ' . db()->error);
    }
    $source_stmt->bind_param('i', $id);
    if (!$source_stmt->execute()) {
        throw new RuntimeException('Could not load AI source attribution: ' . $source_stmt->error);
    }
    $ai_source = $source_stmt->get_result()->fetch_assoc();
    if ($ai_source) {
        $source_parts = parse_url($ai_source['source_url']);
        if (!is_array($source_parts) || !in_array(strtolower($source_parts['scheme'] ?? ''), ['http', 'https'], true) || empty($source_parts['host'])) {
            $ai_source = null;
        }
    }
}

// Increment views
db()->query("UPDATE news SET views=views+1 WHERE id=$id");

$show_views = isset($_SESSION['admin_id']) && in_array($_SESSION['admin_role'] ?? '', ['superadmin','journalist'], true);

// Gallery
$gallery = db()->query("SELECT * FROM news_gallery WHERE news_id=$id ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// Related
$cat_id = $article['category_id'];
$related = db()->query(
    "SELECT n.*, c.name_{$LANG} as cat_name FROM news n JOIN categories c ON n.category_id=c.id"
  . " WHERE n.status='published' AND " . languageContentFilter('n') . " AND n.category_id=$cat_id AND n.id<>$id"
  . " ORDER BY RAND() LIMIT 4"
)->fetch_all(MYSQLI_ASSOC);

function getActiveAdCondition(): string {
    return "is_active=1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())";
}

$share_url = urlencode(SITE_URL . "/article.php?id=$id&lang=$LANG");
$share_title = urlencode(t($article,'title',$LANG));
$yt_channel = getSetting('youtube_url');
$ads_top = db()->query("SELECT * FROM advertisements WHERE position='banner_top' AND " . getActiveAdCondition() . " ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$topPhotoPositions = ['banner_top_photo_1','banner_top_photo_2'];
$ads_top_photos = db()->query("SELECT * FROM advertisements WHERE position IN ('" . implode("','", $topPhotoPositions) . "') AND " . getActiveAdCondition() . " AND image<>'' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$ads_top_photo_slots = array_column($ads_top_photos, null, 'position');
$ads_mid = db()->query("SELECT * FROM advertisements WHERE position='banner_mid' AND " . getActiveAdCondition() . " ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$ads_sidebar = db()->query("SELECT * FROM advertisements WHERE position='sidebar' AND " . getActiveAdCondition() . " ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

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
<meta property="og:image" content="<?= e(newsThumbnail($article['thumbnail'])) ?>">
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

    <?php if ($ads_top): ?>
      <?php $anim_top = !empty($ads_top['is_animated']) ? 'ad-anim-'.$ads_top['animation_type'].' ad-animate-on-scroll' : ''; ?>
      <div class="ad-banner ad-top ad-top-split <?= $anim_top ?>">
        <?php
          $ad_vid_raw = trim($ads_top['video'] ?? '');
          $ad_link = !empty($ads_top['link_url']) ? e($ads_top['link_url']) : '';
        ?>
        <?php if ($ad_vid_raw): ?>
          <?php if (stripos($ad_vid_raw, 'youtube.com') !== false || stripos($ad_vid_raw, 'youtu.be') !== false): ?>
            <?php $embed = null; if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $ad_vid_raw, $m)) { $embed = 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&autoplay=1&mute=1&controls=0&disablekb=1&modestbranding=1&playsinline=1&loop=1&fs=0&iv_load_policy=3&showinfo=0&cc_load_policy=0'; } ?>
              <?php if ($embed): ?>
              <?php if ($ad_link): ?><a href="<?= $ad_link ?>" target="_blank" onclick="adClick(<?= $ads_top['id'] ?>)"><?php endif; ?>
                <div class="ad-video-wrapper" style="position:relative;width:100%;height:100%;">
                  <div class="ad-video-player" style="width:100%;height:100%;">
                    <iframe src="<?= e($embed) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen style="width:100%;height:100%;border:0"></iframe>
                  </div>
                  <button class="ad-fullscreen-btn" aria-label="Open fullscreen">⤢</button>
                </div>
              <?php if ($ad_link): ?></a><?php endif; ?>
            <?php endif; ?>
          <?php elseif (preg_match('#^https?://#i', $ad_vid_raw)): ?>
            <a href="<?= e($ad_vid_raw) ?>" target="_blank" onclick="adClick(<?= $ads_top['id'] ?>)"><div style="width:100%;height:100%;background:#000;color:#fff;display:flex;align-items:center;justify-content:center">Open Video</div></a>
          <?php else: ?>
            <a href="<?= $ad_link ?>" class="ad-banner-link" target="_blank" onclick="adClick(<?= $ads_top['id'] ?>)">
              <video src="<?= UPLOAD_URL . e($ads_top['video']) ?>" muted playsinline loop style="width:100%;height:100%;display:block;object-fit:cover;"></video>
            </a>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?= e($ads_top['link_url']) ?>" class="ad-banner-link" target="_blank" onclick="adClick(<?= $ads_top['id'] ?>)" style="display:block;width:100%;height:100%;background-image:url('<?= UPLOAD_URL . e($ads_top['image']) ?>');background-position:center;background-size:cover;background-repeat:no-repeat;">
            <span class="sr-only"><?= e($ads_top['title']) ?></span>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Removed ad-top-photos on article pages so only top banner shows -->

    <article class="article-body article-story">
      <header class="article-header">
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
          <time class="meta-item" datetime="<?= e(date(DATE_ATOM, strtotime($article['created_at']))) ?>"><i class="fa fa-calendar"></i> <?= date('d M Y, H:i', strtotime($article['created_at'])) ?></time>
          <?php if ($show_views): ?>
          <span class="meta-item"><i class="fa fa-eye"></i> <?= number_format($article['views']) ?> <?= L('views') ?></span>
          <?php endif; ?>
        </div>
        <?php if ($ai_source): ?>
        <p class="article-source-attribution">
          <?= $LANG === 'rw' ? 'Inkomoko:' : 'Source:' ?>
          <a href="<?= e($ai_source['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($ai_source['source_name']) ?></a>
        </p>
        <?php endif; ?>
      </header>

      <?php if ($ads_mid): ?>
      <?php $anim_mid = !empty($ads_mid['is_animated']) ? 'ad-anim-'.$ads_mid['animation_type'].' ad-animate-on-scroll' : ''; ?>
      <div class="ad-banner ad-mid <?= $anim_mid ?>" style="margin:20px 0;">
        <?php
          $admid_vid = trim($ads_mid['video'] ?? '');
        ?>
        <?php if ($admid_vid): ?>
          <?php if (stripos($admid_vid, 'youtube.com') !== false || stripos($admid_vid, 'youtu.be') !== false): ?>
            <?php if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $admid_vid, $m)): ?>
              <a href="<?= e($ads_mid['link_url']) ?>" target="_blank" onclick="adClick(<?= $ads_mid['id'] ?>)">
                <div class="ad-video-wrapper" style="position:relative;width:100%;min-height:150px;height:150px;">
                  <div class="ad-video-player" style="width:100%;height:100%;">
                    <iframe src="<?= e('https://www.youtube.com/embed/' . $m[1] . '?rel=0&autoplay=1&mute=1&controls=0&disablekb=1&modestbranding=1&playsinline=1&loop=1&fs=0&iv_load_policy=3&showinfo=0&cc_load_policy=0') ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen style="width:100%;height:150px;border:0"></iframe>
                  </div>
                  <button class="ad-fullscreen-btn" aria-label="Open fullscreen">⤢</button>
                </div>
              </a>
            <?php endif; ?>
          <?php elseif (preg_match('#^https?://#i', $admid_vid)): ?>
            <a href="<?= e($admid_vid) ?>" target="_blank" onclick="adClick(<?= $ads_mid['id'] ?>)"><div style="width:100%;height:150px;background:#000;color:#fff;display:flex;align-items:center;justify-content:center">Open Video</div></a>
          <?php else: ?>
            <a href="<?= e($ads_mid['link_url']) ?>" target="_blank" onclick="adClick(<?= $ads_mid['id'] ?>)">
              <video src="<?= UPLOAD_URL . e($ads_mid['video']) ?>" muted playsinline loop style="width:100%;height:150px;display:block;object-fit:cover;"></video>
            </a>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?= e($ads_mid['link_url']) ?>" target="_blank" onclick="adClick(<?= $ads_mid['id'] ?>)" style="display:block;width:100%;min-height:150px;height:150px;background-image:url('<?= UPLOAD_URL . e($ads_mid['image']) ?>');background-position:center;background-size:cover;background-repeat:no-repeat;">
            <span class="sr-only"><?= e($ads_mid['title']) ?></span>
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Thumbnail -->
      <div class="article-thumbnail">
        <img src="<?= e(newsThumbnail($article['thumbnail'])) ?>" alt="<?= e(t($article,'title',$LANG)) ?>">
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

      <?php if (!empty($articleReferences)): ?>
      <section class="article-references" aria-labelledby="articleReferencesTitle">
        <h2 id="articleReferencesTitle"><?= $LANG === 'rw' ? 'Aho twakura amakuru n’andi mahuza' : ($LANG === 'fr' ? 'Références et liens associés' : ($LANG === 'sw' ? 'Marejeo na viungo vinavyohusiana' : 'References & Related Links')) ?></h2>
        <ul>
          <?php foreach ($articleReferences as $reference): ?>
          <li>
            <a href="<?= e($reference['url']) ?>" target="_blank" rel="noopener noreferrer">
              <?= e($reference['title']) ?><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <div class="article-actions">
        <div class="share-bar">
          <span><?= L('share') ?>:</span>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>" target="_blank" rel="noopener noreferrer" class="share-btn fb" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="https://twitter.com/intent/tweet?url=<?= $share_url ?>&text=<?= $share_title ?>" target="_blank" rel="noopener noreferrer" class="share-btn tw" aria-label="Share on X"><i class="fab fa-x-twitter"></i></a>
          <a href="https://wa.me/?text=<?= $share_title ?>%20<?= $share_url ?>" target="_blank" rel="noopener noreferrer" class="share-btn wa" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
          <a href="https://t.me/share/url?url=<?= $share_url ?>&text=<?= $share_title ?>" target="_blank" rel="noopener noreferrer" class="share-btn tg" aria-label="Share on Telegram"><i class="fab fa-telegram-plane"></i></a>
        </div>
        <?php if ($yt_channel): ?>
        <div class="article-follow-us">
          <a href="watch.php?src=youtube&lang=<?= $LANG ?>" class="btn btn-youtube">
            <i class="fab fa-youtube"></i> <?= $LANG==='rw'?'Subscribe on YouTube':'Subscribe on YouTube' ?>
          </a>
        </div>
        <?php endif; ?>
      </div>

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
            <img src="<?= newsThumbnail($r['thumbnail']) ?>" alt="<?= e(t($r,'title',$LANG)) ?>" loading="lazy">
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
    $ads_sidebar = db()->query("SELECT * FROM advertisements WHERE position='sidebar' AND is_active=1 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
    foreach ($ads_sidebar as $ad):
    ?>
    <div class="widget ad-widget">
      <?php
        $link = !empty($ad['link_url']) ? e($ad['link_url']) : '';
        $ad_vid = trim($ad['video'] ?? '');
      ?>
      <?php if ($ad_vid): ?>
        <?php if ((stripos($ad_vid, 'youtube.com') !== false) || (stripos($ad_vid, 'youtu.be') !== false)): ?>
          <?php if ($link): ?><a href="<?= $link ?>" target="_blank" onclick="adClick(<?= $ad['id'] ?>)"><?php endif; ?>
            <?php if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $ad_vid, $m)): ?>
              <iframe src="<?= e('https://www.youtube.com/embed/' . $m[1] . '?rel=0&autoplay=1&mute=1&controls=0&disablekb=1&modestbranding=1&playsinline=1&loop=1&fs=0&iv_load_policy=3&showinfo=0&cc_load_policy=0') ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%;height:200px;border:0"></iframe>
            <?php else: ?>
              <a href="<?= e($ad_vid) ?>" target="_blank" class="ad-link">Open Video</a>
            <?php endif; ?>
          <?php if ($link): ?></a><?php endif; ?>
        <?php elseif (preg_match('#^https?://#i', $ad_vid)): ?>
          <a href="<?= e($ad_vid) ?>" target="_blank" onclick="adClick(<?= $ad['id'] ?>)"><div style="width:100%;height:200px;background:#000;color:#fff;display:flex;align-items:center;justify-content:center">Open Video</div></a>
        <?php else: ?>
          <a href="<?= $link ?>" target="_blank"><video src="<?= UPLOAD_URL . e($ad['video']) ?>" muted playsinline loop style="width:100%;height:auto;display:block;object-fit:cover;"></video></a>
        <?php endif; ?>
      <?php else: ?>
        <a href="<?= e($ad['link_url']) ?>" target="_blank"><img src="<?= UPLOAD_URL . e($ad['image']) ?>" alt="<?= e($ad['title']) ?>"></a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div class="widget">
      <div class="widget-title"><?= $LANG==='rw'?'Byasomwe Cyane':'Most Read' ?></div>
      <?php
      $most_read = db()->query("SELECT id, title_rw, title_en, title_sw, title_fr, thumbnail, views FROM news WHERE status='published' ORDER BY views DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
      foreach ($most_read as $i => $mr):
      ?>
      <div class="most-read-item">
        <span class="mr-num"><?= $i+1 ?></span>
        <a class="mr-thumb" href="article.php?id=<?= $mr['id'] ?>&lang=<?= $LANG ?>">
          <img src="<?= newsThumbnail($mr['thumbnail']) ?>" alt="<?= e(t($mr,'title',$LANG)) ?>" loading="lazy">
        </a>
        <div class="mr-info">
          <a href="article.php?id=<?= $mr['id'] ?>&lang=<?= $LANG ?>"><?= e(t($mr,'title',$LANG)) ?></a>
          <?php if ($show_views): ?>
          <span><i class="fa fa-eye"></i> <?= number_format($mr['views']) ?></span>
          <?php endif; ?>
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
