<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentPhase;
use App\Models\BracketMatch;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BracketGenerationService
{
    /**
     * Generate brackets for an entire tournament based on its format
     */
    public function generateTournamentBrackets(Tournament $tournament): ?Collection
    {
        try {
            $checkedInTeams = $tournament->teams()
                                        ->wherePivot('status', 'checked_in')
                                        ->orderBy('pivot_seed')
                                        ->get();

            if ($checkedInTeams->count() < $tournament->min_teams) {
                throw new \Exception("Insufficient teams checked in. Minimum: {$tournament->min_teams}, Checked in: {$checkedInTeams->count()}");
            }

            switch ($tournament->format) {
                case 'single_elimination':
                    return $this->generateSingleEliminationTournament($tournament, $checkedInTeams);
                
                case 'double_elimination':
                    return $this->generateDoubleEliminationTournament($tournament, $checkedInTeams);
                
                case 'swiss':
                    return $this->generateSwissTournament($tournament, $checkedInTeams);
                
                case 'round_robin':
                    return $this->generateRoundRobinTournament($tournament, $checkedInTeams);
                
                case 'group_stage_playoffs':
                    return $this->generateGroupStagePlayoffsTournament($tournament, $checkedInTeams);
                
                default:
                    throw new \Exception("Unsupported tournament format: {$tournament->format}");
            }

        } catch (\Exception $e) {
            Log::error("Tournament bracket generation failed: " . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'format' => $tournament->format
            ]);
            return null;
        }
    }

    /**
     * Generate Single Elimination Tournament
     */
    private function generateSingleEliminationTournament(Tournament $tournament, Collection $teams): Collection
    {
        $phase = $tournament->phases()->where('phase_type', 'playoffs')->first();
        if (!$phase) {
            $phase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => 'Elimination Bracket',
                'slug' => 'elimination-bracket',
                'phase_type' => 'playoffs',
                'phase_order' => 1,
                'team_count' => $teams->count(),
                'match_format' => $tournament->match_format_settings['playoffs'] ?? 'bo3'
            ]);
        }

        $bracket = TournamentBracket::create([
            'tournament_id' => $tournament->id,
            'tournament_phase_id' => $phase->id,
            'name' => 'Main Bracket',
            'bracket_type' => 'single_elimination',
            'bracket_format' => $this->determineBracketFormat($teams->count(), 'single'),
            'team_count' => $teams->count(),
            'round_count' => $this->calculateEliminationRounds($teams->count()),
            'match_settings' => [
                'format' => $tournament->match_format_settings['playoffs'] ?? 'bo3',
                'map_pool' => $tournament->map_pool ?? []
            ]
        ]);

        $this->generateSingleEliminationBracket($bracket, $teams->toArray());
        
        return collect([$bracket]);
    }

    /**
     * Generate bracket for an Event (not Tournament)
     */
    public function generateBracket($event, $format, $seedingMethod = 'rating', $shuffleSeeds = false)
    {
        try {
            $teams = $event->teams;
            
            if ($teams->count() < 2) {
                throw new \Exception("Need at least 2 teams to generate bracket");
            }

            // Create bracket stages based on format
            switch ($format) {
                case 'single_elimination':
                    return $this->generateEventSingleElimination($event, $teams, $seedingMethod, $shuffleSeeds);
                case 'double_elimination':
                    return $this->generateEventDoubleElimination($event, $teams, $seedingMethod, $shuffleSeeds);
                case 'round_robin':
                    return $this->generateEventRoundRobin($event, $teams, $seedingMethod, $shuffleSeeds);
                case 'swiss':
                    return $this->generateEventSwiss($event, $teams, $seedingMethod, $shuffleSeeds);
                default:
                    throw new \Exception("Unsupported format: {$format}");
            }
        } catch (\Exception $e) {
            Log::error("Event bracket generation failed: " . $e->getMessage(), [
                'event_id' => $event->id,
                'format' => $format
            ]);
            throw $e;
        }
    }

    /**
     * Generate Single Elimination for Event
     */
    private function generateEventSingleElimination($event, $teams, $seedingMethod, $shuffleSeeds)
    {
        // Seed teams
        $seededTeams = $this->seedTeams($teams, $seedingMethod, $shuffleSeeds);
        
        // Calculate rounds
        $rounds = ceil(log($teams->count(), 2));
        
        // Create bracket stage
        $stage = \App\Models\BracketStage::create([
            'tournament_id' => null,  // For events, tournament_id is null
            'event_id' => $event->id,
            'name' => 'Elimination Bracket',
            'type' => 'upper_bracket',  // Use 'type' instead of 'stage_type'
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teams->count(),  // Use 'max_teams' instead of 'team_count'
            'current_round' => 1,
            'total_rounds' => $rounds,  // Use 'total_rounds' instead of 'round_count'
            'settings' => [
                'seeding_method' => $seedingMethod,
                'shuffled' => $shuffleSeeds
            ]
        ]);

        // Generate matches for first round
        $matches = [];
        $matchNumber = 1;
        
        for ($i = 0; $i < count($seededTeams); $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                $match = \App\Models\BracketMatch::create([
                    'match_id' => "E{$event->id}_R1_M{$matchNumber}",  // Generate unique match ID
                    'tournament_id' => null,  // For events, tournament_id is null
                    'event_id' => $event->id,
                    'bracket_stage_id' => $stage->id,  // Use 'bracket_stage_id' instead of 'stage_id'
                    'round_name' => 'Round 1',
                    'round_number' => 1,  // Use 'round_number' instead of 'round'
                    'match_number' => $matchNumber++,
                    'team1_id' => $seededTeams[$i]['id'],  // Access as array, not object
                    'team2_id' => $seededTeams[$i + 1]['id'],  // Access as array, not object
                    'status' => 'pending',
                    'best_of' => 3,
                    'scheduled_at' => null
                ]);
                $matches[] = $match;
            }
        }

        // Generate placeholder matches for subsequent rounds
        for ($round = 2; $round <= $rounds; $round++) {
            $matchesInRound = pow(2, $rounds - $round);
            for ($i = 0; $i < $matchesInRound; $i++) {
                $currentMatchNumber = $matchNumber++;
                \App\Models\BracketMatch::create([
                    'match_id' => "E{$event->id}_R{$round}_M{$currentMatchNumber}",  // Generate unique match ID
                    'tournament_id' => null,  // For events, tournament_id is null
                    'event_id' => $event->id,
                    'bracket_stage_id' => $stage->id,  // Use 'bracket_stage_id' instead of 'stage_id'
                    'round_name' => $round === $rounds ? 'Final' : "Round {$round}",
                    'round_number' => $round,  // Use 'round_number' instead of 'round'
                    'match_number' => $currentMatchNumber,
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'best_of' => $round === $rounds ? 5 : 3, // Finals BO5
                    'scheduled_at' => null
                ]);
            }
        }

        return [
            'stage' => $stage,
            'matches' => $matches,
            'rounds' => $rounds
        ];
    }

    /**
     * Seed teams based on method
     */
    private function seedTeams($teams, $method, $shuffle = false)
    {
        $teamsArray = $teams->toArray();
        
        switch ($method) {
            case 'rating':
                usort($teamsArray, function($a, $b) {
                    return ($b['rating'] ?? 1000) - ($a['rating'] ?? 1000);
                });
                break;
            case 'random':
                shuffle($teamsArray);
                break;
            case 'manual':
                // Keep existing order
                break;
        }
        
        if ($shuffle && $method !== 'random') {
            // Shuffle within seed groups
            $groups = array_chunk($teamsArray, 4);
            foreach ($groups as &$group) {
                shuffle($group);
            }
            $teamsArray = array_merge(...$groups);
        }
        
        return collect($teamsArray);
    }

    /**
     * Generate Double Elimination for Event  
     */
    private function generateEventDoubleElimination($event, $teams, $seedingMethod, $shuffleSeeds)
    {
        // TODO: Implement double elimination
        throw new \Exception("Double elimination not yet implemented");
    }

    /**
     * Generate Round Robin for Event
     */
    private function generateEventRoundRobin($event, $teams, $seedingMethod, $shuffleSeeds)
    {
        // TODO: Implement round robin
        throw new \Exception("Round robin not yet implemented");
    }

    /**
     * Generate Swiss for Event
     */
    private function generateEventSwiss($event, $teams, $seedingMethod, $shuffleSeeds)
    {
        // TODO: Implement swiss
        throw new \Exception("Swiss not yet implemented");
    }

    /**
     * Generate Double Elimination Tournament
     */
    private function generateDoubleEliminationTournament(Tournament $tournament, Collection $teams): Collection
    {
        $brackets = collect();

        // Create phases if they don't exist
        $upperPhase = $tournament->phases()->where('phase_type', 'upper_bracket')->first();
        if (!$upperPhase) {
            $upperPhase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => 'Upper Bracket',
                'slug' => 'upper-bracket',
                'phase_type' => 'upper_bracket',
                'phase_order' => 1,
                'team_count' => $teams->count(),
                'match_format' => $tournament->match_format_settings['playoffs'] ?? 'bo3'
            ]);
        }

        $lowerPhase = $tournament->phases()->where('phase_type', 'lower_bracket')->first();
        if (!$lowerPhase) {
            $lowerPhase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => 'Lower Bracket',
                'slug' => 'lower-bracket',
                'phase_type' => 'lower_bracket',
                'phase_order' => 2,
                'team_count' => $teams->count(),
                'match_format' => $tournament->match_format_settings['playoffs'] ?? 'bo3'
            ]);
        }

        // Generate Upper Bracket
        $upperBracket = TournamentBracket::create([
            'tournament_id' => $tournament->id,
            'tournament_phase_id' => $upperPhase->id,
            'name' => 'Upper Bracket',
            'bracket_type' => 'double_elimination_upper',
            'bracket_format' => $this->determineBracketFormat($teams->count(), 'double'),
            'team_count' => $teams->count(),
            'round_count' => $this->calculateEliminationRounds($teams->count()),
            'stage_order' => 1,
            'match_settings' => [
                'format' => $tournament->match_format_settings['playoffs'] ?? 'bo3',
                'map_pool' => $tournament->map_pool ?? []
            ]
        ]);

        $this->generateUpperBracket($upperBracket, $teams->toArray());

        // Generate Lower Bracket
        $lowerBracket = TournamentBracket::create([
            'tournament_id' => $tournament->id,
            'tournament_phase_id' => $lowerPhase->id,
            'name' => 'Lower Bracket',
            'bracket_type' => 'double_elimination_lower',
            'bracket_format' => $this->determineBracketFormat($teams->count(), 'double_lower'),
            'team_count' => $teams->count(),
            'round_count' => $this->calculateLowerBracketRounds($teams->count()),
            'stage_order' => 2,
            'parent_bracket_id' => $upperBracket->id,
            'match_settings' => [
                'format' => $tournament->match_format_settings['playoffs'] ?? 'bo3',
                'map_pool' => $tournament->map_pool ?? []
            ]
        ]);

        $this->generateLowerBracket($lowerBracket, $teams->count());

        $brackets->push($upperBracket);
        $brackets->push($lowerBracket);

        return $brackets;
    }

    /**
     * Generate Swiss System Tournament
     */
    private function generateSwissTournament(Tournament $tournament, Collection $teams): Collection
    {
        $phase = $tournament->phases()->where('phase_type', 'swiss_rounds')->first();
        if (!$phase) {
            $phase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => 'Swiss Rounds',
                'slug' => 'swiss-rounds',
                'phase_type' => 'swiss_rounds',
                'phase_order' => 1,
                'team_count' => $teams->count(),
                'advancement_count' => $tournament->qualification_settings['swiss_qualified'] ?? 8,
                'elimination_count' => $tournament->qualification_settings['swiss_eliminated'] ?? 8,
                'match_format' => $tournament->match_format_settings['swiss'] ?? 'bo1',
                'settings' => [
                    'swiss_wins_required' => $tournament->qualification_settings['swiss_wins_required'] ?? 3,
                    'swiss_losses_eliminated' => $tournament->qualification_settings['swiss_losses_eliminated'] ?? 3,
                    'rounds' => $this->calculateSwissRounds($teams->count())
                ]
            ]);
        }

        $bracket = TournamentBracket::create([
            'tournament_id' => $tournament->id,
            'tournament_phase_id' => $phase->id,
            'name' => 'Swiss System',
            'bracket_type' => 'swiss_system',
            'bracket_format' => "swiss_{$this->calculateSwissRounds($teams->count())}round",
            'team_count' => $teams->count(),
            'round_count' => $this->calculateSwissRounds($teams->count()),
            'match_settings' => [
                'format' => $tournament->match_format_settings['swiss'] ?? 'bo1',
                'map_selection' => 'random', // Swiss typically uses random map selection
                'map_pool' => $tournament->map_pool ?? []
            ]
        ]);

        $this->generateSwissBracket($bracket, $teams->toArray());
        
        return collect([$bracket]);
    }

    /**
     * Generate Round Robin Tournament
     */
    private function generateRoundRobinTournament(Tournament $tournament, Collection $teams): Collection
    {
        $phase = $tournament->phases()->where('phase_type', 'group_stage')->first();
        if (!$phase) {
            $phase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => 'Round Robin',
                'slug' => 'round-robin',
                'phase_type' => 'group_stage',
                'phase_order' => 1,
                'team_count' => $teams->count(),
                'match_format' => $tournament->match_format_settings['group_stage'] ?? 'bo3'
            ]);
        }

        $bracket = TournamentBracket::create([
            'tournament_id' => $tournament->id,
            'tournament_phase_id' => $phase->id,
            'name' => 'Round Robin',
            'bracket_type' => 'round_robin',
            'bracket_format' => "rr_{$teams->count()}teams",
            'team_count' => $teams->count(),
            'round_count' => $teams->count() - 1,
            'match_settings' => [
                'format' => $tournament->match_format_settings['group_stage'] ?? 'bo3',
                'map_pool' => $tournament->map_pool ?? []
            ]
        ]);

        $this->generateRoundRobinBracket($bracket, $teams->toArray());
        
        return collect([$bracket]);
    }

    /**
     * Generate Group Stage + Playoffs Tournament
     */
    private function generateGroupStagePlayoffsTournament(Tournament $tournament, Collection $teams): Collection
    {
        $brackets = collect();
        $groupSize = $tournament->settings['group_size'] ?? 4;
        $groupCount = ceil($teams->count() / $groupSize);

        // Generate Group Stage
        $groupPhase = $tournament->phases()->where('phase_type', 'group_stage')->first();
        if (!$groupPhase) {
            $groupPhase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => 'Group Stage',
                'slug' => 'group-stage',
                'phase_type' => 'group_stage',
                'phase_order' => 1,
                'team_count' => $teams->count(),
                'advancement_count' => $groupCount * 2, // Top 2 from each group
                'match_format' => $tournament->match_format_settings['group_stage'] ?? 'bo3'
            ]);
        }

        // Create groups
        $teamChunks = $teams->chunk($groupSize);
        foreach ($teamChunks as $index => $groupTeams) {
            $groupLetter = chr(65 + $index); // A, B, C, etc.
            
            $groupBracket = TournamentBracket::create([
                'tournament_id' => $tournament->id,
                'tournament_phase_id' => $groupPhase->id,
                'name' => "Group {$groupLetter}",
                'bracket_type' => 'group_stage',
                'bracket_format' => "group_{$groupSize}teams",
                'team_count' => $groupTeams->count(),
                'round_count' => $groupTeams->count() - 1,
                'group_id' => $groupLetter,
                'stage_order' => $index + 1,
                'match_settings' => [
                    'format' => $tournament->match_format_settings['group_stage'] ?? 'bo3',
                    'advancement_count' => 2,
                    'map_pool' => $tournament->map_pool ?? []
                ]
            ]);

            $this->generateRoundRobinBracket($groupBracket, $groupTeams->toArray());
            $brackets->push($groupBracket);
        }

        return $brackets;
    }

    /**
     * Generate Single Elimination Bracket Structure
     */
    private function generateSingleEliminationBracket(TournamentBracket $bracket, array $teams): void
    {
        $matches = [];
        $teamCount = count($teams);
        $totalRounds = $bracket->round_count;

        // Seed teams properly (1 vs lowest, 2 vs second-lowest, etc.)
        $seededTeams = $this->seedTeamsForElimination($teams);
        
        // Generate first round matches
        $round = 1;
        $matchNumber = 1;
        
        for ($i = 0; $i < count($seededTeams); $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                $matchId = "R{$round}M{$matchNumber}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $matchNumber,
                    'team1_id' => $seededTeams[$i]['id'],
                    'team2_id' => $seededTeams[$i + 1]['id'],
                    'status' => 'pending',
                    'winner_advances_to' => $this->getNextRoundMatch($round, $matchNumber, $totalRounds)
                ];
                $matchNumber++;
            }
        }

        // Generate empty matches for subsequent rounds
        for ($round = 2; $round <= $totalRounds; $round++) {
            $matchesInRound = pow(2, $totalRounds - $round);
            
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matchId = "R{$round}M{$m}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $m,
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'winner_advances_to' => $round < $totalRounds ? $this->getNextRoundMatch($round, $m, $totalRounds) : null,
                    'depends_on' => $this->getPreviousRoundMatches($round, $m)
                ];
            }
        }

        $bracket->bracket_data = $matches;
        $bracket->seeding_data = $seededTeams;
        $bracket->save();

        // Create actual match records
        $this->createMatchRecords($bracket, $matches);
    }

    /**
     * Generate Upper Bracket for Double Elimination
     */
    private function generateUpperBracket(TournamentBracket $bracket, array $teams): void
    {
        $matches = [];
        $seededTeams = $this->seedTeamsForElimination($teams);
        $totalRounds = $bracket->round_count;

        // Generate first round matches
        $round = 1;
        $matchNumber = 1;
        
        for ($i = 0; $i < count($seededTeams); $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                $matchId = "UB_R{$round}M{$matchNumber}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $matchNumber,
                    'team1_id' => $seededTeams[$i]['id'],
                    'team2_id' => $seededTeams[$i + 1]['id'],
                    'status' => 'pending',
                    'winner_advances_to' => $this->getNextRoundMatch($round, $matchNumber, $totalRounds, 'UB'),
                    'loser_drops_to' => $this->getLowerBracketDestination($round, $matchNumber)
                ];
                $matchNumber++;
            }
        }

        // Generate empty matches for subsequent rounds
        for ($round = 2; $round <= $totalRounds; $round++) {
            $matchesInRound = pow(2, $totalRounds - $round);
            
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matchId = "UB_R{$round}M{$m}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $m,
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'winner_advances_to' => $round < $totalRounds ? $this->getNextRoundMatch($round, $m, $totalRounds, 'UB') : 'GRAND_FINAL',
                    'loser_drops_to' => $this->getLowerBracketDestination($round, $m),
                    'depends_on' => $this->getPreviousRoundMatches($round, $m, 'UB')
                ];
            }
        }

        $bracket->bracket_data = $matches;
        $bracket->seeding_data = $seededTeams;
        $bracket->save();

        $this->createMatchRecords($bracket, $matches);
    }

    /**
     * Generate Lower Bracket for Double Elimination
     */
    private function generateLowerBracket(TournamentBracket $bracket, int $teamCount): void
    {
        $matches = [];
        $totalRounds = $bracket->round_count;

        // Lower bracket has a complex structure with alternating elimination and advancement rounds
        for ($round = 1; $round <= $totalRounds; $round++) {
            $isEliminationRound = ($round % 2) === 1;
            $matchesInRound = $this->calculateLowerBracketMatches($round, $teamCount);
            
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matchId = "LB_R{$round}M{$m}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $m,
                    'team1_id' => null, // Teams come from upper bracket losses or previous LB matches
                    'team2_id' => null,
                    'status' => 'pending',
                    'is_elimination_round' => $isEliminationRound,
                    'winner_advances_to' => $this->getLowerBracketAdvancement($round, $m, $totalRounds),
                    'loser_eliminated' => true,
                    'feeds_from_upper' => $this->getUpperBracketFeeds($round, $m),
                    'depends_on' => $this->getLowerBracketDependencies($round, $m)
                ];
            }
        }

        $bracket->bracket_data = $matches;
        $bracket->save();

        $this->createMatchRecords($bracket, $matches);
    }

    /**
     * Generate Swiss System Bracket (First Round)
     */
    private function generateSwissBracket(TournamentBracket $bracket, array $teams): void
    {
        // Swiss system generates matches round by round, starting with random pairings
        $matches = [];
        $round = 1;
        
        // Shuffle teams for first round random pairing
        shuffle($teams);
        
        $matchNumber = 1;
        for ($i = 0; $i < count($teams); $i += 2) {
            if (isset($teams[$i + 1])) {
                $matchId = "SW_R{$round}M{$matchNumber}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $matchNumber,
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$i + 1]['id'],
                    'status' => 'pending',
                    'swiss_round' => $round
                ];
                $matchNumber++;
            }
        }

        $bracket->bracket_data = $matches;
        $bracket->seeding_data = $teams;
        $bracket->save();

        $this->createMatchRecords($bracket, $matches);
    }

    /**
     * Generate Round Robin Bracket
     */
    private function generateRoundRobinBracket(TournamentBracket $bracket, array $teams): void
    {
        $matches = [];
        $matchNumber = 1;
        
        // Generate all possible pairings
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $matchId = "RR_M{$matchNumber}";
                $matches[$matchId] = [
                    'match_number' => $matchNumber,
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$j]['id'],
                    'status' => 'pending',
                    'round' => $this->calculateRoundRobinRound($i, $j, count($teams))
                ];
                $matchNumber++;
            }
        }

        $bracket->bracket_data = $matches;
        $bracket->seeding_data = $teams;
        $bracket->save();

        $this->createMatchRecords($bracket, $matches);
    }

    /**
     * Create actual match records in database
     */
    private function createMatchRecords(TournamentBracket $bracket, array $matches): void
    {
        foreach ($matches as $matchId => $matchData) {
            BracketMatch::create([
                'tournament_id' => $bracket->tournament_id,
                'tournament_phase_id' => $bracket->tournament_phase_id,
                'tournament_bracket_id' => $bracket->id,
                'match_identifier' => $matchId,
                'round' => $matchData['round'] ?? 1,
                'match_number' => $matchData['match_number'],
                'team1_id' => $matchData['team1_id'] ?? null,
                'team2_id' => $matchData['team2_id'] ?? null,
                'status' => $matchData['status'] ?? 'pending',
                'match_format' => $bracket->match_settings['format'] ?? 'bo3',
                'scheduled_at' => $this->calculateMatchSchedule($bracket, $matchData),
                'map_data' => [
                    'map_pool' => $bracket->match_settings['map_pool'] ?? [],
                    'veto_format' => $this->getVetoFormat($bracket->match_settings['format'] ?? 'bo3')
                ]
            ]);
        }
    }

    // Helper Methods

    private function seedTeamsForElimination(array $teams): array
    {
        // Standard tournament seeding: 1 vs lowest, 2 vs second-lowest, etc.
        usort($teams, function($a, $b) {
            return ($a['seed'] ?? 999) - ($b['seed'] ?? 999);
        });
        
        $seeded = [];
        $low = 0;
        $high = count($teams) - 1;
        
        while ($low <= $high) {
            $seeded[] = $teams[$low++];
            if ($low <= $high) {
                $seeded[] = $teams[$high--];
            }
        }
        
        return $seeded;
    }

    private function calculateEliminationRounds(int $teamCount): int
    {
        return ceil(log($teamCount, 2));
    }

    private function calculateLowerBracketRounds(int $teamCount): int
    {
        $upperRounds = $this->calculateEliminationRounds($teamCount);
        return ($upperRounds * 2) - 1;
    }

    private function calculateSwissRounds(int $teamCount): int
    {
        // Standard Swiss: ceil(log2(teamCount))
        return max(3, ceil(log($teamCount, 2)));
    }

    private function calculateLowerBracketMatches(int $round, int $teamCount): int
    {
        $baseMatches = ceil($teamCount / 2);
        return max(1, $baseMatches - floor($round / 2));
    }

    private function determineBracketFormat(int $teamCount, string $type): string
    {
        $roundedTeams = pow(2, ceil(log($teamCount, 2))); // Next power of 2
        
        switch ($type) {
            case 'single':
                return "r{$roundedTeams}_single";
            case 'double':
                return "r{$roundedTeams}_double";
            case 'double_lower':
                return "r{$roundedTeams}_double_lower";
            default:
                return "r{$roundedTeams}_custom";
        }
    }

    private function getNextRoundMatch(int $round, int $matchNumber, int $totalRounds, string $prefix = 'R'): ?string
    {
        if ($round >= $totalRounds) return null;
        
        $nextRound = $round + 1;
        $nextMatch = ceil($matchNumber / 2);
        return "{$prefix}{$nextRound}M{$nextMatch}";
    }

    private function getPreviousRoundMatches(int $round, int $matchNumber, string $prefix = 'R'): array
    {
        if ($round <= 1) return [];
        
        $prevRound = $round - 1;
        $match1 = ($matchNumber * 2) - 1;
        $match2 = $matchNumber * 2;
        
        return ["{$prefix}{$prevRound}M{$match1}", "{$prefix}{$prevRound}M{$match2}"];
    }

    private function getLowerBracketDestination(int $round, int $matchNumber): string
    {
        // Simplified lower bracket destination logic
        $lbRound = (($round - 1) * 2) + 1;
        return "LB_R{$lbRound}M{$matchNumber}";
    }

    private function getLowerBracketAdvancement(int $round, int $matchNumber, int $totalRounds): string
    {
        if ($round >= $totalRounds) return 'GRAND_FINAL';
        
        $nextRound = $round + 1;
        $nextMatch = ceil($matchNumber / 2);
        return "LB_R{$nextRound}M{$nextMatch}";
    }

    private function getUpperBracketFeeds(int $round, int $matchNumber): ?string
    {
        // Upper bracket teams drop to specific lower bracket positions
        $ubRound = ceil($round / 2) + 1;
        return "UB_R{$ubRound}M{$matchNumber}";
    }

    private function getLowerBracketDependencies(int $round, int $matchNumber): array
    {
        if ($round <= 1) return [];
        
        $deps = [];
        
        // Previous LB match
        if ($round > 1) {
            $deps[] = "LB_R" . ($round - 1) . "M" . ($matchNumber * 2 - 1);
        }
        
        return $deps;
    }

    private function calculateRoundRobinRound(int $i, int $j, int $teamCount): int
    {
        // Simple round calculation for round robin scheduling
        return 1 + (($i + $j) % ($teamCount - 1));
    }

    private function calculateMatchSchedule(TournamentBracket $bracket, array $matchData): Carbon
    {
        $tournament = $bracket->tournament;
        $baseTime = $tournament->start_date ?? now();
        
        $round = $matchData['round'] ?? 1;
        $matchNumber = $matchData['match_number'] ?? 1;
        
        // Schedule matches with delays between rounds and matches
        $roundDelay = ($round - 1) * 2; // 2 hours per round
        $matchDelay = ($matchNumber - 1) * 1; // 1 hour between matches in same round
        
        return $baseTime->copy()->addHours($roundDelay + $matchDelay);
    }

    private function getVetoFormat(string $matchFormat): array
    {
        switch ($matchFormat) {
            case 'bo1':
                return ['type' => 'random', 'maps' => 1];
            case 'bo3':
                return ['type' => 'ban-ban-pick', 'maps' => 3];
            case 'bo5':
                return ['type' => 'ban-ban-pick-pick-pick', 'maps' => 5];
            case 'bo7':
                return ['type' => 'ban-ban-pick-pick-pick-pick-pick', 'maps' => 7];
            default:
                return ['type' => 'pick', 'maps' => 3];
        }
    }
}