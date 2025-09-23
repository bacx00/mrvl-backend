<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TournamentBracketMasterService
{
    private $singleElimination;
    private $doubleElimination;
    private $swissSystem;
    private $roundRobin;
    private $groupStage;

    public function __construct()
    {
        $this->singleElimination = new SingleEliminationService();
        $this->doubleElimination = new DoubleEliminationService();
        $this->swissSystem = app(SwissSystemService::class);
        $this->roundRobin = new RoundRobinService();
        $this->groupStage = new GroupStageService();
    }

    public function generateBracket($eventId, $format, $teams, $options = [])
    {
        DB::beginTransaction();

        try {
            // Clear existing matches
            DB::table('matches')->where('event_id', $eventId)->delete();

            $matches = [];

            switch ($format) {
                case 'single_elimination':
                    $matches = $this->singleElimination->generateBracket($eventId, $teams);
                    break;

                case 'double_elimination':
                    $matches = $this->doubleElimination->generateBracket($eventId, $teams);
                    break;

                case 'swiss':
                case 'swiss_system':
                    $matches = $this->swissSystem->generateBracket($eventId, $teams);
                    break;

                case 'round_robin':
                    $matches = $this->roundRobin->generateBracket($eventId, $teams);
                    break;

                case 'group_stage':
                    $matches = $this->groupStage->generateBracket($eventId, $teams);
                    break;

                default:
                    throw new \Exception("Unsupported tournament format: {$format}");
            }

            // Save matches to database
            foreach ($matches as $match) {
                DB::table('matches')->insert($match);
            }

            // Update event format and status
            DB::table('events')->where('id', $eventId)->update([
                'format' => $format,
                'status' => 'ongoing',
                'bracket_generated_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            return [
                'success' => true,
                'matches_created' => count($matches),
                'format' => $format,
                'teams_count' => count($teams)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getBracketStructure($eventId, $format)
    {
        switch ($format) {
            case 'single_elimination':
                return $this->singleElimination->getBracketStructure($eventId);

            case 'double_elimination':
                return $this->doubleElimination->getBracketStructure($eventId);

            case 'swiss':
            case 'swiss_system':
                return $this->swissSystem->getBracketStructure($eventId);

            case 'round_robin':
                return $this->roundRobin->getBracketStructure($eventId);

            case 'group_stage':
                return $this->groupStage->getBracketStructure($eventId);

            default:
                throw new \Exception("Unsupported tournament format: {$format}");
        }
    }

    public function advanceWinner($match, $winnerId)
    {
        $event = DB::table('events')->where('id', $match->event_id)->first();

        if (!$event) {
            throw new \Exception('Event not found');
        }

        switch ($event->format) {
            case 'single_elimination':
                $this->singleElimination->advanceWinner($match, $winnerId);
                break;

            case 'double_elimination':
                $this->doubleElimination->advanceWinner($match, $winnerId);
                break;

            case 'swiss':
            case 'swiss_system':
                // Swiss system doesn't have traditional advancement
                break;

            case 'round_robin':
            case 'group_stage':
                // Round robin and group stage don't have advancement
                break;

            default:
                throw new \Exception("Unsupported tournament format for advancement: {$event->format}");
        }

        // Clear bracket cache
        $this->clearBracketCache($match->event_id);
    }

    public function moveLoserToLowerBracket($match, $loserId)
    {
        $event = DB::table('events')->where('id', $match->event_id)->first();

        if ($event && $event->format === 'double_elimination') {
            $this->doubleElimination->moveLoserToLowerBracket($match, $loserId);
            $this->clearBracketCache($match->event_id);
        }
    }

    public function calculateStandings($eventId, $format)
    {
        switch ($format) {
            case 'swiss':
            case 'swiss_system':
                return $this->swissSystem->calculateStandings($eventId);

            case 'round_robin':
                return $this->roundRobin->calculateStandings($eventId);

            case 'group_stage':
                return $this->groupStage->calculateGroupStandings($eventId);

            case 'single_elimination':
            case 'double_elimination':
                return $this->calculateEliminationStandings($eventId);

            default:
                throw new \Exception("Standings not supported for format: {$format}");
        }
    }

    private function calculateEliminationStandings($eventId)
    {
        // Get teams ordered by how far they progressed
        $teams = DB::table('event_teams')
            ->join('teams', 'event_teams.team_id', '=', 'teams.id')
            ->where('event_teams.event_id', $eventId)
            ->select('teams.id', 'teams.name', 'teams.short_name', 'teams.logo')
            ->get();

        $standings = [];
        foreach ($teams as $team) {
            $matches = DB::table('matches')
                ->where('event_id', $eventId)
                ->where(function($query) use ($team) {
                    $query->where('team1_id', $team->id)->orWhere('team2_id', $team->id);
                })
                ->where('status', 'completed')
                ->get();

            $wins = 0;
            $losses = 0;
            $lastRound = 0;

            foreach ($matches as $match) {
                $lastRound = max($lastRound, $match->round);

                if ($match->team1_id == $team->id) {
                    if ($match->team1_score > $match->team2_score) {
                        $wins++;
                    } else {
                        $losses++;
                    }
                } else {
                    if ($match->team2_score > $match->team1_score) {
                        $wins++;
                    } else {
                        $losses++;
                    }
                }
            }

            $standings[] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'team_short_name' => $team->short_name,
                'team_logo' => $team->logo,
                'wins' => $wins,
                'losses' => $losses,
                'last_round' => $lastRound,
                'eliminated' => $losses > 0
            ];
        }

        // Sort by progression (last round reached, then wins)
        usort($standings, function($a, $b) {
            if ($a['last_round'] != $b['last_round']) {
                return $b['last_round'] - $a['last_round'];
            }
            return $b['wins'] - $a['wins'];
        });

        return $standings;
    }

    public function isComplete($eventId, $format)
    {
        switch ($format) {
            case 'round_robin':
                return $this->roundRobin->isComplete($eventId);

            case 'group_stage':
                return $this->groupStage->isAllGroupsComplete($eventId);

            case 'single_elimination':
            case 'double_elimination':
                return $this->isEliminationComplete($eventId);

            case 'swiss':
            case 'swiss_system':
                return $this->isSwissComplete($eventId);

            default:
                return false;
        }
    }

    private function isEliminationComplete($eventId)
    {
        $pendingMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->whereIn('status', ['scheduled', 'live'])
            ->count();

        return $pendingMatches === 0;
    }

    private function isSwissComplete($eventId)
    {
        // Swiss is complete when all planned rounds are finished
        $event = DB::table('events')->where('id', $eventId)->first();
        if (!$event) return false;

        $teamCount = DB::table('event_teams')->where('event_id', $eventId)->count();
        $expectedRounds = max(3, ceil(log($teamCount, 2)));

        $completedRounds = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'swiss')
            ->where('status', 'completed')
            ->max('round') ?? 0;

        return $completedRounds >= $expectedRounds;
    }

    public function getFormatInfo($format)
    {
        $formatInfo = [
            'single_elimination' => [
                'name' => 'Single Elimination',
                'description' => 'Teams are eliminated after one loss. Fast-paced format commonly used in playoffs.',
                'min_teams' => 2,
                'supports_seeding' => true,
                'has_standings' => false,
                'stage_names' => ['Round of 32', 'Round of 16', 'Quarter-Finals', 'Semi-Finals', 'Grand Final'],
                'match_format' => 'bo3'
            ],
            'double_elimination' => [
                'name' => 'Double Elimination',
                'description' => 'Teams must lose twice to be eliminated. Upper and lower bracket system like Marvel Rivals Championship.',
                'min_teams' => 2,
                'supports_seeding' => true,
                'has_standings' => false,
                'stage_names' => ['Upper Bracket', 'Lower Bracket', 'Grand Final'],
                'match_format' => 'bo5'
            ],
            'swiss' => [
                'name' => 'Swiss System',
                'description' => 'Teams play multiple rounds with pairings based on performance. Used in Marvel Rivals Invitational.',
                'min_teams' => 4,
                'supports_seeding' => true,
                'has_standings' => true,
                'stage_names' => ['Swiss Rounds'],
                'match_format' => 'bo3'
            ],
            'round_robin' => [
                'name' => 'Round Robin',
                'description' => 'Every team plays every other team once. Comprehensive format for smaller groups.',
                'min_teams' => 3,
                'supports_seeding' => false,
                'has_standings' => true,
                'stage_names' => ['Round Robin'],
                'match_format' => 'bo3'
            ],
            'group_stage' => [
                'name' => 'Group Stage',
                'description' => 'Teams divided into groups with round robin within each group. Top teams advance.',
                'min_teams' => 4,
                'supports_seeding' => true,
                'has_standings' => true,
                'stage_names' => ['Group Stage'],
                'match_format' => 'bo3'
            ]
        ];

        return $formatInfo[$format] ?? null;
    }

    public function validateTeamCount($format, $teamCount)
    {
        $formatInfo = $this->getFormatInfo($format);

        if (!$formatInfo) {
            return ['valid' => false, 'error' => 'Invalid format'];
        }

        if ($teamCount < $formatInfo['min_teams']) {
            return [
                'valid' => false,
                'error' => "Minimum {$formatInfo['min_teams']} teams required for {$formatInfo['name']}"
            ];
        }

        // Format-specific validations
        switch ($format) {
            case 'group_stage':
                if ($teamCount < 4) {
                    return ['valid' => false, 'error' => 'Group stage requires at least 4 teams'];
                }
                break;

            case 'swiss':
                if ($teamCount % 2 !== 0 && $teamCount < 8) {
                    return ['valid' => false, 'error' => 'Swiss system works best with even number of teams'];
                }
                break;
        }

        return ['valid' => true];
    }

    private function clearBracketCache($eventId)
    {
        cache()->forget("bracket_data_{$eventId}");
        cache()->forget("bracket_metadata_{$eventId}");
        cache()->forget("event_{$eventId}");
    }

    public function getTournamentProgress($eventId, $format)
    {
        $totalMatches = DB::table('matches')->where('event_id', $eventId)->count();
        $completedMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->count();

        $progress = $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100, 1) : 0;

        return [
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'progress_percentage' => $progress,
            'is_complete' => $this->isComplete($eventId, $format),
            'format' => $format,
            'format_info' => $this->getFormatInfo($format)
        ];
    }
}