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
        // Fix incorrect hero avatar paths
        DB::table('users')
            ->where('avatar', 'like', '%/portraits/%')
            ->orWhere('avatar', 'like', '%/storage/images/heroes/%')
            ->get()
            ->each(function ($user) {
                $newPath = null;
                
                // Extract hero name from path
                if (preg_match('/\/([\w-]+)\.(png|webp|jpg)/', $user->avatar, $matches)) {
                    $heroSlug = $matches[1];
                    
                    // Try to find the correct webp image
                    $webpPath = "/images/heroes/{$heroSlug}-headbig.webp";
                    if (file_exists(public_path($webpPath))) {
                        $newPath = $webpPath;
                    }
                }
                
                if ($newPath) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['avatar' => $newPath]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible
    }
};
