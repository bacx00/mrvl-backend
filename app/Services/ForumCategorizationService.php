<?php

namespace App\Services;

use App\Models\ForumThread;
use App\Models\ForumCategory;
use App\Models\ForumTag;
use App\Models\ForumThreadTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ForumCategorizationService
{
    private $cacheService;
    
    public function __construct(ForumCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Auto-categorize a thread based on content analysis
     */
    public function autoCategorizThread($threadId, $title, $content)
    {
        try {
            // Content analysis keywords for different categories
            $categoryKeywords = [
                'strategy' => [
                    'strategy', 'strategies', 'tactic', 'tactics', 'guide', 'tutorial', 
                    'how to', 'tips', 'advice', 'build', 'comp', 'composition', 'meta',
                    'counter', 'positioning', 'rotation', 'timing'
                ],
                'competitive' => [
                    'tournament', 'scrim', 'ranked', 'competitive', 'esports', 'pro',
                    'professional', 'league', 'championship', 'qualifier', 'bracket',
                    'match', 'versus', 'vs', 'team', 'roster'
                ],
                'recruitment' => [
                    'looking for', 'lfg', 'lft', 'recruit', 'recruiting', 'join team',
                    'team tryout', 'applications', 'position open', 'seeking', 'player wanted',
                    'coach wanted', 'substitue', 'sub'
                ],
                'bugs' => [
                    'bug', 'glitch', 'error', 'broken', 'not working', 'issue', 'problem',
                    'crash', 'freeze', 'lag', 'desync', 'exploit', 'fix needed'
                ],
                'feedback' => [
                    'feedback', 'suggestion', 'improve', 'balance', 'nerf', 'buff',
                    'opinion', 'thoughts', 'review', 'rating', 'feature request'
                ],
                'discussion' => [
                    'discuss', 'discussion', 'opinion', 'thoughts', 'what do you think',
                    'debate', 'analysis', 'theory', 'speculation'
                ]
            ];

            $combinedText = strtolower($title . ' ' . $content);
            $categoryScores = [];

            // Calculate category scores based on keyword matches
            foreach ($categoryKeywords as $category => $keywords) {
                $score = 0;
                foreach ($keywords as $keyword) {
                    $score += substr_count($combinedText, $keyword);
                }
                $categoryScores[$category] = $score;
            }

            // Get the highest scoring category
            $suggestedCategory = array_key_exists(max($categoryScores), array_flip($categoryScores)) 
                ? array_search(max($categoryScores), $categoryScores)
                : 'general';

            // Only auto-categorize if confidence is high enough
            if (max($categoryScores) >= 2) {
                $category = ForumCategory::where('slug', $suggestedCategory)->first();
                if ($category) {
                    ForumThread::where('id', $threadId)->update([
                        'category_id' => $category->id,
                        'auto_categorized' => true
                    ]);
                }
            }

            return [
                'suggested_category' => $suggestedCategory,
                'confidence_score' => max($categoryScores),
                'scores' => $categoryScores
            ];

        } catch (\Exception $e) {
            Log::error('Auto-categorization failed', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract and assign tags to a thread
     */
    public function autoTagThread($threadId, $title, $content)
    {
        try {
            $combinedText = strtolower($title . ' ' . $content);
            
            // Hero-based tags
            $heroTags = $this->extractHeroTags($combinedText);
            
            // Gameplay tags
            $gameplayTags = $this->extractGameplayTags($combinedText);
            
            // General tags
            $generalTags = $this->extractGeneralTags($combinedText);
            
            $allTags = array_merge($heroTags, $gameplayTags, $generalTags);
            
            // Create or get existing tags
            $tagIds = [];
            foreach (array_unique($allTags) as $tagName) {
                $tag = ForumTag::firstOrCreate(
                    ['name' => $tagName],
                    [
                        'slug' => Str::slug($tagName),
                        'color' => $this->getTagColor($tagName),
                        'description' => $this->getTagDescription($tagName),
                        'usage_count' => 0
                    ]
                );
                $tagIds[] = $tag->id;
            }

            // Remove existing tags for this thread
            ForumThreadTag::where('thread_id', $threadId)->delete();

            // Assign new tags
            foreach ($tagIds as $tagId) {
                ForumThreadTag::create([
                    'thread_id' => $threadId,
                    'tag_id' => $tagId
                ]);

                // Increment usage count
                ForumTag::where('id', $tagId)->increment('usage_count');
            }

            return $allTags;

        } catch (\Exception $e) {
            Log::error('Auto-tagging failed', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get trending tags based on recent usage
     */
    public function getTrendingTags($limit = 10, $timeframe = 24)
    {
        $cacheKey = "forum:trending_tags:{$timeframe}h";
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $since = now()->subHours($timeframe);

        $trendingTags = DB::table('forum_tags as ft')
            ->join('forum_thread_tags as ftt', 'ft.id', '=', 'ftt.tag_id')
            ->join('forum_threads as th', 'ftt.thread_id', '=', 'th.id')
            ->where('th.created_at', '>=', $since)
            ->where('th.status', 'active')
            ->select([
                'ft.id',
                'ft.name',
                'ft.slug',
                'ft.color',
                'ft.description',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('COUNT(DISTINCT th.user_id) as unique_users')
            ])
            ->groupBy('ft.id', 'ft.name', 'ft.slug', 'ft.color', 'ft.description')
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();

        Cache::put($cacheKey, $trendingTags, 300); // Cache for 5 minutes
        
        return $trendingTags;
    }

    /**
     * Get tag suggestions for a thread based on content
     */
    public function getTagSuggestions($title, $content, $limit = 8)
    {
        $suggestions = [];
        
        // Auto-detect tags
        $autoTags = array_merge(
            $this->extractHeroTags(strtolower($title . ' ' . $content)),
            $this->extractGameplayTags(strtolower($title . ' ' . $content)),
            $this->extractGeneralTags(strtolower($title . ' ' . $content))
        );

        foreach ($autoTags as $tagName) {
            $tag = ForumTag::where('name', $tagName)->first();
            if ($tag) {
                $suggestions[] = [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'confidence' => 'high'
                ];
            } else {
                $suggestions[] = [
                    'name' => $tagName,
                    'color' => $this->getTagColor($tagName),
                    'confidence' => 'medium',
                    'new' => true
                ];
            }
        }

        // Add popular tags as low-confidence suggestions
        $popularTags = ForumTag::orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        foreach ($popularTags as $tag) {
            if (!in_array($tag->name, array_column($suggestions, 'name'))) {
                $suggestions[] = [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'confidence' => 'low'
                ];
            }
        }

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Get threads by category with enhanced filtering
     */
    public function getThreadsByCategory($categorySlug, $options = [])
    {
        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;
        $tags = $options['tags'] ?? [];
        $sortBy = $options['sort'] ?? 'latest';
        $timeframe = $options['timeframe'] ?? null;

        $query = ForumThread::with(['user', 'category', 'tags'])
            ->where('status', 'active');

        // Category filter
        if ($categorySlug !== 'all') {
            $category = ForumCategory::where('slug', $categorySlug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        // Tag filters
        if (!empty($tags)) {
            $query->whereHas('tags', function($q) use ($tags) {
                $q->whereIn('forum_tags.slug', $tags);
            });
        }

        // Timeframe filter
        if ($timeframe) {
            $since = match($timeframe) {
                'today' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                default => now()->subHours((int)$timeframe)
            };
            $query->where('created_at', '>=', $since);
        }

        // Sorting
        $query = match($sortBy) {
            'popular' => $query->orderBy('score', 'desc')->orderBy('views', 'desc'),
            'hot' => $query->orderByRaw('(score * 0.3 + replies * 0.4 + views * 0.0001) DESC'),
            'replies' => $query->orderBy('replies', 'desc'),
            'views' => $query->orderBy('views', 'desc'),
            default => $query->orderBy('created_at', 'desc')
        };

        return $query->offset($offset)->limit($limit)->get();
    }

    /**
     * Get related threads based on tags and category
     */
    public function getRelatedThreads($threadId, $limit = 5)
    {
        $thread = ForumThread::with('tags', 'category')->find($threadId);
        if (!$thread) {
            return collect();
        }

        $tagIds = $thread->tags->pluck('id')->toArray();
        
        $relatedThreads = ForumThread::with(['user', 'category'])
            ->where('id', '!=', $threadId)
            ->where('status', 'active')
            ->where(function($query) use ($thread, $tagIds) {
                // Same category
                if ($thread->category_id) {
                    $query->where('category_id', $thread->category_id);
                }
                
                // Or shared tags
                if (!empty($tagIds)) {
                    $query->orWhereHas('tags', function($q) use ($tagIds) {
                        $q->whereIn('forum_tags.id', $tagIds);
                    });
                }
            })
            ->orderByRaw('
                CASE 
                    WHEN category_id = ? THEN 2 
                    ELSE 0 
                END + 
                (SELECT COUNT(*) FROM forum_thread_tags WHERE thread_id = forum_threads.id AND tag_id IN (' . implode(',', $tagIds ?: [0]) . ')) DESC,
                score DESC
            ', [$thread->category_id])
            ->limit($limit)
            ->get();

        return $relatedThreads;
    }

    /**
     * Private helper methods
     */
    
    private function extractHeroTags($text)
    {
        $heroes = [
            'spider-man', 'spiderman', 'iron man', 'ironman', 'hulk', 'thor', 'captain america',
            'black widow', 'hawkeye', 'scarlet witch', 'vision', 'falcon', 'winter soldier',
            'ant-man', 'wasp', 'captain marvel', 'doctor strange', 'black panther',
            'star-lord', 'gamora', 'rocket', 'groot', 'drax', 'mantis', 'nebula',
            'daredevil', 'jessica jones', 'luke cage', 'iron fist', 'punisher',
            'deadpool', 'wolverine', 'storm', 'cyclops', 'jean grey', 'beast',
            'nightcrawler', 'colossus', 'kitty pryde', 'rogue', 'jubilee'
        ];

        $foundHeroes = [];
        foreach ($heroes as $hero) {
            if (strpos($text, $hero) !== false) {
                $foundHeroes[] = ucwords(str_replace('-', ' ', $hero));
            }
        }

        return $foundHeroes;
    }

    private function extractGameplayTags($text)
    {
        $gameplayKeywords = [
            'tank' => 'Tank',
            'dps' => 'DPS',
            'damage' => 'DPS',
            'support' => 'Support',
            'healer' => 'Support',
            'meta' => 'Meta',
            'counter' => 'Counter',
            'strategy' => 'Strategy',
            'guide' => 'Guide',
            'tutorial' => 'Tutorial',
            'tips' => 'Tips',
            'ranked' => 'Ranked',
            'competitive' => 'Competitive',
            'casual' => 'Casual',
            'team comp' => 'Team Composition',
            'composition' => 'Team Composition'
        ];

        $foundTags = [];
        foreach ($gameplayKeywords as $keyword => $tag) {
            if (strpos($text, $keyword) !== false) {
                $foundTags[] = $tag;
            }
        }

        return $foundTags;
    }

    private function extractGeneralTags($text)
    {
        $generalKeywords = [
            'beginner' => 'Beginner',
            'advanced' => 'Advanced',
            'question' => 'Question',
            'help' => 'Help',
            'discussion' => 'Discussion',
            'news' => 'News',
            'update' => 'Update',
            'patch' => 'Patch',
            'bug' => 'Bug',
            'feedback' => 'Feedback'
        ];

        $foundTags = [];
        foreach ($generalKeywords as $keyword => $tag) {
            if (strpos($text, $keyword) !== false) {
                $foundTags[] = $tag;
            }
        }

        return $foundTags;
    }

    private function getTagColor($tagName)
    {
        // Color mapping for different tag types
        $colorMap = [
            // Heroes
            'Spider-Man' => '#FF0000',
            'Iron Man' => '#FFD700',
            'Hulk' => '#00FF00',
            'Thor' => '#87CEEB',
            'Captain America' => '#0000FF',
            
            // Gameplay
            'Tank' => '#8B4513',
            'DPS' => '#FF4500',
            'Support' => '#32CD32',
            'Meta' => '#9932CC',
            'Strategy' => '#2E8B57',
            'Guide' => '#4169E1',
            'Tips' => '#FF6347',
            
            // General
            'Beginner' => '#90EE90',
            'Advanced' => '#FF6B6B',
            'Question' => '#87CEFA',
            'Help' => '#FFA500',
            'Discussion' => '#DDA0DD',
            'Bug' => '#DC143C',
            'News' => '#4682B4'
        ];

        return $colorMap[$tagName] ?? '#6B7280'; // Default gray
    }

    private function getTagDescription($tagName)
    {
        $descriptions = [
            'Tank' => 'Discussion about tank heroes and strategies',
            'DPS' => 'Topics related to damage-dealing heroes',
            'Support' => 'Support hero guides and team support strategies',
            'Meta' => 'Current game meta discussions and analysis',
            'Strategy' => 'Strategic gameplay discussions and tactics',
            'Guide' => 'Comprehensive guides and tutorials',
            'Beginner' => 'Content suitable for new players',
            'Advanced' => 'Advanced strategies and high-level play',
            'Bug' => 'Bug reports and technical issues',
            'Help' => 'Players seeking assistance and advice'
        ];

        return $descriptions[$tagName] ?? "Discussion about {$tagName}";
    }
}