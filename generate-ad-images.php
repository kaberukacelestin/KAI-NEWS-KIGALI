<?php
/**
 * Generate placeholder advertisement images
 * Run this script once to create sample images for the 4-image grid
 * 
 * Command: php generate-ad-images.php
 */

$uploadDir = 'uploads/ads/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Define 4 different colors for the placeholder images
$colors = [
    ['name' => 'ad-placeholder-1.jpg', 'color' => [0, 102, 204]],      // Blue
    ['name' => 'ad-placeholder-2.jpg', 'color' => [204, 0, 0]],        // Red
    ['name' => 'ad-placeholder-3.jpg', 'color' => [0, 153, 76]],       // Green
    ['name' => 'ad-placeholder-4.jpg', 'color' => [255, 140, 0]],      // Orange
];

// Image dimensions
$width = 400;
$height = 200;

foreach ($colors as $image) {
    // Create image
    $img = imagecreatetruecolor($width, $height);
    
    // Allocate colors
    $bgColor = imagecolorallocate($img, $image['color'][0], $image['color'][1], $image['color'][2]);
    $textColor = imagecolorallocate($img, 255, 255, 255);
    $borderColor = imagecolorallocate($img, 255, 255, 255);
    
    // Fill background
    imagefill($img, 0, 0, $bgColor);
    
    // Draw rectangle border
    imagerectangle($img, 5, 5, $width - 5, $height - 5, $borderColor);
    
    // Add text using built-in fonts
    $fontsize = 5; // Built-in font size (1-5)
    imagestring($img, $fontsize, 120, 90, "Advertisement Space", $textColor);
    
    // Save image
    $filepath = $uploadDir . $image['name'];
    imagejpeg($img, $filepath, 85);
    imagedestroy($img);
    
    echo "✓ Created: {$filepath}\n";
}

echo "\n✓ All placeholder images created successfully!\n";
echo "✓ Next step: Import setup-sample-ads.sql into your database\n";
echo "✓ Then refresh your website to see the 4-image grid!\n";
?>
