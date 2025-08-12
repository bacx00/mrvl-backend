<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class ImageHelper 
{
    /**
     * Get a team logo URL with fallback support
     * 
     * @param string $logoPath The logo path from database
     * @param string $teamName The team name for fallback
     * @return array Array with url, exists, and fallback info
     */
    public static function getTeamLogo($logoPath, $teamName = null)
    {
        // CRITICAL FIX: Block ALL external URLs including Liquipedia
        if (!$logoPath || self::isExternalUrl($logoPath)) {
            return [
                'url' => '/images/team-placeholder.svg',
                'exists' => false,
                'fallback' => [
                    'text' => $teamName ? substr($teamName, 0, 3) : '?',
                    'color' => self::generateColorFromText($teamName ?? 'team'),
                    'type' => 'team-logo'
                ]
            ];
        }

        // Clean up logo path - remove leading slashes and normalize
        $logoPath = ltrim($logoPath, '/');
        $baseName = pathinfo($logoPath, PATHINFO_FILENAME);
        $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
        
        // Priority order for extensions (SVG preferred, then original extension)
        $extensions = [];
        if ($extension) {
            $extensions[] = $extension; // Try original extension first
        }
        // Always try SVG as it's commonly used
        if (!in_array('svg', $extensions)) {
            $extensions[] = 'svg';
        }
        // Then try other common formats
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            if (!in_array($ext, $extensions)) {
                $extensions[] = $ext;
            }
        }
        
        // Generate filename variations to handle naming convention mismatches
        $filenameVariations = self::generateTeamLogoFilenameVariations($baseName, $teamName);
        
        // Priority order for team logo paths
        $possiblePaths = [];
        
        // 1. First priority: /storage/teams/logos/ (Laravel storage symlink)
        foreach ($filenameVariations as $baseNameVariation) {
            foreach ($extensions as $ext) {
                $possiblePaths[] = "/storage/teams/logos/{$baseNameVariation}.{$ext}";
            }
        }
        
        // 2. Second priority: direct /teams/ path
        foreach ($filenameVariations as $baseNameVariation) {
            foreach ($extensions as $ext) {
                $possiblePaths[] = "/teams/{$baseNameVariation}.{$ext}";
            }
        }
        
        // 3. Third priority: /storage/ with original path
        $possiblePaths[] = "/storage/{$logoPath}";
        
        // 4. Last resort: direct path
        $possiblePaths[] = "/{$logoPath}";

        // Check each path and return the first one that exists
        foreach ($possiblePaths as $path) {
            $publicPath = public_path($path);
            
            // For storage paths, also check the actual storage directory
            if (strpos($path, '/storage/') === 0) {
                $storagePath = str_replace('/storage/', '', $path);
                $actualStoragePath = storage_path("app/public/{$storagePath}");
                
                if (file_exists($actualStoragePath) && is_file($actualStoragePath)) {
                    return [
                        'url' => $path,
                        'exists' => true,
                        'fallback' => [
                            'text' => $teamName ?? '?',
                            'color' => self::generateColorFromText($teamName ?? 'team'),
                            'type' => 'team-logo'
                        ]
                    ];
                }
            }
            
            // Check public path for direct access
            if (file_exists($publicPath) && is_file($publicPath)) {
                return [
                    'url' => $path,
                    'exists' => true,
                    'fallback' => [
                        'text' => $teamName ?? '?',
                        'color' => self::generateColorFromText($teamName ?? 'team'),
                        'type' => 'team-logo'
                    ]
                ];
            }
        }

        // Return placeholder if no image found
        return [
            'url' => '/images/team-placeholder.svg',
            'exists' => true,
            'fallback' => [
                'text' => $teamName ? substr($teamName, 0, 3) : '?',
                'color' => self::generateColorFromText($teamName ?? 'team'),
                'type' => 'team-logo',
                'original_path' => $logoPath,
                'variations_tried' => $filenameVariations ?? []
            ]
        ];
    }

    /**
     * Get a hero image URL with fallback support
     * 
     * @param string $heroName The hero name
     * @param string $type The image type (portrait, icon, etc.)
     * @return array Array with url, exists, and fallback info
     */
    public static function getHeroImage($heroName, $type = 'portrait')
    {
        $slugVariations = self::getHeroSlugVariations($heroName);
        
        $imagePaths = [];
        switch ($type) {
            case 'portrait':
            case 'icon':
                // Check for webp images first with all slug variations
                foreach ($slugVariations as $slug) {
                    $imagePaths[] = "/images/heroes/{$slug}-headbig.webp";
                }
                // Fallback to PNG portraits/icons
                foreach ($slugVariations as $slug) {
                    $imagePaths[] = "/images/heroes/portraits/{$slug}.png";
                    $imagePaths[] = "/images/heroes/icons/{$slug}.png";
                    $imagePaths[] = "/images/heroes/{$slug}.png";
                }
                break;
            case 'ability_1':
                foreach ($slugVariations as $slug) {
                    $imagePaths[] = "/images/heroes/abilities/{$slug}_ability_1.png";
                }
                $imagePaths[] = "/images/heroes/abilities/default_ability.png";
                break;
            case 'ability_2':
                foreach ($slugVariations as $slug) {
                    $imagePaths[] = "/images/heroes/abilities/{$slug}_ability_2.png";
                }
                $imagePaths[] = "/images/heroes/abilities/default_ability.png";
                break;
            case 'ultimate':
                foreach ($slugVariations as $slug) {
                    $imagePaths[] = "/images/heroes/abilities/{$slug}_ultimate.png";
                }
                $imagePaths[] = "/images/heroes/abilities/default_ultimate.png";
                break;
            default:
                foreach ($slugVariations as $slug) {
                    $imagePaths[] = "/images/heroes/{$slug}-headbig.webp";
                    $imagePaths[] = "/images/heroes/{$slug}.png";
                }
        }

        // Add question mark fallback for portrait/icon types
        if (in_array($type, ['portrait', 'icon'])) {
            $imagePaths[] = "/images/heroes/question-mark.svg";
        }

        foreach ($imagePaths as $imagePath) {
            $publicPath = public_path($imagePath);
            if (file_exists($publicPath) && is_file($publicPath)) {
                return [
                    'url' => $imagePath,
                    'exists' => true,
                    'fallback' => [
                        'text' => $heroName,
                        'color' => self::getHeroColor($heroName),
                        'type' => $type,
                        'role_color' => self::getRoleColorForHero($heroName)
                    ]
                ];
            }
        }

        // No image found, return fallback with question mark for portraits
        $fallbackUrl = in_array($type, ['portrait', 'icon']) ? '/images/heroes/question-mark.svg' : null;
        
        return [
            'url' => $fallbackUrl,
            'exists' => $fallbackUrl !== null,
            'fallback' => [
                'text' => $heroName,
                'color' => self::getHeroColor($heroName),
                'type' => $type,
                'role_color' => self::getRoleColorForHero($heroName)
            ]
        ];
    }

    /**
     * Get a player avatar with fallback support
     * 
     * @param string $avatarPath The avatar path from database
     * @param string $playerName The player name for fallback
     * @return array Array with url, exists, and fallback info
     */
    public static function getPlayerAvatar($avatarPath, $playerName = null)
    {
        // CRITICAL FIX: Block ALL external URLs including Liquipedia
        if (!$avatarPath || self::isExternalUrl($avatarPath)) {
            return [
                'url' => '/images/player-placeholder.svg',
                'exists' => false,
                'fallback' => [
                    'text' => $playerName ? substr($playerName, 0, 2) : '?',
                    'color' => self::generateColorFromText($playerName ?? 'player'),
                    'type' => 'player-avatar'
                ]
            ];
        }

        // Clean up avatar path - remove leading slashes and normalize
        $avatarPath = ltrim($avatarPath, '/');
        $baseName = pathinfo($avatarPath, PATHINFO_FILENAME);
        $extension = pathinfo($avatarPath, PATHINFO_EXTENSION);
        $extensions = $extension ? [$extension] : ['svg', 'png', 'jpg', 'jpeg', 'webp'];
        
        // Priority order for player avatar paths
        $possiblePaths = [];
        
        // 1. First priority: /storage/players/avatars/ (Laravel storage symlink)
        foreach ($extensions as $ext) {
            $filename = $extension ? $avatarPath : "{$baseName}.{$ext}";
            $possiblePaths[] = "/storage/players/avatars/{$filename}";
        }
        
        // 2. Second priority: direct /images/players/ path
        foreach ($extensions as $ext) {
            $filename = $extension ? $avatarPath : "{$baseName}.{$ext}";
            $possiblePaths[] = "/images/players/{$filename}";
        }
        
        // 3. Third priority: /storage/ with original path
        $possiblePaths[] = "/storage/{$avatarPath}";
        
        // 4. Last resort: direct path
        $possiblePaths[] = "/{$avatarPath}";

        // Check each path and return the first one that exists
        foreach ($possiblePaths as $path) {
            $publicPath = public_path($path);
            
            // For storage paths, also check the actual storage directory
            if (strpos($path, '/storage/') === 0) {
                $storagePath = str_replace('/storage/', '', $path);
                $actualStoragePath = storage_path("app/public/{$storagePath}");
                
                if (file_exists($actualStoragePath) && is_file($actualStoragePath)) {
                    return [
                        'url' => $path,
                        'exists' => true,
                        'fallback' => [
                            'text' => $playerName ? substr($playerName, 0, 2) : '?',
                            'color' => self::generateColorFromText($playerName ?? 'player'),
                            'type' => 'player-avatar'
                        ]
                    ];
                }
            }
            
            // Check public path for direct access
            if (file_exists($publicPath) && is_file($publicPath)) {
                return [
                    'url' => $path,
                    'exists' => true,
                    'fallback' => [
                        'text' => $playerName ? substr($playerName, 0, 2) : '?',
                        'color' => self::generateColorFromText($playerName ?? 'player'),
                        'type' => 'player-avatar'
                    ]
                ];
            }
        }

        // Return placeholder if no image found
        return [
            'url' => '/images/player-placeholder.svg',
            'exists' => true,
            'fallback' => [
                'text' => $playerName ? substr($playerName, 0, 2) : '?',
                'color' => self::generateColorFromText($playerName ?? 'player'),
                'type' => 'player-avatar',
                'original_path' => $avatarPath
            ]
        ];
    }

    /**
     * Get a news featured image with fallback support
     * 
     * @param string $imagePath The image path from database
     * @param string $title The news title for fallback
     * @return array Array with url, exists, and fallback info
     */
    public static function getNewsImage($imagePath, $title = null)
    {
        if (!$imagePath) {
            return [
                'url' => '/images/news-placeholder.svg',
                'exists' => true,
                'fallback' => [
                    'text' => $title ?? 'News',
                    'color' => '#6b7280',
                    'type' => 'news-image'
                ]
            ];
        }

        // Check multiple possible paths with extensions
        $extensions = ['', '.jpg', '.jpeg', '.png', '.webp', '.svg'];
        $baseName = pathinfo($imagePath, PATHINFO_FILENAME);
        
        $possiblePaths = [
            "/storage/{$imagePath}",
            "/storage/news/featured/{$imagePath}",
            "/images/news/{$imagePath}",
            "/news/{$imagePath}",
            "/{$imagePath}"
        ];
        
        // Add paths with different extensions
        foreach ($extensions as $ext) {
            if ($ext !== '') {
                $possiblePaths[] = "/storage/news/featured/{$baseName}{$ext}";
                $possiblePaths[] = "/images/news/{$baseName}{$ext}";
                $possiblePaths[] = "/news/{$baseName}{$ext}";
            }
        }

        foreach ($possiblePaths as $path) {
            $publicPath = public_path($path);
            if (file_exists($publicPath) && is_file($publicPath)) {
                return [
                    'url' => $path,
                    'exists' => true,
                    'fallback' => [
                        'text' => $title ?? 'News',
                        'color' => '#6b7280',
                        'type' => 'news-image'
                    ]
                ];
            }
        }

        // Fallback to placeholder
        return [
            'url' => '/images/news-placeholder.svg',
            'exists' => true,
            'fallback' => [
                'text' => $title ?? 'News',
                'color' => '#6b7280',
                'type' => 'news-image'
            ]
        ];
    }

    /**
     * Get an event image with fallback support
     * 
     * @param string $imagePath The image path from database
     * @param string $eventName The event name for fallback
     * @param string $type The image type (banner, logo)
     * @return array Array with url, exists, and fallback info
     */
    public static function getEventImage($imagePath, $eventName = null, $type = 'banner')
    {
        if (!$imagePath) {
            $placeholderUrl = $type === 'logo' ? '/images/default-placeholder.svg' : '/images/news-placeholder.svg';
            return [
                'url' => $placeholderUrl,
                'exists' => true,
                'fallback' => [
                    'text' => $eventName ?? 'Event',
                    'color' => self::generateColorFromText($eventName ?? 'event'),
                    'type' => "event-{$type}"
                ]
            ];
        }

        // Check multiple possible paths with extensions
        $extensions = ['', '.jpg', '.jpeg', '.png', '.webp', '.svg'];
        $baseName = pathinfo($imagePath, PATHINFO_FILENAME);
        
        $possiblePaths = [
            "/storage/{$imagePath}",
            "/storage/events/{$type}s/{$imagePath}",
            "/images/events/{$imagePath}",
            "/events/{$imagePath}",
            "/{$imagePath}"
        ];
        
        // Add paths with different extensions
        foreach ($extensions as $ext) {
            if ($ext !== '') {
                $possiblePaths[] = "/storage/events/{$type}s/{$baseName}{$ext}";
                $possiblePaths[] = "/images/events/{$baseName}{$ext}";
                $possiblePaths[] = "/events/{$baseName}{$ext}";
            }
        }

        foreach ($possiblePaths as $path) {
            $publicPath = public_path($path);
            if (file_exists($publicPath) && is_file($publicPath)) {
                return [
                    'url' => $path,
                    'exists' => true,
                    'fallback' => [
                        'text' => $eventName ?? 'Event',
                        'color' => self::generateColorFromText($eventName ?? 'event'),
                        'type' => "event-{$type}"
                    ]
                ];
            }
        }

        // Fallback to placeholder
        $placeholderUrl = $type === 'logo' ? '/images/default-placeholder.svg' : '/images/news-placeholder.svg';
        return [
            'url' => $placeholderUrl,
            'exists' => true,
            'fallback' => [
                'text' => $eventName ?? 'Event',
                'color' => self::generateColorFromText($eventName ?? 'event'),
                'type' => "event-{$type}"
            ]
        ];
    }

    /**
     * Create a URL-friendly slug from hero name
     * 
     * @param string $heroName
     * @return string
     */
    private static function createHeroSlug($heroName)
    {
        $slug = strtolower($heroName);
        
        // Special case for Cloak & Dagger
        if (strpos($slug, 'cloak') !== false && strpos($slug, 'dagger') !== false) {
            return 'cloak-dagger';
        }
        
        // Special case for Mr. Fantastic
        if (strpos($slug, 'mr.') !== false && strpos($slug, 'fantastic') !== false) {
            return 'mister-fantastic';
        }
        
        $slug = str_replace([' ', '&', '.', "'", '-'], ['-', '-', '', '', '-'], $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Get multiple slug variations for a hero name to improve matching
     * 
     * @param string $heroName
     * @return array
     */
    private static function getHeroSlugVariations($heroName)
    {
        $variations = [];
        $slug = strtolower($heroName);
        
        // Special cases for Cloak & Dagger - try preferred variation first
        if (strpos($slug, 'cloak') !== false && strpos($slug, 'dagger') !== false) {
            $variations[] = 'cloak-and-dagger'; // Frontend expectation - try first
            $variations[] = 'cloak-dagger'; // Fallback compatibility
        }
        
        // Special cases for Mr. Fantastic
        if (strpos($slug, 'mr.') !== false && strpos($slug, 'fantastic') !== false) {
            $variations[] = 'mister-fantastic';
            $variations[] = 'mr-fantastic';
        }
        
        // The Punisher variations
        if (strpos($slug, 'punisher') !== false) {
            $variations[] = 'the-punisher';
            $variations[] = 'punisher';
        }
        
        // Bruce Banner / Hulk variations
        if (strpos($slug, 'bruce') !== false && strpos($slug, 'banner') !== false) {
            $variations[] = 'bruce-banner';
            $variations[] = 'hulk';
        }
        if (strpos($slug, 'hulk') !== false) {
            $variations[] = 'hulk';
            $variations[] = 'bruce-banner';
        }
        
        // Primary slug using updated logic (unless already added as special case)
        $primarySlug = self::createHeroSlug($heroName);
        if (!in_array($primarySlug, $variations)) {
            $variations[] = $primarySlug;
        }
        
        // General fallback: create a simple slug without special handling
        $basicSlug = str_replace([' ', '&', '.', "'"], ['-', '-', '', ''], $slug);
        $basicSlug = preg_replace('/[^a-z0-9\-]/', '', $basicSlug);
        $basicSlug = preg_replace('/-+/', '-', $basicSlug);
        $basicSlug = trim($basicSlug, '-');
        if (!in_array($basicSlug, $variations)) {
            $variations[] = $basicSlug;
        }
        
        return $variations;
    }

    /**
     * Get theme color for hero
     * 
     * @param string $heroName
     * @return string
     */
    private static function getHeroColor($heroName)
    {
        $colors = [
            'Spider-Man' => '#dc2626',
            'Iron Man' => '#f59e0b',
            'Captain America' => '#2563eb',
            'Thor' => '#7c3aed',
            'Hulk' => '#16a34a',
            'Black Widow' => '#000000',
            'Hawkeye' => '#7c2d12',
            'Doctor Strange' => '#db2777',
            'Scarlet Witch' => '#dc2626',
            'Loki' => '#16a34a',
            'Venom' => '#000000',
            'Magneto' => '#7c3aed',
            'Storm' => '#6b7280',
            'Wolverine' => '#f59e0b',
            'Groot' => '#16a34a',
            'Rocket Raccoon' => '#f59e0b',
            'Star-Lord' => '#dc2626',
            'Mantis' => '#16a34a',
            'Adam Warlock' => '#f59e0b',
            'Luna Snow' => '#3b82f6',
            'Jeff the Land Shark' => '#3b82f6',
            'Cloak & Dagger' => '#6b7280'
        ];
        
        return $colors[$heroName] ?? '#6b7280';
    }

    /**
     * Get role color for hero (simplified lookup)
     * 
     * @param string $heroName
     * @return string
     */
    private static function getRoleColorForHero($heroName)
    {
        // This is a simplified mapping - in a real app you'd query the database
        $roleMapping = [
            'Spider-Man' => '#dc2626', // Duelist
            'Iron Man' => '#dc2626', // Duelist
            'Captain America' => '#3b82f6', // Vanguard
            'Thor' => '#3b82f6', // Vanguard
            'Hulk' => '#3b82f6', // Vanguard
            'Black Widow' => '#dc2626', // Duelist
            'Hawkeye' => '#dc2626', // Duelist
            'Doctor Strange' => '#16a34a', // Strategist
            'Scarlet Witch' => '#dc2626', // Duelist
            'Loki' => '#16a34a', // Strategist
            'Venom' => '#3b82f6', // Vanguard
            'Magneto' => '#3b82f6', // Vanguard
            'Storm' => '#dc2626', // Duelist
            'Wolverine' => '#dc2626', // Duelist
            'Groot' => '#3b82f6', // Vanguard
            'Rocket Raccoon' => '#16a34a', // Strategist
            'Star-Lord' => '#dc2626', // Duelist
            'Mantis' => '#16a34a', // Strategist
            'Adam Warlock' => '#16a34a', // Strategist
            'Luna Snow' => '#16a34a', // Strategist
            'Jeff the Land Shark' => '#3b82f6', // Vanguard
            'Cloak & Dagger' => '#dc2626' // Duelist
        ];
        
        return $roleMapping[$heroName] ?? '#6b7280';
    }

    /**
     * Check if a path is an external URL that should be blocked
     * 
     * @param string $path
     * @return bool
     */
    private static function isExternalUrl($path)
    {
        if (!is_string($path)) {
            return false;
        }
        
        // Block all external HTTP/HTTPS URLs
        if (preg_match('/^https?:\/\//', $path)) {
            return true;
        }
        
        // Block any URLs containing external domains
        $blockedDomains = [
            'liquipedia.net',
            'liquipedia.org',
            'vlr.gg',
            'hltv.org',
            'cdn.',
            'imgur.com',
            'i.imgur.com'
        ];
        
        foreach ($blockedDomains as $domain) {
            if (strpos($path, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate a consistent color based on a string
     * 
     * @param string $text
     * @return string
     */
    public static function generateColorFromText($text)
    {
        $colors = [
            '#dc2626', '#f59e0b', '#16a34a', '#3b82f6', 
            '#7c3aed', '#db2777', '#059669', '#0d9488',
            '#7c2d12', '#1e40af', '#be185d', '#991b1b'
        ];
        
        $hash = crc32($text);
        return $colors[abs($hash) % count($colors)];
    }

    /**
     * Check if an image file exists at the given path
     * 
     * @param string $imagePath
     * @return bool
     */
    public static function imageExists($imagePath)
    {
        if (!$imagePath) {
            return false;
        }

        $publicPath = public_path($imagePath);
        return file_exists($publicPath) && is_file($publicPath);
    }

    /**
     * Get all possible image formats for fallback chain
     * 
     * @param string $basePath
     * @param array $extensions
     * @return array
     */
    public static function getImageFormats($basePath, $extensions = ['webp', 'png', 'jpg', 'jpeg'])
    {
        $formats = [];
        $pathInfo = pathinfo($basePath);
        $baseWithoutExt = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
        
        foreach ($extensions as $ext) {
            $formats[] = $baseWithoutExt . '.' . $ext;
        }
        
        return $formats;
    }

    /**
     * Fix team logo path to use correct storage location
     * 
     * @param string $logoPath The current logo path
     * @return string|null The corrected path or null if not found
     */
    public static function fixTeamLogoPath($logoPath)
    {
        if (!$logoPath) {
            return null;
        }

        // Clean up logo path
        $logoPath = ltrim($logoPath, '/');
        $baseName = pathinfo($logoPath, PATHINFO_FILENAME);
        $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
        
        // If it already has the correct path structure, return as-is
        if (strpos($logoPath, 'teams/logos/') !== false) {
            return $logoPath;
        }
        
        // Extract just the filename if it has any path structure
        $filename = basename($logoPath);
        
        // Check if file exists in storage/teams/logos
        $correctPath = "teams/logos/{$filename}";
        $actualStoragePath = storage_path("app/public/{$correctPath}");
        
        if (file_exists($actualStoragePath) && is_file($actualStoragePath)) {
            return $correctPath;
        }
        
        // Try different extensions if current one doesn't exist
        if ($extension) {
            $extensions = ['svg', 'png', 'jpg', 'jpeg', 'webp'];
            foreach ($extensions as $ext) {
                if ($ext !== $extension) {
                    $testPath = "teams/logos/{$baseName}.{$ext}";
                    $testStoragePath = storage_path("app/public/{$testPath}");
                    
                    if (file_exists($testStoragePath) && is_file($testStoragePath)) {
                        return $testPath;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Generate filename variations to handle different naming conventions
     * 
     * @param string $baseName Original filename without extension
     * @param string $teamName Team name for generating alternative variations
     * @return array Array of possible filename variations
     */
    private static function generateTeamLogoFilenameVariations($baseName, $teamName = null)
    {
        $variations = [];
        
        // 1. Original filename as-is
        $variations[] = $baseName;
        
        // 2. Remove all hyphens (common mismatch: "virtus-pro-logo" vs "virtuspro-logo")
        $noHyphens = str_replace('-', '', $baseName);
        if ($noHyphens !== $baseName) {
            $variations[] = $noHyphens;
        }
        
        // 3. Replace hyphens with underscores
        $underscores = str_replace('-', '_', $baseName);
        if ($underscores !== $baseName) {
            $variations[] = $underscores;
        }
        
        // 4. Generate slug from team name if provided
        if ($teamName) {
            $teamSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $teamName));
            
            // Try with -logo suffix
            $variations[] = $teamSlug . '-logo';
            $variations[] = $teamSlug . 'logo'; // No hyphen version
            $variations[] = $teamSlug . '_logo';
            
            // Try abbreviated versions for long names
            if (strlen($teamSlug) > 8) {
                $words = preg_split('/(?=[A-Z])/', $teamName);
                $words = array_filter($words, function($word) { 
                    return strlen(trim($word)) > 0; 
                });
                
                if (count($words) > 1) {
                    $abbreviation = '';
                    foreach ($words as $word) {
                        $abbreviation .= strtolower(substr(trim($word), 0, 1));
                    }
                    if (strlen($abbreviation) >= 2) {
                        $variations[] = $abbreviation . '-logo';
                        $variations[] = $abbreviation . 'logo';
                        $variations[] = $abbreviation . '_logo';
                    }
                }
            }
            
            // Special cases for known team naming patterns
            $specialCases = self::getTeamLogoSpecialCases($teamName);
            foreach ($specialCases as $specialCase) {
                $variations[] = $specialCase . '-logo';
                $variations[] = $specialCase . 'logo';
                $variations[] = $specialCase . '_logo';
            }
        }
        
        // 5. Common transformations
        // Remove periods and other special characters
        $noPeriods = str_replace(['.', '&', ' '], ['', 'and', ''], $baseName);
        if ($noPeriods !== $baseName) {
            $variations[] = $noPeriods;
        }
        
        // Convert CamelCase to kebab-case
        $kebabCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $baseName));
        if ($kebabCase !== $baseName) {
            $variations[] = $kebabCase;
        }
        
        // Remove duplicates and return
        return array_unique(array_filter($variations, function($v) { 
            return !empty(trim($v)); 
        }));
    }
    
    /**
     * Get special case variations for known team naming patterns
     * 
     * @param string $teamName
     * @return array
     */
    private static function getTeamLogoSpecialCases($teamName)
    {
        $specialCases = [];
        $lowerName = strtolower($teamName);
        
        // Handle common esports team naming patterns
        if (strpos($lowerName, '100 thieves') !== false) {
            $specialCases = ['100t', '100thieves', 'onehundredthieves'];
        } elseif (strpos($lowerName, 'virtus.pro') !== false || strpos($lowerName, 'virtuspro') !== false) {
            $specialCases = ['virtuspro', 'virtus-pro', 'virtus_pro', 'vp'];
        } elseif (strpos($lowerName, 'gen.g') !== false) {
            $specialCases = ['geng', 'gen-g', 'gen_g'];
        } elseif (strpos($lowerName, 'g2 esports') !== false || strpos($lowerName, 'g2') !== false) {
            $specialCases = ['g2', 'g2-esports', 'g2esports'];
        } elseif (strpos($lowerName, 'cloud9') !== false || strpos($lowerName, 'cloud 9') !== false) {
            $specialCases = ['cloud9', 'c9'];
        } elseif (strpos($lowerName, 'team liquid') !== false || strpos($lowerName, 'liquid') !== false) {
            $specialCases = ['liquid', 'team-liquid', 'teamliquid', 'tl'];
        } elseif (strpos($lowerName, 'fnatic') !== false) {
            $specialCases = ['fnatic', 'fnc'];
        } elseif (strpos($lowerName, 'nrg esports') !== false || strpos($lowerName, 'nrg') !== false) {
            $specialCases = ['nrg', 'nrg-esports', 'nrgesports'];
        } elseif (strpos($lowerName, 'evil geniuses') !== false || strpos($lowerName, 'eg') !== false) {
            $specialCases = ['eg', 'evil-geniuses', 'evilgeniuses'];
        }
        
        return $specialCases;
    }

    /**
     * Debug team logo paths - returns detailed information about path resolution
     * 
     * @param string $logoPath
     * @param string $teamName
     * @return array
     */
    public static function debugTeamLogo($logoPath, $teamName = null)
    {
        $result = [
            'original_path' => $logoPath,
            'team_name' => $teamName,
            'fixed_path' => self::fixTeamLogoPath($logoPath),
            'paths_checked' => [],
            'found_paths' => []
        ];
        
        if (!$logoPath) {
            $result['status'] = 'no_path_provided';
            return $result;
        }

        $logoPath = ltrim($logoPath, '/');
        $baseName = pathinfo($logoPath, PATHINFO_FILENAME);
        $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
        $extensions = $extension ? [$extension] : ['svg', 'png', 'jpg', 'jpeg', 'webp'];
        
        // Use the new variations method
        $filenameVariations = self::generateTeamLogoFilenameVariations($baseName, $teamName);
        $result['filename_variations'] = $filenameVariations;
        
        $possiblePaths = [];
        
        foreach ($filenameVariations as $baseNameVariation) {
            foreach ($extensions as $ext) {
                $possiblePaths[] = "/storage/teams/logos/{$baseNameVariation}.{$ext}";
                $possiblePaths[] = "/teams/{$baseNameVariation}.{$ext}";
            }
        }
        
        $possiblePaths[] = "/storage/{$logoPath}";
        $possiblePaths[] = "/{$logoPath}";

        foreach ($possiblePaths as $path) {
            $result['paths_checked'][] = $path;
            
            $publicPath = public_path($path);
            $exists = false;
            
            if (strpos($path, '/storage/') === 0) {
                $storagePath = str_replace('/storage/', '', $path);
                $actualStoragePath = storage_path("app/public/{$storagePath}");
                $exists = file_exists($actualStoragePath) && is_file($actualStoragePath);
            } else {
                $exists = file_exists($publicPath) && is_file($publicPath);
            }
            
            if ($exists) {
                $result['found_paths'][] = $path;
            }
        }
        
        $result['status'] = empty($result['found_paths']) ? 'not_found' : 'found';
        $result['recommended_path'] = !empty($result['found_paths']) ? $result['found_paths'][0] : null;
        
        return $result;
    }
}