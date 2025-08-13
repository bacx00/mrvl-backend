<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix inconsistent player regions to match Marvel Rivals official tournament structure
     */
    public function up(): void
    {
        // Map all legacy and inconsistent regions to proper Marvel Rivals regions
        $regionMappings = [
            'NA' => 'Americas',
            'US' => 'Americas', 
            'LATAM' => 'Americas',
            'BR' => 'Americas',
            'SA' => 'Americas',
            'EU' => 'EMEA',
            'EMEA' => 'EMEA',  // Already correct
            'APAC' => 'Asia',
            'ASIA' => 'Asia',  // Fix the reported issue - ASIA should map to Asia
            'KR' => 'Asia',
            'JP' => 'Asia',
            'Asia' => 'Asia',  // Already correct
            'AU' => 'Oceania',
            'OCE' => 'Oceania',
            'Oceania' => 'Oceania',  // Already correct
            'CN' => 'China',
            'China' => 'China'  // Already correct
        ];

        // Update each region mapping for players
        foreach ($regionMappings as $oldRegion => $newRegion) {
            DB::table('players')->where('region', $oldRegion)->update(['region' => $newRegion]);
        }

        // Log the changes for audit purposes
        \Log::info('Player regions standardized to Marvel Rivals official structure', [
            'mappings_applied' => $regionMappings,
            'migration' => '2025_08_13_004237_fix_inconsistent_player_regions'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't reverse this migration as it fixes data consistency
        // The original data is preserved in the database logs if needed
        \Log::warning('Attempted to rollback player region standardization migration - no action taken', [
            'migration' => '2025_08_13_004237_fix_inconsistent_player_regions'
        ]);
    }
};