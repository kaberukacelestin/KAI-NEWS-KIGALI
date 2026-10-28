<?php
require_once 'auth.php';
require_once '../includes/article-references.php';
ensureArticleReferencesColumn();
requirePermission('edit_news');

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: news-list.php"); exit; }

$article = db()->query("SELECT * FROM news WHERE id=$id")->fetch_assoc();
if (!$article) { header("Location: news-list.php"); exit; }

// SECURITY: Ownership enforcement - journalists can only edit their own articles
if ($admin_role === 'journalist' && $article['admin_id'] != $admin_id) {
    header("Location: news-list.php?err=own");
    exit;
}

$gallery = db()->query("SELECT * FROM news_gallery WHERE news_id=$id ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$categories = getCategories();
$success = $error = '';
$referenceFormRows = decodeArticleReferences($article['article_references'] ?? null);
if (!$referenceFormRows) {
    $referenceFormRows = [['title' => '', 'url' => '']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_id      = intval($_POST['category_id']);
    $title_rw    = trim($_POST['title_rw'] ?? '');
    $desc_rw     = trim($_POST['description_rw'] ?? '');
    $title_en    = trim($_POST['title_en'] ?? '');
    $desc_en     = trim($_POST['description_en'] ?? '');
    $title_sw    = trim($_POST['title_sw'] ?? '');
    $desc_sw     = trim($_POST['description_sw'] ?? '');
    $title_fr    = trim($_POST['title_fr'] ?? '');
    $desc_fr     = trim($_POST['description_fr'] ?? '');
    $is_headline = isset($_POST['is_headline']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status      = $_POST['status'] ?? 'published';
    $referenceTitles = is_array($_POST['reference_titles'] ?? null) ? $_POST['reference_titles'] : [];
    $referenceUrls = is_array($_POST['reference_urls'] ?? null) ? $_POST['reference_urls'] : [];
    $referenceFormRows = [];
    for ($i = 0, $count = max(count($referenceTitles), count($referenceUrls)); $i < $count; $i++) {
        $referenceFormRows[] = [
            'title' => is_scalar($referenceTitles[$i] ?? null) ? (string)$referenceTitles[$i] : '',
            'url' => is_scalar($referenceUrls[$i] ?? null) ? (string)$referenceUrls[$i] : '',
        ];
    }
    if (!$referenceFormRows) {
        $referenceFormRows[] = ['title' => '', 'url' => ''];
    }
    $referenceError = null;
    $articleReferences = validateArticleReferences($referenceTitles, $referenceUrls, $referenceError);

    if (!$title_rw || !$desc_rw || !$cat_id) {
        $error = "Kinyarwanda title and description are required.";
    } elseif ($articleReferences === null) {
        $error = $referenceError;
    } else {
        $thumb_db = $article['thumbnail'];

        // Handle new thumbnail upload
        if (!empty($_FILES['thumbnail']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $allowed)) {
                $thumb_name = 'thumb_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $thumb_path = UPLOAD_PATH . 'thumbnails/' . $thumb_name;
                if (!is_dir(UPLOAD_PATH . 'thumbnails')) mkdir(UPLOAD_PATH . 'thumbnails', 0755, true);
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumb_path)) {
                    // Delete old thumbnail
                    $old = UPLOAD_PATH . $article['thumbnail'];
                    if (file_exists($old)) unlink($old);
                    $thumb_db = 'thumbnails/' . $thumb_name;
                }
            }
        }

        // Handle inline images
        $existing_inline_urls   = $_POST['existing_inline_urls'] ?? [];
        $existing_positions     = $_POST['existing_inline_positions'] ?? [];
        $existing_aligns        = $_POST['existing_inline_aligns'] ?? [];
        $existing_captions      = $_POST['existing_inline_captions'] ?? [];
        $delete_existing_inline = $_POST['delete_inline'] ?? [];

        if (!empty($existing_inline_urls)) {
            $inline_images = [];
            foreach ($existing_inline_urls as $i => $url) {
                if (in_array((string)$i, $delete_existing_inline, true)) {
                    continue;
                }
                $inline_images[] = [
                    'url'     => $url,
                    'caption' => $existing_captions[$i] ?? '',
                    'after_p' => intval($existing_positions[$i] ?? 0),
                    'align'   => in_array($existing_aligns[$i] ?? 'center', ['left','right','center']) ? $existing_aligns[$i] : 'center',
                ];
            }
        } else {
            $inline_images = !empty($article['inline_images']) ? json_decode($article['inline_images'], true) : [];
        }

        if (!empty($_FILES['inline_imgs']['name'][0])) {
            if (!is_dir(UPLOAD_PATH . 'inline')) mkdir(UPLOAD_PATH . 'inline', 0755, true);
            $positions = $_POST['inline_positions'] ?? [];
            $captions_inline = $_POST['inline_captions'] ?? [];
            foreach ($_FILES['inline_imgs']['tmp_name'] as $i => $tmp) {
                if ($_FILES['inline_imgs']['error'][$i] === 0) {
                    $iext = strtolower(pathinfo($_FILES['inline_imgs']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($iext, ['jpg','jpeg','png','webp','gif'])) {
                        $iname = 'inline_' . time() . '_' . $i . '.' . $iext;
                        if (move_uploaded_file($tmp, UPLOAD_PATH . 'inline/' . $iname)) {
                            $inline_images[] = [
                                'url'     => 'inline/' . $iname,
                                'caption' => $captions_inline[$i] ?? '',
                                'after_p' => intval($positions[$i] ?? 0),
                                'align'   => $_POST['inline_aligns'][$i] ?? 'center',
                            ];
                        }
                    }
                }
            }
        }
        $inline_json = !empty($inline_images) ? json_encode($inline_images) : null;
        $references_json = !empty($articleReferences) ? json_encode($articleReferences) : null;

        $stmt = db()->prepare("UPDATE news SET
            category_id=?, title_rw=?, description_rw=?, title_en=?, description_en=?,
            title_sw=?, description_sw=?, title_fr=?, description_fr=?,
            thumbnail=?, inline_images=?, is_headline=?, is_featured=?, status=?, article_references=?
            WHERE id=?");
        $stmt->bind_param("issssssssssiissi",
            $cat_id,
            $title_rw, $desc_rw, $title_en, $desc_en,
            $title_sw, $desc_sw, $title_fr, $desc_fr,
            $thumb_db, $inline_json, $is_headline, $is_featured, $status, $references_json,
            $id
        );

        if ($stmt->execute()) {
            // Handle new gallery images
            if (!empty($_FILES['gallery']['name'][0])) {
                if (!is_dir(UPLOAD_PATH . 'gallery')) mkdir(UPLOAD_PATH . 'gallery', 0755, true);
                $captions = $_POST['gallery_captions'] ?? [];
                $max_order = count($gallery);
                foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['gallery']['error'][$i] === 0) {
                        $gext = strtolower(pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($gext, ['jpg','jpeg','png','webp','gif'])) {
                            $gname = 'gallery_' . $id . '_' . time() . '_' . $i . '.' . $gext;
                            if (move_uploaded_file($tmp, UPLOAD_PATH . 'gallery/' . $gname)) {
                                $gdb = 'gallery/' . $gname;
                                $cap = $captions[$i] ?? '';
                                $ord = $max_order + $i;
                                $gs  = db()->prepare("INSERT INTO news_gallery (news_id, image_path, caption, sort_order) VALUES (?,?,?,?)");
                                $gs->bind_param("issi", $id, $gdb, $cap, $ord);
                                $gs->execute();
                            }
                        }
                    }
                }
            }

            // Delete gallery images if requested
            if (!empty($_POST['delete_gallery'])) {
                foreach ($_POST['delete_gallery'] as $gid) {
                    $gid = intval($gid);
                    $gi = db()->query("SELECT image_path FROM news_gallery WHERE id=$gid AND news_id=$id")->fetch_assoc();
                    if ($gi) {
                        $f = UPLOAD_PATH . $gi['image_path'];
                        if (file_exists($f)) unlink($f);
                        db()->query("DELETE FROM news_gallery WHERE id=$gid");
                    }
                }
            }

            $success = "Article updated successfully! <a href='../article.php?id=$id' target='_blank'>View article</a>";
            
            // Log this activity for audit trail
            logActivity($admin_id, 'edit_article', 'article', $id, json_encode($article), json_encode([
                'title_rw' => $title_rw,
                'category_id' => $cat_id,
                'status' => $status,
                'is_headline' => $is_headline,
                'is_featured' => $is_featured
            ]));
            
            // Reload article and gallery
            $article = db()->query("SELECT * FROM news WHERE id=$id")->fetch_assoc();
            $gallery = db()->query("SELECT * FROM news_gallery WHERE news_id=$id ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = "Database error: " . db()->error;
        }
    }
}

adminHeader("Edit News / Hindura Inkuru");
?>

<div class="admin-form-page">
<?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="newsForm">

  <!-- Language Tabs -->
  <div class="lang-tabs">
    <button type="button" class="ltab active" data-lang="rw">🇷🇼 Kinyarwanda <span class="required-mark">*</span></button>
    <button type="button" class="ltab" data-lang="en">🇬🇧 English</button>
    <button type="button" class="ltab" data-lang="sw">🇹🇿 Swahili</button>
    <button type="button" class="ltab" data-lang="fr">🇫🇷 Français</button>
  </div>

  <div class="lang-panel active" id="panel-rw">
    <div class="form-group">
      <label>Umutwe w'Inkuru (Kinyarwanda) <span class="required-mark">*</span></label>
      <input type="text" name="title_rw" value="<?= e($article['title_rw']) ?>" required maxlength="500">
    </div>
    <div class="form-group">
      <label>Ibisobanuro (Kinyarwanda) <span class="required-mark">*</span></label>
      <textarea name="description_rw" rows="10" required><?= e($article['description_rw']) ?></textarea>
    </div>
  </div>

  <div class="lang-panel" id="panel-en">
    <div class="form-group">
      <label>Title (English)</label>
      <input type="text" name="title_en" value="<?= e($article['title_en']) ?>" maxlength="500">
    </div>
    <div class="form-group">
      <label>Description (English)</label>
      <textarea name="description_en" rows="10"><?= e($article['description_en']) ?></textarea>
    </div>
  </div>

  <div class="lang-panel" id="panel-sw">
    <div class="form-group">
      <label>Kichwa (Swahili)</label>
      <input type="text" name="title_sw" value="<?= e($article['title_sw']) ?>" maxlength="500">
    </div>
    <div class="form-group">
      <label>Maelezo (Swahili)</label>
      <textarea name="description_sw" rows="10"><?= e($article['description_sw']) ?></textarea>
    </div>
  </div>

  <div class="lang-panel" id="panel-fr">
    <div class="form-group">
      <label>Titre (Français)</label>
      <input type="text" name="title_fr" value="<?= e($article['title_fr']) ?>" maxlength="500">
    </div>
    <div class="form-group">
      <label>Description (Français)</label>
      <textarea name="description_fr" rows="10"><?= e($article['description_fr']) ?></textarea>
    </div>
  </div>

  <div class="form-group article-references-form">
    <label>References / Related Links <small>(optional)</small></label>
    <p class="form-hint">Add links related to this article, such as a source, YouTube video, or social media post.</p>
    <div class="article-reference-list">
      <?php foreach ($referenceFormRows as $reference): ?>
      <div class="article-reference-row">
        <input type="text" name="reference_titles[]" value="<?= e($reference['title'] ?? '') ?>" maxlength="200" placeholder="Link title (for example: Original interview)">
        <input type="url" name="reference_urls[]" value="<?= e($reference['url'] ?? '') ?>" maxlength="2048" placeholder="https://..." inputmode="url">
        <button type="button" class="article-reference-remove" aria-label="Remove link"><i class="fa fa-trash" aria-hidden="true"></i></button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm edit article-reference-add"><i class="fa fa-plus"></i> Add link</button>
    <template class="article-reference-template">
      <div class="article-reference-row">
        <input type="text" name="reference_titles[]" maxlength="200" placeholder="Link title (for example: Original interview)">
        <input type="url" name="reference_urls[]" maxlength="2048" placeholder="https://..." inputmode="url">
        <button type="button" class="article-reference-remove" aria-label="Remove link"><i class="fa fa-trash" aria-hidden="true"></i></button>
      </div>
    </template>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Icyiciro / Category <span class="required-mark">*</span></label>
      <select name="category_id" required>
        <option value="">-- Hitamo icyiciro --</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $article['category_id']==$cat['id']?'selected':'' ?>>
          <?= e($cat['name_rw']) ?> / <?= e($cat['name_en']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Imiterere / Status</label>
      <select name="status">
        <option value="published" <?= $article['status']==='published'?'selected':'' ?>>Published</option>
        <option value="draft"     <?= $article['status']==='draft'?'selected':'' ?>>Draft</option>
      </select>
    </div>
  </div>

  <div class="form-row checkboxes">
    <label class="check-label">
      <input type="checkbox" name="is_headline" value="1" <?= $article['is_headline']?'checked':'' ?>>
      <span>⭐ Slider Headline</span>
    </label>
    <label class="check-label">
      <input type="checkbox" name="is_featured" value="1" <?= $article['is_featured']?'checked':'' ?>>
      <span>📌 Featured Article</span>
    </label>
  </div>

  <!-- Current Thumbnail -->
  <div class="form-group">
    <label>Current Thumbnail</label>
    <img src="<?= e(newsThumbnail($article['thumbnail'])) ?>" style="max-height:160px;border-radius:8px;margin-bottom:10px;display:block;">
    <label>Replace Thumbnail (leave empty to keep current)</label>
    <div class="upload-zone" id="thumbZone">
      <i class="fa fa-cloud-upload-alt"></i>
      <p>Click or drag new thumbnail here</p>
      <small>JPG, PNG, WebP — Max 5MB</small>
      <input type="file" name="thumbnail" id="thumbInput" accept="image/*">
    </div>
    <div id="thumbPreview" class="img-preview hidden"></div>
  </div>

    <?php if (!empty($article['inline_images'])):
      $existingInline = json_decode($article['inline_images'], true);
    ?>
    <div class="form-group">
      <label>Current Inline Photos</label>
      <div class="inline-existing-list">
        <?php foreach ($existingInline as $index => $img): ?>
        <div class="inline-existing-card">
          <img src="<?= UPLOAD_URL . e($img['url']) ?>" alt="<?= e($img['caption'] ?? 'Inline image') ?>">
          <div class="inline-existing-meta">
            <p><strong>After paragraph:</strong> <?= intval($img['after_p'] ?? 0) ?></p>
            <p><strong>Alignment:</strong> <?= e($img['align'] ?? 'center') ?></p>
            <p><strong>Caption:</strong> <?= e($img['caption'] ?: '—') ?></p>
            <label>
              <input type="checkbox" name="delete_inline[]" value="<?= $index ?>"> Delete this inline photo
            </label>
            <input type="hidden" name="existing_inline_urls[]" value="<?= e($img['url']) ?>">
            <input type="hidden" name="existing_inline_positions[]" value="<?= intval($img['after_p'] ?? 0) ?>">
            <input type="hidden" name="existing_inline_aligns[]" value="<?= e($img['align'] ?? 'center') ?>">
            <input type="hidden" name="existing_inline_captions[]" value="<?= e($img['caption'] ?? '') ?>">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($gallery)): ?>
    <div class="form-group">
      <label>Current Gallery Photos (check to delete)</label>
      <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px">
        <?php foreach ($gallery as $g): ?>
        <div style="position:relative;text-align:center">
          <img src="<?= UPLOAD_URL . e($g['image_path']) ?>" style="width:100px;height:75px;object-fit:cover;border-radius:6px;display:block">
          <label style="font-size:11px;color:#e74c3c;margin-top:4px;cursor:pointer">
            <input type="checkbox" name="delete_gallery[]" value="<?= $g['id'] ?>"> Delete
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <!-- Add More Gallery -->
  <div class="form-group">
    <label>Add More Gallery Photos <small>(optional)</small></label>
    <div class="upload-zone" id="galleryZone">
      <i class="fa fa-images"></i>
      <p>Select additional gallery images</p>
      <small>You can select multiple files at once</small>
      <input type="file" name="gallery[]" id="galleryInput" accept="image/*" multiple>
    </div>
    <div id="galleryPreview" class="gallery-preview-wrap"></div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn-publish">
      <i class="fa fa-save"></i> Bika Impinduka / Save Changes
    </button>
    <a href="news-list.php" class="btn-cancel">Cancel</a>
  </div>

</form>
</div>

<style>
.inline-row { display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #dce3ea; border-radius:8px; padding:12px; margin-bottom:10px; flex-wrap:wrap; }
.inline-row input[type=file] { flex:1; min-width:150px; }
.inline-row input[type=number] { width:80px; padding:6px 8px; border:1.5px solid #ddd; border-radius:6px; }
.inline-row select { padding:6px 10px; border:1.5px solid #ddd; border-radius:6px; }
.inline-row input[type=text] { flex:2; min-width:150px; padding:6px 10px; border:1.5px solid #ddd; border-radius:6px; }
.inline-row .remove-inline { background:#e74c3c; color:#fff; border:none; border-radius:6px; padding:6px 10px; cursor:pointer; }
.inline-existing-list { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:16px; }
.inline-existing-card { background:#fff; border:1px solid #dce3ea; border-radius:10px; overflow:hidden; }
.inline-existing-card img { width:100%; height:160px; object-fit:cover; display:block; }
.inline-existing-meta { padding:12px; }
.inline-existing-meta p { margin:0 0 8px; font-size:.92rem; color:#333; }
.inline-existing-meta label { display:block; margin-top:8px; font-size:.92rem; }
</style>

<script>
let inlineCount = 0;
document.getElementById('addInlineBtn').addEventListener('click', function() {
  inlineCount++;
  const wrap = document.getElementById('inlineImagesContainer');
  const row = document.createElement('div');
  row.className = 'inline-row';
  row.innerHTML = `
    <input type="file" name="inline_imgs[]" accept="image/*">
    <label style="font-size:.8rem;color:#555;white-space:nowrap">After paragraph #</label>
    <input type="number" name="inline_positions[]" value="1" min="0" max="99">
    <select name="inline_aligns[]">
      <option value="center">⬛ Center</option>
      <option value="left">◀ Float Left</option>
      <option value="right">▶ Float Right</option>
    </select>
    <input type="text" name="inline_captions[]" placeholder="Caption (optional)">
    <button type="button" class="remove-inline" onclick="this.parentElement.remove()"><i class="fa fa-trash"></i></button>
  `;
  wrap.appendChild(row);
});

function renderPreviewImage(file, container) {
  container.innerHTML = '';
  const img = document.createElement('img');
  img.src = URL.createObjectURL(file);
  img.style.maxWidth = '100%';
  img.style.borderRadius = '10px';
  img.style.boxShadow = '0 4px 16px rgba(0,0,0,0.12)';
  img.onload = () => URL.revokeObjectURL(img.src);
  container.appendChild(img);
  container.classList.remove('hidden');
}

document.getElementById('thumbInput').addEventListener('change', function() {
  if (this.files && this.files[0]) {
    renderPreviewImage(this.files[0], document.getElementById('thumbPreview'));
  }
});

document.getElementById('galleryInput').addEventListener('change', function() {
  const preview = document.getElementById('galleryPreview');
  preview.innerHTML = '';
  if (this.files) {
    Array.from(this.files).forEach(file => {
      const thumb = document.createElement('img');
      thumb.src = URL.createObjectURL(file);
      thumb.style.width = '120px';
      thumb.style.height = '90px';
      thumb.style.objectFit = 'cover';
      thumb.style.borderRadius = '8px';
      thumb.style.margin = '6px';
      thumb.onload = () => URL.revokeObjectURL(thumb.src);
      preview.appendChild(thumb);
    });
  }
});
</script>
<script src="../assets/js/admin.js"></script>
<script src="../assets/js/article-references.js"></script>
<?php adminFooter(); ?>
