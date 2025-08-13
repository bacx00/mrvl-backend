<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventManagementService
{
    /**
     * Create event from template
     */
    public function createFromTemplate(array $templateData, User $organizer): Event
    {
        DB::beginTransaction();
        
        try {
            $slug = $this->generateUniqueSlug($templateData['name']);
            
            $event = Event::create([
                'name' => $templateData['name'],
                'slug' => $slug,
                'description' => $templateData['description'],
                'type' => $templateData['type'],
                'tier' => $templateData['tier'] ?? 'B',
                'format' => $templateData['format'],
                'region' => $templateData['region'],
                'game_mode' => $templateData['game_mode'],
                'max_teams' => $templateData['max_teams'],
                'prize_pool' => $templateData['prize_pool'] ?? 0,
                'currency' => $templateData['currency'] ?? 'USD',
                'prize_distribution' => $templateData['prize_distribution'] ?? [],
                'rules' => $templateData['rules'] ?? null,
                'registration_requirements' => $templateData['registration_requirements'] ?? [],
                'streams' => $templateData['streams'] ?? [],
                'social_links' => $templateData['social_links'] ?? [],
                'timezone' => $templateData['timezone'] ?? 'UTC',
                'organizer_id' => $organizer->id,
                'status' => 'upcoming',
                'featured' => $templateData['featured'] ?? false,
                'public' => $templateData['public'] ?? true,
                // Template specific dates will be calculated
                'start_date' => $this->calculateStartDate($templateData),
                'end_date' => $this->calculateEndDate($templateData),
                'registration_start' => $this->calculateRegistrationStart($templateData),
                'registration_end' => $this->calculateRegistrationEnd($templateData)
            ]);

            // Apply template-specific configurations
            $this->applyTemplateConfigurations($event, $templateData);
            
            DB::commit();
            Log::info('Event created from template', ['event_id' => $event->id, 'template' => $templateData['template_type'] ?? 'custom']);
            
            return $event;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create event from template: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clone existing event
     */
    public function cloneEvent(Event $sourceEvent, array $overrides = []): Event
    {
        DB::beginTransaction();
        
        try {
            $cloneName = $overrides['name'] ?? $sourceEvent->name . ' (Copy)';
            $slug = $this->generateUniqueSlug($cloneName);
            
            $clonedEvent = Event::create([
                'name' => $cloneName,
                'slug' => $slug,
                'description' => $overrides['description'] ?? $sourceEvent->description,
                'type' => $overrides['type'] ?? $sourceEvent->type,
                'tier' => $overrides['tier'] ?? $sourceEvent->tier,
                'format' => $overrides['format'] ?? $sourceEvent->format,
                'region' => $overrides['region'] ?? $sourceEvent->region,
                'game_mode' => $overrides['game_mode'] ?? $sourceEvent->game_mode,
                'max_teams' => $overrides['max_teams'] ?? $sourceEvent->max_teams,
                'prize_pool' => $overrides['prize_pool'] ?? $sourceEvent->prize_pool,
                'currency' => $overrides['currency'] ?? $sourceEvent->currency,
                'prize_distribution' => $overrides['prize_distribution'] ?? $sourceEvent->prize_distribution,
                'rules' => $overrides['rules'] ?? $sourceEvent->rules,
                'registration_requirements' => $overrides['registration_requirements'] ?? $sourceEvent->registration_requirements,
                'streams' => $overrides['streams'] ?? $sourceEvent->streams,
                'social_links' => $overrides['social_links'] ?? $sourceEvent->social_links,
                'timezone' => $overrides['timezone'] ?? $sourceEvent->timezone,
                'organizer_id' => $sourceEvent->organizer_id,
                'status' => 'upcoming',
                'featured' => false, // Cloned events are not featured by default
                'public' => $overrides['public'] ?? $sourceEvent->public,
                // New dates for cloned event
                'start_date' => $overrides['start_date'] ?? Carbon::now()->addWeeks(2),
                'end_date' => $overrides['end_date'] ?? Carbon::now()->addWeeks(3),
                'registration_start' => $overrides['registration_start'] ?? Carbon::now()->addDays(1),
                'registration_end' => $overrides['registration_end'] ?? Carbon::now()->addWeeks(1)
            ]);

            // Clone related data if specified
            if ($overrides['clone_teams'] ?? false) {
                $this->cloneEventTeams($sourceEvent, $clonedEvent);
            }
            
            DB::commit();
            Log::info('Event cloned successfully', ['source_id' => $sourceEvent->id, 'clone_id' => $clonedEvent->id]);
            
            return $clonedEvent;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to clone event: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create multi-stage event system
     */
    public function createMultiStageEvent(array $eventData, array $stages): Event
    {
        DB::beginTransaction();
        
        try {
            // Create main event
            $mainEvent = $this->createFromTemplate($eventData, User::find($eventData['organizer_id']));
            
            // Create sub-events for each stage
            foreach ($stages as $index => $stage) {
                $stageEvent = Event::create([
                    'name' => $stage['name'],
                    'slug' => $this->generateUniqueSlug($stage['name']),
                    'description' => $stage['description'] ?? "Stage " . ($index + 1) . " of " . $mainEvent->name,
                    'type' => $stage['type'] ?? $mainEvent->type,
                    'tier' => $stage['tier'] ?? $mainEvent->tier,
                    'format' => $stage['format'] ?? $mainEvent->format,
                    'region' => $mainEvent->region,
                    'game_mode' => $mainEvent->game_mode,
                    'max_teams' => $stage['max_teams'] ?? $mainEvent->max_teams,
                    'start_date' => Carbon::parse($stage['start_date']),
                    'end_date' => Carbon::parse($stage['end_date']),
                    'registration_start' => Carbon::parse($stage['registration_start'] ?? $stage['start_date'])->subDays(7),
                    'registration_end' => Carbon::parse($stage['registration_end'] ?? $stage['start_date'])->subDays(1),
                    'timezone' => $mainEvent->timezone,
                    'organizer_id' => $mainEvent->organizer_id,
                    'status' => 'upcoming',
                    'public' => $stage['public'] ?? true,
                    // Link to parent event
                    'parent_event_id' => $mainEvent->id,
                    'stage_order' => $index + 1,
                    'advancement_count' => $stage['advancement_count'] ?? 0
                ]);
            }
            
            DB::commit();
            Log::info('Multi-stage event created', ['main_event_id' => $mainEvent->id, 'stages' => count($stages)]);
            
            return $mainEvent;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create multi-stage event: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create recurring event series
     */
    public function createRecurringEvents(array $eventData, array $recurringConfig): array
    {
        $events = [];
        $startDate = Carbon::parse($recurringConfig['start_date']);
        $endDate = Carbon::parse($recurringConfig['end_date']);
        $interval = $recurringConfig['interval']; // 'weekly', 'monthly', etc.
        $count = $recurringConfig['count'] ?? 0;
        
        DB::beginTransaction();
        
        try {
            $currentDate = $startDate->copy();
            $eventNumber = 1;
            
            while (($count > 0 && $eventNumber <= $count) || ($count === 0 && $currentDate->lte($endDate))) {
                $eventName = $eventData['name'] . " #" . $eventNumber;
                
                $recurringEvent = Event::create([
                    'name' => $eventName,
                    'slug' => $this->generateUniqueSlug($eventName),
                    'description' => $eventData['description'],
                    'type' => $eventData['type'],
                    'tier' => $eventData['tier'] ?? 'B',
                    'format' => $eventData['format'],
                    'region' => $eventData['region'],
                    'game_mode' => $eventData['game_mode'],
                    'max_teams' => $eventData['max_teams'],
                    'start_date' => $currentDate->copy(),
                    'end_date' => $currentDate->copy()->addHours($eventData['duration_hours'] ?? 8),
                    'registration_start' => $currentDate->copy()->subDays(7),
                    'registration_end' => $currentDate->copy()->subDays(1),
                    'timezone' => $eventData['timezone'] ?? 'UTC',
                    'organizer_id' => $eventData['organizer_id'],
                    'status' => 'upcoming',
                    'public' => $eventData['public'] ?? true,
                    'series_id' => $eventData['series_id'] ?? null,
                    'series_number' => $eventNumber
                ]);
                
                $events[] = $recurringEvent;
                
                // Calculate next date
                switch ($interval) {
                    case 'daily':
                        $currentDate->addDay();
                        break;
                    case 'weekly':
                        $currentDate->addWeek();
                        break;
                    case 'monthly':
                        $currentDate->addMonth();
                        break;
                    default:
                        $currentDate->addWeek();
                }
                
                $eventNumber++;
            }
            
            DB::commit();
            Log::info('Recurring events created', ['count' => count($events), 'interval' => $interval]);
            
            return $events;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create recurring events: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get event templates
     */
    public function getEventTemplates(): array
    {
        return [
            'championship' => [
                'name' => 'Championship Tournament',
                'type' => 'championship',
                'tier' => 'S',
                'format' => 'double_elimination',
                'max_teams' => 32,
                'game_mode' => '6v6',
                'prize_pool' => 50000,
                'currency' => 'USD',
                'prize_distribution' => [
                    '1st' => 0.5,
                    '2nd' => 0.3,
                    '3rd' => 0.15,
                    '4th' => 0.05
                ],
                'registration_requirements' => [
                    'min_players' => 6,
                    'max_players' => 10,
                    'min_elo' => 2000,
                    'verified_team' => true
                ]
            ],
            'qualifier' => [
                'name' => 'Regional Qualifier',
                'type' => 'qualifier',
                'tier' => 'A',
                'format' => 'swiss',
                'max_teams' => 64,
                'game_mode' => '6v6',
                'advancement_count' => 8,
                'registration_requirements' => [
                    'min_players' => 6,
                    'max_players' => 8,
                    'region_restricted' => true
                ]
            ],
            'community' => [
                'name' => 'Community Cup',
                'type' => 'community',
                'tier' => 'B',
                'format' => 'single_elimination',
                'max_teams' => 16,
                'game_mode' => '6v6',
                'prize_pool' => 1000,
                'public' => true,
                'registration_requirements' => [
                    'min_players' => 6,
                    'max_players' => 8
                ]
            ],
            'scrim' => [
                'name' => 'Practice Scrimmage',
                'type' => 'scrim',
                'tier' => 'C',
                'format' => 'round_robin',
                'max_teams' => 8,
                'game_mode' => '6v6',
                'public' => true,
                'registration_requirements' => [
                    'min_players' => 6,
                    'max_players' => 8
                ]
            ]
        ];
    }

    /**
     * Optimize event listing with advanced filtering and caching
     */
    public function getOptimizedEventsList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $cacheKey = 'events_list_' . md5(serialize($filters) . $page . $perPage);
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $page, $perPage) {
            $query = Event::query()
                ->with(['organizer:id,name,avatar', 'teams:id,name,short_name,logo'])
                ->select([
                    'id', 'name', 'slug', 'description', 'logo', 'type', 'tier', 'format',
                    'region', 'status', 'start_date', 'end_date', 'max_teams', 'prize_pool',
                    'currency', 'featured', 'public', 'organizer_id', 'views', 'created_at'
                ]);

            // Apply filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }
            
            if (!empty($filters['tier'])) {
                $query->where('tier', $filters['tier']);
            }
            
            if (!empty($filters['region'])) {
                $query->where('region', $filters['region']);
            }
            
            if (!empty($filters['format'])) {
                $query->where('format', $filters['format']);
            }
            
            if (!empty($filters['featured'])) {
                $query->where('featured', true);
            }
            
            if (!empty($filters['search'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('name', 'LIKE', "%{$filters['search']}%")
                      ->orWhere('description', 'LIKE', "%{$filters['search']}%");
                });
            }
            
            if (!empty($filters['prize_min'])) {
                $query->where('prize_pool', '>=', $filters['prize_min']);
            }
            
            if (!empty($filters['date_from'])) {
                $query->where('start_date', '>=', $filters['date_from']);
            }
            
            if (!empty($filters['date_to'])) {
                $query->where('start_date', '<=', $filters['date_to']);
            }

            // Sorting
            $sortBy = $filters['sort'] ?? 'start_date';
            $sortDir = $filters['direction'] ?? 'asc';
            
            switch ($sortBy) {
                case 'prize_pool':
                    $query->orderBy('prize_pool', $sortDir);
                    break;
                case 'teams':
                    $query->withCount('teams')->orderBy('teams_count', $sortDir);
                    break;
                case 'recent':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'popular':
                    $query->orderBy('views', 'desc');
                    break;
                default:
                    $query->orderBy('start_date', $sortDir);
            }

            $events = $query->paginate($perPage, ['*'], 'page', $page);
            
            // Transform data for frontend
            $transformedEvents = $events->getCollection()->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'logo' => $event->logo,
                    'type' => $event->type,
                    'tier' => $event->tier,
                    'format' => $event->format,
                    'region' => $event->region,
                    'status' => $event->status,
                    'start_date' => $event->start_date->toISOString(),
                    'end_date' => $event->end_date->toISOString(),
                    'team_count' => $event->teams->count(),
                    'max_teams' => $event->max_teams,
                    'prize_pool' => $event->prize_pool,
                    'currency' => $event->currency,
                    'formatted_prize' => $event->formatted_prize_pool,
                    'featured' => $event->featured,
                    'public' => $event->public,
                    'views' => $event->views,
                    'organizer' => $event->organizer ? [
                        'id' => $event->organizer->id,
                        'name' => $event->organizer->name,
                        'avatar' => $event->organizer->avatar
                    ] : null,
                    'registration_status' => $this->getRegistrationStatus($event),
                    'progress' => $this->calculateEventProgress($event)
                ];
            });

            return [
                'data' => $transformedEvents,
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                    'from' => $events->firstItem(),
                    'to' => $events->lastItem()
                ]
            ];
        });
    }

    // Helper methods
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;
        
        while (Event::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    private function calculateStartDate(array $templateData): Carbon
    {
        if (isset($templateData['start_date'])) {
            return Carbon::parse($templateData['start_date']);
        }
        
        // Default to 2 weeks from now
        return Carbon::now()->addWeeks(2);
    }

    private function calculateEndDate(array $templateData): Carbon
    {
        if (isset($templateData['end_date'])) {
            return Carbon::parse($templateData['end_date']);
        }
        
        $startDate = $this->calculateStartDate($templateData);
        $duration = $templateData['duration_days'] ?? 3;
        
        return $startDate->copy()->addDays($duration);
    }

    private function calculateRegistrationStart(array $templateData): Carbon
    {
        if (isset($templateData['registration_start'])) {
            return Carbon::parse($templateData['registration_start']);
        }
        
        return $this->calculateStartDate($templateData)->subWeeks(2);
    }

    private function calculateRegistrationEnd(array $templateData): Carbon
    {
        if (isset($templateData['registration_end'])) {
            return Carbon::parse($templateData['registration_end']);
        }
        
        return $this->calculateStartDate($templateData)->subDays(1);
    }

    private function applyTemplateConfigurations(Event $event, array $templateData): void
    {
        // Apply any template-specific post-creation configurations
        if (isset($templateData['auto_seed']) && $templateData['auto_seed']) {
            // Enable auto-seeding based on team ratings
            $event->update(['seeding_data' => ['method' => 'rating', 'auto' => true]]);
        }
        
        if (isset($templateData['auto_bracket']) && $templateData['auto_bracket']) {
            // Enable auto-bracket generation when registration closes
            $event->update(['bracket_data' => ['auto_generate' => true]]);
        }
    }

    private function cloneEventTeams(Event $sourceEvent, Event $clonedEvent): void
    {
        foreach ($sourceEvent->teams as $team) {
            $clonedEvent->teams()->attach($team->id, [
                'seed' => null, // Reset seeds for cloned event
                'status' => 'registered',
                'registered_at' => now()
            ]);
        }
    }

    private function getRegistrationStatus(Event $event): string
    {
        $now = Carbon::now();
        
        if ($event->registration_start && $now->lt($event->registration_start)) {
            return 'not_open';
        }
        
        if ($event->registration_end && $now->gt($event->registration_end)) {
            return 'closed';
        }
        
        if ($event->teams->count() >= $event->max_teams) {
            return 'full';
        }
        
        return 'open';
    }

    private function calculateEventProgress(Event $event): array
    {
        $totalMatches = $event->matches()->count();
        $completedMatches = $event->matches()->where('status', 'completed')->count();
        
        return [
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'percentage' => $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100, 1) : 0
        ];
    }
}