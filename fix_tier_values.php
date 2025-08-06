<?php

// Fix tier values in scrapers
$files = [
    'app/Services/EnhancedLiquipediaScraper.php',
    'app/Services/ComprehensiveLiquipediaScraper.php',
    'app/Services/LiquipediaScraper.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Replace A-Tier with A, S-Tier with S, etc.
        $content = str_replace("'A-Tier'", "'A'", $content);
        $content = str_replace("'S-Tier'", "'S'", $content);
        $content = str_replace("'B-Tier'", "'B'", $content);
        $content = str_replace("'C-Tier'", "'C'", $content);
        $content = str_replace('"A-Tier"', '"A"', $content);
        $content = str_replace('"S-Tier"', '"S"', $content);
        $content = str_replace('"B-Tier"', '"B"', $content);
        $content = str_replace('"C-Tier"', '"C"', $content);
        
        file_put_contents($file, $content);
        echo "Fixed tier values in $file\n";
    }
}

echo "Done!\n";