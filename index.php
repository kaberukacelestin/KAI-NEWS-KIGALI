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

$leadHeadlineId = !empty($headlines) ? (int)$headlines[0]['id'] : 0;
$headlineSideStories = [];
foreach ($latest as $story) {
    if ((int)$story['id'] !== $leadHeadlineId) {
        $headlineSideStories[] = $story;
        if (count($headlineSideStories) === 4) {
            break;
        }
    }
}
if (count($headlineSideStories) < 4) {
    foreach (array_slice($headlines, 1) as $story) {
        $alreadyShown = false;
        foreach ($headlineSideStories as $sideStory) {
            if ((int)$sideStory['id'] === (int)$story['id']) {
                $alreadyShown = true;
                break;
            }
        }
        if (!$alreadyShown) {
            $headlineSideStories[] = $story;
        }
        if (count($headlineSideStories) === 4) {
            break;
        }
    }
}
$leftHeadlineStories = array_slice($headlineSideStories, 0, 2);
$rightHeadlineStories = array_slice($headlineSideStories, 2, 2);

// Get featured
$featured = db()->query(
    "SELECT n.*, c.name_{$LANG} as cat_name, c.slug as cat_slug"
  . " FROM news n JOIN categories c ON n.category_id=c.id"
  . " WHERE n.status='published' AND " . languageContentFilter() . " AND n.is_featured=1"
  . " ORDER BY n.created_at DESC LIMIT 4"
)->fetch_all(MYSQLI_ASSOC);

function getActiveAdCondition(): string {
    return "is_active=1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())";
}

// Get ads
// Prefer to fetch separate top image and top video ads so a new video ad doesn't replace the banner image
$ads_top_image = db()->query("SELECT * FROM advertisements WHERE position='banner_top' AND " . getActiveAdCondition() . " AND image<>'' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$ads_top_video = db()->query("SELECT * FROM advertisements WHERE position='banner_top' AND " . getActiveAdCondition() . " AND video<>'' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$topPhotoPositions = ['banner_top_photo_1','banner_top_photo_2'];
$ads_top_photos = db()->query("SELECT * FROM advertisements WHERE position IN ('" . implode("','", $topPhotoPositions) . "') AND " . getActiveAdCondition() . " AND image<>'' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$ads_top_photo_slots = array_column($ads_top_photos, null, 'position');
$ads_mid = db()->query("SELECT * FROM advertisements WHERE position='banner_mid' AND " . getActiveAdCondition() . " ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$ads_sidebar = db()->query("SELECT * FROM advertisements WHERE position='sidebar' AND " . getActiveAdCondition() . " ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

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
$homepage_video_links = getSetting('homepage_video_links');
$live_event_thumbnail_file = getSetting('live_event_thumbnail');
$live_event_thumbnail = $live_event_thumbnail_file ? UPLOAD_URL . ltrim($live_event_thumbnail_file, '/') : getYouTubeThumbnailUrl($live_event_url);

function normalizeYouTubeEmbedUrl($url) {
    if (!$url) return false;
    $url = trim($url);
    if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return false;
}

function buildInteractiveEmbedUrl($url) {
    if (!$url) return '';
    $separator = strpos($url, '?') === false ? '?' : '&';
    return $url . $separator . 'rel=0&autoplay=0&controls=1&playsinline=1';
}

function resolveAdVideoUrl($video, &$exists = null) {
    $exists = false;
    if (!$video) {
        return '';
    }
    $video = trim($video);
    if (preg_match('#^https?://#i', $video)) {
        $exists = true;
        return $video;
    }
    $normalized = ltrim($video, '/');
    if (strpos($normalized, 'uploads/') === 0) {
        $normalized = substr($normalized, strlen('uploads/'));
    }
    $filename = basename($normalized);
    $candidates = [
        $normalized,
        'ads/videos/' . $filename,
        'ads/' . $filename,
    ];

    foreach ($candidates as $candidate) {
        if (file_exists(UPLOAD_PATH . $candidate)) {
            $exists = true;
            return UPLOAD_URL . $candidate;
        }
    }

    // Fallback to the most likely local ad path even if the file is missing.
    return UPLOAD_URL . 'ads/videos/' . $filename;
}

function parseHomepageVideoLinks($raw) {
    $list = [];
    foreach (preg_split('/[\r\n]+/', trim($raw)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = explode('|', $line, 2);
        if (count($parts) === 2) {
            $title = trim($parts[0]);
            $url = trim($parts[1]);
        } else {
            $title = '';
            $url = trim($parts[0]);
        }
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) continue;
        $urlParts = parse_url($url);
        if (!is_array($urlParts)) continue;
        $scheme = strtolower($urlParts['scheme'] ?? '');
        $host = strtolower($urlParts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !$host) continue;

        $youtubeHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be', 'www.youtu.be'];
        $youtubeId = in_array($host, $youtubeHosts, true) ? getYouTubeVideoId($url) : false;
        $extension = strtolower(pathinfo($urlParts['path'] ?? '', PATHINFO_EXTENSION));
        $embedUrl = '';
        $embedType = '';
        $path = $urlParts['path'] ?? '';
        if (!$youtubeId && in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('#^(?:/video)?/([0-9]+)/?$#', $path, $match)) {
            $embedType = 'vimeo';
            $embedUrl = 'https://player.vimeo.com/video/' . $match[1] . '?autoplay=1&muted=1';
        } elseif (!$youtubeId && in_array($host, ['dailymotion.com', 'www.dailymotion.com', 'dai.ly'], true)) {
            if (preg_match('#^/(?:embed/)?(?:video/)?([A-Za-z0-9]+)(?:_[^/]*)?/?$#', $path, $match)) {
                $embedType = 'dailymotion';
                $embedUrl = 'https://www.dailymotion.com/embed/video/' . $match[1] . '?autoplay=1&mute=1';
            }
        } elseif (!$youtubeId && in_array($host, ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com'], true)
            && preg_match('#^/@[^/]+/video/([0-9]+)/?$#', $path, $match)) {
            $embedType = 'tiktok';
            $embedUrl = 'https://www.tiktok.com/player/v1/' . $match[1] . '?autoplay=1';
        } elseif (!$youtubeId && in_array($host, ['instagram.com', 'www.instagram.com'], true)
            && preg_match('#^/(p|reel|tv)/([A-Za-z0-9_-]+)/?$#', $path, $match)) {
            $embedType = 'instagram';
            $embedUrl = 'https://www.instagram.com/' . $match[1] . '/' . $match[2] . '/embed/';
        } elseif (!$youtubeId && (
            in_array($host, ['facebook.com', 'www.facebook.com', 'm.facebook.com'], true)
                && (preg_match('#/(?:videos|reel)/([0-9]+)#', $path) || !empty($urlParts['query']) && preg_match('/(?:^|&)v=([0-9]+)/', $urlParts['query']))
            || $host === 'fb.watch'
        )) {
            $embedType = 'facebook';
            $embedUrl = 'https://www.facebook.com/plugins/video.php?' . http_build_query([
                'href' => $url,
                'show_text' => 'false',
                'autoplay' => 'true',
            ]);
        }
        $type = $youtubeId
            ? 'youtube'
            : ($embedUrl !== ''
                ? 'embed'
                : (in_array($extension, ['mp4', 'webm', 'ogg', 'mov'], true) ? 'file' : 'embed'));
        if ($type === 'embed' && $embedUrl === '') {
            $embedUrl = $url;
            $embedType = 'external';
        }
        $autoTitle = $title === '';
        $list[] = [
            'title' => $title ?: (($type === 'youtube' ? 'YouTube video ' : ($type === 'embed' ? ucfirst($embedType) . ' video ' : 'Video ')) . (count($list) + 1)),
            'auto_title' => $autoTitle,
            'url' => $url,
            'type' => $type,
            'youtube_id' => $youtubeId ?: '',
            'embed_sandbox' => $embedType === 'external',
            'embed_url' => $youtubeId
                ? 'https://www.youtube-nocookie.com/embed/' . $youtubeId . '?rel=0&controls=0&disablekb=1&fs=0&autoplay=1&mute=1&playsinline=1&enablejsapi=1'
                : $embedUrl,
            'mime_type' => $type === 'file' ? [
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'ogg' => 'video/ogg',
                'mov' => 'video/quicktime',
            ][$extension] : '',
            'thumbnail' => $youtubeId ? getYouTubeThumbnailUrl($url) : ''
        ];
    }
    return $list;
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
$homepage_video_items = parseHomepageVideoLinks($homepage_video_links);
$homepage_video_active_index = null;
foreach ($homepage_video_items as $index => $item) {
    if (in_array($item['type'], ['youtube', 'file', 'embed'], true)) {
        $homepage_video_active_index = $index;
        break;
    }
}
if (!empty($ad_video_file)) {
    $resolvedFile = resolveAdVideoUrl($ad_video_file, $videoExists);
    if ($videoExists) {
        $ad_video_source = $resolvedFile;
        $ad_video_type = 'file';
    } elseif (!empty($ad_video_url)) {
        if (stripos($ad_video_url, 'youtube.com') !== false || stripos($ad_video_url, 'youtu.be') !== false) {
            $embed = normalizeYouTubeEmbedUrl($ad_video_url);
            if ($embed) {
                $ad_video_type = 'youtube';
                $ad_video_source = buildInteractiveEmbedUrl($embed);
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
} elseif (!empty($ad_video_url)) {
    if (stripos($ad_video_url, 'youtube.com') !== false || stripos($ad_video_url, 'youtu.be') !== false) {
        $embed = normalizeYouTubeEmbedUrl($ad_video_url);
        if ($embed) {
            $ad_video_type = 'youtube';
            $ad_video_source = buildInteractiveEmbedUrl($embed);
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
<body class="home">

<?php include 'includes/header.php'; ?>

<!-- ========== TOP AD BANNER ========== -->
<!-- ========== TOP AD BANNER (Split 70/30) ========== -->
<?php
  // Use image ad for left panel and video ad for right panel; fall back gracefully
  $imgAd = $ads_top_image ?: null;
  $vidAd = $ads_top_video ?: null;
  $anim_top = '';
  $anim_attrs = '';
  if ($imgAd && !empty($imgAd['is_animated'])) {
    $anim_top = 'ad-anim-'.$imgAd['animation_type'].' ad-animate-on-scroll';
    $interval = isset($imgAd['animation_interval']) ? intval($imgAd['animation_interval']) : 12;
    $anim_attrs = ' data-noncontinuous="1" data-anim-interval="'. $interval .'"';
  } elseif ($vidAd && !empty($vidAd['is_animated'])) {
    $anim_top = 'ad-anim-'.$vidAd['animation_type'].' ad-animate-on-scroll';
    $interval = isset($vidAd['animation_interval']) ? intval($vidAd['animation_interval']) : 12;
    $anim_attrs = ' data-noncontinuous="1" data-anim-interval="'. $interval .'"';
  }
  // Show top banner to admins if any site-level video is configured, but
  // for public visitors require a real active banner image/video ad.
  if (isset($_SESSION['admin_id'])) {
    $showTop = $imgAd || $vidAd || $ad_video_type !== 'none';
  } else {
    $showTop = $imgAd || $vidAd;
  }
?>
<?php if ($showTop): ?>
<div class="ad-banner ad-top ad-top-split <?= $anim_top ?>"<?= $anim_attrs ?>>
  <div class="ad-top-inner" style="min-height:150px;height:150px;display:flex;gap:0;align-items:stretch;">
    <div class="ad-left" style="flex:1 1 100%;background-position:center;background-size:cover;background-repeat:no-repeat;<?php if ($imgAd && !empty($imgAd['image'])): ?>background-image:url('<?= UPLOAD_URL . e($imgAd['image']) ?>');<?php elseif ($vidAd && !empty($vidAd['image'])): ?>background-image:url('<?= UPLOAD_URL . e($vidAd['image']) ?>');<?php endif; ?>">
      <?php if ($imgAd): ?>
      <a href="<?= e($imgAd['link_url']) ?>" class="ad-banner-link" target="_blank" onclick="adClick(<?= $imgAd['id'] ?>)" style="display:block;width:100%;height:100%;">
        <span class="sr-only"><?= e($imgAd['title']) ?></span>
      </a>
      <?php endif; ?>
    </div>
    <!-- Right ad panel removed for homepage to show single full-width banner -->
  </div>
</div>
<?php endif; ?>

<?php if (!empty($ads_top_photo_slots) || isset($_SESSION['admin_id'])): ?>
<div class="ad-banner ad-top ad-top-photos">
  <div class="ad-top-photos-inner">
    <?php foreach ($topPhotoPositions as $position): ?>
      <?php $photoAd = $ads_top_photo_slots[$position] ?? null; ?>
      <?php if (!$photoAd && !isset($_SESSION['admin_id'])) continue; ?>
      <div class="ad-photo-panel">
        <?php if ($photoAd): ?>
          <?php
            $p_attrs = '';
            $p_classes = 'ad-banner-link';
            if (!empty($photoAd['is_animated'])) {
              $p_classes .= ' ad-animate-on-scroll ad-anim-'.($photoAd['animation_type']?:'fade');
              $p_interval = intval($photoAd['animation_interval'] ?? 12);
              $p_attrs = ' data-noncontinuous="1" data-anim-interval="'.$p_interval.'"';
            }
          ?>
          <a href="<?= e($photoAd['link_url']) ?>" class="<?= $p_classes ?>"<?= $p_attrs ?> target="_blank" onclick="adClick(<?= $photoAd['id'] ?>)" style="background-image:url('<?= UPLOAD_URL . e($photoAd['image']) ?>');"></a>
        <?php else: ?>
          <a href="admin/ads.php?position=<?= urlencode($position) ?>&lang=<?= $LANG ?>" class="ad-placeholder ad-placeholder-link" style="display:flex;align-items:center;justify-content:center;color:#fff;background:#111;text-decoration:none;">Add Ad</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<section class="section kai-tv-section" aria-labelledby="kaiTvTitle">
  <div class="section-header">
    <h2 id="kaiTvTitle"><span class="kai-tv-live-badge" aria-label="Live">🔴 LIVE</span> KAI NEWS KIGALI TV</h2>
    <div class="section-line"></div>
  </div>
  <div class="kai-tv-grid">
    <div class="kai-tv-featured">
      <?php if ($homepage_video_active_index !== null): ?>
      <div class="kai-tv-player" id="kaiTvPlayer" aria-live="polite">
        <div class="kai-tv-media-host" id="kaiTvMediaHost"><div id="kaiTvMedia"></div></div>
        <button type="button" class="kai-tv-mute" id="kaiTvMute" aria-label="Unmute video" aria-pressed="false">
          <i class="fa fa-volume-xmark" aria-hidden="true"></i>
          <span>Unmute</span>
        </button>
        <div class="kai-tv-playback-notice" id="kaiTvPlaybackNotice" hidden>
          <span id="kaiTvPlaybackMessage"></span>
          <button type="button" id="kaiTvStart">Start video</button>
        </div>
      </div>
      <h3 class="kai-tv-current-title" id="kaiTvCurrentTitle"><?= e($homepage_video_items[$homepage_video_active_index]['title']) ?></h3>
      <?php else: ?>
      <div class="kai-tv-empty">
        <i class="fa fa-circle-play" aria-hidden="true"></i>
        <p><?= $LANG==='rw' ? 'Amakuru ya videwo azagaragara hano.' : ($LANG==='fr' ? 'Les vidéos d’actualité apparaîtront ici.' : ($LANG==='sw' ? 'Habari za video zitaonekana hapa.' : 'Video news will appear here.')) ?></p>
        <?php if (isset($_SESSION['admin_id'])): ?>
        <a href="admin/settings.php"><?= $LANG==='rw' ? 'Ongeraho videwo muri Igenamiterere' : ($LANG==='fr' ? 'Ajouter des vidéos dans les paramètres' : ($LANG==='sw' ? 'Ongeza video kwenye Mipangilio' : 'Add videos in Settings')) ?></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="kai-tv-playlist">
      <h3><?= $LANG==='rw' ? 'IBIKURIKIRA KURI KAI NEWS KIGALI TV' : ($LANG==='fr' ? 'Vidéos d’actualité' : ($LANG==='sw' ? 'Habari za video' : 'Latest videos')) ?></h3>
      <div class="kai-tv-playlist-items">
        <?php if (empty($homepage_video_items)): ?>
        <p class="kai-tv-empty-list"><?= $LANG==='rw' ? 'Nta videwo zihari.' : ($LANG==='fr' ? 'Aucune vidéo pour le moment.' : ($LANG==='sw' ? 'Hakuna video kwa sasa.' : 'No videos available yet.')) ?></p>
        <?php endif; ?>
        <?php foreach ($homepage_video_items as $index => $item): ?>
          <?php if (in_array($item['type'], ['youtube', 'file', 'embed'], true)): ?>
          <button type="button" class="kai-tv-item<?= $index === $homepage_video_active_index ? ' active' : '' ?>"
            data-title="<?= e($item['title']) ?>"
            data-type="<?= e($item['type']) ?>"
            data-url="<?= e($item['url']) ?>"
            data-video-id="<?= e($item['youtube_id']) ?>"
            data-embed-url="<?= e($item['embed_url']) ?>"
            data-embed-sandbox="<?= $item['embed_sandbox'] ? 'true' : 'false' ?>"
            data-mime-type="<?= e($item['mime_type']) ?>"
            data-auto-title="<?= $item['auto_title'] ? 'true' : 'false' ?>"
            aria-current="<?= $index === $homepage_video_active_index ? 'true' : 'false' ?>"
            aria-label="<?= e('Play ' . $item['title']) ?>">
            <span class="kai-tv-item-thumb" style="background-image:url('<?= e($item['thumbnail'] ?: 'assets/images/default-thumbnail.svg') ?>')">
              <span class="kai-tv-item-play"><i class="fa fa-play" aria-hidden="true"></i></span>
            </span>
            <span class="kai-tv-item-title"><?= e($item['title']) ?></span>
          </button>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const player = document.getElementById('kaiTvPlayer');
  const title = document.getElementById('kaiTvCurrentTitle');
  if (!player) return;

  const host = document.getElementById('kaiTvMediaHost');
  const muteButton = document.getElementById('kaiTvMute');
  const playbackNotice = document.getElementById('kaiTvPlaybackNotice');
  const playbackMessage = document.getElementById('kaiTvPlaybackMessage');
  const startButton = document.getElementById('kaiTvStart');
  const videoItems = Array.from(document.querySelectorAll('.kai-tv-item[data-type]'));
  const playableItems = videoItems.filter(function (item) {
    return item.dataset.type === 'youtube' || item.dataset.type === 'file' || item.dataset.type === 'embed';
  });
  let currentIndex = playableItems.findIndex(function (item) {
    return item.classList.contains('active');
  });
  let youtubePlayer = null;
  let youtubeApiReady = false;
  let muted = true;

  function showPlaybackNotice(message) {
    if (!playbackNotice || !playbackMessage) return;
    playbackMessage.textContent = message;
    playbackNotice.hidden = false;
  }

  function hidePlaybackNotice() {
    if (playbackNotice) playbackNotice.hidden = true;
  }

  function updateMuteButton() {
    if (!muteButton) return;
    muteButton.setAttribute('aria-label', muted ? 'Unmute video' : 'Mute video');
    muteButton.setAttribute('aria-pressed', muted ? 'false' : 'true');
    muteButton.querySelector('i').className = muted ? 'fa fa-volume-xmark' : 'fa fa-volume-high';
    muteButton.querySelector('span').textContent = muted ? 'Unmute' : 'Mute';
  }

  function updatePlaylistSelection() {
    playableItems.forEach(function (item, index) {
      const selected = index === currentIndex;
      item.classList.toggle('active', selected);
      item.setAttribute('aria-current', selected ? 'true' : 'false');
    });
  }

  function setYoutubeMute() {
    if (!youtubePlayer || typeof youtubePlayer.mute !== 'function') return;
    if (muted) youtubePlayer.mute();
    else youtubePlayer.unMute();
  }

  function buildYoutubePlayer(item) {
    if (!window.YT || !window.YT.Player) return;
    host.replaceChildren();
    const target = document.createElement('div');
    target.id = 'kaiTvMedia';
    host.appendChild(target);
    youtubePlayer = new window.YT.Player(target.id, {
      videoId: item.dataset.videoId,
      playerVars: {
        autoplay: 1,
        controls: 0,
        disablekb: 1,
        fs: 0,
        mute: 1,
        playsinline: 1,
        rel: 0,
        enablejsapi: 1,
        origin: window.location.origin
      },
      events: {
        onReady: function (event) {
          setYoutubeMute();
          event.target.playVideo();
        },
        onStateChange: function (event) {
          if (event.data === window.YT.PlayerState.ENDED) playNextVideo();
        },
        onAutoplayBlocked: function () {
          showPlaybackNotice('Your browser blocked autoplay. Select Start video to begin.');
        },
        onError: function () {
          showPlaybackNotice('This video could not be played. Loading the next video.');
          window.setTimeout(playNextVideo, 1800);
        }
      }
    });
  }

  function playVideoAt(index) {
    if (!playableItems.length) return;
    currentIndex = (index + playableItems.length) % playableItems.length;
    const item = playableItems[currentIndex];
    updatePlaylistSelection();
    if (title) title.textContent = item.dataset.title;
    hidePlaybackNotice();
    if (muteButton) muteButton.hidden = item.dataset.type === 'embed';

    if (item.dataset.type === 'youtube') {
      if (youtubePlayer && youtubeApiReady) {
        setYoutubeMute();
        youtubePlayer.loadVideoById(item.dataset.videoId);
      } else {
        if (youtubePlayer && typeof youtubePlayer.destroy === 'function') {
          youtubePlayer.destroy();
          youtubePlayer = null;
        }
        buildYoutubePlayer(item);
      }
      return;
    }

    if (youtubePlayer && typeof youtubePlayer.destroy === 'function') {
      youtubePlayer.destroy();
      youtubePlayer = null;
    }
    if (item.dataset.type === 'embed') {
      const iframe = document.createElement('iframe');
      iframe.src = item.dataset.embedUrl;
      iframe.title = item.dataset.title;
      iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      iframe.allowFullscreen = true;
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      if (item.dataset.embedSandbox === 'true') {
        iframe.setAttribute('sandbox', 'allow-scripts allow-forms allow-presentation');
      }
      host.replaceChildren(iframe);
      return;
    }

    const video = document.createElement('video');
    video.autoplay = true;
    video.controls = false;
    video.muted = muted;
    video.defaultMuted = muted;
    video.playsInline = true;
    video.preload = 'auto';
    video.disablePictureInPicture = true;
    video.addEventListener('ended', playNextVideo);
    video.addEventListener('error', function () {
      showPlaybackNotice('This video could not be played. Loading the next video.');
      window.setTimeout(playNextVideo, 1800);
    }, { once: true });
    const source = document.createElement('source');
    source.src = item.dataset.url;
    source.type = item.dataset.mimeType;
    video.appendChild(source);
    host.replaceChildren(video);
    video.play().catch(function () {
      showPlaybackNotice('Your browser blocked autoplay. Select Start video to begin.');
    });
  }

  function playNextVideo() {
    playVideoAt(currentIndex + 1);
  }

  playableItems.forEach(function (item, index) {
    item.addEventListener('click', function () {
      playVideoAt(index);
    });
  });

  function loadYoutubeApi() {
    if (window.YT && window.YT.Player) {
      youtubeApiReady = true;
      if (playableItems[currentIndex].dataset.type === 'youtube') {
        buildYoutubePlayer(playableItems[currentIndex]);
      }
      return;
    }
    const previousCallback = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = function () {
      if (typeof previousCallback === 'function') previousCallback();
      youtubeApiReady = true;
      if (playableItems[currentIndex].dataset.type === 'youtube') {
        buildYoutubePlayer(playableItems[currentIndex]);
      }
    };
    const script = document.createElement('script');
    script.src = 'https://www.youtube.com/iframe_api';
    script.onerror = function () {
      if (playableItems[currentIndex].dataset.type === 'youtube') {
        showPlaybackNotice('The video player could not load. Check your internet connection.');
      }
    };
    document.head.appendChild(script);
  }

  document.querySelectorAll('.kai-tv-item[data-type="youtube"][data-auto-title="true"]').forEach(function (item) {
    const oembedUrl = 'https://www.youtube.com/oembed?format=json&url=' + encodeURIComponent(item.dataset.url);
    fetch(oembedUrl)
      .then(function (response) {
        if (!response.ok) throw new Error('YouTube title lookup failed.');
        return response.json();
      })
      .then(function (video) {
        if (!video.title) throw new Error('YouTube did not return a video title.');
        item.dataset.title = video.title;
        item.querySelector('.kai-tv-item-title').textContent = video.title;
        if (item.classList.contains('active') && title) title.textContent = video.title;
      })
      .catch(function () {
        item.dataset.title = 'YouTube video';
        item.querySelector('.kai-tv-item-title').textContent = 'YouTube video';
        if (item.classList.contains('active') && title) title.textContent = 'YouTube video';
      });
  });

  if (muteButton) {
    muteButton.addEventListener('click', function () {
      muted = !muted;
      const video = host.querySelector('video');
      if (video) video.muted = muted;
      setYoutubeMute();
      updateMuteButton();
    });
  }
  if (startButton) {
    startButton.addEventListener('click', function () {
      hidePlaybackNotice();
      const video = host.querySelector('video');
      if (video) {
        video.play().catch(function () {
          showPlaybackNotice('Playback is still blocked by your browser.');
        });
      } else if (youtubePlayer) {
        setYoutubeMute();
        youtubePlayer.playVideo();
      }
    });
  }

  updateMuteButton();
  if (playableItems.length) {
    if (currentIndex < 0) currentIndex = 0;
    updatePlaylistSelection();
    if (playableItems[currentIndex].dataset.type === 'youtube') {
      loadYoutubeApi();
    } else {
      playVideoAt(currentIndex);
      if (playableItems.some(function (item) { return item.dataset.type === 'youtube'; })) {
        loadYoutubeApi();
      }
    }
  } else {
    const mediaHost = document.getElementById('kaiTvMediaHost');
    if (mediaHost) mediaHost.closest('.kai-tv-player').remove();
    const emptyState = document.createElement('div');
    emptyState.className = 'kai-tv-empty';
    emptyState.textContent = 'Add a YouTube link or MP4, WEBM, OGG, or MOV file in Settings to start KAI NEWS KIGALI TV.';
    const featured = document.querySelector('.kai-tv-featured');
    if (featured) featured.prepend(emptyState);
  }
});
</script>

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
<section class="headline-showcase<?= $leftHeadlineStories ? ' has-left' : '' ?><?= $rightHeadlineStories ? ' has-right' : '' ?>" aria-label="<?= $LANG==='rw' ? 'Amakuru agezweho' : ($LANG==='fr' ? 'À la une' : ($LANG==='sw' ? 'Habari kuu' : 'Top stories')) ?>">
  <?php foreach (['left' => $leftHeadlineStories, 'right' => $rightHeadlineStories] as $side => $stories): ?>
    <?php if ($stories): ?>
    <div class="headline-side-list headline-side-<?= $side ?>">
      <?php foreach ($stories as $story): ?>
      <article class="headline-side-card">
        <a href="article.php?id=<?= $story['id'] ?>&lang=<?= $LANG ?>">
          <img src="<?= newsThumbnail($story['thumbnail']) ?>" alt="<?= e(t($story,'title',$LANG)) ?>" loading="lazy">
          <h2><?= e(t($story,'title',$LANG)) ?></h2>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($side === 'left'): ?>
    <div class="hero-slider" id="heroSlider">
      <div class="slides-container" id="slidesContainer">
        <?php foreach ($headlines as $i => $h): ?>
        <div class="slide <?= $i===0?'active':'' ?>" data-index="<?= $i ?>">
          <div class="slide-img" style="background-image:url('<?= newsThumbnail($h['thumbnail']) ?>')"></div>
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
          <button class="dot <?= $i===0?'active':'' ?>" data-i="<?= $i ?>" aria-label="<?= e(t($h,'title',$LANG)) ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ========== MAIN CONTENT ========== -->
<div class="container main-layout">
  <div class="content-area">

    <?php $show_views = canSeeViews(); ?>
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
              <?php if ($show_views): ?>
              <span><i class="fa fa-eye"></i> <?= number_format($n['views']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Mid Ad Banner -->
    <?php if ($ads_mid): ?>
    <?php
      $anim_mid = '';
      $anim_mid_data = '';
      if (!empty($ads_mid['is_animated'])) {
          $anim_mid = 'ad-anim-'.$ads_mid['animation_type'].' ad-animate-on-scroll';
          $interval = intval($ads_mid['animation_interval'] ?? 12);
          $anim_mid_data = ' data-noncontinuous="1" data-anim-interval="'. $interval .'"';
      }
    ?>
    <div class="ad-banner ad-mid <?= $anim_mid ?>"<?= $anim_mid_data ?>>
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
      <div class="video-live-grid<?= !empty($live_event_url) ? ' has-live-player' : '' ?>">
        <?php if (!empty($live_event_url)): ?>
        <?php
          $liveUrl = trim($live_event_url);
          $liveEmbed = '';
          $liveVideoType = '';
          if ($liveUrl) {
              if (stripos($liveUrl, 'youtube.com') !== false || stripos($liveUrl, 'youtu.be') !== false) {
                  $embed = normalizeYouTubeEmbedUrl($liveUrl);
                  if ($embed) {
                      $liveEmbed = buildInteractiveEmbedUrl($embed);
                  }
              } elseif (preg_match('#\.(mp4|webm|ogg|mov)(\?|$)#i', $liveUrl)) {
                  $liveVideoType = 'file';
              } else {
                  $liveVideoType = 'external';
              }
          }
        ?>
        <div class="live-event-card">
          <div class="live-event-content" style="padding:24px;display:flex;flex-direction:column;justify-content:space-between;gap:18px;">
            <div>
              <span class="sponsored-badge"><?= $LANG==='rw' ? 'LIVE EVENT' : ($LANG==='fr' ? 'Événement en direct' : ($LANG==='sw' ? 'Tukio la Moja kwa Moja' : 'Live Event')) ?></span>
              <h3 style="margin:14px 0 8px;font-size:1.55rem;line-height:1.2;"><?= e($live_event_title ?: ($LANG==='rw' ? 'Ikipe yacu iri kumurongo' : ($LANG==='fr' ? 'Notre événement en direct' : ($LANG==='sw' ? 'Tukio letu la moja kwa moja' : 'Our live event')))) ?></h3>
              <?php if (!empty($live_event_time)): ?>
              <p style="margin:0 0 10px;color:#5c6a85;font-weight:600;"><?= e($live_event_time) ?></p>
              <?php endif; ?>
              <?php if (!empty($live_event_description)): ?>
              <p style="margin:0;color:#4a5568;line-height:1.6;"><?= e($live_event_description) ?></p>
              <?php endif; ?>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <a href="watch.php?src=live&lang=<?= $LANG ?>" class="btn btn-live" style="min-width:180px;display:inline-flex;align-items:center;justify-content:center;"><?= e($live_event_button_text ?: ($LANG==='rw' ? 'Reba Live' : ($LANG==='fr' ? 'Voir en direct' : ($LANG==='sw' ? 'Tazama moja kwa moja' : 'Watch Live')))) ?></a>
              <?php if ($liveVideoType === 'external'): ?>
              <a href="<?= e($liveUrl) ?>" target="_blank" class="btn btn-outline" style="min-width:180px;"><?= $LANG==='rw' ? 'Fungura hanze' : ($LANG==='fr' ? 'Ouvrir en externe' : ($LANG==='sw' ? 'Fungua nje' : 'Open externally')) ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($live_event_url)): ?>
        <div class="live-video-card">
          <?php if ($liveEmbed): ?>
          <div class="live-video-player">
            <iframe src="<?= e($liveEmbed) ?>" title="<?= e($live_event_title ?: 'Live event') ?>" frameborder="0" allow="encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
          </div>
          <?php elseif ($liveVideoType === 'file'): ?>
          <div class="live-video-player">
            <video controls preload="none" playsinline style="width:100%;height:100%;object-fit:cover;">
              <source src="<?= e($liveUrl) ?>" type="video/mp4">
            </video>
          </div>
          <?php else: ?>
          <div class="live-video-player">
            <iframe src="watch.php?src=live&lang=<?= $LANG ?>" title="<?= e($live_event_title ?: 'Live event') ?>" frameborder="0" allow="encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
          </div>
          <?php endif; ?>
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
              <source src="<?= e($online_radio_url) ?>"<?= $online_radio_mime ? ' type="'.e($online_radio_mime).'"' : '' ?> >
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

        <?php
          $showAdVideo = $ad_video_type !== 'none';
          if ($showAdVideo && !empty($live_event_url)) {
              $liveYoutubeId = getYouTubeVideoId($live_event_url);
              $adYoutubeId = $ad_video_type === 'youtube' ? getYouTubeVideoId($ad_video_url) : false;
              $sameYoutubeVideo = $liveYoutubeId && $adYoutubeId && $liveYoutubeId === $adYoutubeId;
              $sameFileVideo = $liveVideoType === 'file'
                  && $ad_video_type === 'file'
                  && rtrim($liveUrl, '/') === rtrim($ad_video_source, '/');
              $showAdVideo = !$sameYoutubeVideo && !$sameFileVideo;
          }
        ?>
        <?php if ($showAdVideo): ?>
        <div class="ad-video-card">
          <?php if ($ad_video_type === 'youtube'): ?>
          <div class="ad-video-player">
            <iframe src="<?= e($ad_video_source) ?>" title="<?= e($ad_video_title ?: 'Advertisement video') ?>" frameborder="0" allow="encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
          </div>
          <?php elseif ($ad_video_type === 'file'): ?>
          <div class="ad-video-player">
            <video controls preload="none" playsinline style="width:100%;height:100%;object-fit:cover;">
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
    <?php
      $s_attr = '';
      $s_classes = 'widget ad-widget';
      if (!empty($ad['is_animated'])) {
        $s_classes .= ' ad-animate-on-scroll ad-anim-'.($ad['animation_type']?:'fade');
        $s_interval = intval($ad['animation_interval'] ?? 12);
        $s_attr = ' data-noncontinuous="1" data-anim-interval="'.$s_interval.'"';
      }
    ?>
    <div class="<?= $s_classes ?>"<?= $s_attr ?>>
      <a href="<?= e($ad['link_url']) ?>" target="_blank">
        <img src="<?= UPLOAD_URL . e($ad['image']) ?>" alt="<?= e($ad['title']) ?>">
      </a>
    </div>
    <?php endforeach; ?>

    <!-- Most Read -->
    <div class="widget">
      <?php $show_views = canSeeViews(); ?>
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
