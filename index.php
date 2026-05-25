<?php
require_once 'includes/config.php';

// Get headlines for slider
$headlines = db()->query(
    "SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug,"
  . " a.full_name as author_name, a.profile_picture as author_photo"
  . " FROM news n"
  . " JOIN categories c ON n.category_id=c.id"
  . " JOIN admins a ON n.admin_id=a.id"
  . " WHERE n.status='published' AND " . languageContentFilter() . " AND n.is_headline=1"
  . " ORDER BY n.created_at DESC LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

// Get latest news
$latest = db()->query(
    "SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug"
  . " FROM news n JOIN categories c ON n.category_id=c.id"
  . " WHERE n.status='published' AND " . languageContentFilter()
  . " ORDER BY n.created_at DESC LIMIT 12"
)->fetch_all(MYSQLI_ASSOC);

// Get featured
$featured = db()->query(
    "SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug"
  . " FROM news n JOIN categories c ON n.category_id=c.id"
  . " WHERE n.status='published' AND " . languageContentFilter() . " AND n.is_featured=1"
  . " ORDER BY n.created_at DESC LIMIT 4"
)->fetch_all(MYSQLI_ASSOC);

// Get ads
$ads_top = db()->query("SELECT * FROM advertisements WHERE position='banner_top' AND is_active=1 ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$ads_mid = db()->query("SELECT * FROM advertisements WHERE position='banner_mid' AND is_active=1 ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$ads_sidebar = db()->query("SELECT * FROM advertisements WHERE position='sidebar' AND is_active=1 ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

// Ad video, live event and radio settings
$ad_video_url = getSetting('ad_video_url');
$ad_video_file = getSetting('ad_video_file');
$live_event_url = getSetting('live_event_url');
$live_event_time = getSetting('live_event_time');
$live_event_title = getSetting('live_event_title');
$live_event_button_text = getSetting('live_event_button_text');
$live_event_description = getSetting('live_event_description');
$ad_video_title = getSetting('ad_video_title');
$ad_video_button_text = getSetting('ad_video_button_text');
$ad_video_description = getSetting('ad_video_description');
$online_radio_url = getSetting('online_radio_url');
$live_event_thumbnail_file = getSetting('live_event_thumbnail');
$live_event_thumbnail = $live_event_thumbnail_file ? UPLOAD_URL . ltrim($live_event_thumbnail_file, '/') : getYouTubeThumbnailUrl($live_event_url);

function normalizeYouTubeEmbedUrl($url) {
    if (!$url) return false;
    $url = trim($url);
    if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#youtube\.com/(?:channel|c|user)/([^/?&]+)#i', $url, $m)) {
        return 'https://www.youtube.com/embed?listType=user_uploads&list=' . urlencode($m[1]);
    }
    if (preg_match('#youtube\.com/@([^/?&]+)#i', $url, $m)) {
        return 'https://www.youtube.com/embed?listType=user_uploads&list=' . urlencode($m[1]);
    }
    return false;
}

function buildEmbedParams($url) {
    if (!$url) return '';
    $separator = strpos($url, '?') === false ? '?' : '&';
    return $url . $separator . 'rel=0&autoplay=1&mute=1&controls=1';
}

function getAudioMimeType($url) {
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'mp3':
            return 'audio/mpeg';
        case 'ogg':
        case 'oga':
            return 'audio/ogg';
        case 'wav':
            return 'audio/wav';
        case 'aac':
            return 'audio/aac';
        case 'm4a':
            return 'audio/mp4';
        case 'opus':
            return 'audio/opus';
        default:
            return null;
    }
}

$online_radio_mime = getAudioMimeType($online_radio_url);

$ad_video_source = '';
$ad_video_type = 'none';
if (!empty($ad_video_file)) {
    $ad_video_source = UPLOAD_URL . ltrim($ad_video_file, '/');
    $ad_video_type = 'file';
} elseif (!empty($ad_video_url)) {
    if (stripos($ad_video_url, 'youtube.com') !== false || stripos($ad_video_url, 'youtu.be') !== false) {
        $embed = normalizeYouTubeEmbedUrl($ad_video_url);
        if ($embed) {
            $ad_video_type = 'youtube';
            $ad_video_source = buildEmbedParams($embed);
        } else {
            $ad_video_type = 'external';
            $ad_video_source = $ad_video_url;
        }
    } elseif (preg_match('#\.(mp4|webm|ogg|mov)(\?|$)#i', $ad_video_url)) {
        $ad_video_type = 'file';
        $ad_video_source = $ad_video_url;
    } else {
        $ad_video_type = 'external';
        $ad_video_source = $ad_video_url;
    }
}

$categories = getCategories();

// News by category
$cat_news = [];
foreach ($categories as $cat) {
    $sid = $cat['id'];
    $cat_news[$sid] = db()->query(
        "SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug"
      . " FROM news n JOIN categories c ON n.category_id=c.id"
      . " WHERE n.status='published' AND " . languageContentFilter() . " AND n.category_id={$sid}"
      . " ORDER BY n.created_at DESC LIMIT 4"
    )->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KAI News Kigali — <?= L('home') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

<!-- ========== TOP AD BANNER ========== -->
<!-- ========== TOP AD BANNER (Split 70/30) ========== -->
<?php $anim_top = !empty($ads_top['is_animated']) ? 'ad-anim-'.$ads_top['animation_type'].' ad-animate-on-scroll' : ''; ?>
<?php if ($ads_top || $ad_video_type !== 'none'): ?>
<div class="ad-banner ad-top ad-top-split <?= $anim_top ?>">
  <div class="ad-top-inner" style="min-height:150px;height:150px;display:flex;gap:0;align-items:stretch;">
    <div class="ad-left" style="flex:0 0 70%;background-position:center;background-size:cover;background-repeat:no-repeat;<?php if ($ads_top && !empty($ads_top['image'])): ?>background-image:url('<?= UPLOAD_URL . e($ads_top['image']) ?>');<?php endif; ?>">
      <?php if (!empty($ads_top)): ?>
      <a href="<?= e($ads_top['link_url']) ?>" class="ad-banner-link" target="_blank" onclick="adClick(<?= $ads_top['id'] ?>)" style="display:block;width:100%;height:100%;">
        <span class="sr-only"><?= e($ads_top['title']) ?></span>
      </a>
      <?php endif; ?>
    </div>
    <div class="ad-right" style="flex:0 0 30%;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#000;">
      <?php if (!empty($ads_top['video'])): ?>
        <?php
          $video_rel = ltrim($ads_top['video'], '/');
          $video_fs = UPLOAD_PATH . $video_rel;
          $vid = UPLOAD_URL . e($ads_top['video']);
          $link = !empty($ads_top['link_url']) ? e($ads_top['link_url']) : '';
        ?>
        <!-- debug: video file exists on server? <?= file_exists($video_fs) ? 'yes' : 'no' ?>; server-path: <?= $video_fs ?>; public-url: <?= $vid ?> -->
        <?php if ($link): ?>
        <a href="<?= $link ?>" target="_blank" onclick="adClick(<?= $ads_top['id'] ?>)" style="display:block;width:100%;height:100%;">
          <video src="<?= $vid ?>" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;"></video>
        </a>
        <?php else: ?>
        <video src="<?= $vid ?>" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;"></video>
        <?php endif; ?>
      <?php elseif ($ad_video_type === 'youtube' && $ad_video_source): ?>

        <div class="ad-video-player" style="width:100%;height:100%;border-radius:0;">
          <iframe src="<?= e($ad_video_source) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
      <?php elseif ($ad_video_type === 'file' && $ad_video_source): ?>
        <video src="<?= e($ad_video_source) ?>" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;"></video>
      <?php else: ?>
        <div class="ad-placeholder" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#fff;background:#111;">Advertise Here</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ========== BREAKING TICKER ========== -->
<div class="breaking-bar">
  <span class="breaking-label"><?= L('breaking') ?></span>
  <div class="ticker-wrap">
    <div class="ticker" id="ticker">
      <?php foreach (array_slice($latest, 0, 8) as $item): ?>
        <span class="ticker-item">
          <a href="article.php?id=<?= $item['id'] ?>&lang=<?= $LANG ?>">
            <?= e(t($item, 'title', $LANG)) ?>
          </a>
        </span>
        <span class="ticker-sep">◆</span>
      <?php endforeach; ?>
    </div>
  </div>
</div>


<!-- ========== HERO SLIDER ========== -->
<?php if (!empty($headlines)): ?>
<section class="hero-slider" id="heroSlider">
  <div class="slides-container" id="slidesContainer">
    <?php foreach ($headlines as $i => $h): ?>
    <div class="slide <?= $i===0?'active':'' ?>" data-index="<?= $i ?>">
      <div class="slide-img" style="background-image:url('<?= UPLOAD_URL . e($h['thumbnail']) ?>')"></div>
      <div class="slide-overlay"></div>
      <div class="slide-content">
        <span class="slide-cat"><?= e($h['cat_name']) ?></span>
        <div class="slide-author">
          <img src="<?= profileThumbnail($h['author_photo']) ?>" alt="<?= e($h['author_name']) ?>">
          <span><?= L('by') ?> <?= e($h['author_name']) ?></span>
        </div>
        <h2 class="slide-title">
          <a href="article.php?id=<?= $h['id'] ?>&lang=<?= $LANG ?>"><?= e(t($h,'title',$LANG)) ?></a>
        </h2>
        <p class="slide-desc"><?= e(mb_substr(strip_tags(t($h,'description',$LANG)),0,150)) ?>...</p>
        <a href="article.php?id=<?= $h['id'] ?>&lang=<?= $LANG ?>" class="slide-btn"><?= L('read_more') ?></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button class="slider-btn prev" id="prevBtn"><i class="fa fa-chevron-left"></i></button>
  <button class="slider-btn next" id="nextBtn"><i class="fa fa-chevron-right"></i></button>
  <div class="slider-dots" id="sliderDots">
    <?php foreach ($headlines as $i => $h): ?>
      <button class="dot <?= $i===0?'active':'' ?>" data-i="<?= $i ?>"></button>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ========== MAIN CONTENT ========== -->
<div class="container main-layout">
  <div class="content-area">

    <!-- Latest News Grid -->
    <section class="section">
      <div class="section-header">
        <h2><?= L('latest') ?></h2>
        <div class="section-line"></div>
      </div>
      <div class="news-grid">
        <?php foreach ($latest as $n): ?>
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
              <span><i class="fa fa-eye"></i> <?= number_format($n['views']) ?></span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Mid Ad Banner -->
    <?php if ($ads_mid): ?>
    <?php $anim_mid = !empty($ads_mid['is_animated']) ? 'ad-anim-'.$ads_mid['animation_type'].' ad-animate-on-scroll' : ''; ?>
    <div class="ad-banner ad-mid <?= $anim_mid ?>">
      <a href="<?= e($ads_mid['link_url']) ?>" class="ad-banner-link" target="_blank"
         style="display:block;width:100%;min-height:150px;height:150px;background-image:url('<?= UPLOAD_URL . e($ads_mid['image']) ?>');background-position:center;background-size:cover;background-repeat:no-repeat;">
        <span class="sr-only"><?= e($ads_mid['title']) ?></span>
      </a>
    </div>
    <?php endif; ?>

    <!-- Category Sections -->
    <?php foreach ($categories as $cat): ?>
      <?php if (!empty($cat_news[$cat['id']])): ?>
      <section class="section cat-section">
        <div class="section-header">
          <h2><?= e($cat['name_'.$LANG]) ?></h2>
          <div class="section-line"></div>
          <a href="category.php?slug=<?= urlencode($cat['slug']) ?>&lang=<?= $LANG ?>" class="see-all">
            <?= L('read_more') ?> <i class="fa fa-arrow-right"></i>
          </a>
        </div>
        <div class="news-grid cat-grid-4">
          <?php foreach ($cat_news[$cat['id']] as $n): ?>
          <article class="news-card mini-card">
            <a href="article.php?id=<?= $n['id'] ?>&lang=<?= $LANG ?>" class="card-img-wrap">
              <img src="<?= newsThumbnail($n['thumbnail']) ?>" alt="<?= e(t($n,'title',$LANG)) ?>" loading="lazy">
            </a>
            <div class="card-body">
              <h3 class="card-title">
                <a href="article.php?id=<?= $n['id'] ?>&lang=<?= $LANG ?>"><?= e(t($n,'title',$LANG)) ?></a>
              </h3>
              <div class="card-meta">
                <span><i class="fa fa-clock"></i> <?= date('d M Y', strtotime($n['created_at'])) ?></span>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!empty($live_event_url) || $ad_video_type !== 'none' || !empty($online_radio_url)): ?>
    <section class="section video-live-section">
      <div class="section-header">
        <h2><?= $LANG==='rw' ? 'Ibirori na Video' : ($LANG==='fr' ? 'Événement en direct et vidéo' : ($LANG==='sw' ? 'Matukio ya Moja kwa Moja na Video' : 'Live Event & Video')) ?></h2>
        <div class="section-line"></div>
      </div>
      <div class="video-live-grid">
        <?php if (!empty($live_event_url)): ?>
        <div class="live-event-card">
          <?php if (!empty($live_event_thumbnail)): ?>
          <div class="live-event-thumb">
            <img src="<?= e($live_event_thumbnail) ?>" alt="<?= e($live_event_title ?: 'Live event thumbnail') ?>" loading="lazy">
          </div>
          <?php endif; ?>
          <div class="video-event-info">
            <span class="sponsored-label"><?= $LANG==='rw' ? 'LIVE EVENTS' : ($LANG==='fr' ? 'Événement' : ($LANG==='sw' ? 'Matukio' : 'Live Event')) ?></span>
            <h3><?= e($live_event_title ?: ($LANG==='rw' ? 'Kurikira Live' : ($LANG==='fr' ? 'Regardez l\'événement en direct' : ($LANG==='sw' ? 'Tazama tukio la moja kwa moja' : 'Watch the live event')))) ?></h3>
            <?php if (!empty($live_event_description)): ?>
            <p><?= e($live_event_description) ?></p>
            <?php endif; ?>
            <?php if (!empty($live_event_time)): ?>
            <span class="live-time"><i class="fa fa-clock"></i> <?= e($live_event_time) ?></span>
            <?php endif; ?>
          </div>
          <a href="watch.php?src=live&lang=<?= $LANG ?>" class="btn btn-live btn-lg">
            <i class="fa fa-broadcast-tower"></i> <?= e($live_event_button_text ?: ($LANG==='rw' ? 'Reba Ibirori Live' : ($LANG==='fr' ? 'Regarder le live' : ($LANG==='sw' ? 'Tazama live' : 'Watch Live')))) ?>
          </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($online_radio_url)): ?>
        <div class="radio-card">
          <div class="radio-card-header">
            <span class="sponsored-badge"><?= $LANG==='rw' ? 'RADIO LIVE' : ($LANG==='fr' ? 'Radio en direct' : ($LANG==='sw' ? 'Redio Moja kwa Moja' : 'Online Radio')) ?></span>
            <h3><?= $LANG==='rw' ? 'KAI NEWS RADIO' : ($LANG==='fr' ? 'Écoutez notre radio' : ($LANG==='sw' ? 'Sikiliza redio yetu' : 'Listen to our radio')) ?></h3>
            <p><?= $LANG==='rw' ? 'kurikira Radio yacu hano.' : ($LANG==='fr' ? 'Écoutez le flux radio en direct sans quitter le site.' : ($LANG==='sw' ? 'Sikiliza redio mkondoni bila kutoka tovuti.' : 'Listen to the live radio stream without leaving the site.')) ?></p>
          </div>
          <div class="radio-player">
            <audio controls preload="none">
              <source src="<?= e($online_radio_url) ?>"<?= $online_radio_mime ? ' type="'.e($online_radio_mime).'"' : '' ?>>
              <?= $LANG==='rw' ? 'Radio live ntabwo ishobora gucurangwa.' : ($LANG==='fr' ? 'Le flux radio ne peut pas être lu.' : ($LANG==='sw' ? 'Mtiririko wa redio hauwezi kuchezwa.' : 'The radio stream cannot be played.')) ?>
            </audio>
            <?php if (!$online_radio_mime): ?>
            <div class="radio-player-fallback">
              <p><?= $LANG==='rw' ? 'Niba atari gukina, soma uriya murongo wa radio uturutse hanze.' : ($LANG==='fr' ? 'Si cela ne fonctionne pas, ouvrez le flux radio dans un nouvel onglet.' : ($LANG==='sw' ? 'Ikiwa haiwezi kucheza, fungua mtiririko wa redio kwenye kichupo kipya.' : 'If playback does not work, open the radio stream in a new tab.')) ?></p>
              <a href="<?= e($online_radio_url) ?>" target="_blank" class="btn btn-live"><?= $LANG==='rw' ? 'Fungura Radio' : ($LANG==='fr' ? 'Ouvrir la radio' : ($LANG==='sw' ? 'Fungua redio' : 'Open Radio')) ?></a>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($ad_video_type !== 'none'): ?>
        <div class="ad-video-card">
          <div class="ad-video-card-header">
            <span class="sponsored-badge"><?= $LANG==='rw' ? 'AMAMAZA HANO' : ($LANG==='fr' ? 'Sponsorisé' : ($LANG==='sw' ? 'Imefadhiliwa' : 'Sponsored')) ?></span>
            <h3><?= e($ad_video_title ?: (L('advertise') . ' Video')) ?></h3>
            <?php if (!empty($ad_video_description)): ?>
            <p><?= e($ad_video_description) ?></p>
            <?php endif; ?>
          </div>
          <?php if ($ad_video_type === 'youtube'): ?>
          <div class="ad-video-player">
            <iframe src="<?= e($ad_video_source) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
          </div>
          <?php elseif ($ad_video_type === 'file'): ?>
          <div class="ad-video-player">
            <video autoplay muted loop playsinline controls>
              <source src="<?= e($ad_video_source) ?>" type="video/mp4">
              <?= L('advertise') ?> video not supported.
            </video>
          </div>
          <?php else: ?>
          <div class="ad-video-player ad-placeholder">
            <p><?= $LANG==='rw' ? 'Video ya kwamamaza yashyizweho ariko ntishobora gusubizwaho hano.' : ($LANG==='fr' ? 'La vidéo publicitaire est configurée mais ne peut pas être lue ici.' : ($LANG==='sw' ? 'Video ya matangazo imewekwa lakini haiwezi kuchezwa hapa.' : 'Advertisement video is configured but cannot be played here.')) ?></p>
            <a href="<?= e($ad_video_source) ?>" target="_blank" class="btn btn-live"><?= e($ad_video_button_text ?: ($LANG==='rw' ? 'Reba Videwo' : ($LANG==='fr' ? 'Voir la vidéo' : ($LANG==='sw' ? 'Tazama video' : 'Watch Video')))) ?></a>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

  </div><!-- /content-area -->

  <!-- SIDEBAR -->
  <aside class="sidebar">

    <!-- Search -->
    <div class="widget search-widget">
      <form action="search.php" method="GET">
        <input type="hidden" name="lang" value="<?= $LANG ?>">
        <div class="search-box">
          <input type="text" name="q" placeholder="<?= L('search') ?>...">
          <button type="submit"><i class="fa fa-search"></i></button>
        </div>
      </form>
    </div>

    <!-- Sidebar Ads -->
    <?php foreach ($ads_sidebar as $ad): ?>
    <div class="widget ad-widget">
      <a href="<?= e($ad['link_url']) ?>" target="_blank">
        <img src="<?= UPLOAD_URL . e($ad['image']) ?>" alt="<?= e($ad['title']) ?>">
      </a>
    </div>
    <?php endforeach; ?>

    <!-- Most Read -->
    <div class="widget">
      <div class="widget-title"><?= $LANG==='rw'?'Byasomwe Cyane':($LANG==='en'?'Most Read':($LANG==='sw'?'Zilizosomwa Zaidi':'Les plus lues')) ?></div>
      <?php
      $most_read = db()->query(
          "SELECT n.id, n.title_rw, n.title_en, n.title_sw, n.title_fr, n.thumbnail, n.views, n.created_at"
        . " FROM news n"
        . " WHERE n.status='published' AND " . languageContentFilter()
        . " ORDER BY n.views DESC LIMIT 5"
      )->fetch_all(MYSQLI_ASSOC);
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

    <!-- Categories Widget -->
    <div class="widget">
      <div class="widget-title"><?= $LANG==='rw'?'Ushobora kunyura hano':($LANG==='en'?'Categories':'Catégories') ?></div>
      <?php foreach ($categories as $cat): ?>
      <a href="category.php?slug=<?= urlencode($cat['slug']) ?>&lang=<?= $LANG ?>" class="cat-link">
        <i class="fa fa-chevron-right"></i> <?= e($cat['name_'.$LANG]) ?>
      </a>
      <?php endforeach; ?>
    </div>

  </aside><!-- /sidebar -->
</div><!-- /main-layout -->

<?php include 'includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
