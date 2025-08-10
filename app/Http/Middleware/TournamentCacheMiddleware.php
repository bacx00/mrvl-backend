<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\EnhancedTournamentCacheService;
use App\Models\Tournament;
use Illuminate\Support\Facades\Log;

class TournamentCacheMiddleware
{
    protected $cacheService;

    public function __construct(EnhancedTournamentCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$options): mixed
    {
        $cacheStrategy = $options[0] ?? 'default';
        
        // Handle cache reading before processing
        $cachedResponse = $this->handleCacheRead($request, $cacheStrategy);
        if ($cachedResponse) {
            return $cachedResponse;
        }

        $response = $next($request);

        // Handle cache writing after processing
        $this->handleCacheWrite($request, $response, $cacheStrategy);

        return $response;
    }

    /**
     * Handle cache reading
     */
    private function handleCacheRead(Request $request, string $strategy): ?JsonResponse
    {
        try {
            switch ($strategy) {
                case 'tournament_list':
                    return $this->handleTournamentListCache($request);
                    
                case 'tournament_details':
                    return $this->handleTournamentDetailsCache($request);
                    
                case 'tournament_statistics':
                    return $this->handleTournamentStatsCache($request);
                    
                case 'tournament_bracket':
                    return $this->handleTournamentBracketCache($request);
                    
                case 'user_tournaments':
                    return $this->handleUserTournamentsCache($request);
                    
                case 'admin_dashboard':
                    return $this->handleAdminDashboardCache($request);
                    
                default:
                    return null;
            }
        } catch (\Exception $e) {
            Log::warning('Tournament cache read failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle cache writing
     */
    private function handleCacheWrite(Request $request, $response, string $strategy): void
    {
        try {
            if (!$response instanceof JsonResponse || $response->getStatusCode() !== 200) {
                return;
            }

            $data = json_decode($response->getContent(), true);
            if (!$data || !isset($data['success']) || !$data['success']) {
                return;
            }

            switch ($strategy) {
                case 'tournament_list':
                    $this->cacheTournamentListResponse($request, $data);
                    break;
                    
                case 'tournament_details':
                    $this->cacheTournamentDetailsResponse($request, $data);
                    break;
                    
                case 'tournament_statistics':
                    $this->cacheTournamentStatsResponse($request, $data);
                    break;
                    
                case 'tournament_bracket':
                    $this->cacheTournamentBracketResponse($request, $data);
                    break;
                    
                case 'user_tournaments':
                    $this->cacheUserTournamentsResponse($request, $data);
                    break;
                    
                case 'admin_dashboard':
                    $this->cacheAdminDashboardResponse($request, $data);
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('Tournament cache write failed: ' . $e->getMessage());
        }
    }

    private function handleTournamentListCache(Request $request): ?JsonResponse
    {
        $filters = [
            'type' => $request->get('type'),
            'format' => $request->get('format'),
            'status' => $request->get('status'),
            'region' => $request->get('region'),
            'limit' => $request->get('limit', 20),
            'offset' => $request->get('offset', 0),
            'search' => $request->get('search'),
        ];
        
        $cached = $this->cacheService->getCachedTournamentList($filters);
        
        if ($cached) {
            return response()->json([
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'cache_time' => $cached['cached_at'] ?? null
            ]);
        }
        
        return null;
    }

    private function handleTournamentDetailsCache(Request $request): ?JsonResponse
    {
        $tournamentId = $this->extractTournamentId($request);
        
        if (!$tournamentId) {
            return null;
        }
        
        $cached = $this->cacheService->getCachedTournamentDetails($tournamentId);
        
        if ($cached) {
            return response()->json([
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'cache_time' => $cached['cached_at'] ?? null
            ]);
        }
        
        return null;
    }

    private function handleTournamentStatsCache(Request $request): ?JsonResponse
    {
        $tournamentId = $this->extractTournamentId($request);
        
        if (!$tournamentId) {
            return null;
        }
        
        $cached = $this->cacheService->getCachedTournamentStatistics($tournamentId);
        
        if ($cached) {
            return response()->json([
                'success' => true,
                'data' => $cached,
                'cached' => true
            ]);
        }
        
        return null;
    }

    private function handleTournamentBracketCache(Request $request): ?JsonResponse
    {
        $tournamentId = $this->extractTournamentId($request);
        
        if (!$tournamentId) {
            return null;
        }
        
        $cached = $this->cacheService->getCachedTournamentBracketData($tournamentId);
        
        if ($cached) {
            return response()->json([
                'success' => true,
                'data' => $cached,
                'cached' => true
            ]);
        }
        
        return null;
    }

    private function handleUserTournamentsCache(Request $request): ?JsonResponse
    {
        $userId = auth()->id();
        
        if (!$userId) {
            return null;
        }
        
        $cached = $this->cacheService->getCachedUserTournaments($userId);
        
        if ($cached) {
            return response()->json([
                'success' => true,
                'data' => $cached,
                'cached' => true
            ]);
        }
        
        return null;
    }

    private function handleAdminDashboardCache(Request $request): ?JsonResponse
    {
        $cached = $this->cacheService->getCachedAdminDashboard();
        
        if ($cached) {
            return response()->json([
                'success' => true,
                'data' => $cached,
                'cached' => true
            ]);
        }
        
        return null;
    }

    private function cacheTournamentListResponse(Request $request, array $data): void
    {
        if (!isset($data['data'])) {
            return;
        }
        
        $filters = [
            'type' => $request->get('type'),
            'format' => $request->get('format'),
            'status' => $request->get('status'),
            'region' => $request->get('region'),
            'limit' => $request->get('limit', 20),
            'offset' => $request->get('offset', 0),
            'search' => $request->get('search'),
        ];
        
        $tournaments = collect($data['data']['tournaments'] ?? []);
        $total = $data['data']['total'] ?? $tournaments->count();
        
        $this->cacheService->cacheTournamentList($filters, $tournaments, $total);
    }

    private function cacheTournamentDetailsResponse(Request $request, array $data): void
    {
        $tournamentId = $this->extractTournamentId($request);
        
        if (!$tournamentId || !isset($data['data'])) {
            return;
        }
        
        // Cache the response data directly since it's already formatted
        $this->cacheService->cacheTournamentDetails(
            Tournament::find($tournamentId)
        );
    }

    private function cacheTournamentStatsResponse(Request $request, array $data): void
    {
        $tournamentId = $this->extractTournamentId($request);
        
        if (!$tournamentId || !isset($data['data'])) {
            return;
        }
        
        $tournament = Tournament::find($tournamentId);
        if ($tournament) {
            $this->cacheService->cacheTournamentStatistics($tournament, $data['data']);
        }
    }

    private function cacheTournamentBracketResponse(Request $request, array $data): void
    {
        $tournamentId = $this->extractTournamentId($request);
        
        if (!$tournamentId || !isset($data['data'])) {
            return;
        }
        
        $tournament = Tournament::find($tournamentId);
        if ($tournament) {
            $this->cacheService->cacheTournamentBracketData($tournament, $data['data']);
        }
    }

    private function cacheUserTournamentsResponse(Request $request, array $data): void
    {
        $userId = auth()->id();
        
        if (!$userId || !isset($data['data'])) {
            return;
        }
        
        $this->cacheService->cacheUserTournaments($userId, $data['data']);
    }

    private function cacheAdminDashboardResponse(Request $request, array $data): void
    {
        if (!isset($data['data'])) {
            return;
        }
        
        $this->cacheService->cacheAdminDashboard($data['data']);
    }

    private function extractTournamentId(Request $request): ?int
    {
        // Try to extract tournament ID from route parameters
        $tournament = $request->route('tournament');
        
        if ($tournament instanceof Tournament) {
            return $tournament->id;
        }
        
        if (is_numeric($tournament)) {
            return (int) $tournament;
        }
        
        // Try to extract from URL path
        $path = $request->path();
        if (preg_match('/tournaments\/(\d+)/', $path, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
}