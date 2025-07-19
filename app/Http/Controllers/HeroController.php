<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HeroController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('marvel_rivals_heroes')
                ->where('active', true);

            // Filter by role
            if ($request->role && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            // Search functionality
            if ($request->search) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            }

            // Sort options
            $sortBy = $request->get('sort', 'role');
            switch ($sortBy) {
                case 'name':
                    $query->orderBy('name', 'asc');
                    break;
                case 'popularity':
                    $query->orderBy('usage_rate', 'desc');
                    break;
                case 'win_rate':
                    $query->orderBy('win_rate', 'desc');
                    break;
                default: // role
                    $query->orderBy('role', 'asc')
                          ->orderBy('sort_order', 'asc')
                          ->orderBy('name', 'asc');
            }

            $heroes = $query->get();

            // Format hero data with image paths and fallbacks
            $heroesData = $heroes->map(function($hero) {
                return [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'slug' => $hero->slug,
                    'role' => $hero->role,
                    'description' => $hero->description,
                    'abilities' => $hero->abilities ? json_decode($hero->abilities, true) : [],
                    'stats' => [
                        'usage_rate' => $hero->usage_rate ?? 0,
                        'win_rate' => $hero->win_rate ?? 0,
                        'pick_rate' => $hero->pick_rate ?? 0,
                        'ban_rate' => $hero->ban_rate ?? 0
                    ],
                    'images' => [
                        'portrait' => $this->getHeroImagePath($hero->name, 'portrait'),
                        'icon' => $this->getHeroImagePath($hero->name, 'icon'),
                        'ability_1' => $this->getHeroImagePath($hero->name, 'ability_1'),
                        'ability_2' => $this->getHeroImagePath($hero->name, 'ability_2'),
                        'ultimate' => $this->getHeroImagePath($hero->name, 'ultimate')
                    ],
                    'fallback' => [
                        'text' => $hero->name,
                        'role_icon' => "/images/roles/" . strtolower($hero->role) . ".png",
                        'color' => $this->getRoleColor($hero->role)
                    ],
                    'meta' => [
                        'difficulty' => $hero->difficulty ?? 'Medium',
                        'release_date' => $hero->release_date,
                        'season_added' => $hero->season_added ?? 'Launch',
                        'is_new' => $hero->is_new ?? false
                    ]
                ];
            });

            // Group by role for organized display
            $herosByRole = $heroesData->groupBy('role');

            return response()->json([
                'data' => $heroesData,
                'grouped_by_role' => $herosByRole,
                'stats' => [
                    'total_heroes' => $heroes->count(),
                    'by_role' => [
                        'Vanguard' => $heroes->where('role', 'Vanguard')->count(),
                        'Duelist' => $heroes->where('role', 'Duelist')->count(),
                        'Strategist' => $heroes->where('role', 'Strategist')->count()
                    ]
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching heroes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($heroSlug)
    {
        try {
            $hero = DB::table('marvel_rivals_heroes')
                ->where('slug', $heroSlug)
                ->where('active', true)
                ->first();

            if (!$hero) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero not found'
                ], 404);
            }

            // Get hero statistics and meta information
            $heroStats = $this->getHeroStats($hero->id);
            $teamComps = $this->getHeroTeamComps($hero->name);
            $counters = $this->getHeroCounters($hero->name);

            $heroData = [
                'id' => $hero->id,
                'name' => $hero->name,
                'slug' => $hero->slug,
                'role' => $hero->role,
                'description' => $hero->description,
                'lore' => $hero->lore,
                'abilities' => $hero->abilities ? json_decode($hero->abilities, true) : [],
                'stats' => $heroStats,
                'images' => [
                    'portrait' => $this->getHeroImagePath($hero->name, 'portrait'),
                    'icon' => $this->getHeroImagePath($hero->name, 'icon'),
                    'ability_1' => $this->getHeroImagePath($hero->name, 'ability_1'),
                    'ability_2' => $this->getHeroImagePath($hero->name, 'ability_2'),
                    'ultimate' => $this->getHeroImagePath($hero->name, 'ultimate'),
                    'gallery' => $this->getHeroGalleryImages($hero->name)
                ],
                'fallback' => [
                    'text' => $hero->name,
                    'role_icon' => "/images/roles/" . strtolower($hero->role) . ".png",
                    'color' => $this->getRoleColor($hero->role)
                ],
                'meta' => [
                    'difficulty' => $hero->difficulty ?? 'Medium',
                    'release_date' => $hero->release_date,
                    'season_added' => $hero->season_added ?? 'Launch',
                    'is_new' => $hero->is_new ?? false,
                    'voice_actor' => $hero->voice_actor,
                    'height' => $hero->height,
                    'universe' => $hero->universe ?? 'Marvel'
                ],
                'gameplay' => [
                    'team_compositions' => $teamComps,
                    'counters' => $counters,
                    'tips' => $this->getHeroTips($hero->name),
                    'best_maps' => $this->getHeroBestMaps($hero->name)
                ]
            ];

            return response()->json([
                'data' => $heroData,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getHeroImages(Request $request)
    {
        try {
            $heroName = $request->get('hero');
            $type = $request->get('type', 'all'); // portrait, icon, ability, all

            if (!$heroName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero name is required'
                ], 400);
            }

            $images = [];

            if ($type === 'all' || $type === 'portrait') {
                $images['portrait'] = $this->getHeroImagePath($heroName, 'portrait');
            }

            if ($type === 'all' || $type === 'icon') {
                $images['icon'] = $this->getHeroImagePath($heroName, 'icon');
            }

            if ($type === 'all' || $type === 'ability') {
                $images['abilities'] = [
                    'ability_1' => $this->getHeroImagePath($heroName, 'ability_1'),
                    'ability_2' => $this->getHeroImagePath($heroName, 'ability_2'),
                    'ultimate' => $this->getHeroImagePath($heroName, 'ultimate')
                ];
            }

            // Check if images exist and provide fallbacks
            $imagesWithStatus = [];
            foreach ($images as $key => $imagePath) {
                if (is_array($imagePath)) {
                    $imagesWithStatus[$key] = [];
                    foreach ($imagePath as $subKey => $subPath) {
                        $imagesWithStatus[$key][$subKey] = [
                            'url' => $subPath,
                            'exists' => $this->imageExists($subPath),
                            'fallback' => [
                                'text' => $heroName,
                                'type' => $subKey,
                                'color' => $this->getHeroColor($heroName)
                            ]
                        ];
                    }
                } else {
                    $imagesWithStatus[$key] = [
                        'url' => $imagePath,
                        'exists' => $this->imageExists($imagePath),
                        'fallback' => [
                            'text' => $heroName,
                            'type' => $key,
                            'color' => $this->getHeroColor($heroName)
                        ]
                    ];
                }
            }

            return response()->json([
                'data' => $imagesWithStatus,
                'hero' => $heroName,
                'type' => $type,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero images: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateHeroStats(Request $request, $heroId)
    {
        $this->authorize('manage-heroes');
        
        $request->validate([
            'usage_rate' => 'nullable|numeric|min:0|max:100',
            'win_rate' => 'nullable|numeric|min:0|max:100',
            'pick_rate' => 'nullable|numeric|min:0|max:100',
            'ban_rate' => 'nullable|numeric|min:0|max:100'
        ]);

        try {
            DB::table('marvel_rivals_heroes')
                ->where('id', $heroId)
                ->update([
                    'usage_rate' => $request->usage_rate,
                    'win_rate' => $request->win_rate,
                    'pick_rate' => $request->pick_rate,
                    'ban_rate' => $request->ban_rate,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Hero stats updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating hero stats: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for Marvel Rivals hero system

    private function getHeroImagePath($heroName, $type)
    {
        $slug = $this->createHeroSlug($heroName);
        
        switch ($type) {
            case 'portrait':
                // Check for webp images first
                $webpPath = "/images/heroes/{$slug}-headbig.webp";
                if ($this->imageExists($webpPath)) {
                    return $webpPath;
                }
                return "/images/heroes/portraits/{$slug}.png";
            case 'icon':
                // Check for webp icon
                $webpPath = "/images/heroes/{$slug}-headbig.webp";
                if ($this->imageExists($webpPath)) {
                    return $webpPath;
                }
                return "/images/heroes/icons/{$slug}.png";
            case 'ability_1':
                return "/images/heroes/abilities/{$slug}_ability_1.png";
            case 'ability_2':
                return "/images/heroes/abilities/{$slug}_ability_2.png";
            case 'ultimate':
                return "/images/heroes/abilities/{$slug}_ultimate.png";
            default:
                // Default to webp if available
                $webpPath = "/images/heroes/{$slug}-headbig.webp";
                if ($this->imageExists($webpPath)) {
                    return $webpPath;
                }
                return "/images/heroes/{$slug}.png";
        }
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

    private function imageExists($imagePath)
    {
        // Check if image file exists in public storage
        $publicPath = public_path($imagePath);
        return file_exists($publicPath);
    }

    private function getHeroColor($heroName)
    {
        // Return theme color for hero fallbacks
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

    private function getRoleColor($role)
    {
        $colors = [
            'Vanguard' => '#3b82f6',    // Blue
            'Duelist' => '#dc2626',     // Red
            'Strategist' => '#16a34a'   // Green
        ];
        
        return $colors[$role] ?? '#6b7280';
    }

    private function getHeroGalleryImages($heroName)
    {
        $slug = $this->createHeroSlug($heroName);
        
        return [
            "/images/heroes/gallery/{$slug}_1.png",
            "/images/heroes/gallery/{$slug}_2.png",
            "/images/heroes/gallery/{$slug}_3.png",
            "/images/heroes/gallery/{$slug}_skin_1.png",
            "/images/heroes/gallery/{$slug}_skin_2.png"
        ];
    }

    private function getHeroStats($heroId)
    {
        // This would come from actual match data analysis
        return [
            'usage_rate' => 0,
            'win_rate' => 0,
            'pick_rate' => 0,
            'ban_rate' => 0,
            'average_eliminations' => 0,
            'average_deaths' => 0,
            'average_assists' => 0,
            'average_damage' => 0,
            'average_healing' => 0
        ];
    }

    private function getHeroTeamComps($heroName)
    {
        // Return popular team compositions featuring this hero
        $teamComps = [
            'Meta Composition' => [
                'description' => 'Current meta team composition',
                'heroes' => [$heroName, 'Luna Snow', 'Mantis'],
                'win_rate' => 0,
                'popularity' => 0
            ],
            'Aggressive Composition' => [
                'description' => 'High damage team composition',
                'heroes' => [$heroName, 'Hela', 'Hawkeye'],
                'win_rate' => 0,
                'popularity' => 0
            ]
        ];
        
        return $teamComps;
    }

    private function getHeroCounters($heroName)
    {
        // Return heroes that counter and are countered by this hero
        return [
            'strong_against' => [],
            'weak_against' => [],
            'synergizes_with' => []
        ];
    }

    private function getHeroTips($heroName)
    {
        // Return gameplay tips for the hero
        return [
            'Use your abilities in combination for maximum effect',
            'Position yourself carefully to avoid enemy focus fire',
            'Coordinate with your team for optimal results'
        ];
    }

    private function getHeroBestMaps($heroName)
    {
        // Return maps where this hero performs well
        return [
            'Hellfire Gala: Krakoa',
            'Hydra Charteris Base: Hell\'s Heaven',
            'Empire of Eternal Night: Central Park'
        ];
    }

    public function getRoles()
    {
        return response()->json([
            'data' => [
                [
                    'name' => 'Vanguard',
                    'description' => 'Tank heroes who protect the team and control space',
                    'color' => '#3b82f6',
                    'icon' => '/images/roles/vanguard.png',
                    'count' => 12
                ],
                [
                    'name' => 'Duelist',
                    'description' => 'Damage dealers who eliminate enemies',
                    'color' => '#dc2626',
                    'icon' => '/images/roles/duelist.png',
                    'count' => 19
                ],
                [
                    'name' => 'Strategist',
                    'description' => 'Support heroes who heal and buff allies',
                    'color' => '#16a34a',
                    'icon' => '/images/roles/strategist.png',
                    'count' => 8
                ]
            ],
            'total_heroes' => 39,
            'success' => true
        ]);
    }

    public function getSeasonTwoHeroes()
    {
        try {
            $season2Heroes = DB::table('marvel_rivals_heroes')
                ->where('season_added', 'Season 2')
                ->where('active', true)
                ->get();

            $heroesData = $season2Heroes->map(function($hero) {
                return [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'role' => $hero->role,
                    'release_date' => $hero->release_date,
                    'is_new' => true,
                    'images' => [
                        'portrait' => $this->getHeroImagePath($hero->name, 'portrait'),
                        'icon' => $this->getHeroImagePath($hero->name, 'icon')
                    ],
                    'fallback' => [
                        'text' => $hero->name,
                        'color' => $this->getHeroColor($hero->name)
                    ]
                ];
            });

            return response()->json([
                'data' => $heroesData,
                'season' => 'Season 2',
                'count' => $season2Heroes->count(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching Season 2 heroes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllHeroImages()
    {
        try {
            // Get all heroes from database
            $heroes = DB::table('marvel_rivals_heroes')->get();
            
            $heroImages = $heroes->map(function($hero) {
                $imagePath = $this->getHeroImagePath($hero->name, 'portrait');
                $imageExists = $this->imageExists($imagePath);
                
                return [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'slug' => $hero->slug,
                    'image_url' => $imageExists ? $imagePath : null,
                    'image_exists' => $imageExists,
                    'fallback_text' => $hero->name,
                    'role' => $hero->role,
                    'role_color' => $this->getRoleColor($hero->role)
                ];
            });

            return response()->json([
                'data' => $heroImages,
                'total' => $heroImages->count(),
                'missing_images' => $heroImages->where('image_exists', false)->pluck('name')->values(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero images: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getHeroImageBySlug($slug)
    {
        try {
            // Get hero from database
            $hero = DB::table('marvel_rivals_heroes')
                ->where('slug', $slug)
                ->first();

            if (!$hero) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero not found',
                    'fallback_text' => ucwords(str_replace('-', ' ', $slug))
                ], 404);
            }

            $imagePath = $this->getHeroImagePath($hero->name, 'portrait');
            $imageExists = $this->imageExists($imagePath);

            return response()->json([
                'data' => [
                    'hero_name' => $hero->name,
                    'slug' => $hero->slug,
                    'image_url' => $imageExists ? $imagePath : null,
                    'image_exists' => $imageExists,
                    'fallback_text' => $hero->name,
                    'role' => $hero->role,
                    'role_color' => $this->getRoleColor($hero->role)
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero image: ' . $e->getMessage()
            ], 500);
        }
    }
}