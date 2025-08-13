<?php

namespace App\Http\Controllers;

use App\Services\PerformanceMonitoringService;
use App\Services\EnhancedCacheService;
use App\Services\DatabaseOptimizationService;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    protected $performanceService;
    protected $cacheService;
    protected $dbOptimizationService;
    
    public function __construct(
        PerformanceMonitoringService $performanceService,
        EnhancedCacheService $cacheService,
        DatabaseOptimizationService $dbOptimizationService
    ) {
        $this->performanceService = $performanceService;
        $this->cacheService = $cacheService;
        $this->dbOptimizationService = $dbOptimizationService;
    }
    
    /**
     * Get current performance metrics
     */
    public function getMetrics()
    {
        $metrics = $this->performanceService->captureMetrics();
        
        return response()->json([
            'status' => 'success',
            'data' => $metrics
        ]);
    }
    
    /**
     * Get performance report
     */
    public function getReport($period = 'last_hour')
    {
        $report = $this->performanceService->getPerformanceReport($period);
        
        return response()->json([
            'status' => 'success',
            'data' => $report
        ]);
    }
    
    /**
     * Run performance optimization
     */
    public function runOptimization(Request $request)
    {
        // Check if user is admin
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $results = $this->performanceService->runOptimization();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Optimization completed',
            'results' => $results
        ]);
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats()
    {
        $stats = $this->cacheService->getStatistics();
        
        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
    
    /**
     * Clear cache
     */
    public function clearCache(Request $request)
    {
        // Check if user is admin
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $type = $request->input('type', 'all');
        
        if ($type === 'all') {
            $result = $this->cacheService->clearAll();
        } else {
            $tags = $request->input('tags', []);
            $this->cacheService->invalidateByTags($tags);
            $result = ['status' => 'success', 'message' => 'Cache tags invalidated'];
        }
        
        return response()->json($result);
    }
    
    /**
     * Warm up cache
     */
    public function warmUpCache(Request $request)
    {
        // Check if user is admin
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $result = $this->cacheService->warmUp();
        
        return response()->json($result);
    }
}