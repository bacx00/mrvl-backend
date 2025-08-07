<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateHeroImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heroes:update-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update hero image URLs in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting hero image update...');

        // Get all heroes from database
        $heroes = DB::table('marvel_rivals_heroes')->get();
        $updated = 0;

        foreach($heroes as $hero) {
            // Create slug from hero name
            $slug = $this->createHeroSlug($hero->name);
            
            $imageUrl = "/images/heroes/{$slug}-headbig.webp";
            $iconUrl = "/images/heroes/{$slug}-headbig.webp";
            
            // Update the hero with image URLs
            DB::table('marvel_rivals_heroes')
                ->where('id', $hero->id)
                ->update([
                    'image_url' => $imageUrl,
                    'icon_url' => $iconUrl,
                    'updated_at' => now()
                ]);
            
            $this->line("Updated {$hero->name} with image: {$imageUrl}");
            $updated++;
        }

        $this->info("Successfully updated {$updated} heroes with image URLs!");
        return 0;
    }

    private function createHeroSlug($heroName)
    {
        // Convert hero names to URL-friendly slugs matching downloaded files
        $slug = strtolower($heroName);
        
        // Special case for Cloak & Dagger
        if (strpos($slug, 'cloak') !== false && strpos($slug, 'dagger') !== false) {
            return 'cloak-dagger';
        }
        
        $slug = str_replace([' ', '&', '.', "'", '-'], ['-', '-', '', '', '-'], $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
