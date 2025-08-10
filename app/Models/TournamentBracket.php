<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class TournamentBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'tournament_phase_id', 'name', 'bracket_type', 'bracket_format',
        'bracket_data', 'seeding_data', 'advancement_rules', 'elimination_rules',
        'team_count', 'round_count', 'current_round', 'status', 'position_data',
        'match_settings', 'tiebreaker_rules', 'completed_at', 'results_data',
        'group_id', 'stage_order', 'parent_bracket_id', 'reset_occurred'
    ];

    protected $casts = [
        'bracket_data' => 'array',
        'seeding_data' => 'array',
        'advancement_rules' => 'array',
        'elimination_rules' => 'array',
        'position_data' => 'array',
        'match_settings' => 'array',
        'tiebreaker_rules' => 'array',
        'results_data' => 'array',
        'completed_at' => 'datetime',
        'team_count' => 'integer',
        'round_count' => 'integer',
        'current_round' => 'integer',
        'stage_order' => 'integer',
        'reset_occurred' => 'boolean'
    ];

    protected $attributes = [
        'status' => 'pending',
        'current_round' => 1,
        'reset_occurred' => false
    ];

    // Bracket Types
    public const BRACKET_TYPES = [
        'single_elimination' => 'Single Elimination',
        'double_elimination_upper' => 'Double Elimination (Upper Bracket)',
        'double_elimination_lower' => 'Double Elimination (Lower Bracket)',
        'swiss_system' => 'Swiss System',
        'round_robin' => 'Round Robin',
        'group_stage' => 'Group Stage',
        'ladder' => 'Ladder',
        'custom' => 'Custom Format'
    ];

    // Bracket Formats (specific to Liquipedia structure)
    public const BRACKET_FORMATS = [
        'r8_single' => 'R8 Single Elimination',
        'r16_single' => 'R16 Single Elimination',
        'r32_single' => 'R32 Single Elimination',
        'r8_double' => 'R8 Double Elimination',
        'r16_double' => 'R16 Double Elimination',
        'r32_double' => 'R32 Double Elimination',
        'swiss_5round' => '5-Round Swiss',
        'swiss_7round' => '7-Round Swiss',
        'group_4teams' => '4-Team Groups',
        'group_6teams' => '6-Team Groups'
    ];

    // Bracket Status
    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active', 
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'reset' => 'Reset Required'
    ];

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'tournament_phase_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BracketMatch::class);
    }

    public function parentBracket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_bracket_id');
    }

    public function childBrackets(): HasMany
    {
        return $this->hasMany(self::class, 'parent_bracket_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('bracket_type', $type);
    }

    public function scopeByFormat($query, $format)
    {
        return $query->where('bracket_format', $format);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('stage_order')->orderBy('created_at');
    }

    // Accessors
    public function getFormattedTypeAttribute()
    {
        return self::BRACKET_TYPES[$this->bracket_type] ?? $this->bracket_type;
    }

    public function getFormattedFormatAttribute()
    {
        return self::BRACKET_FORMATS[$this->bracket_format] ?? $this->bracket_format;
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->status === 'completed') return 100;
        if ($this->status === 'pending') return 0;

        $totalMatches = $this->matches()->count();
        if ($totalMatches === 0) return 0;

        $completedMatches = $this->matches()->where('status', 'completed')->count();
        return round(($completedMatches / $totalMatches) * 100, 1);
    }

    public function getRoundsProgressAttribute()
    {
        if ($this->round_count === 0) return 0;
        
        return round(($this->current_round / $this->round_count) * 100, 1);
    }

    public function getIsDoubleEliminationAttribute()
    {
        return in_array($this->bracket_type, [
            'double_elimination_upper',
            'double_elimination_lower'
        ]);
    }

    public function getRequiresBracketResetAttribute()
    {
        if (!$this->is_double_elimination) return false;
        
        // Check if lower bracket winner beat upper bracket winner in Grand Final
        $grandFinal = $this->getGrandFinalMatch();
        return $grandFinal && $this->isLowerBracketWinner($grandFinal) && !$this->reset_occurred;
    }

    // Bracket Generation Methods
    public function generateBracket(array $teams): bool
    {
        if ($this->status !== 'pending') return false;
        if (empty($teams)) return false;

        $this->team_count = count($teams);
        
        switch ($this->bracket_type) {
            case 'single_elimination':
                return $this->generateSingleEliminationBracket($teams);
            
            case 'double_elimination_upper':
                return $this->generateUpperBracket($teams);
            
            case 'double_elimination_lower':
                return $this->generateLowerBracket($teams);
            
            case 'swiss_system':
                return $this->generateSwissBracket($teams);
            
            case 'round_robin':
                return $this->generateRoundRobinBracket($teams);
            
            case 'group_stage':
                return $this->generateGroupStageBracket($teams);
            
            default:
                return false;
        }
    }

    private function generateSingleEliminationBracket(array $teams): bool
    {
        $teamCount = count($teams);
        $this->round_count = ceil(log($teamCount, 2));
        
        // Generate R#M# format matches (Liquipedia style)
        $matches = [];
        $round = 1;
        $teamsInRound = $teams;

        // Create first round matches
        for ($i = 0; $i < count($teamsInRound); $i += 2) {
            if (isset($teamsInRound[$i + 1])) {
                $matchId = "R{$round}M" . (($i / 2) + 1);
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => ($i / 2) + 1,
                    'team1' => $teamsInRound[$i],
                    'team2' => $teamsInRound[$i + 1],
                    'winner_advances_to' => $this->getNextRoundMatch($round, ($i / 2) + 1),
                    'status' => 'pending'
                ];
            }
        }

        // Generate subsequent rounds
        while ($round < $this->round_count) {
            $round++;
            $matchesInRound = pow(2, $this->round_count - $round);
            
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matchId = "R{$round}M{$m}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $m,
                    'team1' => null, // To be determined
                    'team2' => null, // To be determined
                    'winner_advances_to' => $round < $this->round_count ? 
                                          "R" . ($round + 1) . "M" . ceil($m / 2) : null,
                    'status' => 'pending'
                ];
            }
        }

        $this->bracket_data = $matches;
        $this->seeding_data = $teams;
        $this->status = 'active';
        $this->save();

        // Create actual match records
        return $this->createMatchRecords();
    }

    private function generateUpperBracket(array $teams): bool
    {
        // Similar to single elimination but with different advancement rules
        $result = $this->generateSingleEliminationBracket($teams);
        
        if ($result) {
            // Modify advancement rules for upper bracket
            $bracketData = $this->bracket_data;
            foreach ($bracketData as $matchId => &$match) {
                $match['loser_advances_to'] = $this->getLowerBracketDestination($matchId);
            }
            
            $this->bracket_data = $bracketData;
            $this->save();
        }
        
        return $result;
    }

    private function generateLowerBracket(array $teams): bool
    {
        // Generate complex lower bracket structure
        $teamCount = count($teams);
        $rounds = (ceil(log($teamCount, 2)) * 2) - 1; // Lower bracket has more rounds
        
        $matches = [];
        $this->round_count = $rounds;
        
        // Lower bracket has alternating elimination and winner-stays rounds
        for ($round = 1; $round <= $rounds; $round++) {
            $isEliminationRound = ($round % 2) === 1;
            $matchesInRound = $this->getLowerBracketMatchCount($round, $teamCount);
            
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matchId = "LB_R{$round}M{$m}";
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => $m,
                    'is_elimination' => $isEliminationRound,
                    'team1' => null,
                    'team2' => null,
                    'winner_advances_to' => $this->getLowerBracketAdvancement($round, $m),
                    'loser_eliminated' => true,
                    'status' => 'pending'
                ];
            }
        }
        
        $this->bracket_data = $matches;
        $this->status = 'active';
        $this->save();
        
        return $this->createMatchRecords();
    }

    private function generateSwissBracket(array $teams): bool
    {
        $teamCount = count($teams);
        $this->round_count = ceil(log($teamCount, 2)); // Typically 5-7 rounds
        
        // Swiss system generates matches round by round
        // Start with round 1 only
        $matches = $this->generateSwissRound(1, $teams);
        
        $this->bracket_data = $matches;
        $this->seeding_data = $teams;
        $this->status = 'active';
        $this->save();
        
        return $this->createMatchRecords();
    }

    private function generateSwissRound(int $round, array $teams): array
    {
        $matches = [];
        shuffle($teams); // Random pairing for first round
        
        // For subsequent rounds, use Swiss pairing algorithm
        if ($round > 1) {
            $teams = $this->getSwissStandings();
            $teams = $this->applySwissPairingRules($teams);
        }
        
        for ($i = 0; $i < count($teams); $i += 2) {
            if (isset($teams[$i + 1])) {
                $matchId = "SW_R{$round}M" . (($i / 2) + 1);
                $matches[$matchId] = [
                    'round' => $round,
                    'match_number' => ($i / 2) + 1,
                    'team1' => $teams[$i],
                    'team2' => $teams[$i + 1],
                    'status' => 'pending',
                    'swiss_round' => $round
                ];
            }
        }
        
        return $matches;
    }

    private function generateRoundRobinBracket(array $teams): bool
    {
        $teamCount = count($teams);
        $this->round_count = $teamCount - 1;
        
        $matches = [];
        $matchNumber = 1;
        
        // Generate all possible pairings
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                $matchId = "RR_M{$matchNumber}";
                $matches[$matchId] = [
                    'match_number' => $matchNumber,
                    'team1' => $teams[$i],
                    'team2' => $teams[$j],
                    'status' => 'pending',
                    'round' => $this->calculateRoundRobinRound($i, $j, $teamCount)
                ];
                $matchNumber++;
            }
        }
        
        $this->bracket_data = $matches;
        $this->seeding_data = $teams;
        $this->status = 'active';
        $this->save();
        
        return $this->createMatchRecords();
    }

    private function generateGroupStageBracket(array $teams): bool
    {
        $groupSize = $this->match_settings['group_size'] ?? 4;
        $groupCount = ceil(count($teams) / $groupSize);
        
        $groups = [];
        $teamIndex = 0;
        
        // Distribute teams into groups
        for ($g = 1; $g <= $groupCount; $g++) {
            $groupTeams = array_slice($teams, $teamIndex, $groupSize);
            $teamIndex += $groupSize;
            
            // Generate round robin for each group
            $groupMatches = $this->generateGroupMatches($groupTeams, $g);
            $groups["Group_{$g}"] = [
                'teams' => $groupTeams,
                'matches' => $groupMatches
            ];
        }
        
        $this->bracket_data = $groups;
        $this->seeding_data = $teams;
        $this->status = 'active';
        $this->save();
        
        return $this->createMatchRecords();
    }

    private function generateGroupMatches(array $teams, int $groupId): array
    {
        $matches = [];
        $matchNumber = 1;
        
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $matchId = "G{$groupId}_M{$matchNumber}";
                $matches[$matchId] = [
                    'group_id' => $groupId,
                    'match_number' => $matchNumber,
                    'team1' => $teams[$i],
                    'team2' => $teams[$j],
                    'status' => 'pending'
                ];
                $matchNumber++;
            }
        }
        
        return $matches;
    }

    // Bracket Management Methods
    public function advanceTeam(int $teamId, string $matchId, bool $isWinner = true): bool
    {
        $bracketData = $this->bracket_data;
        
        if (!isset($bracketData[$matchId])) return false;
        
        $match = &$bracketData[$matchId];
        
        if ($isWinner && isset($match['winner_advances_to'])) {
            $nextMatchId = $match['winner_advances_to'];
            if (isset($bracketData[$nextMatchId])) {
                $this->assignTeamToMatch($bracketData[$nextMatchId], $teamId);
            }
        } elseif (!$isWinner && isset($match['loser_advances_to'])) {
            $nextMatchId = $match['loser_advances_to'];
            if (isset($bracketData[$nextMatchId])) {
                $this->assignTeamToMatch($bracketData[$nextMatchId], $teamId);
            }
        }
        
        $this->bracket_data = $bracketData;
        $this->save();
        
        return true;
    }

    private function assignTeamToMatch(array &$match, int $teamId): void
    {
        if ($match['team1'] === null) {
            $match['team1'] = $teamId;
        } elseif ($match['team2'] === null) {
            $match['team2'] = $teamId;
        }
    }

    public function generateNextSwissRound(): bool
    {
        if ($this->bracket_type !== 'swiss_system') return false;
        if ($this->current_round >= $this->round_count) return false;
        
        $nextRound = $this->current_round + 1;
        $activeTeams = $this->getActiveSwissTeams();
        
        $newMatches = $this->generateSwissRound($nextRound, $activeTeams);
        $bracketData = $this->bracket_data;
        $bracketData = array_merge($bracketData, $newMatches);
        
        $this->bracket_data = $bracketData;
        $this->current_round = $nextRound;
        $this->save();
        
        return $this->createMatchRecords();
    }

    public function performBracketReset(): bool
    {
        if (!$this->requires_bracket_reset) return false;
        
        // Create reset bracket for Grand Final
        $grandFinal = $this->getGrandFinalMatch();
        if (!$grandFinal) return false;
        
        $resetMatch = [
            'round' => $this->round_count + 1,
            'match_number' => 1,
            'team1' => $grandFinal->team1_id,
            'team2' => $grandFinal->team2_id,
            'is_reset_match' => true,
            'status' => 'pending'
        ];
        
        $bracketData = $this->bracket_data;
        $bracketData['RESET_GF'] = $resetMatch;
        
        $this->bracket_data = $bracketData;
        $this->reset_occurred = true;
        $this->round_count += 1;
        $this->save();
        
        return $this->createMatchRecords();
    }

    // Helper Methods
    private function getNextRoundMatch(int $round, int $matchNumber): ?string
    {
        $nextRound = $round + 1;
        $nextMatch = ceil($matchNumber / 2);
        return "R{$nextRound}M{$nextMatch}";
    }

    private function getLowerBracketDestination(string $upperMatchId): ?string
    {
        // Complex logic for determining where upper bracket losers go in lower bracket
        preg_match('/R(\d+)M(\d+)/', $upperMatchId, $matches);
        if (!$matches) return null;
        
        $round = (int)$matches[1];
        $match = (int)$matches[2];
        
        // Simplified lower bracket destination logic
        $lbRound = (($round - 1) * 2) + 1;
        return "LB_R{$lbRound}M{$match}";
    }

    private function getLowerBracketMatchCount(int $round, int $totalTeams): int
    {
        // Complex calculation based on double elimination structure
        $baseMatches = ceil($totalTeams / 2);
        return max(1, $baseMatches - floor($round / 2));
    }

    private function getLowerBracketAdvancement(int $round, int $match): ?string
    {
        if ($round >= $this->round_count) return 'GRAND_FINAL';
        
        $nextRound = $round + 1;
        $nextMatch = ceil($match / 2);
        return "LB_R{$nextRound}M{$nextMatch}";
    }

    private function getSwissStandings(): array
    {
        // Get current Swiss standings from tournament
        return $this->tournament->swiss_standings->toArray();
    }

    private function applySwissPairingRules(array $teams): array
    {
        // Implement Swiss pairing algorithm
        // 1. Pair teams with same score
        // 2. Avoid repeat matchups
        // 3. Consider color balance
        
        usort($teams, function($a, $b) {
            return $b['swiss_score'] - $a['swiss_score'];
        });
        
        $pairedTeams = [];
        $used = [];
        
        foreach ($teams as $team) {
            if (in_array($team['id'], $used)) continue;
            
            $opponent = $this->findSwissOpponent($team, $teams, $used);
            if ($opponent) {
                $pairedTeams[] = $team;
                $pairedTeams[] = $opponent;
                $used[] = $team['id'];
                $used[] = $opponent['id'];
            }
        }
        
        return $pairedTeams;
    }

    private function findSwissOpponent(array $team, array $allTeams, array $used): ?array
    {
        foreach ($allTeams as $potential) {
            if ($potential['id'] === $team['id'] || in_array($potential['id'], $used)) continue;
            
            // Check if they've played before
            if (!$this->haveTeamsPlayedBefore($team['id'], $potential['id'])) {
                return $potential;
            }
        }
        
        // If no new opponent available, allow repeat (shouldn't happen in well-structured Swiss)
        foreach ($allTeams as $potential) {
            if ($potential['id'] !== $team['id'] && !in_array($potential['id'], $used)) {
                return $potential;
            }
        }
        
        return null;
    }

    private function haveTeamsPlayedBefore(int $team1Id, int $team2Id): bool
    {
        return $this->matches()
                   ->where(function($query) use ($team1Id, $team2Id) {
                       $query->where('team1_id', $team1Id)->where('team2_id', $team2Id);
                   })
                   ->orWhere(function($query) use ($team1Id, $team2Id) {
                       $query->where('team1_id', $team2Id)->where('team2_id', $team1Id);
                   })
                   ->exists();
    }

    private function calculateRoundRobinRound(int $i, int $j, int $teamCount): int
    {
        // Simple round calculation for round robin
        return 1 + (($i + $j) % ($teamCount - 1));
    }

    private function getActiveSwissTeams(): array
    {
        // Get teams that are still active in Swiss (not eliminated)
        return $this->tournament->teams()
                   ->wherePivot('swiss_losses', '<', 3) // Not eliminated
                   ->get()
                   ->toArray();
    }

    private function getGrandFinalMatch(): ?BracketMatch
    {
        return $this->matches()
                   ->where('round', $this->round_count)
                   ->where('match_number', 1)
                   ->first();
    }

    private function isLowerBracketWinner(BracketMatch $match): bool
    {
        $winnerTeamId = $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id;
        
        // Check if winner came from lower bracket
        // This would require tracking bracket paths
        return $this->tournament->teams()
                   ->wherePivot('team_id', $winnerTeamId)
                   ->whereNotNull('pivot_elimination_round')
                   ->exists();
    }

    private function createMatchRecords(): bool
    {
        $bracketData = $this->bracket_data;
        
        foreach ($bracketData as $matchId => $matchData) {
            // Skip if match already exists
            $existingMatch = $this->matches()
                                 ->where('match_identifier', $matchId)
                                 ->first();
            
            if ($existingMatch) continue;
            
            BracketMatch::create([
                'tournament_id' => $this->tournament_id,
                'tournament_phase_id' => $this->tournament_phase_id,
                'tournament_bracket_id' => $this->id,
                'match_identifier' => $matchId,
                'round' => $matchData['round'] ?? 1,
                'match_number' => $matchData['match_number'] ?? 1,
                'team1_id' => $matchData['team1'] ?? null,
                'team2_id' => $matchData['team2'] ?? null,
                'status' => $matchData['status'] ?? 'pending',
                'match_format' => $this->match_settings['format'] ?? 'bo3',
                'scheduled_at' => $this->calculateMatchSchedule($matchData)
            ]);
        }
        
        return true;
    }

    private function calculateMatchSchedule(array $matchData): ?Carbon
    {
        // Simple scheduling logic - can be made more sophisticated
        $baseTime = $this->tournament->start_date ?? now();
        $roundDelay = ($matchData['round'] - 1) * 2; // 2 hours per round
        $matchDelay = ($matchData['match_number'] - 1) * 1; // 1 hour between matches
        
        return $baseTime->copy()->addHours($roundDelay + $matchDelay);
    }

    public function complete(): bool
    {
        if ($this->status === 'completed') return false;
        
        $this->status = 'completed';
        $this->completed_at = now();
        $this->results_data = $this->calculateBracketResults();
        $this->save();
        
        return true;
    }

    private function calculateBracketResults(): array
    {
        $completedMatches = $this->matches()->where('status', 'completed')->get();
        
        return [
            'total_matches' => $completedMatches->count(),
            'bracket_winner' => $this->getBracketWinner(),
            'final_standings' => $this->getFinalStandings(),
            'completed_at' => now()->toDateTimeString()
        ];
    }

    private function getBracketWinner(): ?int
    {
        switch ($this->bracket_type) {
            case 'single_elimination':
            case 'double_elimination_upper':
            case 'double_elimination_lower':
                return $this->getEliminationWinner();
            
            case 'swiss_system':
                return $this->getSwissWinner();
            
            case 'round_robin':
            case 'group_stage':
                return $this->getRoundRobinWinner();
            
            default:
                return null;
        }
    }

    private function getEliminationWinner(): ?int
    {
        $finalMatch = $this->matches()
                          ->where('round', $this->current_round)
                          ->where('status', 'completed')
                          ->first();
        
        if (!$finalMatch) return null;
        
        return $finalMatch->team1_score > $finalMatch->team2_score ? 
               $finalMatch->team1_id : $finalMatch->team2_id;
    }

    private function getSwissWinner(): ?int
    {
        $standings = $this->tournament->swiss_standings;
        return $standings->first()?->id;
    }

    private function getRoundRobinWinner(): ?int
    {
        // Calculate round robin standings
        $teamStats = [];
        
        foreach ($this->matches()->where('status', 'completed')->get() as $match) {
            $winner = $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id;
            $loser = $winner === $match->team1_id ? $match->team2_id : $match->team1_id;
            
            $teamStats[$winner]['wins'] = ($teamStats[$winner]['wins'] ?? 0) + 1;
            $teamStats[$loser]['losses'] = ($teamStats[$loser]['losses'] ?? 0) + 1;
        }
        
        // Sort by wins
        arsort($teamStats);
        return array_key_first($teamStats);
    }

    private function getFinalStandings(): array
    {
        // Return final standings based on bracket type
        // Implementation would vary by bracket type
        return [];
    }
}