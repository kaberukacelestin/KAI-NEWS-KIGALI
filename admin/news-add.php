<?php
require_once 'auth.php';
require_once '../includes/article-references.php';
ensureArticleReferencesColumn();
requirePermission('add_news');

$success = $error = '';
$categories = getCategories();
$referenceFormRows = [['title' => '', 'url' => '']];

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
    } elseif (empty($_FILES['thumbnail']['name'])) {
        $error = "Thumbnail image is required.";
    } else {
        $ext     = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) {
            $error = "Invalid thumbnail format. Use JPG, PNG, or WebP.";
        } else {
            $thumb_name = 'thumb_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $thumb_path = UPLOAD_PATH . 'thumbnails/' . $thumb_name;
            if (!is_dir(UPLOAD_PATH . 'thumbnails')) mkdir(UPLOAD_PATH . 'thumbnails', 0755, true);

            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumb_path)) {
                $thumb_db = 'thumbnails/' . $thumb_name;

                // --- Handle inline images ---
                $inline_images = [];
                if (!empty($_FILES['inline_imgs']['name'][0])) {
                    if (!is_dir(UPLOAD_PATH . 'inline')) mkdir(UPLOAD_PATH . 'inline', 0755, true);
                    $positions = $_POST['inline_positions'] ?? [];
                    $captions_inline = $_POST['inline_captions'] ?? [];
                    foreach ($_FILES['inline_imgs']['tmp_name'] as $i => $tmp) {
                        if ($_FILES['inline_imgs']['error'][$i] === 0) {
                            $iext = strtolower(pathinfo($_FILES['inline_imgs']['name'][$i], PATHINFO_EXTENSION));
                            if (in_array($iext, $allowed)) {
                                $iname = 'inline_' . time() . '_' . $i . '.' . $iext;
                                $ipath = UPLOAD_PATH . 'inline/' . $iname;
                                if (move_uploaded_file($tmp, $ipath)) {
                                    $inline_images[] = [
                                        'url'      => 'inline/' . $iname,
                                        'caption'  => $captions_inline[$i] ?? '',
                                        'after_p'  => intval($positions[$i] ?? 0),
                                        'align'    => $_POST['inline_aligns'][$i] ?? 'center',
                                    ];
                                }
                            }
                        }
                    }
                }
                $inline_json = !empty($inline_images) ? json_encode($inline_images) : null;
                $references_json = !empty($articleReferences) ? json_encode($articleReferences) : null;

                $stmt = db()->prepare("INSERT INTO news
                    (category_id, admin_id, title_rw, description_rw, title_en, description_en,
                     title_sw, description_sw, title_fr, description_fr,
                     thumbnail, inline_images, is_headline, is_featured, status, article_references)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("iissssssssssiiss",
                    $cat_id, $admin_id,
                    $title_rw, $desc_rw, $title_en, $desc_en,
                    $title_sw, $desc_sw, $title_fr, $desc_fr,
                    $thumb_db, $inline_json, $is_headline, $is_featured, $status, $references_json
                );

                if ($stmt->execute()) {
                    $news_id = db()->insert_id;

                    if ($admin_role === 'journalist') {
                        $notifyRecipients = db()->query("SELECT id FROM admins WHERE role='notifier'")->fetch_all(MYSQLI_ASSOC);
                        foreach ($notifyRecipients as $recipient) {
                            createNotification(
                                $recipient['id'],
                                'news_published',
                                'New article added by journalist',
                                'A journalist has submitted a new article. Review it in the dashboard.',
                                $admin_id,
                                $news_id
                            );
                        }
                    }

                    // Gallery images
                    if (!empty($_FILES['gallery']['name'][0])) {
                        if (!is_dir(UPLOAD_PATH . 'gallery')) mkdir(UPLOAD_PATH . 'gallery', 0755, true);
                        $captions = $_POST['gallery_captions'] ?? [];
                        foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                            if ($_FILES['gallery']['error'][$i] === 0) {
                                $gext = strtolower(pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION));
                                if (in_array($gext, $allowed)) {
                                    $gname = 'gallery_' . $news_id . '_' . time() . '_' . $i . '.' . $gext;
                                    $gpath = UPLOAD_PATH . 'gallery/' . $gname;
                                    if (move_uploaded_file($tmp, $gpath)) {
                                        $gdb  = 'gallery/' . $gname;
                                        $cap  = $captions[$i] ?? '';
                                        $ord  = $i;
                                        $gs   = db()->prepare("INSERT INTO news_gallery (news_id, image_path, caption, sort_order) VALUES (?,?,?,?)");
                                        $gs->bind_param("issi", $news_id, $gdb, $cap, $ord);
                                        $gs->execute();
                                    }
                                }
                            }
                        }
                    }
                    $success = "News published successfully! <a href='../article.php?id=$news_id'>View article</a>";
                } else {
                    $error = "Database error: " . db()->error;
                }
            } else {
                $error = "Failed to upload thumbnail.";
            }
        }
    }
}

adminHeader("Add News / Ongera Amakuru");
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

  <!-- RW -->
  <div class="lang-panel active" id="panel-rw">
    <div class="form-group">
      <label>Umutwe w'Inkuru (Kinyarwanda) <span class="required-mark">*</span></label>
      <input type="text" name="title_rw" value="<?= e($_POST['title_rw']??'') ?>" placeholder="Andika umutwe w'inkuru..." required maxlength="500">
    </div>
    <div class="form-group">
      <label>Ibisobanuro (Kinyarwanda) <span class="required-mark">*</span></label>
      <textarea name="description_rw" id="desc_rw" rows="10" placeholder="Andika ibisobanuro bya inkuru..." required><?= e($_POST['description_rw']??'') ?></textarea>
    </div>
  </div>

  <!-- EN -->
  <div class="lang-panel" id="panel-en">
    <div class="form-group">
      <label>Title (English)</label>
      <input type="text" name="title_en" value="<?= e($_POST['title_en']??'') ?>" placeholder="Enter news title in English..." maxlength="500">
    </div>
    <div class="form-group">
      <label>Description (English)</label>
      <textarea name="description_en" id="desc_en" rows="10" placeholder="Write the article in English..."><?= e($_POST['description_en']??'') ?></textarea>
    </div>
  </div>

  <!-- SW -->
  <div class="lang-panel" id="panel-sw">
    <div class="form-group">
      <label>Kichwa (Swahili)</label>
      <input type="text" name="title_sw" value="<?= e($_POST['title_sw']??'') ?>" placeholder="Andika kichwa cha habari..." maxlength="500">
    </div>
    <div class="form-group">
      <label>Maelezo (Swahili)</label>
      <textarea name="description_sw" id="desc_sw" rows="10" placeholder="Andika habari kwa Kiswahili..."><?= e($_POST['description_sw']??'') ?></textarea>
    </div>
  </div>

  <!-- FR -->
  <div class="lang-panel" id="panel-fr">
    <div class="form-group">
      <label>Titre (Français)</label>
      <input type="text" name="title_fr" value="<?= e($_POST['title_fr']??'') ?>" placeholder="Entrez le titre en français..." maxlength="500">
    </div>
    <div class="form-group">
      <label>Description (Français)</label>
      <textarea name="description_fr" id="desc_fr" rows="10" placeholder="Rédigez l'article en français..."><?= e($_POST['description_fr']??'') ?></textarea>
    </div>
  </div>

  <div class="form-group article-references-form">
    <label>References / Related Links <small>(optional)</small></label>
    <p class="form-hint">Add links related to this article, such as a source, YouTube video, or social media post.</p>
    <div class="article-reference-list">
      <?php foreach ($referenceFormRows as $reference): ?>
      <div class="article-reference-row">
        <input type="text" name="reference_titles[]" value="<?= e($reference['title']) ?>" maxlength="200" placeholder="Link title (for example: Original interview)">
        <input type="url" name="reference_urls[]" value="<?= e($reference['url']) ?>" maxlength="2048" placeholder="https://..." inputmode="url">
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
        <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id']==$cat['id'])?'selected':'' ?>>
          <?= e($cat['name_rw']) ?> / <?= e($cat['name_en']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Imiterere / Status</label>
      <select name="status">
        <option value="published" <?= (($_POST['status']??'published')==='published')?'selected':'' ?>>Published</option>
        <option value="draft"     <?= (($_POST['status']??'')==='draft')?'selected':'' ?>>Draft</option>
      </select>
    </div>
  </div>

  <div class="form-row checkboxes">
    <label class="check-label">
      <input type="checkbox" name="is_headline" value="1" <?= isset($_POST['is_headline'])?'checked':'' ?>>
      <span>⭐ Slider Headline</span>
    </label>
    <label class="check-label">
      <input type="checkbox" name="is_featured" value="1" <?= isset($_POST['is_featured'])?'checked':'' ?>>
      <span>📌 Featured Article</span>
    </label>
  </div>

  <!-- Thumbnail -->
  <div class="form-group">
    <label>Thumbnail / Ifoto nkuru <span class="required-mark">*</span></label>
    <div class="upload-zone" id="thumbZone">
      <i class="fa fa-cloud-upload-alt"></i>
      <p>Kanda hano cyangwa ukurure ifoto / Click or drag thumbnail here</p>
      <small>JPG, PNG, WebP — Max 5MB</small>
      <input type="file" name="thumbnail" id="thumbInput" accept="image/*" required>
    </div>
    <div id="thumbPreview" class="img-preview hidden"></div>
  </div>

  <!-- =============================================
       INLINE IMAGES — Photos inside the article body
  ============================================== -->
  <div class="form-group" style="border:2px dashed #3498db;border-radius:10px;padding:20px;background:#f0f8ff">
    <label style="color:#2980b9;font-size:1rem">
      <i class="fa fa-images"></i> Inline Article Photos
      <small style="font-weight:400;color:#666"> — Insert photos between paragraphs to keep readers engaged</small>
    </label>
    <p style="font-size:.82rem;color:#666;margin:4px 0 14px">
      Upload one or more photos, set <em>after which paragraph</em> each should appear, and choose alignment.
      These are rendered inside the article text, not in the gallery.
    </p>

    <div id="inlineImagesContainer">
      <!-- rows added by JS -->
    </div>

    <button type="button" id="addInlineBtn" class="btn-sm edit" style="margin-top:8px">
      <i class="fa fa-plus"></i> Add Inline Photo
    </button>
  </div>

  <!-- Gallery -->
  <div class="form-group">
    <label>Gallery Photos / Amafoto yinshi <small>(optional — bottom gallery)</small></label>
    <div class="upload-zone" id="galleryZone">
      <i class="fa fa-images"></i>
      <p>Hitamo amafoto menshi / Select multiple gallery images</p>
      <small>You can select multiple files at once</small>
      <input type="file" name="gallery[]" id="galleryInput" accept="image/*" multiple>
    </div>
    <div id="galleryPreview" class="gallery-preview-wrap"></div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn-publish">
      <i class="fa fa-paper-plane"></i> Ohereza Inkuru / Publish News
    </button>
    <a href="news-list.php" class="btn-cancel">Reka / Cancel</a>
  </div>

</form>
</div>

<style>
.inline-row { display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #dce3ea; border-radius:8px; padding:12px; margin-bottom:10px; flex-wrap:wrap; }
.inline-row .inline-preview { width:70px; height:55px; object-fit:cover; border-radius:6px; border:1px solid #ccc; display:none; }
.inline-row input[type=file] { flex:1; min-width:150px; }
.inline-row input[type=number] { width:80px; padding:6px 8px; border:1.5px solid #ddd; border-radius:6px; }
.inline-row select { padding:6px 10px; border:1.5px solid #ddd; border-radius:6px; }
.inline-row input[type=text] { flex:2; min-width:150px; padding:6px 10px; border:1.5px solid #ddd; border-radius:6px; }
.inline-row .remove-inline { background:#e74c3c; color:#fff; border:none; border-radius:6px; padding:6px 10px; cursor:pointer; }
</style>

<script>
let inlineCount = 0;

document.getElementById('addInlineBtn').addEventListener('click', function() {
  inlineCount++;
  const wrap = document.getElementById('inlineImagesContainer');
  const row = document.createElement('div');
  row.className = 'inline-row';
  row.innerHTML = `
    <img class="inline-preview" alt="">
    <input type="file" name="inline_imgs[]" accept="image/*" onchange="previewInline(this)">
    <label style="font-size:.8rem;color:#555;white-space:nowrap">After paragraph #</label>
    <input type="number" name="inline_positions[]" value="1" min="0" max="99" title="Insert after paragraph number (0 = before text)">
    <select name="inline_aligns[]" title="Alignment">
      <option value="center">⬛ Center</option>
      <option value="left">◀ Float Left</option>
      <option value="right">▶ Float Right</option>
    </select>
    <input type="text" name="inline_captions[]" placeholder="Caption (optional)">
    <button type="button" class="remove-inline" onclick="this.parentElement.remove()"><i class="fa fa-trash"></i></button>
  `;
  wrap.appendChild(row);
});

function previewInline(input) {
  const img = input.closest('.inline-row').querySelector('.inline-preview');
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
    r.readAsDataURL(input.files[0]);
  }
}

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
