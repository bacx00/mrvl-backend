<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Updating TeamController for Coach Support ===" . PHP_EOL;

$controllerPath = 'app/Http/Controllers/TeamController.php';
$backupPath = $controllerPath . '.backup';

// Create backup
copy($controllerPath, $backupPath);
echo "✅ Created backup at {$backupPath}" . PHP_EOL;

$content = file_get_contents($controllerPath);

// Add coach fields to index method selection
$oldSelect = "'t.coach', 't.website', 't.earnings', 't.social_media', 't.achievements',";
$newSelect = "'t.coach', 't.coach_name', 't.coach_nationality', 't.coach_social_media', 't.website', 't.earnings', 't.social_media', 't.achievements',";

$content = str_replace($oldSelect, $newSelect, $content);

// Add coach fields to the formatted response in index method
$oldFormat = "'coach' => \$team->coach,";
$newFormat = "'coach' => \$team->coach,
                    'coach_name' => \$team->coach_name,
                    'coach_nationality' => \$team->coach_nationality,
                    'coach_social_media' => \$team->coach_social_media ? json_decode(\$team->coach_social_media, true) : [],";

$content = str_replace($oldFormat, $newFormat, $content);

// Update store method validation
$oldStoreValidation = "'social_links' => 'nullable|array'
        ]);";
$newStoreValidation = "'social_links' => 'nullable|array',
            'coach_name' => 'nullable|string|max:255',
            'coach_nationality' => 'nullable|string|max:255',
            'coach_social_media' => 'nullable|array'
        ]);";

$content = str_replace($oldStoreValidation, $newStoreValidation, $content);

// Update store method insertGetId
$oldStoreInsert = "'social_media' => json_encode(\$request->social_links ?? []),
                'created_at' => now(),
                'updated_at' => now()";
$newStoreInsert = "'social_media' => json_encode(\$request->social_links ?? []),
                'coach_name' => \$request->coach_name,
                'coach_nationality' => \$request->coach_nationality,
                'coach_social_media' => json_encode(\$request->coach_social_media ?? []),
                'created_at' => now(),
                'updated_at' => now()";

$content = str_replace($oldStoreInsert, $newStoreInsert, $content);

// Find and update the update method validation - look for existing coach fields pattern
$updateValidationPattern = "/('twitch_url'\s*=>\s*'nullable\|string\|url',\s*\n\s*'instagram'\s*=>\s*'nullable\|string',)/";
$newUpdateValidation = "$1
                'coach_name' => 'nullable|string|max:255',
                'coach_nationality' => 'nullable|string|max:255', 
                'coach_social_media' => 'nullable|array',";

$content = preg_replace($updateValidationPattern, $newUpdateValidation, $content);

// Find the update array in the update method and add coach fields
$updateArrayPattern = "/(\\$updateData\s*=\s*\\[[\s\S]*?)(\\];)/";
if (preg_match($updateArrayPattern, $content, $matches)) {
    $existingArray = $matches[1];
    $newArray = $existingArray . "\n                
            // Coach fields
            if (isset(\$validated['coach_name'])) \$updateData['coach_name'] = \$validated['coach_name'];
            if (isset(\$validated['coach_nationality'])) \$updateData['coach_nationality'] = \$validated['coach_nationality'];
            if (isset(\$validated['coach_social_media'])) \$updateData['coach_social_media'] = json_encode(\$validated['coach_social_media']);
            
            ";
    $content = str_replace($matches[1] . $matches[2], $newArray . $matches[2], $content);
}

// Write the updated content
file_put_contents($controllerPath, $content);

echo "✅ Updated TeamController with coach field support" . PHP_EOL;
echo "📁 Backup saved as: {$backupPath}" . PHP_EOL;
echo PHP_EOL . "TeamController update completed!" . PHP_EOL;