<?php
require_once 'auth.php';
requirePermission('manage_settings');

$success = $error = '';

function getYouTubeEmbedUrl($url) {
    if (!$url) return false;
    $url = trim($url);
    if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0';
    }
    return false;
}

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
      'site_name', 'site_tagline', 'site_email', 'site_phone',
      'site_address', 'facebook_url', 'X_url', 'instagram_url',
      'youtube_url', 'online_radio_url', 'live_event_url', 'live_event_time', 'live_event_title', 'live_event_button_text', 'live_event_description', 'homepage_video_links',
      'ad_video_url', 'ad_video_file', 'ad_video_title', 'ad_video_button_text', 'ad_video_description', 'whatsapp_number',
      'breaking_news', 'footer_text'
    ];
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $val_escaped = db()->real_escape_string($val);
        $key_escaped = db()->real_escape_string($key);
        db()->query("INSERT INTO settings (key_name, value) VALUES ('$key_escaped', '$val_escaped')
                     ON DUPLICATE KEY UPDATE value='$val_escaped'");
    }

    $maxFileSize = 30 * 1024 * 1024; // 30MB
    if (isset($_FILES['ad_video_file']) && $_FILES['ad_video_file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['ad_video_file']['size'] > $maxFileSize) {
            $error = 'Uploaded video must be 30MB or smaller.';
        } else {
            $uploadDir = UPLOAD_PATH . 'ads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $tmpName = $_FILES['ad_video_file']['tmp_name'];
            $originalName = basename($_FILES['ad_video_file']['name']);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = ['mp4', 'webm', 'ogg', 'mov'];
            if (in_array($ext, $allowed, true)) {
                $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                $targetName = $safeName . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $targetName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $fileSetting = db()->real_escape_string('ads/' . $targetName);
                    db()->query("INSERT INTO settings (key_name, value) VALUES ('ad_video_file', '$fileSetting') ON DUPLICATE KEY UPDATE value='$fileSetting'");
                } else {
                    $error = 'Failed to upload the advertisement video file.';
                }
            } else {
                $error = 'Only MP4, WEBM, OGG, and MOV video files are allowed.';
            }
        }
    }

    if (isset($_FILES['live_event_thumbnail_file']) && $_FILES['live_event_thumbnail_file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['live_event_thumbnail_file']['size'] > 5 * 1024 * 1024) {
            $error = 'Thumbnail image must be 5MB or smaller.';
        } else {
            $uploadDir = UPLOAD_PATH . 'live-event/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $tmpName = $_FILES['live_event_thumbnail_file']['tmp_name'];
            $originalName = basename($_FILES['live_event_thumbnail_file']['name']);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed, true)) {
                $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                $targetName = $safeName . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $targetName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $fileSetting = db()->real_escape_string('live-event/' . $targetName);
                    db()->query("INSERT INTO settings (key_name, value) VALUES ('live_event_thumbnail', '$fileSetting') ON DUPLICATE KEY UPDATE value='$fileSetting'");
                } else {
                    $error = 'Failed to upload the live event thumbnail image.';
                }
            } else {
                $error = 'Only JPG, JPEG, PNG, and WEBP images are allowed for the thumbnail.';
            }
        }
    }

    if (!$error) {
        $success = 'Settings saved successfully!';
    }
}

// Load current settings
$settings = [];
$rows = db()->query("SELECT key_name, value FROM settings")->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $r) $settings[$r['key_name']] = $r['value'];

function s($key) {
    global $settings;
    return htmlspecialchars($settings[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

adminHeader("Settings / Igenamiterere");
?>

<div class="admin-form-page">
<?php if ($success): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">

  <h3 style="color:var(--navy);margin-bottom:20px;font-size:1rem;border-bottom:2px solid var(--light);padding-bottom:10px">
    <i class="fa fa-globe"></i> Site Information
  </h3>

  <div class="form-row">
    <div class="form-group">
      <label>Site Name / Izina ry'Urubuga</label>
      <input type="text" name="site_name" value="<?= s('site_name') ?>" placeholder="KAI News Kigali">
    </div>
    <div class="form-group">
      <label>Tagline / Inshuti</label>
      <input type="text" name="site_tagline" value="<?= s('site_tagline') ?>" placeholder="Amakuru Ahuza Kigali n'Isi Yose">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Contact Email</label>
      <input type="email" name="site_email" value="<?= s('site_email') ?>" placeholder="info@kainews.rw">
    </div>
    <div class="form-group">
      <label>Phone Number</label>
      <input type="text" name="site_phone" value="<?= s('site_phone') ?>" placeholder="+250 7XX XXX XXX">
    </div>
  </div>

  <div class="form-group">
    <label>Address / Aderesi</label>
    <input type="text" name="site_address" value="<?= s('site_address') ?>" placeholder="Kigali, Rwanda">
  </div>

  <div class="form-group">
    <label>Breaking News Ticker <small>(separate headlines with |)</small></label>
    <textarea name="breaking_news" rows="3" placeholder="Breaking: Story one | Breaking: Story two"><?= s('breaking_news') ?></textarea>
  </div>

  <h3 style="color:var(--navy);margin:28px 0 20px;font-size:1rem;border-bottom:2px solid var(--light);padding-bottom:10px">
    <i class="fa fa-share-alt"></i> Social Media Links
  </h3>

  <div class="form-row">
    <div class="form-group">
      <label><i class="fab fa-facebook" style="color:#1877f2"></i> Facebook URL</label>
      <input type="url" name="facebook_url" value="<?= s('facebook_url') ?>" placeholder="https://facebook.com/kainews">
    </div>
    <div class="form-group">
      <label><i class="fab fa-twitter" style="color:#000"></i> Twitter/X URL</label>
      <input type="url" name="twitter_url" value="<?= s('X_url') ?>" placeholder="https://x.com/kainews">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><i class="fab fa-instagram" style="color:#e1306c"></i> Instagram URL</label>
      <input type="url" name="instagram_url" value="<?= s('instagram_url') ?>" placeholder="https://instagram.com/kainews">
    </div>
    <div class="form-group">
      <label><i class="fab fa-youtube" style="color:#ff0000"></i> YouTube URL</label>
      <input type="url" name="youtube_url" value="<?= s('youtube_url') ?>" placeholder="https://youtube.com/@kainews">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><i class="fa fa-broadcast-tower" style="color:#0d1f3c"></i> Online Radio Stream URL</label>
      <input type="url" name="online_radio_url" value="<?= s('online_radio_url') ?>" placeholder="https://radio.example.com/live">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><i class="fa fa-video" style="color:#c8102e"></i> Live Event URL</label>
      <input type="url" name="live_event_url" value="<?= s('live_event_url') ?>" placeholder="https://youtube.com/live/...">
    </div>
    <div class="form-group">
      <label><i class="fa fa-clock" style="color:#c8102e"></i> Live Event Time</label>
      <input type="text" name="live_event_time" value="<?= s('live_event_time') ?>" placeholder="20:00 Kigali / 8:00 PM">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><i class="fa fa-image" style="color:#0d1f3c"></i> Live Event Thumbnail</label>
      <input type="file" name="live_event_thumbnail_file" accept="image/jpeg,image/png,image/webp">
      <?php if (s('live_event_thumbnail')): ?>
      <p style="font-size:0.88rem;color:var(--muted);margin-top:6px;">Current thumbnail:</p>
      <img src="<?= UPLOAD_URL . e(s('live_event_thumbnail')) ?>" alt="Live event thumbnail" style="max-width:240px;border-radius:10px;border:1px solid #dde3ed;">
      <?php endif; ?>
    </div>
  </div>

  <div class="form-group">
    <label><i class="fa fa-film" style="color:#0d1f3c"></i> KAI NEWS KIGALI TV Videos</label>
    <textarea name="homepage_video_links" rows="5" placeholder="Optional title | https://www.youtube.com/watch?v=VIDEO_ID\nAnother video title | https://youtu.be/VIDEO_ID\nhttps://www.youtube.com/watch?v=OTHER_ID"><?= s('homepage_video_links') ?></textarea>
    <p style="font-size:0.9rem;color:var(--muted);margin-top:6px;">Add one video per line as <code>News title | video URL</code>. YouTube, Vimeo, Dailymotion, TikTok, Instagram, and Facebook video links use their embedded players. Direct MP4, WEBM, OGG, and MOV URLs also play here. Other links are opened inside the player when the website permits embedding; some sites block this, so use a direct video URL or official embed link if needed. Add a title for non-YouTube links.</p>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><i class="fa fa-video-camera" style="color:#0d1f3c"></i> Advertisement Video URL</label>
      <input type="url" name="ad_video_url" value="<?= s('ad_video_url') ?>" placeholder="https://example.com/video.mp4">
    </div>
    <div class="form-group">
      <label><i class="fa fa-upload" style="color:#0d1f3c"></i> Upload Ad Video File</label>
      <input type="file" name="ad_video_file" accept="video/mp4,video/webm,video/ogg,video/quicktime">
      <?php if (s('ad_video_file')): ?>
      <p style="font-size:0.88rem;color:var(--muted);margin-top:6px;">Current file: <a href="<?= UPLOAD_URL . e(s('ad_video_file')) ?>" target="_blank"><?= basename(s('ad_video_file')) ?></a></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Ad Video Title</label>
      <input type="text" name="ad_video_title" value="<?= s('ad_video_title') ?>" placeholder="Byamamaza Video">
    </div>
    <div class="form-group">
      <label>Ad Video Button Text</label>
      <input type="text" name="ad_video_button_text" value="<?= s('ad_video_button_text') ?>" placeholder="Reba Videwo">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group" style="width:100%;">
      <label>Ad Video Description</label>
      <textarea name="ad_video_description" rows="2" placeholder="Optional message shown under the ad title."><?= s('ad_video_description') ?></textarea>
    </div>
  </div>
  <?php if (s('ad_video_file') || s('ad_video_url')): ?>
  <div class="form-row">
    <div class="form-group" style="width:100%;">
      <label>Video Preview</label>
      <?php if (s('ad_video_file')): ?>
        <video controls style="width:100%;max-width:100%;border-radius:10px;" src="<?= UPLOAD_URL . e(s('ad_video_file')) ?>" preload="metadata"></video>
      <?php elseif (getYouTubeEmbedUrl(s('ad_video_url'))): ?>
        <div style="position:relative;padding-top:56.25%;border-radius:10px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.08);">
          <iframe src="<?= e(getYouTubeEmbedUrl(s('ad_video_url'))) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
            style="position:absolute;top:0;left:0;width:100%;height:100%;"></iframe>
        </div>
      <?php else: ?>
        <a href="<?= e(s('ad_video_url')) ?>" target="_blank" style="display:inline-block;padding:10px 16px;border:1px solid #dde3ed;border-radius:10px;color:var(--navy);background:#fafafa;">Open video link</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
    <button type="submit" class="btn-publish">
      <i class="fa fa-save"></i> Bika Igenamiterere / Save Settings
    </button>
  </div>

</form>
</div>

<?php adminFooter(); ?>
