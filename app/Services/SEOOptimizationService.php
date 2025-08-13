<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SEOOptimizationService
{
    const CACHE_TTL = 3600; // 1 hour for SEO data
    
    /**
     * Generate optimized meta tags for different page types
     */
    public function generateMetaTags($type, $data = null)
    {
        switch ($type) {
            case 'match':
                return $this->generateMatchMetaTags($data);
            case 'tournament':
                return $this->generateTournamentMetaTags($data);
            case 'team':
                return $this->generateTeamMetaTags($data);
            case 'player':
                return $this->generatePlayerMetaTags($data);
            case 'news':
                return $this->generateNewsMetaTags($data);
            case 'forum':
                return $this->generateForumMetaTags($data);
            default:
                return $this->generateDefaultMetaTags();
        }
    }
    
    /**
     * Generate match-specific meta tags
     */
    private function generateMatchMetaTags($match)
    {
        if (!$match) return $this->generateDefaultMetaTags();
        
        $team1 = $match->team1_name ?? 'Team A';
        $team2 = $match->team2_name ?? 'Team B';
        $score = isset($match->team1_score) && isset($match->team2_score) 
            ? " ({$match->team1_score}-{$match->team2_score})" 
            : '';
        
        $title = "{$team1} vs {$team2}{$score} - MRVL Esports";
        $description = "Watch {$team1} take on {$team2} in this Marvel Rivals competitive match. Live scores, statistics, and real-time updates on MRVL platform.";
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords([$team1, $team2, 'match', 'live score', 'marvel rivals']),
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'website',
            'og:image' => $this->generateMatchImage($match),
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'canonical' => "/matches/{$match->id}",
            'schema' => $this->generateMatchSchema($match)
        ];
    }
    
    /**
     * Generate tournament-specific meta tags
     */
    private function generateTournamentMetaTags($tournament)
    {
        if (!$tournament) return $this->generateDefaultMetaTags();
        
        $title = "{$tournament->name} - Marvel Rivals Tournament | MRVL";
        $description = "Follow {$tournament->name} tournament with live brackets, scores, and standings. {$tournament->team_count} teams competing for {$tournament->prize_pool}.";
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords([$tournament->name, 'tournament', 'bracket', 'esports']),
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'website',
            'og:image' => $tournament->logo ?? '/default-tournament.jpg',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'canonical' => "/tournaments/{$tournament->id}",
            'schema' => $this->generateTournamentSchema($tournament)
        ];
    }
    
    /**
     * Generate team-specific meta tags
     */
    private function generateTeamMetaTags($team)
    {
        if (!$team) return $this->generateDefaultMetaTags();
        
        $title = "{$team->name} - Team Profile | MRVL Esports";
        $description = "{$team->name} team profile on MRVL. View roster, match history, statistics, and tournament results. ELO Rating: {$team->elo_rating}, Region: {$team->region}.";
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords([$team->name, $team->region, 'team profile', 'roster']),
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'profile',
            'og:image' => $team->logo ?? '/default-team.jpg',
            'twitter:card' => 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'canonical' => "/teams/{$team->id}",
            'schema' => $this->generateTeamSchema($team)
        ];
    }
    
    /**
     * Generate player-specific meta tags
     */
    private function generatePlayerMetaTags($player)
    {
        if (!$player) return $this->generateDefaultMetaTags();
        
        $title = "{$player->username} - Player Profile | MRVL Esports";
        $teamName = $player->team_name ?? 'Free Agent';
        $description = "{$player->username} ({$player->real_name}) - {$player->role} player for {$teamName}. Statistics, match history, and achievements on MRVL platform.";
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords([$player->username, $player->role, $teamName, 'player profile']),
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'profile',
            'og:image' => $player->avatar ?? '/default-avatar.png',
            'twitter:card' => 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'canonical' => "/players/{$player->id}",
            'schema' => $this->generatePlayerSchema($player)
        ];
    }
    
    /**
     * Generate news article meta tags
     */
    private function generateNewsMetaTags($article)
    {
        if (!$article) return $this->generateDefaultMetaTags();
        
        $title = "{$article->title} | MRVL News";
        $description = Str::limit(strip_tags($article->content), 155);
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords(array_merge(
                explode(' ', $article->tags ?? ''),
                ['marvel rivals', 'esports news']
            )),
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'article',
            'og:image' => $article->featured_image ?? '/default-news.jpg',
            'og:article:published_time' => $article->published_at,
            'og:article:author' => $article->author_name ?? 'MRVL Editorial',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'canonical' => "/news/{$article->id}",
            'schema' => $this->generateArticleSchema($article)
        ];
    }
    
    /**
     * Generate forum thread meta tags
     */
    private function generateForumMetaTags($thread)
    {
        if (!$thread) return $this->generateDefaultMetaTags();
        
        $title = "{$thread->title} - MRVL Community Forum";
        $description = Str::limit(strip_tags($thread->first_post_content ?? ''), 155);
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords(['forum', 'discussion', 'community', 'marvel rivals']),
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'website',
            'twitter:card' => 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'canonical' => "/forum/threads/{$thread->id}",
            'schema' => $this->generateForumSchema($thread)
        ];
    }
    
    /**
     * Generate default meta tags for homepage and generic pages
     */
    private function generateDefaultMetaTags()
    {
        return [
            'title' => 'MRVL - Marvel Rivals Esports Platform',
            'description' => 'The ultimate Marvel Rivals competitive gaming platform. Live tournaments, team rankings, player statistics, and community hub for esports enthusiasts.',
            'keywords' => 'Marvel Rivals, esports, tournaments, competitive gaming, team rankings, player stats, MRVL',
            'og:title' => 'MRVL - Marvel Rivals Esports Platform',
            'og:description' => 'The premier destination for Marvel Rivals competitive gaming',
            'og:type' => 'website',
            'og:image' => '/og-image.jpg',
            'og:url' => 'https://mrvl.gg',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => 'MRVL - Marvel Rivals Platform',
            'twitter:description' => 'The premier Marvel Rivals esports platform',
            'twitter:site' => '@MRVLesports',
            'canonical' => '/',
            'schema' => $this->generateOrganizationSchema()
        ];
    }
    
    /**
     * Generate keywords from array
     */
    private function generateKeywords($keywords)
    {
        $filtered = array_filter($keywords, function($keyword) {
            return !empty($keyword) && strlen($keyword) > 2;
        });
        
        return implode(', ', array_slice($filtered, 0, 10));
    }
    
    /**
     * Generate match image URL
     */
    private function generateMatchImage($match)
    {
        if (isset($match->team1_logo) && isset($match->team2_logo)) {
            // Could generate a composite image server-side
            return "/api/og-image/match/{$match->id}";
        }
        return '/default-match.jpg';
    }
    
    /**
     * Generate JSON-LD structured data for matches
     */
    private function generateMatchSchema($match)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => "{$match->team1_name} vs {$match->team2_name}",
            'startDate' => $match->scheduled_at,
            'location' => [
                '@type' => 'VirtualLocation',
                'name' => 'MRVL Platform'
            ],
            'competitor' => [
                ['@type' => 'SportsTeam', 'name' => $match->team1_name],
                ['@type' => 'SportsTeam', 'name' => $match->team2_name]
            ],
            'sport' => 'Esports - Marvel Rivals'
        ];
    }
    
    /**
     * Generate JSON-LD structured data for tournaments
     */
    private function generateTournamentSchema($tournament)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $tournament->name,
            'startDate' => $tournament->start_date,
            'endDate' => $tournament->end_date,
            'location' => [
                '@type' => 'VirtualLocation',
                'name' => 'MRVL Platform'
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => $tournament->organizer ?? 'MRVL'
            ],
            'sport' => 'Esports - Marvel Rivals',
            'numberOfParticipants' => $tournament->team_count
        ];
    }
    
    /**
     * Generate JSON-LD structured data for teams
     */
    private function generateTeamSchema($team)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SportsTeam',
            'name' => $team->name,
            'alternateName' => $team->short_name,
            'logo' => $team->logo,
            'sport' => 'Esports - Marvel Rivals',
            'memberOf' => [
                '@type' => 'SportsOrganization',
                'name' => 'MRVL League'
            ]
        ];
    }
    
    /**
     * Generate JSON-LD structured data for players
     */
    private function generatePlayerSchema($player)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $player->real_name ?? $player->username,
            'alternateName' => $player->username,
            'image' => $player->avatar,
            'jobTitle' => "{$player->role} Player",
            'memberOf' => [
                '@type' => 'SportsTeam',
                'name' => $player->team_name ?? 'Free Agent'
            ],
            'nationality' => $player->country
        ];
    }
    
    /**
     * Generate JSON-LD structured data for articles
     */
    private function generateArticleSchema($article)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article->title,
            'description' => Str::limit(strip_tags($article->content), 155),
            'image' => $article->featured_image,
            'datePublished' => $article->published_at,
            'dateModified' => $article->updated_at,
            'author' => [
                '@type' => 'Person',
                'name' => $article->author_name ?? 'MRVL Editorial'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'MRVL',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => '/logo.svg'
                ]
            ]
        ];
    }
    
    /**
     * Generate JSON-LD structured data for forum threads
     */
    private function generateForumSchema($thread)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => $thread->title,
            'text' => Str::limit(strip_tags($thread->first_post_content ?? ''), 155),
            'dateCreated' => $thread->created_at,
            'author' => [
                '@type' => 'Person',
                'name' => $thread->author_name ?? 'Anonymous'
            ],
            'interactionStatistic' => [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/CommentAction',
                'userInteractionCount' => $thread->reply_count ?? 0
            ]
        ];
    }
    
    /**
     * Generate JSON-LD structured data for organization
     */
    private function generateOrganizationSchema()
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'MRVL',
            'url' => 'https://mrvl.gg',
            'logo' => '/logo.svg',
            'description' => 'The ultimate Marvel Rivals esports platform',
            'sameAs' => [
                'https://twitter.com/MRVLesports',
                'https://discord.gg/mrvl',
                'https://youtube.com/@MRVLesports'
            ]
        ];
    }
    
    /**
     * Generate sitemap entries
     */
    public function generateSitemap()
    {
        $sitemap = [];
        
        // Add static pages
        $sitemap[] = ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'];
        $sitemap[] = ['url' => '/tournaments', 'priority' => 0.9, 'changefreq' => 'daily'];
        $sitemap[] = ['url' => '/teams', 'priority' => 0.8, 'changefreq' => 'weekly'];
        $sitemap[] = ['url' => '/players', 'priority' => 0.8, 'changefreq' => 'weekly'];
        $sitemap[] = ['url' => '/news', 'priority' => 0.7, 'changefreq' => 'daily'];
        $sitemap[] = ['url' => '/forum', 'priority' => 0.7, 'changefreq' => 'hourly'];
        
        // Add dynamic content
        $this->addDynamicSitemapEntries($sitemap);
        
        return $sitemap;
    }
    
    /**
     * Add dynamic content to sitemap
     */
    private function addDynamicSitemapEntries(&$sitemap)
    {
        // Add recent tournaments
        $tournaments = Cache::remember('sitemap_tournaments', 3600, function() {
            return \App\Models\Tournament::where('status', 'active')
                ->orWhere('status', 'completed')
                ->orderBy('updated_at', 'desc')
                ->limit(100)
                ->get(['id', 'updated_at']);
        });
        
        foreach ($tournaments as $tournament) {
            $sitemap[] = [
                'url' => "/tournaments/{$tournament->id}",
                'priority' => 0.7,
                'changefreq' => 'weekly',
                'lastmod' => $tournament->updated_at
            ];
        }
        
        // Add teams
        $teams = Cache::remember('sitemap_teams', 3600, function() {
            return \App\Models\Team::where('matches_played', '>', 0)
                ->orderBy('elo_rating', 'desc')
                ->limit(200)
                ->get(['id', 'updated_at']);
        });
        
        foreach ($teams as $team) {
            $sitemap[] = [
                'url' => "/teams/{$team->id}",
                'priority' => 0.6,
                'changefreq' => 'weekly',
                'lastmod' => $team->updated_at
            ];
        }
        
        // Add recent news
        $news = Cache::remember('sitemap_news', 3600, function() {
            return \App\Models\News::where('status', 'published')
                ->orderBy('published_at', 'desc')
                ->limit(100)
                ->get(['id', 'updated_at']);
        });
        
        foreach ($news as $article) {
            $sitemap[] = [
                'url' => "/news/{$article->id}",
                'priority' => 0.6,
                'changefreq' => 'monthly',
                'lastmod' => $article->updated_at
            ];
        }
    }
    
    /**
     * Preload critical resources for performance
     */
    public function getPreloadResources()
    {
        return [
            ['href' => '/static/css/main.css', 'as' => 'style'],
            ['href' => '/static/js/main.js', 'as' => 'script'],
            ['href' => '/favicon.svg', 'as' => 'image', 'type' => 'image/svg+xml'],
            ['href' => 'https://fonts.bunny.net', 'as' => 'preconnect', 'crossorigin' => true]
        ];
    }
    
    /**
     * Get optimized robots.txt content
     */
    public function getRobotsTxt()
    {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /login\n";
        $content .= "Disallow: /register\n";
        $content .= "Disallow: /search?*\n";
        $content .= "Crawl-delay: 1\n\n";
        $content .= "Sitemap: https://mrvl.gg/sitemap.xml\n";
        
        return $content;
    }
}