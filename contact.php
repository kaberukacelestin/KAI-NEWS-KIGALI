<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $LANG==='en'?'Contact':'Twandikire' ?> — KAI News Kigali</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container" style="padding:40px 0">
  <h1><?= $LANG==='en'?'Contact Us':($LANG==='fr'?'Contactez-nous':'Twandikire') ?></h1>
  <?php $siteEmail = getSetting('site_email') ?: 'info@kainews.rw'; ?>
  <?php $sitePhone = getSetting('site_phone') ?: '+250 700 000 000'; ?>
  <?php $siteAddress = getSetting('site_address') ?: 'Kigali, Rwanda'; ?>
  <p>
    <?= $LANG==='en'?'Please email us at':($LANG==='fr'?'Veuillez nous envoyer un e-mail à':'Twandikira kuri') ?>
    <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a>
    <?= $LANG==='en'?'or call':($LANG==='fr'?'ou appelez':'cyangwa utubaze kuri') ?>
    <a href="tel:<?= e($sitePhone) ?>"><?= e($sitePhone) ?></a>.
  </p>
  <p><?= e($siteAddress) ?></p>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>