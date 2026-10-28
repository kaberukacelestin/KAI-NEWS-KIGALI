<?php
require_once 'auth.php';
requirePermission('manage_ads');

$msg = '';
$errors = [];

// Ensure the advertisements table has the needed columns and enum values for the photo slots.
$col = db()->query("SHOW COLUMNS FROM advertisements LIKE 'animation_interval'")->fetch_assoc();
if (!$col) {
  db()->query("ALTER TABLE advertisements ADD COLUMN animation_interval INT DEFAULT NULL");
}

$positionCol = db()->query("SHOW COLUMNS FROM advertisements LIKE 'position'")->fetch_assoc();
if ($positionCol && strpos($positionCol['Type'], "banner_top_photo_1") === false) {
  db()->query("ALTER TABLE advertisements MODIFY position ENUM('banner_top','banner_top_photo_1','banner_top_photo_2','banner_mid','sidebar','footer','popup') DEFAULT 'banner_top'");
}

$adPositions = [
    'banner_top' => 'Top Banner (Home)',
    'banner_top_photo_1' => 'Top Banner Photo 1 (Home, left)',
    'banner_top_photo_2' => 'Top Banner Photo 2 (Home, right)',
    'banner_mid' => 'Mid Banner (Home)',
    'sidebar' => 'Sidebar',
    'footer' => 'Footer',
    'popup' => 'Popup',
];

function getActiveAdCondition(): string {
    return "is_active=1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())";
}

$uniqueSlots = ['banner_top','banner_top_photo_1','banner_top_photo_2','banner_mid','footer','popup'];

$selectedPosition = $_GET['position'] ?? 'banner_top';
if (!isset($adPositions[$selectedPosition])) {
    $selectedPosition = 'banner_top';
}

if (isset($_GET['toggle'])) {
    $tid = intval($_GET['toggle']);
    db()->query("UPDATE advertisements SET is_active = NOT is_active WHERE id=$tid");
    header("Location: ads.php?msg=updated"); exit;
}

if (isset($_GET['delete'])) {
    $did = intval($_GET['delete']);
    $ad = db()->query("SELECT image, video FROM advertisements WHERE id=$did")->fetch_assoc();
    if ($ad) {
      if (!empty($ad['image'])) {
        $f = UPLOAD_PATH . $ad['image'];
        if (file_exists($f)) unlink($f);
      }
      // also remove video if present (only unlink local files; don't unlink URLs)
      if (!empty($ad['video'])) {
        if (!preg_match('#^https?://#i', $ad['video'])) {
          $v = UPLOAD_PATH . $ad['video'];
          if (file_exists($v)) unlink($v);
        }
      }
      db()->query("DELETE FROM advertisements WHERE id=$did");
    }
    header("Location: ads.php?msg=deleted"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title        = trim($_POST['title']       ?? '');
  $link         = trim($_POST['link_url']    ?? '');
  $position     = $_POST['position']         ?? 'banner_top';
  $start        = $_POST['start_date']       ?: null;
  $end          = $_POST['end_date']         ?: null;
  $is_animated  = isset($_POST['is_animated']) ? 1 : 0;
  $anim_type    = $_POST['animation_type']   ?? 'fade';
  $anim_interval = intval($_POST['animation_interval'] ?? 12);
  $video_url_input = trim($_POST['video_url'] ?? '');
  $topPhotoPositions = ['banner_top_photo_1','banner_top_photo_2'];
  $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

  $existingAd = null;
  if ($edit_id) {
    $existingAd = db()->query("SELECT image, video, position FROM advertisements WHERE id=$edit_id")->fetch_assoc();
    if ($existingAd) {
      if (!isset($adPositions[$position])) {
        $position = $existingAd['position'];
      }
    }
  }

  if (!isset($adPositions[$position])) {
    $position = 'banner_top';
  }

  if ($title === '') {
    $errors[] = 'Ad title is required.';
  }

  $hasExistingMedia = !empty($existingAd['image'] ?? '') || !empty($existingAd['video'] ?? '');
  if (in_array($position, $topPhotoPositions, true) && empty($_FILES['image']['name']) && ($existingAd['image'] ?? '') === '') {
    $errors[] = 'Top banner photo slots require an image upload.';
  }
  if (!in_array($position, $topPhotoPositions, true) && empty($_FILES['image']['name']) && empty($_FILES['video']['name']) && $video_url_input === '' && !$hasExistingMedia) {
    $errors[] = 'Please upload an image, a video file, or provide a video URL.';
  }
  if (in_array($position, $topPhotoPositions, true) && $video_url_input !== '') {
    $errors[] = 'Photo-only slots cannot use video URLs.';
  }

  $fdb = null;
  $fdb_video = null;

  if (!empty($_FILES['image']['name'])) {
    $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','gif'];
    if (in_array($ext, $allowed, true)) {
      if (!is_dir(UPLOAD_PATH . 'ads')) mkdir(UPLOAD_PATH . 'ads', 0755, true);
      $fname = 'ad_' . time() . '_' . rand(100,999) . '.' . $ext;
      $fpath = UPLOAD_PATH . 'ads/' . $fname;
      if (move_uploaded_file($_FILES['image']['tmp_name'], $fpath)) {
        $fdb  = 'ads/' . $fname;
      } else {
        $errors[] = 'Failed to save the uploaded image. Please try again.';
      }
    } else {
      $errors[] = 'Invalid image type. Allowed: jpg, jpeg, png, webp, gif.';
    }
  }

  if (!empty($_FILES['video']['name'])) {
    $vext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
    $vallowed = ['mp4','webm','ogg','mov'];
    if (in_array($vext, $vallowed, true)) {
      if (!is_dir(UPLOAD_PATH . 'ads/videos')) mkdir(UPLOAD_PATH . 'ads/videos', 0755, true);
      $vname = 'advid_' . time() . '_' . rand(100,999) . '.' . $vext;
      $vpath = UPLOAD_PATH . 'ads/videos/' . $vname;
      if (move_uploaded_file($_FILES['video']['tmp_name'], $vpath)) {
        $fdb_video = 'ads/videos/' . $vname;
      } else {
        $errors[] = 'Failed to save the uploaded video. Please try again.';
      }
    } else {
      $errors[] = 'Invalid video type. Allowed: mp4, webm, ogg, mov.';
    }
  }

  if (empty($fdb_video) && $video_url_input) {
    $fdb_video = $video_url_input;
  }

  if (empty($errors)) {
    if (in_array($position, $uniqueSlots, true)) {
      $positionEscaped = db()->real_escape_string($position);
      $where = "position='$positionEscaped' AND is_active=1";
      if ($edit_id) {
        $where .= " AND id<>$edit_id";
      }
      db()->query("UPDATE advertisements SET is_active=0 WHERE $where");
    }

    if ($edit_id) {
      // Update fields (only update uploaded files if provided)
      $fields = [ 'title' => $title, 'link_url' => $link, 'position' => $position, 'start_date' => $start, 'end_date' => $end, 'is_animated' => $is_animated, 'animation_type' => $anim_type, 'animation_interval' => $anim_interval ];
      $params = [];
      $types = '';
      $setParts = [];
      foreach ($fields as $k => $v) {
        $setParts[] = "$k=?";
        $params[] = $v;
        if (in_array($k, ['is_animated','animation_interval'], true)) $types .= 'i'; else $types .= 's';
      }
      if ($fdb) { $setParts[] = "image=?"; $params[] = $fdb; $types .= 's'; }
      if ($fdb_video) { $setParts[] = "video=?"; $params[] = $fdb_video; $types .= 's'; }
      $types .= 'i';
      $params[] = $edit_id;
      $sql = "UPDATE advertisements SET " . implode(', ', $setParts) . " WHERE id=?";
      $stmt = db()->prepare($sql);
      if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
          $msg = 'Advertisement updated successfully!';
        } else {
          $msg = 'Failed to update advertisement: ' . db()->error;
        }
      } else {
        $msg = 'Failed to prepare update statement: ' . db()->error;
      }
    } elseif ($title && ($fdb || $fdb_video)) {
      $fdb_db = $fdb ?: '';
      $fdb_video_db = $fdb_video ?: '';
      $titleEsc = db()->real_escape_string($title);
      $imageEsc = db()->real_escape_string($fdb_db);
      $videoEsc = db()->real_escape_string($fdb_video_db);
      $linkEsc = db()->real_escape_string($link);
      $positionEsc = db()->real_escape_string($position);
      $startEsc = $start === null ? 'NULL' : "'" . db()->real_escape_string($start) . "'";
      $endEsc = $end === null ? 'NULL' : "'" . db()->real_escape_string($end) . "'";
      $isAnimatedEsc = intval($is_animated);
      $animTypeEsc = db()->real_escape_string($anim_type);
      $animIntervalEsc = intval($anim_interval);

      $sql = "INSERT INTO advertisements (title, image, video, link_url, position, start_date, end_date, is_animated, animation_type, animation_interval) VALUES ('$titleEsc', '$imageEsc', '$videoEsc', '$linkEsc', '$positionEsc', $startEsc, $endEsc, $isAnimatedEsc, '$animTypeEsc', $animIntervalEsc)";
      if (db()->query($sql)) {
        $msg = 'Advertisement added successfully!';
      } else {
        $msg = 'Failed to add advertisement: ' . db()->error;
      }
    }
  }
}

$ads = db()->query("SELECT * FROM advertisements ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Backfill: set default animation_interval for existing animated ads lacking an interval
$needs = db()->query("SELECT COUNT(*) as c FROM advertisements WHERE is_animated=1 AND (animation_interval IS NULL OR animation_interval=0)")->fetch_assoc();
if (!empty($needs['c'])) {
  db()->query("UPDATE advertisements SET animation_interval=12 WHERE is_animated=1 AND (animation_interval IS NULL OR animation_interval=0)");
}

$emptySlots = [];
$activePositions = array_column(db()->query("SELECT position FROM advertisements WHERE " . getActiveAdCondition())->fetch_all(MYSQLI_ASSOC), 'position');
foreach ($uniqueSlots as $slot) {
    if (!in_array($slot, $activePositions, true)) {
        $emptySlots[$slot] = $adPositions[$slot];
    }
}

adminHeader("Advertisements");
?>


<style>
/* Animation previews for the admin */
.anim-demo { display:inline-block; width:40px; height:20px; background:#e74c3c; border-radius:4px; }
.ad-anim-badge { font-size:.75rem; padding:2px 8px; border-radius:10px; background:#9b59b6; color:#fff; }

/* --- ANIMATION CSS for the FRONTEND (injected inline) ---
   Copy the @keyframes below into your assets/css/style.css
   so they're available on article/index pages without loading this file.
*/
@keyframes ad-slide-in {
  0%   { transform: translateX(-60px); opacity: 0; }
  100% { transform: translateX(0);     opacity: 1; }
}
@keyframes ad-fade-in {
  0%   { opacity: 0; }
  100% { opacity: 1; }
}
@keyframes ad-bounce-in {
  0%   { transform: scale(0.6); opacity: 0; }
  60%  { transform: scale(1.08); opacity: 1; }
  80%  { transform: scale(0.96); }
  100% { transform: scale(1); }
}
@keyframes ad-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(192,57,43,.5); }
  50%      { box-shadow: 0 0 0 12px rgba(192,57,43,0); }
}
@keyframes ad-zoom-in {
  0%   { transform: scale(1.18); opacity: 0; }
  100% { transform: scale(1);    opacity: 1; }
}
@keyframes ad-flip-in {
  0%   { transform: rotateY(90deg); opacity: 0; }
  100% { transform: rotateY(0deg);  opacity: 1; }
}
.ad-anim-slide  { animation: ad-slide-in  .6s ease both; }
.ad-anim-fade   { animation: ad-fade-in   .8s ease both; }
.ad-anim-bounce { animation: ad-bounce-in .7s ease both; }
.ad-anim-pulse  { animation: ad-pulse     1.5s infinite; }
.ad-anim-zoom   { animation: ad-zoom-in   .6s ease both; }
.ad-anim-flip   { animation: ad-flip-in   .7s ease both; perspective: 600px; }
</style>

<div class="admin-section">
<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php elseif ($msg || ($_GET['msg']??'')): ?>
<div class="alert alert-success"><?= e($msg ?: 'Updated!') ?></div>
<?php endif; ?>

<h3>Add New Advertisement</h3>
<form method="POST" enctype="multipart/form-data" class="ad-form">
  <div class="form-row">
    <div class="form-group">
      <label>Ad Title / Izina</label>
      <input type="text" name="title" required placeholder="e.g. MTN Rwanda Banner">
    </div>
    <div class="form-group">
      <label>Position on Site</label>
      <select name="position">
        <?php foreach ($adPositions as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === $selectedPosition ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Click Link URL</label>
      <input type="url" name="link_url" placeholder="https://advertiser-website.com">
    </div>
    <div class="form-group">
      <label>Ad Image / Ifoto</label>
      <input type="file" name="image" accept="image/*">
      <small style="display:block;color:#6b7280;margin-top:4px;font-size:.85rem;">Optional. For Top Banner Photo slots, upload one image per slot. There are only two top photo slots now, and video is not used for these positions.</small>
    </div>
    <div class="form-group">
      <label>Ad Video (optional) / Video</label>
      <input type="file" name="video" accept="video/*">
      <small style="display:block;color:#6b7280;margin-top:4px;font-size:.85rem;">Upload a video file or paste a video URL. For Top Banner (Home), the right 30% panel will use the video while the left panel can show an image.</small>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group" style="width:100%">
      <label>Or YouTube / External Video URL (optional)</label>
      <input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=... or https://example.com/video.mp4" style="width:100%">
      <small style="display:block;color:#6b7280;margin-top:4px;font-size:.85rem;">If provided, this URL will be used instead of an uploaded video file.</small>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Start Date (optional)</label>
      <input type="date" name="start_date">
    </div>
    <div class="form-group">
      <label>End Date (optional)</label>
      <input type="date" name="end_date">
    </div>
  </div>

  <!-- Animation Controls -->
  <div style="background:#f8f0ff;border:1.5px solid #9b59b6;border-radius:10px;padding:18px;margin-bottom:16px">
    <label style="color:#8e44ad;font-size:.95rem;font-weight:700;display:block;margin-bottom:12px">
      <i class="fa fa-magic"></i> Animation Settings
    </label>
    <div class="form-row" style="align-items:center">
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <label class="check-label" style="margin:0">
          <input type="checkbox" name="is_animated" id="animToggle" value="1">
          <span>Enable Animation on this Ad</span>
        </label>
      </div>
      <div class="form-group" id="animTypeWrap" style="display:none">
        <label>Animation Style</label>
        <select name="animation_type" id="animTypeSelect">
          <option value="fade">✨ Fade In — smooth appearance</option>
          <option value="slide">➡ Slide In — enters from left</option>
          <option value="bounce">🎾 Bounce In — springs in with energy</option>
          <option value="pulse">💓 Pulse — continuous heartbeat glow</option>
          <option value="zoom">🔍 Zoom In — scales from larger</option>
          <option value="flip">🔄 Flip In — 3D flip reveal</option>
        </select>
      </div>
      <div class="form-group" id="animIntervalWrap" style="display:none;margin-left:12px">
        <label>Interval (seconds)</label>
        <input type="number" name="animation_interval" id="animIntervalInput" min="4" max="120" value="12" style="width:110px">
      </div>
    </div>
    <!-- Live preview -->
    <div id="animPreviewWrap" style="display:none;margin-top:12px;text-align:center">
      <p style="font-size:.8rem;color:#888;margin-bottom:6px">Preview (click to replay):</p>
      <div id="animPreviewBox" style="display:inline-block;background:#c0392b;color:#fff;padding:14px 30px;border-radius:8px;font-weight:700;cursor:pointer"
           onclick="replayPreview()">ADVERTISEMENT</div>
    </div>
  </div>

  <button type="submit" class="btn-publish"><i class="fa fa-plus"></i> Add Advertisement</button>
</form>

<?php if (!empty($emptySlots)): ?>
  <div class="admin-section" style="margin-top:20px;padding:18px 18px 12px;border:1px solid #dde3ed;border-radius:12px;background:#fbfcfe;">
    <h3 style="margin-bottom:14px;color:#1d3557;">Empty ad slots</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <?php foreach ($emptySlots as $slot => $label): ?>
      <div style="background:#fff;border:1px dashed #bbc4d8;border-radius:12px;padding:14px;display:flex;flex-direction:column;justify-content:space-between;min-height:96px;">
        <div style="font-weight:700;color:#2c3e50;margin-bottom:10px;"><?= e($label) ?></div>
        <a href="ads.php?position=<?= urlencode($slot) ?>" class="btn-sm" style="align-self:flex-start;background:#0d6efd;color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none;">Add ad for this slot</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<hr style="margin:30px 0">

<h3>All Advertisements (<?= count($ads) ?>)</h3>
<table class="admin-table">
  <thead>
    <tr><th>Image</th><th>Title</th><th>Position</th><th>Animation</th><th>Interval(s)</th><th>Clicks</th><th>Status</th><th>Dates</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($ads as $ad): ?>
    <tr>
      <td>
        <?php if (!empty($ad['video'])): ?>
          <?php if (preg_match('#^https?://#i', $ad['video']) && preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)#i', $ad['video'])): ?>
            <?php
              // Try to build a simple embed URL
              if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $ad['video'], $m)): ?>
                <?php
                  $yid = $m[1];
                  $embed = 'https://www.youtube.com/embed/' . $yid . '?rel=0&mute=1';
                ?>
                <iframe src="<?= e($embed) ?>" class="table-thumb" style="height:60px; width:120px;" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <?php else: ?>
              <a href="<?= e($ad['video']) ?>" target="_blank" class="table-thumb" style="display:inline-block; height:60px; width:120px; background:#000; color:#fff; text-decoration:none; text-align:center; line-height:60px;">Open Video</a>
            <?php endif; ?>
          <?php elseif (preg_match('#^https?://#i', $ad['video'])): ?>
            <a href="<?= e($ad['video']) ?>" target="_blank" class="table-thumb" style="display:inline-block; height:60px; width:120px; background:#000; color:#fff; text-decoration:none; text-align:center; line-height:60px;">Open Video</a>
          <?php else: ?>
            <video src="<?= UPLOAD_URL . e($ad['video']) ?>" class="table-thumb" style="max-height:60px;" muted playsinline></video>
          <?php endif; ?>
        <?php elseif (!empty($ad['image'])): ?>
          <img src="<?= UPLOAD_URL . e($ad['image']) ?>" class="table-thumb" alt="">
        <?php else: ?>
          —
        <?php endif; ?>
      </td>
      <td><?= e($ad['title']) ?><br><small><a href="<?= e($ad['link_url']) ?>" target="_blank"><?= e(mb_substr($ad['link_url'],0,40)) ?></a></small></td>
      <td><span class="badge published"><?= e($ad['position']) ?></span></td>
      <td>
        <?php if (!empty($ad['is_animated'])): ?>
          <span class="ad-anim-badge"><i class="fa fa-magic"></i> <?= e($ad['animation_type']) ?></span>
        <?php else: ?>
          <span style="color:#aaa;font-size:.8rem">Static</span>
        <?php endif; ?>
      </td>
      <td style="text-align:center;">
        <?= !empty($ad['animation_interval']) ? intval($ad['animation_interval']) : '&ndash;' ?>
      </td>
      <td><?= number_format($ad['clicks']) ?></td>
      <td>
        <a href="ads.php?toggle=<?= $ad['id'] ?>" class="badge <?= $ad['is_active']?'published':'draft' ?>">
          <?= $ad['is_active'] ? 'Active' : 'Inactive' ?>
        </a>
      </td>
      <td>
        <?= $ad['start_date'] ? date('d/m/Y', strtotime($ad['start_date'])) : '—' ?>
        <?= $ad['end_date']   ? ' – ' . date('d/m/Y', strtotime($ad['end_date'])) : '' ?>
      </td>
      <td>
        <a href="#" class="btn-sm edit-btn" data-id="<?= $ad['id'] ?>" style="margin-right:6px"><i class="fa fa-edit"></i> Edit</a>
        <a href="ads.php?delete=<?= $ad['id'] ?>" class="btn-sm del" onclick="return confirm('Delete this ad?')"><i class="fa fa-trash"></i></a>
      </td>
    </tr>
    <tr class="edit-row" id="edit-row-<?= $ad['id'] ?>" style="display:none;background:#fbfbff;">
      <td colspan="9" style="padding:12px 16px;">
        <form class="inline-edit-form" data-id="<?= $ad['id'] ?>">
          <input type="hidden" name="edit_id" value="<?= $ad['id'] ?>">
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;gap:8px;align-items:center;">
              <input type="checkbox" name="is_animated" <?= !empty($ad['is_animated']) ? 'checked' : '' ?>> Enable animation
            </label>
            <label>Type
              <select name="animation_type">
                <option value="fade" <?= $ad['animation_type']==='fade' ? 'selected' : '' ?>>fade</option>
                <option value="slide" <?= $ad['animation_type']==='slide' ? 'selected' : '' ?>>slide</option>
                <option value="bounce" <?= $ad['animation_type']==='bounce' ? 'selected' : '' ?>>bounce</option>
                <option value="pulse" <?= $ad['animation_type']==='pulse' ? 'selected' : '' ?>>pulse</option>
                <option value="zoom" <?= $ad['animation_type']==='zoom' ? 'selected' : '' ?>>zoom</option>
                <option value="flip" <?= $ad['animation_type']==='flip' ? 'selected' : '' ?>>flip</option>
              </select>
            </label>
            <label>Interval (s)
              <input type="number" name="animation_interval" min="4" max="120" value="<?= intval($ad['animation_interval'] ?: 12) ?>" style="width:90px">
            </label>
            <label>Link URL
              <input type="url" name="link_url" value="<?= e($ad['link_url']) ?>" style="min-width:320px">
            </label>
            <button type="submit" class="btn btn-sm btn-primary">Save</button>
            <button type="button" class="btn btn-sm btn-outline cancel-edit">Cancel</button>
          </div>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- ============================================
  COPY THE SECTION BELOW INTO YOUR style.css
  (These are the animation keyframes for the frontend)
  ============================================ -->
<details style="margin-top:30px;background:#f8f9fa;border-radius:8px;padding:12px 16px">
  <summary style="cursor:pointer;font-weight:700;color:#555"><i class="fa fa-code"></i> Copy animation CSS → paste into assets/css/style.css</summary>
  <pre style="font-size:.75rem;overflow:auto;margin-top:10px;background:#1a1a2e;color:#00ff88;padding:16px;border-radius:6px">/* ===== ANIMATED ADS ===== */
@keyframes ad-slide-in  { 0%{transform:translateX(-60px);opacity:0} 100%{transform:translateX(0);opacity:1} }
@keyframes ad-fade-in   { 0%{opacity:0} 100%{opacity:1} }
@keyframes ad-bounce-in { 0%{transform:scale(.6);opacity:0} 60%{transform:scale(1.08);opacity:1} 80%{transform:scale(.96)} 100%{transform:scale(1)} }
@keyframes ad-pulse     { 0%,100%{box-shadow:0 0 0 0 rgba(192,57,43,.5)} 50%{box-shadow:0 0 0 12px rgba(192,57,43,0)} }
@keyframes ad-zoom-in   { 0%{transform:scale(1.18);opacity:0} 100%{transform:scale(1);opacity:1} }
@keyframes ad-flip-in   { 0%{transform:rotateY(90deg);opacity:0} 100%{transform:rotateY(0deg);opacity:1} }
.ad-anim-slide  { animation:ad-slide-in  .6s ease both; }
.ad-anim-fade   { animation:ad-fade-in   .8s ease both; }
.ad-anim-bounce { animation:ad-bounce-in .7s ease both; }
.ad-anim-pulse  { animation:ad-pulse     1.5s infinite; }
.ad-anim-zoom   { animation:ad-zoom-in   .6s ease both; }
.ad-anim-flip   { animation:ad-flip-in   .7s ease both; perspective:600px; }
/* Intersection Observer trigger (add via JS) */
.ad-animate-on-scroll { opacity:0; }
.ad-animate-on-scroll.ad-visible { opacity:1; }</pre>
</details>

</div>

<script>
const animToggle = document.getElementById('animToggle');
const animTypeWrap = document.getElementById('animTypeWrap');
const animPreviewWrap = document.getElementById('animPreviewWrap');
const animTypeSelect = document.getElementById('animTypeSelect');
const previewBox = document.getElementById('animPreviewBox');

const animClasses = {
  fade:   'ad-anim-fade',   slide:  'ad-anim-slide',
  bounce: 'ad-anim-bounce', pulse:  'ad-anim-pulse',
  zoom:   'ad-anim-zoom',   flip:   'ad-anim-flip'
};

animToggle.addEventListener('change', function() {
  animTypeWrap.style.display  = this.checked ? 'block' : 'none';
  animIntervalWrap.style.display = this.checked ? 'block' : 'none';
  animPreviewWrap.style.display = this.checked ? 'block' : 'none';
  if (this.checked) replayPreview();
});

animTypeSelect.addEventListener('change', replayPreview);

function replayPreview() {
  const box = previewBox;
  // Remove all animation classes
  Object.values(animClasses).forEach(c => box.classList.remove(c));
  void box.offsetWidth; // reflow to restart animation
  const type = animTypeSelect.value;
  box.classList.add(animClasses[type] || 'ad-anim-fade');
}
// Inline edit handling for ads table
document.querySelectorAll('.edit-btn').forEach(function(btn) {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    var id = btn.dataset.id;
    var row = document.getElementById('edit-row-' + id);
    if (!row) return;
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
  });
});

document.querySelectorAll('.cancel-edit').forEach(function(btn){
  btn.addEventListener('click', function(){
    var row = btn.closest('tr.edit-row'); if (!row) return; row.style.display='none';
  });
});

document.querySelectorAll('.inline-edit-form').forEach(function(form){
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var data = new FormData(form);
    // send via fetch
    fetch('ads.php', { method: 'POST', body: data }).then(function(resp){
      if (resp.ok) return resp.text(); throw new Error('Network error');
    }).then(function(txt){
      // reload to reflect changes
      location.reload();
    }).catch(function(err){
      alert('Failed to update ad: ' + err.message);
    });
  });
});

</script>

<?php adminFooter(); ?>
