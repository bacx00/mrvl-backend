<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    Schema::table('tournaments', function (Blueprint $table) {
        // Add missing columns if they don't exist
        if (!Schema::hasColumn('tournaments', 'currency')) {
            $table->string('currency', 10)->default('USD')->after('region');
        }
        if (!Schema::hasColumn('tournaments', 'timezone')) {
            $table->string('timezone', 50)->default('UTC')->after('currency');
        }
        if (!Schema::hasColumn('tournaments', 'max_teams')) {
            $table->integer('max_teams')->nullable()->after('timezone');
        }
        if (!Schema::hasColumn('tournaments', 'min_teams')) {
            $table->integer('min_teams')->nullable()->after('max_teams');
        }
        if (!Schema::hasColumn('tournaments', 'featured')) {
            $table->boolean('featured')->default(false)->after('min_teams');
        }
        if (!Schema::hasColumn('tournaments', 'public')) {
            $table->boolean('public')->default(true)->after('featured');
        }
        if (!Schema::hasColumn('tournaments', 'views')) {
            $table->integer('views')->default(0)->after('public');
        }
        if (!Schema::hasColumn('tournaments', 'current_phase')) {
            $table->string('current_phase')->nullable()->after('views');
        }
        if (!Schema::hasColumn('tournaments', 'qualification_settings')) {
            $table->json('qualification_settings')->nullable()->after('rules');
        }
        if (!Schema::hasColumn('tournaments', 'match_format_settings')) {
            $table->json('match_format_settings')->nullable()->after('qualification_settings');
        }
        if (!Schema::hasColumn('tournaments', 'map_pool')) {
            $table->json('map_pool')->nullable()->after('match_format_settings');
        }
    });

    echo "✅ Tournament columns added successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}