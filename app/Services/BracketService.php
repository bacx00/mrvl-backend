<?php

namespace App\Services;

use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\BracketPosition;
use App\Models\BracketSeeding;
use App\Models\BracketStanding;
use App\Models\Tournament;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class BracketService
{
    /**
     * Generate a complete double elimination bracket like Liquipedia
     */
    public function generateDoubleEliminationBracket(
        Tournament|Event $tournament,
        Collection $teams,
        array $options = []
    ): array {
        $options = array_merge([
            'best_of' => '3',
            'seeding_method' => 'seed',
            'third_place_match' => false,
            'bracket_reset' => true
        ], $options);

        DB::beginTransaction();
        
        try {
            // Validate team count (must be power of 2)
            $teamCount = $teams->count();
            if (!$this->isPowerOfTwo($teamCount)) {
                throw new Exception("Double elimination requires a power of 2 teams. Got {$teamCount} teams.");
            }

            // Create bracket stages
            $upperStage = $this->createBracketStage($tournament, 'upper_bracket', 'Upper Bracket', 1, $teamCount);
            $lowerStage = $this->createBracketStage($tournament, 'lower_bracket', 'Lower Bracket', 2, $teamCount);
            $finalStage = $this->createBracketStage($tournament, 'grand_final', 'Grand Final', 3, 2);

            // Seed teams
            $this->seedTeams($upperStage, $teams, $options['seeding_method']);

            // Generate upper bracket matches
            $upperMatches = $this->generateUpperBracketMatches($upperStage, $teamCount, $options);

            // Generate lower bracket matches  
            $lowerMatches = $this->generateLowerBracketMatches($lowerStage, $teamCount, $options);

            // Generate grand final
            $finalMatches = $this->generateGrandFinalMatch($finalStage, $options);

            // Create positions for all matches
            $this->generatePositionsForDoubleElimination($upperStage, $lowerStage, $finalStage);

            // Link matches together (progression paths)
            $this->linkDoubleEliminationMatches($upperMatches, $lowerMatches, $finalMatches);

            DB::commit();

            return [
                'upper_stage' => $upperStage,
                'lower_stage' => $lowerStage,
                'final_stage' => $finalStage,
                'upper_matches' => $upperMatches,
                'lower_matches' => $lowerMatches,
                'final_matches' => $finalMatches
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate single elimination bracket
     */
    public function generateSingleEliminationBracket(
        Tournament|Event $tournament,
        Collection $teams,
        array $options = []
    ): array {
        $options = array_merge([
            'best_of' => '3',
            'seeding_method' => 'seed',
            'third_place_match' => true
        ], $options);

        DB::beginTransaction();

        try {
            $teamCount = $teams->count();
            if (!$this->isPowerOfTwo($teamCount)) {
                throw new Exception("Single elimination requires a power of 2 teams. Got {$teamCount} teams.");
            }

            // Create main bracket stage
            $mainStage = $this->createBracketStage($tournament, 'upper_bracket', 'Main Bracket', 1, $teamCount);
            $stages = ['main_stage' => $mainStage];

            // Seed teams
            $this->seedTeams($mainStage, $teams, $options['seeding_method']);

            // Generate matches
            $matches = $this->generateSingleEliminationMatches($mainStage, $teamCount, $options);

            // Third place match if requested
            if ($options['third_place_match']) {
                $thirdPlaceStage = $this->createBracketStage($tournament, 'third_place', 'Third Place Match', 2, 2);
                $stages['third_place_stage'] = $thirdPlaceStage;
                
                $thirdPlaceMatch = $this->generateThirdPlaceMatch($thirdPlaceStage, $options);
                $matches['third_place'] = $thirdPlaceMatch;
            }

            DB::commit();

            return array_merge(['matches' => $matches], $stages);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate Swiss system bracket
     */
    public function generateSwissBracket(
        Tournament|Event $tournament,
        Collection $teams,
        array $options = []
    ): array {
        $options = array_merge([
            'rounds' => ceil(log($teams->count(), 2)),
            'best_of' => '3',
            'seeding_method' => 'random'
        ], $options);

        DB::beginTransaction();

        try {
            $teamCount = $teams->count();
            $rounds = $options['rounds'];

            // Create Swiss stage
            $swissStage = $this->createBracketStage($tournament, 'swiss', 'Swiss System', 1, $teamCount, $rounds);

            // Seed teams
            $this->seedTeams($swissStage, $teams, $options['seeding_method']);

            // Generate first round matches (random or seeded pairing)
            $matches = $this->generateSwissRoundMatches($swissStage, 1, $teams, $options);

            DB::commit();

            return [
                'swiss_stage' => $swissStage,
                'matches' => $matches,
                'total_rounds' => $rounds
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate round robin bracket
     */
    public function generateRoundRobinBracket(
        Tournament|Event $tournament,
        Collection $teams,
        array $options = []
    ): array {
        $options = array_merge([
            'best_of' => '3',
            'double_round_robin' => false
        ], $options);

        DB::beginTransaction();

        try {
            $teamCount = $teams->count();
            $totalRounds = $teamCount - 1;
            if ($options['double_round_robin']) {
                $totalRounds *= 2;
            }

            // Create round robin stage
            $rrStage = $this->createBracketStage($tournament, 'round_robin', 'Round Robin', 1, $teamCount, $totalRounds);

            // Seed teams
            $this->seedTeams($rrStage, $teams, 'seed');

            // Generate all matches
            $matches = $this->generateRoundRobinMatches($rrStage, $teams, $options);

            DB::commit();

            return [
                'round_robin_stage' => $rrStage,
                'matches' => $matches,
                'total_rounds' => $totalRounds
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create a bracket stage
     */
    private function createBracketStage(
        Tournament|Event $tournament,
        string $type,
        string $name,
        int $order,
        int $maxTeams,
        int $totalRounds = null
    ): BracketStage {
        $data = [
            'name' => $name,
            'type' => $type,
            'stage_order' => $order,
            'status' => 'pending',
            'max_teams' => $maxTeams
        ];

        if ($tournament instanceof Tournament) {
            $data['tournament_id'] = $tournament->id;
            $data['event_id'] = null;
        } else {
            // For events, use the default tournament ID (8) to satisfy the NOT NULL constraint
            $data['tournament_id'] = 8; // Default tournament for event brackets
            $data['event_id'] = $tournament->id;
        }

        if ($totalRounds) {
            $data['total_rounds'] = $totalRounds;
        }

        return BracketStage::create($data);
    }

    /**
     * Seed teams into a bracket stage
     */
    private function seedTeams(BracketStage $stage, Collection $teams, string $method): void
    {
        $seededTeams = match($method) {
            'random' => $teams->shuffle(),
            'rating' => $teams->sortByDesc('rating'),
            'seed' => $teams->sortBy('pivot.seed'),
            default => $teams
        };

        $seed = 1;
        foreach ($seededTeams as $team) {
            BracketSeeding::create([
                'tournament_id' => $stage->tournament_id,
                'event_id' => $stage->event_id,
                'bracket_stage_id' => $stage->id,
                'team_id' => $team->id,
                'seed' => $seed++,
                'seeding_method' => $method,
                'seeded_at' => now()
            ]);
        }
    }

    /**
     * Generate upper bracket matches for double elimination
     */
    private function generateUpperBracketMatches(BracketStage $stage, int $teamCount, array $options): Collection
    {
        $matches = collect();
        $seeds = $stage->seedings->pluck('team_id', 'seed')->toArray();
        $totalRounds = ceil(log($teamCount, 2));

        // Generate first round matches with seeded matchups
        $firstRoundMatches = $teamCount / 2;
        for ($i = 1; $i <= $firstRoundMatches; $i++) {
            $seed1 = $i;
            $seed2 = $teamCount - $i + 1;
            
            $match = BracketMatch::create([
                'match_id' => "UB{$seed1}-{$seed2}",
                'tournament_id' => $stage->tournament_id,
                'event_id' => $stage->event_id,
                'bracket_stage_id' => $stage->id,
                'round_name' => 'Upper Bracket Round 1',
                'round_number' => 1,
                'match_number' => $i,
                'team1_id' => $seeds[$seed1] ?? null,
                'team2_id' => $seeds[$seed2] ?? null,
                'team1_source' => "Seed #{$seed1}",
                'team2_source' => "Seed #{$seed2}",
                'status' => 'pending',
                'best_of' => $options['best_of']
            ]);

            $matches->push($match);
        }

        // Generate subsequent rounds
        for ($round = 2; $round <= $totalRounds; $round++) {
            $matchesInRound = $teamCount / pow(2, $round);
            $roundName = $this->getRoundName($round, $totalRounds);

            for ($match = 1; $match <= $matchesInRound; $match++) {
                $matchId = "UB{$round}-{$match}";
                
                $bracketMatch = BracketMatch::create([
                    'match_id' => $matchId,
                    'tournament_id' => $stage->tournament_id,
                    'event_id' => $stage->event_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => $roundName,
                    'round_number' => $round,
                    'match_number' => $match,
                    'status' => 'pending',
                    'best_of' => $options['best_of']
                ]);

                $matches->push($bracketMatch);
            }
        }

        return $matches;
    }

    /**
     * Generate lower bracket matches for double elimination
     */
    private function generateLowerBracketMatches(BracketStage $stage, int $teamCount, array $options): Collection
    {
        $matches = collect();
        $upperRounds = ceil(log($teamCount, 2));
        $lowerRounds = ($upperRounds * 2) - 2;

        $matchNumber = 1;
        for ($round = 1; $round <= $lowerRounds; $round++) {
            // Complex lower bracket structure
            $matchesInRound = $this->calculateLowerBracketMatches($round, $teamCount);
            $roundName = "Lower Bracket Round {$round}";

            for ($match = 1; $match <= $matchesInRound; $match++) {
                $matchId = "LB{$round}-{$matchNumber}";
                
                $bracketMatch = BracketMatch::create([
                    'match_id' => $matchId,
                    'tournament_id' => $stage->tournament_id,
                    'event_id' => $stage->event_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => $roundName,
                    'round_number' => $round,
                    'match_number' => $matchNumber++,
                    'status' => 'pending',
                    'best_of' => $options['best_of']
                ]);

                $matches->push($bracketMatch);
            }
        }

        return $matches;
    }

    /**
     * Generate grand final match
     */
    private function generateGrandFinalMatch(BracketStage $stage, array $options): BracketMatch
    {
        return BracketMatch::create([
            'match_id' => 'GF',
            'tournament_id' => $stage->tournament_id,
            'event_id' => $stage->event_id,
            'bracket_stage_id' => $stage->id,
            'round_name' => 'Grand Final',
            'round_number' => 1,
            'match_number' => 1,
            'team1_source' => 'Upper Bracket Champion',
            'team2_source' => 'Lower Bracket Champion',
            'status' => 'pending',
            'best_of' => $options['best_of']
        ]);
    }

    /**
     * Link double elimination matches for progression
     */
    private function linkDoubleEliminationMatches(
        Collection $upperMatches,
        Collection $lowerMatches,
        BracketMatch $grandFinal
    ): void {
        // Link upper bracket progression
        $upperByRound = $upperMatches->groupBy('round_number');
        foreach ($upperByRound as $round => $matches) {
            if ($upperByRound->has($round + 1)) {
                $nextRoundMatches = $upperByRound[$round + 1];
                foreach ($matches as $index => $match) {
                    $nextMatchIndex = intval($index / 2);
                    if (isset($nextRoundMatches[$nextMatchIndex])) {
                        $match->update([
                            'winner_advances_to' => $nextRoundMatches[$nextMatchIndex]->match_id
                        ]);
                    }
                }
            } else {
                // Last upper bracket match advances to grand final
                $match->update(['winner_advances_to' => 'GF']);
            }
        }

        // Link upper bracket losers to lower bracket
        foreach ($upperMatches as $match) {
            $lowerBracketMatch = $this->findLowerBracketDestination($match, $lowerMatches);
            if ($lowerBracketMatch) {
                $match->update(['loser_advances_to' => $lowerBracketMatch->match_id]);
            }
        }

        // Link lower bracket progression
        $lowerByRound = $lowerMatches->groupBy('round_number');
        foreach ($lowerByRound as $round => $matches) {
            if ($lowerByRound->has($round + 1)) {
                $nextRoundMatches = $lowerByRound[$round + 1];
                foreach ($matches as $index => $match) {
                    $nextMatchIndex = intval($index / 2);
                    if (isset($nextRoundMatches[$nextMatchIndex])) {
                        $match->update([
                            'winner_advances_to' => $nextRoundMatches[$nextMatchIndex]->match_id
                        ]);
                    }
                }
            } else {
                // Last lower bracket match advances to grand final
                $match->update(['winner_advances_to' => 'GF']);
            }
        }
    }

    /**
     * Progress Swiss system to next round
     */
    public function generateNextSwissRound(BracketStage $stage, int $roundNumber): Collection
    {
        // Get current standings
        $standings = $this->calculateSwissStandings($stage);
        
        // Pair teams with similar records
        $matches = collect();
        $pairedTeams = collect();

        // Group by score and pair within score groups
        $scoreGroups = $standings->groupBy('swiss_score');
        
        foreach ($scoreGroups as $score => $teams) {
            $availableTeams = $teams->whereNotIn('team_id', $pairedTeams->pluck('team_id'));
            
            while ($availableTeams->count() >= 2) {
                $team1 = $availableTeams->shift();
                $team2 = $availableTeams->shift();
                
                $match = BracketMatch::create([
                    'match_id' => "SW{$roundNumber}-" . ($matches->count() + 1),
                    'tournament_id' => $stage->tournament_id,
                    'event_id' => $stage->event_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => "Swiss Round {$roundNumber}",
                    'round_number' => $roundNumber,
                    'match_number' => $matches->count() + 1,
                    'team1_id' => $team1->team_id,
                    'team2_id' => $team2->team_id,
                    'team1_source' => "Swiss Pairing ({$team1->wins}-{$team1->losses})",
                    'team2_source' => "Swiss Pairing ({$team2->wins}-{$team2->losses})",
                    'status' => 'pending',
                    'best_of' => '3'
                ]);

                $matches->push($match);
                $pairedTeams->push($team1, $team2);
            }
        }

        return $matches;
    }

    // Utility methods
    private function isPowerOfTwo(int $number): bool
    {
        return $number > 0 && ($number & ($number - 1)) === 0;
    }

    private function getRoundName(int $round, int $totalRounds): string
    {
        $remainingRounds = $totalRounds - $round + 1;
        
        return match($remainingRounds) {
            1 => 'Upper Bracket Finals',
            2 => 'Upper Bracket Semifinals',
            3 => 'Upper Bracket Quarterfinals',
            default => "Upper Bracket Round {$round}"
        };
    }

    private function calculateLowerBracketMatches(int $round, int $teamCount): int
    {
        // Lower bracket has alternating pattern
        $upperRounds = ceil(log($teamCount, 2));
        
        if ($round % 2 === 1) {
            // Odd rounds: new teams from upper bracket
            $upperRoundLosing = ceil($round / 2) + 1;
            return $teamCount / pow(2, $upperRoundLosing);
        } else {
            // Even rounds: winners from previous lower bracket round
            return $teamCount / pow(2, $round / 2 + 1);
        }
    }

    private function findLowerBracketDestination(BracketMatch $upperMatch, Collection $lowerMatches): ?BracketMatch
    {
        // Complex logic to determine where upper bracket losers go in lower bracket
        $upperRound = $upperMatch->round_number;
        $upperMatchNumber = $upperMatch->match_number;
        
        // This is simplified - real implementation would be more complex
        return $lowerMatches->where('round_number', $upperRound)->first();
    }

    private function calculateSwissStandings(BracketStage $stage): Collection
    {
        // Calculate current Swiss standings based on completed matches
        return $stage->seedings->map(function ($seeding) use ($stage) {
            $teamMatches = BracketMatch::where('bracket_stage_id', $stage->id)
                ->where(function ($q) use ($seeding) {
                    $q->where('team1_id', $seeding->team_id)
                      ->orWhere('team2_id', $seeding->team_id);
                })
                ->where('status', 'completed')
                ->get();

            $wins = $teamMatches->where('winner_id', $seeding->team_id)->count();
            $losses = $teamMatches->where('winner_id', '!=', $seeding->team_id)->count();

            return (object) [
                'team_id' => $seeding->team_id,
                'team' => $seeding->team,
                'wins' => $wins,
                'losses' => $losses,
                'swiss_score' => $wins - ($losses * 0.5), // Basic Swiss scoring
                'matches_played' => $wins + $losses
            ];
        })->sortByDesc('swiss_score');
    }

    /**
     * Calculate final standings after tournament completion
     */
    public function calculateFinalStandings(Tournament|Event $tournament): Collection
    {
        // Implementation depends on tournament format
        // This would calculate final placements based on elimination rounds
        return collect();
    }

    /**
     * Generate positions for double elimination visualization
     */
    private function generatePositionsForDoubleElimination(
        BracketStage $upperStage,
        BracketStage $lowerStage,
        BracketStage $finalStage
    ): void {
        // Generate upper bracket positions
        $upperMatches = $upperStage->matches;
        foreach ($upperMatches as $match) {
            BracketPosition::create([
                'bracket_match_id' => $match->id,
                'bracket_stage_id' => $upperStage->id,
                'column_position' => $match->round_number,
                'row_position' => $match->match_number * 2 - 1,
                'tier' => $match->round_number - 1
            ]);
        }

        // Generate lower bracket positions
        $lowerMatches = $lowerStage->matches;
        foreach ($lowerMatches as $match) {
            BracketPosition::create([
                'bracket_match_id' => $match->id,
                'bracket_stage_id' => $lowerStage->id,
                'column_position' => $match->round_number + 10, // Offset from upper
                'row_position' => $match->match_number * 2,
                'tier' => $match->round_number - 1
            ]);
        }

        // Generate final position
        $finalMatch = $finalStage->matches->first();
        if ($finalMatch) {
            BracketPosition::create([
                'bracket_match_id' => $finalMatch->id,
                'bracket_stage_id' => $finalStage->id,
                'column_position' => 20,
                'row_position' => 1,
                'tier' => 99
            ]);
        }
    }

    /**
     * Generate other bracket types
     */
    private function generateSingleEliminationMatches(BracketStage $stage, int $teamCount, array $options): Collection
    {
        // Similar to upper bracket generation
        return $this->generateUpperBracketMatches($stage, $teamCount, $options);
    }

    private function generateThirdPlaceMatch(BracketStage $stage, array $options): BracketMatch
    {
        return BracketMatch::create([
            'match_id' => 'TP',
            'tournament_id' => $stage->tournament_id,
            'event_id' => $stage->event_id,
            'bracket_stage_id' => $stage->id,
            'round_name' => 'Third Place Match',
            'round_number' => 1,
            'match_number' => 1,
            'team1_source' => 'Semifinal Loser 1',
            'team2_source' => 'Semifinal Loser 2',
            'status' => 'pending',
            'best_of' => $options['best_of']
        ]);
    }

    private function generateSwissRoundMatches(BracketStage $stage, int $round, Collection $teams, array $options): Collection
    {
        $matches = collect();
        $shuffledTeams = $teams->shuffle();
        $matchNumber = 1;

        for ($i = 0; $i < $shuffledTeams->count(); $i += 2) {
            if (isset($shuffledTeams[$i + 1])) {
                $match = BracketMatch::create([
                    'match_id' => "SW{$round}-{$matchNumber}",
                    'tournament_id' => $stage->tournament_id,
                    'event_id' => $stage->event_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => "Swiss Round {$round}",
                    'round_number' => $round,
                    'match_number' => $matchNumber++,
                    'team1_id' => $shuffledTeams[$i]->id,
                    'team2_id' => $shuffledTeams[$i + 1]->id,
                    'team1_source' => "Swiss Round {$round}",
                    'team2_source' => "Swiss Round {$round}",
                    'status' => 'pending',
                    'best_of' => $options['best_of']
                ]);

                $matches->push($match);
            }
        }

        return $matches;
    }

    private function generateRoundRobinMatches(BracketStage $stage, Collection $teams, array $options): Collection
    {
        $matches = collect();
        $teamsArray = $teams->values();
        $matchNumber = 1;

        for ($i = 0; $i < $teamsArray->count(); $i++) {
            for ($j = $i + 1; $j < $teamsArray->count(); $j++) {
                $match = BracketMatch::create([
                    'match_id' => "RR-{$matchNumber}",
                    'tournament_id' => $stage->tournament_id,
                    'event_id' => $stage->event_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => 'Round Robin',
                    'round_number' => 1,
                    'match_number' => $matchNumber++,
                    'team1_id' => $teamsArray[$i]->id,
                    'team2_id' => $teamsArray[$j]->id,
                    'team1_source' => 'Round Robin',
                    'team2_source' => 'Round Robin',
                    'status' => 'pending',
                    'best_of' => $options['best_of']
                ]);

                $matches->push($match);
            }
        }

        if ($options['double_round_robin']) {
            // Create return matches
            $returnMatches = $matches->map(function ($match) use ($stage, &$matchNumber) {
                return BracketMatch::create([
                    'match_id' => "RR-{$matchNumber}",
                    'tournament_id' => $stage->tournament_id,
                    'event_id' => $stage->event_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => 'Round Robin (Return)',
                    'round_number' => 2,
                    'match_number' => $matchNumber++,
                    'team1_id' => $match->team2_id, // Swap teams
                    'team2_id' => $match->team1_id,
                    'team1_source' => 'Round Robin Return',
                    'team2_source' => 'Round Robin Return',
                    'status' => 'pending',
                    'best_of' => $options['best_of']
                ]);
            });

            $matches = $matches->merge($returnMatches);
        }

        return $matches;
    }
}