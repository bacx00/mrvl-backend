<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Adding Coach Image Upload Functionality ===" . PHP_EOL;

// Add coach image upload method to TeamController
$controllerPath = 'app/Http/Controllers/TeamController.php';
$content = file_get_contents($controllerPath);

// Check if the method already exists
if (strpos($content, 'uploadCoachImage') !== false) {
    echo "✅ Coach image upload method already exists" . PHP_EOL;
} else {
    // Find the end of the class and add the new method before the closing brace
    $methodToAdd = '
    /**
     * Upload coach image for a team
     */
    public function uploadCoachImage(Request $request, $teamId)
    {
        try {
            $team = DB::table(\'teams\')->where(\'id\', $teamId)->first();
            
            if (!$team) {
                return response()->json([
                    \'success\' => false,
                    \'message\' => \'Team not found\'
                ], 404);
            }

            $request->validate([
                \'coach_image\' => \'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048\'
            ]);

            $image = $request->file(\'coach_image\');
            $imageName = \'coach_\' . time() . \'.\' . $image->extension();
            
            // Store in public/teams/coaches directory
            $image->move(public_path(\'teams/coaches\'), $imageName);
            
            $imagePath = \'/teams/coaches/\' . $imageName;
            
            // Update team with coach image path
            DB::table(\'teams\')->where(\'id\', $teamId)->update([
                \'coach_image\' => $imagePath,
                \'updated_at\' => now()
            ]);

            return response()->json([
                \'success\' => true,
                \'message\' => \'Coach image uploaded successfully\',
                \'data\' => [
                    \'coach_image_url\' => $imagePath
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                \'success\' => false,
                \'message\' => \'Error uploading coach image: \' . $e->getMessage()
            ], 500);
        }
    }
';

    // Find the last closing brace of the class
    $lastBracePos = strrpos($content, '}');
    if ($lastBracePos !== false) {
        $content = substr_replace($content, $methodToAdd . PHP_EOL . '}', $lastBracePos, 1);
        file_put_contents($controllerPath, $content);
        echo "✅ Added coach image upload method to TeamController" . PHP_EOL;
    } else {
        echo "❌ Could not find class closing brace" . PHP_EOL;
    }
}

// Create coaches directory if it doesn't exist
$coachesDir = 'public/teams/coaches';
if (!file_exists($coachesDir)) {
    mkdir($coachesDir, 0755, true);
    echo "✅ Created coaches directory: {$coachesDir}" . PHP_EOL;
} else {
    echo "✅ Coaches directory already exists: {$coachesDir}" . PHP_EOL;
}

// Add route to api.php if not exists
$routesPath = 'routes/api.php';
$routesContent = file_get_contents($routesPath);

if (strpos($routesContent, 'uploadCoachImage') === false) {
    // Add the route after existing team routes
    $newRoute = "\n// Coach image upload\nRoute::post('/teams/{teamId}/coach/upload', [TeamController::class, 'uploadCoachImage']);\n";
    
    // Find a good place to add it (after team routes)
    if (strpos($routesContent, 'TeamController') !== false) {
        // Find last occurrence of TeamController
        $pos = strrpos($routesContent, 'TeamController');
        $endOfLine = strpos($routesContent, "\n", $pos);
        $routesContent = substr_replace($routesContent, $newRoute, $endOfLine, 0);
        file_put_contents($routesPath, $routesContent);
        echo "✅ Added coach image upload route to api.php" . PHP_EOL;
    } else {
        echo "⚠️  Could not find TeamController routes, please add manually:" . PHP_EOL;
        echo "Route::post('/teams/{teamId}/coach/upload', [TeamController::class, 'uploadCoachImage']);" . PHP_EOL;
    }
} else {
    echo "✅ Coach image upload route already exists" . PHP_EOL;
}

echo PHP_EOL . "Coach image upload functionality setup completed!" . PHP_EOL;