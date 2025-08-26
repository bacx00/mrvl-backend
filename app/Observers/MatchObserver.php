<?php

namespace App\Observers;

use App\Models\MvrlMatch;
use App\Services\MatchPlayerStatsService;
use Illuminate\Support\Facades\Log;

class MatchObserver
{
    protected $statsService;
    
    public function __construct(MatchPlayerStatsService $statsService)
    {
        $this->statsService = $statsService;
    }
    
    /**
     * Handle the Match "created" event.
     */
    public function created(MvrlMatch $match)
    {
        $this->syncStats($match);
    }
    
    /**
     * Handle the Match "updated" event.
     */
    public function updated(MvrlMatch $match)
    {
        // Only sync if maps_data or status changed
        if ($match->isDirty(['maps_data', 'status'])) {
            $this->syncStats($match);
        }
    }
    
    /**
     * Handle the Match "saved" event.
     */
    public function saved(MvrlMatch $match)
    {
        // Additional sync point for safety
        if ($match->status === 'completed') {
            $this->syncStats($match);
        }
    }
    
    /**
     * Sync match stats
     */
    private function syncStats(MvrlMatch $match)
    {
        try {
            Log::info("Auto-syncing stats for match {$match->id}");
            $this->statsService->syncMatchStats($match->id);
        } catch (\Exception $e) {
            Log::error("Failed to auto-sync stats for match {$match->id}: " . $e->getMessage());
        }
    }
}