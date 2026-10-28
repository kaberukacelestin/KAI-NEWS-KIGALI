<?php
require_once 'includes/config.php';

$src = $_GET['src'] ?? 'youtube';
$videoUrl = '';
$pageTitle = '';
$pageLabel = '';
if ($src === 'live') {
    $videoUrl = getSetting('live_event_url');
    $pageLabel = $LANG === 'rw' ? 'Live events' : ($LANG === 'fr' ? 'Événement en direct' : ($LANG === 'sw' ? 'Matukio ya Moja kwa Moja' : 'Live Event'));
    $pageTitle = $LANG === 'rw' ? 'Reba Live kuri KAI News' : ($LANG === 'fr' ? 'Regarder en direct sur KAI News' : ($LANG === 'sw' ? 'Tazama moja kwa moja kwenye KAI News' : 'Watch Live on KAI News'));
} elseif ($src === 'video') {
    $rawUrl = trim($_GET['url'] ?? '');
    $videoUrl = filter_var($rawUrl, FILTER_VALIDATE_URL) ? $rawUrl : '';
    $pageLabel = $LANG === 'rw' ? 'Video' : ($LANG === 'fr' ? 'Vidéo' : ($LANG === 'sw' ? 'Video' : 'Video'));
    $pageTitle = $LANG === 'rw' ? 'Reba video kuri KAI News' : ($LANG === 'fr' ? 'Regarder une vidéo sur KAI News' : ($LANG === 'sw' ? 'Tazama video kwenye KAI News' : 'Watch video on KAI News'));
} else {
    $videoUrl = getSetting('youtube_url');
    $pageLabel = $LANG === 'rw' ? 'YouTube Channel' : ($LANG === 'fr' ? 'Chaîne YouTube' : ($LANG === 'sw' ? 'Kituo cha YouTube' : 'YouTube Channel'));
    $pageTitle = $LANG === 'rw' ? 'Reba kuri KAI News' : ($LANG === 'fr' ? 'Regarder sur KAI News' : ($LANG === 'sw' ? 'Tazama kwenye KAI News' : 'Watch on KAI News'));
}

function normalizeYouTubeEmbedUrl($url) {
    if (!$url) return false;
    $url = trim($url);
    if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return false;
}

$watchUrl = trim($videoUrl);
$embedUrl = null;
$embedType = 'none';
$hasHls = false;
if ($watchUrl) {
    if (stripos($watchUrl, 'youtube.com') !== false || stripos($watchUrl, 'youtu.be') !== false) {
        $embedUrl = normalizeYouTubeEmbedUrl($watchUrl);
        if ($embedUrl) {
            $embedType = 'youtube';
        } else {
            $embedType = 'external';
        }
    } elseif (stripos($watchUrl, '.m3u8') !== false || stripos($watchUrl, '.mp4') !== false) {
        $embedType = 'video';
        $hasHls = stripos($watchUrl, '.m3u8') !== false;
    } elseif (preg_match('#^https?://#i', $watchUrl)) {
        $embedType = 'external';
    }
}

$liveEventTitle = trim(getSetting('live_event_title') ?: $pageTitle);
$liveEventDescription = trim(getSetting('live_event_description'));
$liveEventTime = trim(getSetting('live_event_time'));
$liveSidebarAds = [];
if ($src === 'live') {
    $liveSidebarAds = db()->query(
        "SELECT title, image, link_url FROM advertisements"
      . " WHERE position='sidebar' AND is_active=1"
      . " AND (start_date IS NULL OR start_date <= CURDATE())"
      . " AND (end_date IS NULL OR end_date >= CURDATE())"
      . " AND image<>'' ORDER BY created_at DESC LIMIT 2"
    )->fetch_all(MYSQLI_ASSOC);
}

function embedParam($url) {
    if (!$url) return '';
    $params = [
        'rel' => 0,
        'autoplay' => 0,
        'controls' => 1,
        'modestbranding' => 1,
        'playsinline' => 1,
        'loop' => 0,
        'fs' => 1,
    ];
    if (preg_match('#/embed/([A-Za-z0-9_-]{11})#', $url, $m)) {
        $params['playlist'] = $m[1];
    }
    $separator = strpos($url, '?') !== false ? '&' : '?';
    return $url . $separator . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — KAI News Kigali</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="assets/images/logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
<?php if ($src === 'live'): ?>
.watch-live-page { min-height: 100vh; background: #f5f7fa; }
.watch-live-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 28px; align-items: start; padding: 32px 0 48px; }
.watch-live-main { min-width: 0; }
.watch-live-heading { margin-bottom: 18px; }
.watch-live-heading h1 { margin: 10px 0 6px; color: var(--navy); font-size: 1.8rem; line-height: 1.25; }
.watch-live-heading p { color: var(--muted); line-height: 1.6; }
.watch-live-label { display: inline-flex; align-items: center; gap: 8px; color: var(--red); font-size: 0.82rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.watch-live-label::before { content: ''; width: 9px; height: 9px; border-radius: 50%; background: var(--red); }
.watch-live-player { width: 100%; max-width: none; aspect-ratio: 16 / 9; margin: 0; overflow: hidden; background: #000; box-shadow: var(--card-shadow); }
.watch-live-player iframe, .watch-live-player video { display: block; width: 100%; height: 100%; border: 0; object-fit: contain; }
.watch-live-sidebar { display: grid; gap: 18px; }
.watch-live-sidebar-title { margin-bottom: 8px; color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
.watch-live-ad { overflow: hidden; background: #fff; border-radius: var(--radius); box-shadow: var(--card-shadow); }
.watch-live-ad img { width: 100%; height: auto; }
.watch-live-details { padding: 18px; background: #fff; border-radius: var(--radius); box-shadow: var(--card-shadow); }
.watch-live-details h2 { margin-bottom: 8px; color: var(--navy); font-size: 1rem; }
.watch-live-details p { color: var(--muted); font-size: 0.92rem; line-height: 1.6; }
.watch-live-details p + p { margin-top: 8px; }
.watch-live-page .watch-empty { display: grid; place-content: center; height: 100%; gap: 14px; padding: 24px; color: #fff; text-align: center; }
.watch-live-page .watch-empty .btn-live { justify-self: center; }
@media (max-width: 900px) {
  .watch-live-layout { grid-template-columns: minmax(0, 1fr) 250px; gap: 20px; }
}
@media (max-width: 700px) {
  .watch-live-layout { grid-template-columns: 1fr; padding-top: 22px; }
  .watch-live-sidebar { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
  .watch-live-heading h1 { font-size: 1.45rem; }
}
<?php else: ?>
html, body { margin: 0; height: 100%; background: #000; }
.watch-fullscreen-page { overflow: hidden; }
.watch-fullscreen-page .watch-page { height: 100vh; display: flex; align-items: center; justify-content: center; background: #000; }
.watch-fullscreen-page .watch-player { width: 100%; height: 100%; }
.watch-fullscreen-page .watch-player iframe,
.watch-fullscreen-page .watch-player video { width: 100%; height: 100%; border: 0; object-fit: cover; }
.watch-fullscreen-page .watch-empty { color: #fff; text-align: center; padding: 20px; }
<?php endif; ?>
</style>
</head>
<body class="<?= $src === 'live' ? 'watch-live-page' : 'watch-fullscreen-page' ?>">
<?php if ($src === 'live'): ?>
<?php include 'includes/header.php'; ?>
<main class="container watch-live-layout">
  <section class="watch-live-main">
    <header class="watch-live-heading">
      <span class="watch-live-label"><?= $LANG === 'rw' ? 'KURI LIVE' : ($LANG === 'fr' ? 'EN DIRECT' : ($LANG === 'sw' ? 'MOJA KWA MOJA' : 'LIVE')) ?></span>
      <h1><?= e($liveEventTitle) ?></h1>
      <?php if ($liveEventTime): ?><p><?= e($liveEventTime) ?></p><?php endif; ?>
      <?php if ($liveEventDescription): ?><p><?= e($liveEventDescription) ?></p><?php endif; ?>
    </header>
<?php else: ?>
<div class="watch-page">
<?php endif; ?>
<div class="watch-player<?= $src === 'live' ? ' watch-live-player' : '' ?>">
  <?php if ($embedType === 'none'): ?>
    <div class="watch-empty">
      <p><?= $LANG === 'rw' ? 'Nta videwo cyangwa link ya YouTube yashyizweho. Ongera ushyire URL ya YouTube muri settings.' : ($LANG === 'fr' ? 'Aucune vidéo ou lien YouTube n\'a été configuré. Ajoutez une URL YouTube dans les paramètres.' : ($LANG === 'sw' ? 'Hakuna video au kiunga cha YouTube kilichowekwa. Ongeza URL ya YouTube katika mipangilio.' : 'No video or YouTube link is configured. Add a YouTube URL in settings.')) ?></p>
    </div>
  <?php elseif ($embedType === 'youtube'): ?>
    <iframe src="<?= e(embedParam($embedUrl)) ?>" title="<?= e($pageTitle) ?>" allow="encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
  <?php elseif ($embedType === 'video'): ?>
    <video id="watchPlayer" controls preload="metadata" playsinline></video>
    <?php if ($hasHls): ?>
      <script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.0/dist/hls.min.js"></script>
    <?php endif; ?>
    <script>
      (function(){
        var url = <?= json_encode($watchUrl) ?>;
        var video = document.getElementById('watchPlayer');
        if (!video) return;
        video.loop = false;
        video.playsInline = true;
        video.setAttribute('playsinline', '');
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
          video.src = url;
        } else if (window.Hls && Hls.isSupported()) {
          var hls = new Hls();
          hls.loadSource(url);
          hls.attachMedia(video);
        } else {
          video.src = url;
        }
      })();
    </script>
  <?php else: ?>
    <div class="watch-empty">
      <p><?= $LANG === 'rw' ? 'Iyi link ntabwo ishobora gushyirwa mu murongo wa video hano. Kanda hano kugirango uyirebe hanze ya site.' : ($LANG === 'fr' ? 'Ce lien ne peut pas être lu ici. Cliquez ci-dessous pour le regarder en externe.' : ($LANG === 'sw' ? 'Kiungo hiki hakiwezi kuchezwa hapa. Bonyea hapo chini kutazama kwa nje.' : 'This link cannot be played here. Click below to watch externally.')) ?></p>
      <a href="<?= e($watchUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-live"><i class="fa fa-arrow-right"></i> <?= $LANG === 'rw' ? 'Fungura hanze' : ($LANG === 'fr' ? 'Ouvrir en externe' : ($LANG === 'sw' ? 'Fungua nje' : 'Open externally')) ?></a>
    </div>
  <?php endif; ?>
</div>
<?php if ($src === 'live'): ?>
  </section>
  <aside class="watch-live-sidebar">
    <?php if ($liveSidebarAds): ?>
    <div>
      <div class="watch-live-sidebar-title"><?= $LANG === 'rw' ? 'Kwamamaza' : ($LANG === 'fr' ? 'Publicité' : ($LANG === 'sw' ? 'Tangazo' : 'Advertisement')) ?></div>
      <?php foreach ($liveSidebarAds as $ad): ?>
      <div class="watch-live-ad">
        <?php if (!empty($ad['link_url'])): ?><a href="<?= e($ad['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?php endif; ?>
          <img src="<?= UPLOAD_URL . e($ad['image']) ?>" alt="<?= e($ad['title']) ?>">
        <?php if (!empty($ad['link_url'])): ?></a><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="watch-live-details">
      <h2><?= $LANG === 'rw' ? 'Ibyerekeye ikiganiro' : ($LANG === 'fr' ? 'À propos du direct' : ($LANG === 'sw' ? 'Kuhusu matangazo ya moja kwa moja' : 'About this live event')) ?></h2>
      <?php if ($liveEventTime): ?><p><i class="fa fa-clock"></i> <?= e($liveEventTime) ?></p><?php endif; ?>
      <p><?= e($liveEventDescription ?: ($LANG === 'rw' ? 'Kurikirana amakuru agezweho kuri KAI News Kigali.' : ($LANG === 'fr' ? 'Suivez les dernières actualités sur KAI News Kigali.' : ($LANG === 'sw' ? 'Fuatilia habari za moja kwa moja kutoka KAI News Kigali.' : 'Follow the latest live coverage from KAI News Kigali.')))) ?></p>
    </div>
  </aside>
</main>
<?php include 'includes/footer.php'; ?>
<?php else: ?>
</div>
<?php endif; ?>
</body>
</html>
