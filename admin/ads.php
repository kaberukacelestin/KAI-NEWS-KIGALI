<?php
require_once 'auth.php';
requirePermission('manage_ads');

$msg = '';

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
      // also remove video if present
      if (!empty($ad['video'])) {
        $v = UPLOAD_PATH . $ad['video'];
        if (file_exists($v)) unlink($v);
      }
      db()->query("DELETE FROM advertisements WHERE id=$did");
    }
    header("Location: ads.php?msg=deleted"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title']       ?? '');
    $link       = trim($_POST['link_url']    ?? '');
    $position   = $_POST['position']         ?? 'banner_top';
    $start      = $_POST['start_date']       ?: null;
    $end        = $_POST['end_date']         ?: null;
    $is_animated = isset($_POST['is_animated']) ? 1 : 0;
    $anim_type  = $_POST['animation_type']   ?? 'fade';

    $fdb = null;
    $fdb_video = null;
    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
      $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png','webp','gif'];
      if (in_array($ext, $allowed)) {
        if (!is_dir(UPLOAD_PATH . 'ads')) mkdir(UPLOAD_PATH . 'ads', 0755, true);
        $fname = 'ad_' . time() . '_' . rand(100,999) . '.' . $ext;
        $fpath = UPLOAD_PATH . 'ads/' . $fname;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $fpath)) {
          $fdb  = 'ads/' . $fname;
        }
      }
    }

    // Handle optional video upload
    if (!empty($_FILES['video']['name'])) {
      $vext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
      $vallowed = ['mp4','webm','ogg','mov'];
      if (in_array($vext, $vallowed)) {
        if (!is_dir(UPLOAD_PATH . 'ads/videos')) mkdir(UPLOAD_PATH . 'ads/videos', 0755, true);
        $vname = 'advid_' . time() . '_' . rand(100,999) . '.' . $vext;
        $vpath = UPLOAD_PATH . 'ads/videos/' . $vname;
        if (move_uploaded_file($_FILES['video']['tmp_name'], $vpath)) {
          $fdb_video = 'ads/videos/' . $vname;
        }
      }
    }

    if ($title && ($fdb || $fdb_video)) {
      // Some installations still have `image` column NOT NULL. Use empty string instead of NULL.
      $fdb_db = $fdb ?: '';
      $fdb_video_db = $fdb_video ?: '';

      $stmt = db()->prepare("INSERT INTO advertisements (title, image, video, link_url, position, start_date, end_date, is_animated, animation_type) VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param("sssssssis", $title, $fdb_db, $fdb_video_db, $link, $position, $start, $end, $is_animated, $anim_type);
      if ($stmt->execute()) {
        $msg = 'Advertisement added successfully!';
      } else {
        $msg = 'Failed to add advertisement: ' . db()->error;
      }
    }
}

$ads = db()->query("SELECT * FROM advertisements ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

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
<?php if ($msg || ($_GET['msg']??'')): ?>
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
        <option value="banner_top">Top Banner (Home)</option>
        <option value="banner_mid">Mid Banner (Home)</option>
        <option value="sidebar">Sidebar</option>
        <option value="footer">Footer</option>
        <option value="popup">Popup</option>
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
      <small style="display:block;color:#6b7280;margin-top:4px;font-size:.85rem;">Optional. If you want a video-only top banner, leave this blank.</small>
    </div>
    <div class="form-group">
      <label>Ad Video (optional) / Video</label>
      <input type="file" name="video" accept="video/*">
      <small style="display:block;color:#6b7280;margin-top:4px;font-size:.85rem;">Upload only a video file. The right 30% banner panel will use this video when you select Banner Top.</small>
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

<hr style="margin:30px 0">

<h3>All Advertisements (<?= count($ads) ?>)</h3>
<table class="admin-table">
  <thead>
    <tr><th>Image</th><th>Title</th><th>Position</th><th>Animation</th><th>Clicks</th><th>Status</th><th>Dates</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($ads as $ad): ?>
    <tr>
      <td>
        <?php if (!empty($ad['video'])): ?>
          <video src="<?= UPLOAD_URL . e($ad['video']) ?>" class="table-thumb" style="max-height:60px;" muted playsinline></video>
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
        <a href="ads.php?delete=<?= $ad['id'] ?>" class="btn-sm del" onclick="return confirm('Delete this ad?')"><i class="fa fa-trash"></i></a>
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
</script>

<?php adminFooter(); ?>
