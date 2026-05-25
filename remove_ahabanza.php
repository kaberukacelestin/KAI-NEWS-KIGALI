<?php
require_once 'includes/config.php';

// Delete the ahabanza articles we just added (last 3 articles)
$result = db()->query("DELETE FROM news WHERE category_id = (SELECT id FROM categories WHERE slug='ahabanza') AND is_headline=1");

if ($result) {
    echo "✓ Ahabanza articles removed from database\n";
} else {
    echo "✗ Error removing articles\n";
}
