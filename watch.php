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
    if (preg_match('#youtube\.com/(?:channel|c|user)/([^/?&]+)#i', $url, $m)) {
        return 'https://www.youtube.com/embed?listType=user_uploads&list=' . urlencode($m[1]);
    }
    if (preg_match('#youtube\.com/@([^/?&]+)#i', $url, $m)) {
        return 'https://www.youtube.com/embed?listType=user_uploads&list=' . urlencode($m[1]);
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

function embedParam($url) {
    if (strpos($url, '?') !== false) {
        return $url . '&rel=0';
    }
    return $url . '?rel=0';
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
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container watch-page">
  <div class="page-heading">
    <h1><?= e($pageTitle) ?></h1>
    <p class="watch-subtitle">
      <?= $LANG === 'rw' ? 'Ubu ushobora kureba videwo za YouTube cyangwa amashusho ya live ku rubuga rwa KAI News, utavuye ku rubuga rwa YouTube.' : ($LANG === 'fr' ? 'Vous pouvez maintenant regarder les vidéos YouTube ou le direct directement sur le site KAI News, sans quitter YouTube.' : ($LANG === 'sw' ? 'Sasa unaweza kutazama video za YouTube au moja kwa moja moja kwa moja kwenye tovuti ya KAI News bila kutoka YouTube.' : 'You can now watch YouTube videos or live streams directly on the KAI News site without leaving YouTube.')) ?>
    </p>
  </div>

  <?php if ($embedType === 'none'): ?>
    <div class="watch-empty">
      <p><?= $LANG === 'rw' ? 'Nta videwo cyangwa link ya YouTube yashyizweho. Ongera ushyire URL ya YouTube muri settings.' : ($LANG === 'fr' ? 'Aucune vidéo ou lien YouTube n\'a été configuré. Ajoutez une URL YouTube dans les paramètres.' : ($LANG === 'sw' ? 'Hakuna video au kiunga cha YouTube kilichowekwa. Ongeza URL ya YouTube katika mipangilio.' : 'No video or YouTube link is configured. Add a YouTube URL in settings.')) ?></p>
    </div>
  <?php elseif ($embedType === 'youtube'): ?>
    <div class="watch-player">
      <iframe src="<?= e(embedParam($embedUrl)) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
  <?php elseif ($embedType === 'video'): ?>
    <div class="watch-player">
      <video id="watchPlayer" controls playsinline></video>
    </div>
    <?php if ($hasHls): ?>
      <script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.0/dist/hls.min.js"></script>
    <?php endif; ?>
    <script>
      (function(){
        var url = <?= json_encode($watchUrl) ?>;
        var video = document.getElementById('watchPlayer');
        if (!video) return;
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
      <a href="<?= e($watchUrl) ?>" target="_blank" class="btn-live"><i class="fa fa-arrow-right"></i> <?= $LANG === 'rw' ? 'Fungura hanze' : ($LANG === 'fr' ? 'Ouvrir en externe' : ($LANG === 'sw' ? 'Fungua nje' : 'Open externally')) ?></a>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
