/* Global News — Admin JS */

// ============================================
// LANGUAGE TABS
// ============================================
document.querySelectorAll('.ltab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.ltab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.lang-panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    const panel = document.getElementById('panel-' + tab.dataset.lang);
    if (panel) panel.classList.add('active');
  });
});

// ============================================
// THUMBNAIL PREVIEW
// ============================================
const thumbInput = document.getElementById('thumbInput');
const thumbPreview = document.getElementById('thumbPreview');
const thumbZone = document.getElementById('thumbZone');

if (thumbInput) {
  thumbInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file && file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = e => {
        thumbPreview.innerHTML = '<img src="' + e.target.result + '" style="max-width:100%;max-height:200px;border-radius:8px;margin-top:10px">';
        thumbPreview.classList.remove('hidden');
        thumbZone.style.borderColor = '#c8102e';
      };
      reader.readAsDataURL(file);
    }
  });
}

// Drag & drop thumbnail
if (thumbZone) {
  thumbZone.addEventListener('dragover', e => { e.preventDefault(); thumbZone.style.borderColor = '#c8102e'; });
  thumbZone.addEventListener('dragleave', () => { thumbZone.style.borderColor = ''; });
  thumbZone.addEventListener('drop', e => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
      const dt = new DataTransfer();
      dt.items.add(file);
      thumbInput.files = dt.files;
      thumbInput.dispatchEvent(new Event('change'));
    }
    thumbZone.style.borderColor = '';
  });
}

// ============================================
// GALLERY PREVIEW
// ============================================
const galleryInput = document.getElementById('galleryInput');
const galleryPreview = document.getElementById('galleryPreview');

if (galleryInput) {
  galleryInput.addEventListener('change', function() {
    galleryPreview.innerHTML = '';
    Array.from(this.files).forEach(file => {
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
          const img = document.createElement('img');
          img.src = e.target.result;
          galleryPreview.appendChild(img);
        };
        reader.readAsDataURL(file);
      }
    });
  });
}

// ============================================
// SIDEBAR ACTIVE LINK
// ============================================
const path = window.location.pathname;
document.querySelectorAll('.sidebar-nav a').forEach(a => {
  if (a.href && path.includes(a.getAttribute('href').replace('../', ''))) {
    a.classList.add('active');
  }
});
