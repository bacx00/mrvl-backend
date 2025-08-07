<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\ImageHelper;

class FixImageUrls extends Command
{
    protected $signature = 'images:fix-urls {--dry-run : Show what would be fixed without making changes} {--type= : Fix specific type: teams, heroes, news, events, all}';
    
    protected $description = 'Fix and validate all image URLs across the system';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $type = $this->option('type') ?? 'all';

        $this->info('Marvel Rivals Image URL Fixer');
        $this->info('==============================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        switch ($type) {
            case 'teams':
                $this->fixTeamLogos($dryRun);
                break;
            case 'heroes':
                $this->fixHeroImages($dryRun);
                break;
            case 'news':
                $this->fixNewsImages($dryRun);
                break;
            case 'events':
                $this->fixEventImages($dryRun);
                break;
            case 'all':
                $this->fixTeamLogos($dryRun);
                $this->fixHeroImages($dryRun);
                $this->fixNewsImages($dryRun);
                $this->fixEventImages($dryRun);
                break;
            default:
                $this->error("Unknown type: {$type}. Use: teams, heroes, news, events, all");
                return;
        }

        $this->info('Image URL fixing completed!');
    }

    private function fixTeamLogos($dryRun = true)
    {
        $this->info('Checking team logos...');
        
        $teams = DB::table('teams')->select('id', 'name', 'logo')->get();
        $fixed = 0;
        $issues = 0;

        foreach ($teams as $team) {
            $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
            
            if (!$logoInfo['exists'] || $logoInfo['url'] !== $team->logo) {
                $issues++;
                $this->warn("Team '{$team->name}': {$team->logo} -> {$logoInfo['url']}");
                
                if (!$dryRun) {
                    DB::table('teams')
                        ->where('id', $team->id)
                        ->update(['logo' => $logoInfo['url']]);
                    $fixed++;
                }
            }
        }

        $this->info("Teams checked: {$teams->count()}, Issues found: {$issues}, Fixed: {$fixed}");
    }

    private function fixHeroImages($dryRun = true)
    {
        $this->info('Checking hero images...');
        
        $heroes = DB::table('marvel_rivals_heroes')->select('id', 'name', 'image_url', 'icon_url')->get();
        $fixed = 0;
        $issues = 0;

        foreach ($heroes as $hero) {
            $portraitInfo = ImageHelper::getHeroImage($hero->name, 'portrait');
            $iconInfo = ImageHelper::getHeroImage($hero->name, 'icon');
            
            $needsUpdate = false;
            $updates = [];

            if ($hero->image_url !== $portraitInfo['url']) {
                $issues++;
                $this->warn("Hero '{$hero->name}' portrait: {$hero->image_url} -> {$portraitInfo['url']}");
                $updates['image_url'] = $portraitInfo['url'];
                $needsUpdate = true;
            }

            if ($hero->icon_url !== $iconInfo['url']) {
                $issues++;
                $this->warn("Hero '{$hero->name}' icon: {$hero->icon_url} -> {$iconInfo['url']}");
                $updates['icon_url'] = $iconInfo['url'];
                $needsUpdate = true;
            }

            if ($needsUpdate && !$dryRun) {
                DB::table('marvel_rivals_heroes')
                    ->where('id', $hero->id)
                    ->update($updates);
                $fixed++;
            }
        }

        $this->info("Heroes checked: {$heroes->count()}, Issues found: {$issues}, Fixed: {$fixed}");
    }

    private function fixNewsImages($dryRun = true)
    {
        $this->info('Checking news images...');
        
        $news = DB::table('news')->select('id', 'title', 'featured_image')->get();
        $fixed = 0;
        $issues = 0;

        foreach ($news as $article) {
            if (!$article->featured_image) continue;

            $imageInfo = ImageHelper::getNewsImage($article->featured_image, $article->title);
            
            if (!$imageInfo['exists'] || $imageInfo['url'] !== $article->featured_image) {
                $issues++;
                $this->warn("News '{$article->title}': {$article->featured_image} -> {$imageInfo['url']}");
                
                if (!$dryRun) {
                    DB::table('news')
                        ->where('id', $article->id)
                        ->update(['featured_image' => $imageInfo['url']]);
                    $fixed++;
                }
            }
        }

        $this->info("News articles checked: {$news->count()}, Issues found: {$issues}, Fixed: {$fixed}");
    }

    private function fixEventImages($dryRun = true)
    {
        $this->info('Checking event images...');
        
        $events = DB::table('events')->select('id', 'name', 'logo', 'banner')->get();
        $fixed = 0;
        $issues = 0;

        foreach ($events as $event) {
            $needsUpdate = false;
            $updates = [];

            if ($event->logo) {
                $logoInfo = ImageHelper::getEventImage($event->logo, $event->name, 'logo');
                
                if (!$logoInfo['exists'] || $logoInfo['url'] !== $event->logo) {
                    $issues++;
                    $this->warn("Event '{$event->name}' logo: {$event->logo} -> {$logoInfo['url']}");
                    $updates['logo'] = $logoInfo['url'];
                    $needsUpdate = true;
                }
            }

            if ($event->banner) {
                $bannerInfo = ImageHelper::getEventImage($event->banner, $event->name, 'banner');
                
                if (!$bannerInfo['exists'] || $bannerInfo['url'] !== $event->banner) {
                    $issues++;
                    $this->warn("Event '{$event->name}' banner: {$event->banner} -> {$bannerInfo['url']}");
                    $updates['banner'] = $bannerInfo['url'];
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate && !$dryRun) {
                DB::table('events')
                    ->where('id', $event->id)
                    ->update($updates);
                $fixed++;
            }
        }

        $this->info("Events checked: {$events->count()}, Issues found: {$issues}, Fixed: {$fixed}");
    }
}