<?php
$categories = getCategories();
$current_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<header class="site-header">
  <!-- Top Bar -->
  <div class="top-bar">
    <div class="container top-bar-inner">
      <div class="top-bar-left">
        <span><i class="fa fa-calendar"></i> <?= date('l, d F Y') ?></span>
        <span><i class="fa fa-map-marker-alt"></i> Kigali, Rwanda</span>
      </div>
      <div class="top-bar-right">
        <div class="header-actions">
          <?php if (isset($_SESSION['admin_id'])): ?>
            <a href="admin/dashboard.php" class="btn btn-primary">Dashboard</a>
            <a href="admin/logout.php" class="btn btn-outline">Logout</a>
          <?php else: ?>
            <a href="admin/login.php" class="btn btn-primary">Login</a>
          <?php endif; ?>
        </div>
        <div class="social-links top-social-links">
          <?php if ($yt = getSetting('youtube_url')): ?>
          <a href="watch.php?src=youtube&lang=<?= $LANG ?>" title="YouTube"><i class="fab fa-youtube"></i></a>
          <?php endif; ?>
          <?php if ($fb = getSetting('facebook_url')): ?>
          <a href="<?= e($fb) ?>" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
          <?php endif; ?>
          <?php if ($tw = getSetting('twitter_url')): ?>
          <a href="<?= e($tw) ?>" target="_blank" title="Twitter"><i class="fab fa-x-twitter"></i></a>
          <?php endif; ?>
          <?php if ($ig = getSetting('instagram_url')): ?>
          <a href="<?= e($ig) ?>" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
          <?php endif; ?>
        </div>
        <div class="lang-switcher">
          <?php
          $langs = ['rw'=>'🇷🇼 Kinyarwanda','en'=>'🇬🇧 English','sw'=>'🇹🇿 Swahili','fr'=>'🇫🇷 Français'];
          foreach ($langs as $code => $label):
            $active = ($LANG === $code) ? 'active' : '';
            $url    = strtok($current_url, '?');
            $params = $_GET;
            $params['lang'] = $code;
            $query  = http_build_query($params);
          ?>
          <a href="<?= $url ?>?<?= $query ?>" class="lang-btn <?= $active ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Logo + Nav -->
  <div class="header-main">
    <div class="container header-inner" style="position:relative;">
      <a href="index.php?lang=<?= $LANG ?>" class="site-logo">
        <img src="assets/images/logo.png" alt="KAI News Kigali">
      </a>
      <nav class="main-nav" id="mainNav">
        <a href="index.php?lang=<?= $LANG ?>" class="nav-link"><?= L('home') ?></a>
        <?php foreach ($categories as $category): ?>
          <?php if ($category['slug'] === 'ahabanza') continue; ?>
        <a href="category.php?slug=<?= urlencode($category['slug']) ?>&lang=<?= $LANG ?>" class="nav-link">
          <?= e($category['name_'.$LANG]) ?>
        </a>
        <?php endforeach; ?>
        <a href="contact.php?lang=<?= $LANG ?>" class="nav-link"><?= L('contact') ?></a>
        <a href="advertise.php?lang=<?= $LANG ?>" class="nav-link nav-advertise"><?= L('advertise') ?></a>
      </nav>
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <!-- KAI Brand Accent Bar -->
  <div class="brand-accent-bar"></div>
</header>
