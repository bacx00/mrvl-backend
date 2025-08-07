<?php
/**
 * Create test images for upload testing
 */

echo "🖼️ Creating test images for upload testing...\n\n";

// Create test directory
$testDir = 'storage/app/public/test-images';
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
    echo "✅ Created test directory: $testDir\n";
}

// Create simple test images using GD extension (if available)
if (extension_loaded('gd')) {
    echo "✅ GD extension available, creating test images...\n";
    
    // Test image formats and sizes
    $testImages = [
        'test-logo.png' => ['width' => 200, 'height' => 200, 'type' => 'png'],
        'test-logo.jpg' => ['width' => 200, 'height' => 200, 'type' => 'jpg'],
        'test-avatar.png' => ['width' => 150, 'height' => 150, 'type' => 'png'],
        'test-large.png' => ['width' => 2000, 'height' => 2000, 'type' => 'png'], // Large image test
    ];
    
    foreach ($testImages as $filename => $config) {
        $image = imagecreatetruecolor($config['width'], $config['height']);
        
        // Create a gradient background
        for ($y = 0; $y < $config['height']; $y++) {
            for ($x = 0; $x < $config['width']; $x++) {
                $red = min(255, $x * 255 / $config['width']);
                $green = min(255, $y * 255 / $config['height']);
                $blue = 150;
                $color = imagecolorallocate($image, $red, $green, $blue);
                imagesetpixel($image, $x, $y, $color);
            }
        }
        
        // Add text
        $white = imagecolorallocate($image, 255, 255, 255);
        $text = "TEST\n" . pathinfo($filename, PATHINFO_EXTENSION);
        imagestring($image, 5, 10, 10, $text, $white);
        
        // Save image
        $filepath = $testDir . '/' . $filename;
        if ($config['type'] === 'png') {
            imagepng($image, $filepath);
        } else {
            imagejpeg($image, $filepath, 90);
        }
        
        imagedestroy($image);
        
        // Check file size
        $filesize = filesize($filepath);
        echo "✅ Created: $filename (" . round($filesize / 1024, 2) . " KB)\n";
    }
    
    echo "\n";
} else {
    echo "⚠️ GD extension not available, creating simple text files as test images...\n";
    
    // Create simple text files to simulate images
    $testFiles = [
        'test-logo.txt' => 'Test logo file for upload testing',
        'test-avatar.txt' => 'Test avatar file for upload testing',
    ];
    
    foreach ($testFiles as $filename => $content) {
        $filepath = $testDir . '/' . $filename;
        file_put_contents($filepath, $content);
        echo "✅ Created: $filename\n";
    }
}

// Create a simple SVG test image
$svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
  <rect width="100" height="100" fill="#ff0000"/>
  <text x="10" y="50" fill="white" font-size="12">TEST SVG</text>
</svg>';

file_put_contents($testDir . '/test-logo.svg', $svgContent);
echo "✅ Created: test-logo.svg\n\n";

// Show all created test files
echo "📁 Test files created in $testDir:\n";
$files = scandir($testDir);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        $size = filesize($testDir . '/' . $file);
        echo "   - $file (" . round($size / 1024, 2) . " KB)\n";
    }
}

echo "\n✅ Test images ready for upload testing!\n";
echo "Use these files to test the image upload functionality.\n";