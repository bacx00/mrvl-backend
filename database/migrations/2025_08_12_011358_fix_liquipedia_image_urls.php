<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove ALL Liquipedia URLs from teams.logo field
        // Replace with NULL to trigger placeholder fallback system
        DB::table('teams')
            ->where('logo', 'like', '%liquipedia.net%')
            ->update(['logo' => null]);
            
        // Also remove any other external URLs that might cause CORS issues
        DB::table('teams')
            ->whereRaw('logo REGEXP "^https?://"')
            ->whereRaw('logo NOT LIKE "%' . config('app.url', 'localhost') . '%"')
            ->update(['logo' => null]);
            
        echo "Fixed " . DB::table('teams')->whereNull('logo')->count() . " team logos to use fallback system\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we're removing invalid URLs
        // Teams that need logos should have them uploaded to local storage
        echo "This migration cannot be reversed - team logos should be uploaded to local storage\n";
    }
};
