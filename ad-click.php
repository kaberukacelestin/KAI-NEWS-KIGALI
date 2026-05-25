<?php
require_once 'includes/config.php';
$id = intval($_GET['id'] ?? 0);
if ($id) db()->query("UPDATE advertisements SET clicks=clicks+1 WHERE id=$id");
exit;
?>
