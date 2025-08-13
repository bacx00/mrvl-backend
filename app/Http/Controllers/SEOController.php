<?php

namespace App\Http\Controllers;

use App\Services\SEOOptimizationService;
use App\Services\EnhancedCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SEOController extends Controller
{
    protected $seoService;
    protected $cacheService;
    
    public function __construct(SEOOptimizationService $seoService, EnhancedCacheService $cacheService)
    {
        $this->seoService = $seoService;
        $this->cacheService = $cacheService;
    }
    
    /**
     * Get meta tags for a specific page
     */
    public function getMetaTags(Request $request, $type, $id = null)
    {
        $cacheKey = "seo_meta_{$type}_{$id}";
        
        $metaTags = $this->cacheService->remember($cacheKey, 3600, function() use ($type, $id) {
            $data = null;
            
            if ($id) {
                switch ($type) {
                    case 'match':
                        $data = \App\Models\MvrlMatch::find($id);
                        break;
                    case 'tournament':
                        $data = \App\Models\Tournament::find($id);
                        break;
                    case 'team':
                        $data = \App\Models\Team::find($id);
                        break;
                    case 'player':
                        $data = \App\Models\Player::find($id);
                        break;
                    case 'news':
                        $data = \App\Models\News::find($id);
                        break;
                    case 'forum':
                        $data = \App\Models\ForumThread::find($id);
                        break;
                }
            }
            
            return $this->seoService->generateMetaTags($type, $data);
        }, ['seo']);
        
        return response()->json($metaTags);
    }
    
    /**
     * Generate sitemap
     */
    public function sitemap()
    {
        $sitemap = $this->cacheService->remember('sitemap', 3600, function() {
            return $this->seoService->generateSitemap();
        }, ['seo']);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($sitemap as $entry) {
            $xml .= '<url>';
            $xml .= '<loc>https://mrvl.gg' . $entry['url'] . '</loc>';
            
            if (isset($entry['lastmod'])) {
                $xml .= '<lastmod>' . $entry['lastmod'] . '</lastmod>';
            }
            
            $xml .= '<changefreq>' . $entry['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $entry['priority'] . '</priority>';
            $xml .= '</url>';
        }
        
        $xml .= '</urlset>';
        
        return Response::make($xml, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }
    
    /**
     * Generate robots.txt
     */
    public function robots()
    {
        $content = $this->seoService->getRobotsTxt();
        
        return Response::make($content, 200, [
            'Content-Type' => 'text/plain'
        ]);
    }
    
    /**
     * Get preload resources for performance
     */
    public function getPreloadResources()
    {
        $resources = $this->seoService->getPreloadResources();
        
        return response()->json($resources);
    }
}