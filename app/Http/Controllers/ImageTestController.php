<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ImageHelper;

class ImageTestController extends Controller
{
    /**
     * Test all image types and return comprehensive status
     */
    public function testAllImages()
    {
        $results = [
            'heroes' => $this->testHeroImages(),
            'teams' => $this->testTeamImages(),
            'news' => $this->testNewsImages(),
            'events' => $this->testEventImages(),
            'placeholders' => $this->testPlaceholderImages(),
            'summary' => []
        ];

        // Generate summary
        $totalImages = 0;
        $workingImages = 0;
        foreach ($results as $category => $data) {
            if ($category === 'summary') continue;
            
            $categoryTotal = count($data['tested']);
            $categoryWorking = count(array_filter($data['tested'], function($item) {
                return $item['exists'];
            }));
            
            $totalImages += $categoryTotal;
            $workingImages += $categoryWorking;
            
            $results['summary'][$category] = [
                'total' => $categoryTotal,
                'working' => $categoryWorking,
                'percentage' => $categoryTotal > 0 ? round(($categoryWorking / $categoryTotal) * 100, 2) : 0
            ];
        }

        $results['summary']['overall'] = [
            'total' => $totalImages,
            'working' => $workingImages,
            'percentage' => $totalImages > 0 ? round(($workingImages / $totalImages) * 100, 2) : 0
        ];

        return response()->json([
            'data' => $results,
            'success' => true,
            'timestamp' => now()
        ]);
    }

    private function testHeroImages()
    {
        $heroes = ['Spider-Man', 'Iron Man', 'Captain America', 'Thor', 'Hulk', 'Black Widow', 'Hawkeye', 'Doctor Strange', 'Scarlet Witch', 'Wolverine', 'Storm', 'Magneto', 'Loki', 'Venom', 'Groot', 'Rocket Raccoon', 'Star-Lord', 'Mantis', 'Adam Warlock', 'Luna Snow', 'Jeff the Land Shark', 'Cloak & Dagger'];
        
        $tested = [];
        foreach ($heroes as $hero) {
            $portrait = ImageHelper::getHeroImage($hero, 'portrait');
            $icon = ImageHelper::getHeroImage($hero, 'icon');
            $ability1 = ImageHelper::getHeroImage($hero, 'ability_1');
            $ability2 = ImageHelper::getHeroImage($hero, 'ability_2');
            $ultimate = ImageHelper::getHeroImage($hero, 'ultimate');

            $tested[] = [
                'name' => $hero,
                'portrait' => $portrait,
                'icon' => $icon,
                'ability_1' => $ability1,
                'ability_2' => $ability2,
                'ultimate' => $ultimate,
                'exists' => $portrait['exists']
            ];
        }

        return [
            'category' => 'heroes',
            'tested' => $tested,
            'issues' => array_filter($tested, function($item) {
                return !$item['exists'];
            })
        ];
    }

    private function testTeamImages()
    {
        $teamLogos = [
            '100t-logo.png' => '100 Thieves',
            'g2-logo.png' => 'G2 Esports', 
            'sentinels-logo.png' => 'Sentinels',
            'fnatic-logo.png' => 'Fnatic',
            'liquid-logo.png' => 'Team Liquid',
            'cloud9-logo.png' => 'Cloud9'
        ];
        
        $tested = [];
        foreach ($teamLogos as $logo => $name) {
            $logoInfo = ImageHelper::getTeamLogo($logo, $name);
            $tested[] = [
                'name' => $name,
                'logo_path' => $logo,
                'logo_info' => $logoInfo,
                'exists' => $logoInfo['exists']
            ];
        }

        return [
            'category' => 'teams',
            'tested' => $tested,
            'issues' => array_filter($tested, function($item) {
                return !$item['exists'];
            })
        ];
    }

    private function testNewsImages()
    {
        $newsImages = [
            'patch-notes.jpg' => 'Latest Patch Notes',
            'season-format.jpg' => 'Season Format Update',
            'top-plays.jpg' => 'Top Plays of the Week'
        ];
        
        $tested = [];
        foreach ($newsImages as $image => $title) {
            $imageInfo = ImageHelper::getNewsImage($image, $title);
            $tested[] = [
                'title' => $title,
                'image_path' => $image,
                'image_info' => $imageInfo,
                'exists' => $imageInfo['exists']
            ];
        }

        return [
            'category' => 'news',
            'tested' => $tested,
            'issues' => array_filter($tested, function($item) {
                return !$item['exists'];
            })
        ];
    }

    private function testEventImages()
    {
        $eventImages = [
            'mrvl-invitational.jpg' => 'MRVL Invitational',
            'champions-tour-americas.jpg' => 'Champions Tour Americas',
            'mr-global-challenge.jpg' => 'Marvel Rivals Global Challenge'
        ];
        
        $tested = [];
        foreach ($eventImages as $image => $name) {
            $imageInfo = ImageHelper::getEventImage($image, $name, 'banner');
            $tested[] = [
                'name' => $name,
                'image_path' => $image,
                'image_info' => $imageInfo,
                'exists' => $imageInfo['exists']
            ];
        }

        return [
            'category' => 'events',
            'tested' => $tested,
            'issues' => array_filter($tested, function($item) {
                return !$item['exists'];
            })
        ];
    }

    private function testPlaceholderImages()
    {
        $placeholders = [
            'team-placeholder.svg' => 'Team Placeholder',
            'news-placeholder.svg' => 'News Placeholder', 
            'player-placeholder.svg' => 'Player Placeholder',
            'default-placeholder.svg' => 'Default Placeholder',
            'heroes/question-mark.svg' => 'Hero Question Mark',
            'roles/vanguard.png' => 'Vanguard Role',
            'roles/duelist.png' => 'Duelist Role',
            'roles/strategist.png' => 'Strategist Role'
        ];
        
        $tested = [];
        foreach ($placeholders as $path => $name) {
            $exists = file_exists(public_path("/images/{$path}"));
            $tested[] = [
                'name' => $name,
                'path' => "/images/{$path}",
                'exists' => $exists
            ];
        }

        return [
            'category' => 'placeholders',
            'tested' => $tested,
            'issues' => array_filter($tested, function($item) {
                return !$item['exists'];
            })
        ];
    }

    /**
     * Test specific image URL
     */
    public function testImageUrl(Request $request)
    {
        $url = $request->get('url');
        
        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'URL parameter is required'
            ], 400);
        }

        $publicPath = public_path($url);
        $exists = file_exists($publicPath) && is_file($publicPath);
        
        $result = [
            'url' => $url,
            'public_path' => $publicPath,
            'exists' => $exists,
            'is_file' => is_file($publicPath),
            'is_readable' => is_readable($publicPath)
        ];

        if ($exists) {
            $result['file_size'] = filesize($publicPath);
            $result['mime_type'] = mime_content_type($publicPath);
        }

        return response()->json([
            'data' => $result,
            'success' => true
        ]);
    }
}