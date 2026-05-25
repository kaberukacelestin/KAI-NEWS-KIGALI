<footer class="site-footer">
  <div class="footer-top">
    <div class="container footer-grid">
      <div class="footer-col">
        <img src="assets/images/logo.png" alt="KAI News Kigali" class="footer-logo">
        <p><?= e(getSetting('site_tagline') ?: 'Amakuru Ahuza Kigali n\'Isi Yose') ?></p>
        <div class="social-links">
          <?php if ($fb = getSetting('facebook_url')): ?>
          <a href="<?= e($fb) ?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
          <?php endif; ?>
          <?php if ($tw = getSetting('twitter_url')): ?>
          <a href="<?= e($tw) ?>" target="_blank"><i class="fab fa-x-twitter"></i></a>
          <?php endif; ?>
          <?php if ($yt = getSetting('youtube_url')): ?>
          <a href="watch.php?src=youtube&lang=<?= $LANG ?>"><i class="fab fa-youtube"></i></a>
          <?php endif; ?>
          <?php if ($wa = getSetting('whatsapp_number')): ?>
          <a href="https://wa.me/<?= e($wa) ?>" target="_blank"><i class="fab fa-whatsapp"></i></a>
          <?php endif; ?>
        </div>
      </div>
      <div class="footer-col">
        <h4><?= $LANG==='en'?'Categories':($LANG==='fr'?'Catégories':($LANG==='sw'?'Makundi':'Ushobora kunyura hano')) ?></h4>
        <?php foreach (getCategories() as $category): ?>
        <a href="category.php?slug=<?= urlencode($category['slug']) ?>&lang=<?= $LANG ?>"><?= e($category['name_'.$LANG]) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="footer-col">
        <h4><?= $LANG==='en'?'Quick Links':($LANG==='fr'?'Liens rapides':($LANG==='sw'?'Viungo vya Haraka':'Quick Links')) ?></h4>
        <a href="index.php?lang=<?= $LANG ?>"><?= L('home') ?></a>
        <a href="contact.php?lang=<?= $LANG ?>"><?= L('contact') ?></a>
        <a href="advertise.php?lang=<?= $LANG ?>"><?= L('advertise') ?></a>
        <?php if ($radio = getSetting('online_radio_url')): ?>
        <a href="<?= e($radio) ?>" target="_blank"><?= $LANG==='en'?'Online Radio':($LANG==='fr'?'Radio en ligne':($LANG==='sw'?'Redio Mtandaoni':'Radio Internet')) ?></a>
        <?php endif; ?>
        <a href="privacy.php?lang=<?= $LANG ?>"><?= $LANG==='en'?'Privacy Policy':($LANG==='fr'?'Politique de confidentialité':'Privacy') ?></a>
      </div>
      <div class="footer-col">
        <?php $siteEmail = getSetting('site_email') ?: 'kaberuka079@gmail.com'; ?>
        <?php $sitePhone = getSetting('site_phone') ?: '+250 798650610'; ?>
         <?php $sitePhone = getSetting('site_phone') ?: '+250 788751310'; ?>
        <?php $siteAddress = getSetting('site_address') ?: 'Kigali, Rwanda'; ?>
        <h4><?= $LANG==='en'?'Contact Us':($LANG==='fr'?'Contactez-nous':'Twandikire') ?></h4>
        <p><i class="fa fa-envelope"></i> <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a></p>
        <p><i class="fa fa-phone"></i> <a href="tel:<?= e($sitePhone) ?>"><?= e($sitePhone) ?></a></p>
        <p><i class="fa fa-map-marker-alt"></i> <?= e($siteAddress) ?></p>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">
      <p>&copy; <?= date('Y') ?> KAI News Kigali. <?= $LANG==='en'?'All rights reserved.':($LANG==='fr'?'Tous droits réservés.':($LANG==='sw'?'Haki zote zimehifadhiwa.':'All rights reserved.')) ?></p>
    </div>
  </div>
</footer>
