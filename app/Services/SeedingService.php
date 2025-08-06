<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeedingService
{
    /**
     * Apply advanced seeding methods to tournament teams
     */
    public function applySeedingMethod($teams, $method, $options = [])
    {
        switch ($method) {
            case 'rating':
                return $this->seedByRating($teams, $options);
            case 'manual':
                return $this->seedManually($teams, $options);
            case 'random':
                return $this->seedRandomly($teams, $options);
            case 'balanced':
                return $this->seedBalanced($teams, $options);
            case 'regional':
                return $this->seedRegionally($teams, $options);
            case 'performance':
                return $this->seedByPerformance($teams, $options);
            default:
                return $this->seedByRating($teams, $options);
        }
    }

    /**
     * Seed teams by their current rating/ranking
     */
    private function seedByRating($teams, $options = [])
    {
        // Sort teams by rating (highest first)
        $sortedTeams = collect($teams)->sortByDesc(function ($team) {
            return $team['rating'] ?? 0;
        })->values()->all();

        // Apply additional randomization if requested
        if ($options['randomize_seeds'] ?? false) {
            $sortedTeams = $this->applyLimitedRandomization($sortedTeams, $options['randomization_factor'] ?? 0.2);
        }

        return $sortedTeams;
    }

    /**
     * Use existing manual seed positions
     */
    private function seedManually($teams, $options = [])
    {
        // Sort by existing seed value
        $sortedTeams = collect($teams)->sortBy(function ($team) {
            return $team['seed'] ?? 999; // Unseeded teams go to end
        })->values()->all();

        return $sortedTeams;
    }

    /**
     * Completely random seeding
     */
    private function seedRandomly($teams, $options = [])
    {
        $shuffledTeams = $teams;
        shuffle($shuffledTeams);
        return $shuffledTeams;
    }

    /**
     * Balanced seeding to distribute strength across bracket
     */
    private function seedBalanced($teams, $options = [])
    {
        // First sort by rating
        $ratedTeams = $this->seedByRating($teams, $options);
        
        // Group teams into tiers
        $tierSize = max(1, floor(count($ratedTeams) / 4)); // 4 tiers
        $tiers = [];
        
        for ($i = 0; $i < 4; $i++) {
            $tiers[$i] = array_slice($ratedTeams, $i * $tierSize, $tierSize);
        }
        
        // Distribute teams from each tier across bracket positions
        $balancedTeams = [];
        $maxTierSize = max(array_map('count', $tiers));
        
        for ($pos = 0; $pos < $maxTierSize; $pos++) {
            for ($tier = 0; $tier < 4; $tier++) {
                if (isset($tiers[$tier][$pos])) {
                    $balancedTeams[] = $tiers[$tier][$pos];
                }
            }
        }
        
        return $balancedTeams;
    }

    /**
     * Regional seeding to avoid early regional matchups
     */
    private function seedRegionally($teams, $options = [])
    {
        // Group teams by region
        $regionalGroups = [];
        foreach ($teams as $team) {
            $region = $team['region'] ?? 'Unknown';
            if (!isset($regionalGroups[$region])) {
                $regionalGroups[$region] = [];
            }
            $regionalGroups[$region][] = $team;
        }
        
        // Sort each regional group by rating
        foreach ($regionalGroups as $region => &$regionTeams) {
            $regionTeams = $this->seedByRating($regionTeams, $options);
        }
        
        // Distribute regionally to minimize early conflicts
        $seededTeams = [];
        $maxRegionalTeams = max(array_map('count', $regionalGroups));
        
        for ($pos = 0; $pos < $maxRegionalTeams; $pos++) {
            foreach ($regionalGroups as $region => $regionTeams) {
                if (isset($regionTeams[$pos])) {
                    $seededTeams[] = $regionTeams[$pos];
                }
            }
        }
        
        return $seededTeams;
    }

    /**
     * Seed by recent performance/form
     */
    private function seedByPerformance($teams, $options = [])
    {
        // Calculate recent performance score for each team
        $performanceScores = [];
        
        foreach ($teams as $team) {
            $performanceScores[$team['id']] = $this->calculatePerformanceScore($team['id'], $options);
        }
        
        // Sort teams by performance score
        $sortedTeams = collect($teams)->sortByDesc(function ($team) use ($performanceScores) {
            return $performanceScores[$team['id']] ?? 0;
        })->values()->all();
        
        return $sortedTeams;
    }

    /**
     * Calculate team performance score based on recent matches
     */
    private function calculatePerformanceScore($teamId, $options = [])
    {
        $weeksBack = $options['performance_weeks'] ?? 8;
        $cutoffDate = now()->subWeeks($weeksBack);
        
        // Get recent matches
        $matches = DB::table('matches')
            ->where(function ($query) use ($teamId) {
                $query->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
            })
            ->where('status', 'completed')
            ->where('completed_at', '>=', $cutoffDate)
            ->orderBy('completed_at', 'desc')
            ->limit(20) // Last 20 matches
            ->get();
        
        if ($matches->isEmpty()) {
            return 0;
        }
        
        $totalScore = 0;
        $matchCount = 0;
        $recentWeight = 2.0; // More recent matches weighted higher
        
        foreach ($matches as $match) {
            $isTeam1 = $match->team1_id == $teamId;
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            // Calculate match performance (0-1 scale)
            $maxScore = max($teamScore, $opponentScore);
            if ($maxScore > 0) {
                $matchPerformance = $teamScore / $maxScore;
                
                // Apply time-based weighting (more recent = higher weight)
                $daysAgo = now()->diffInDays($match->completed_at);
                $timeWeight = max(0.1, $recentWeight - ($daysAgo / 30)); // Decay over 30 days
                
                $totalScore += $matchPerformance * $timeWeight;
                $matchCount += $timeWeight;
            }
        }
        
        return $matchCount > 0 ? $totalScore / $matchCount : 0;
    }

    /**
     * Apply limited randomization to seeded teams
     */
    private function applyLimitedRandomization($teams, $factor = 0.2)
    {
        $teamCount = count($teams);
        $swapCount = max(1, floor($teamCount * $factor));
        
        for ($i = 0; $i < $swapCount; $i++) {
            // Randomly swap teams within a limited range
            $pos1 = rand(0, $teamCount - 1);
            $maxRange = max(1, floor($teamCount * 0.3)); // Max 30% range
            $pos2 = max(0, min($teamCount - 1, $pos1 + rand(-$maxRange, $maxRange)));
            
            // Swap teams
            $temp = $teams[$pos1];
            $teams[$pos1] = $teams[$pos2];
            $teams[$pos2] = $temp;
        }
        
        return $teams;
    }

    /**
     * Generate optimal bracket seeding for single elimination
     */
    public function generateSingleEliminationSeeds($teams)
    {
        $teamCount = count($teams);
        $bracketSize = $this->getNextPowerOfTwo($teamCount);
        
        // Create seeding pattern that avoids strong early matchups
        $seedingPattern = $this->generateSeedingPattern($bracketSize);
        
        $seededBracket = [];
        for ($i = 0; $i < $bracketSize; $i++) {
            $seedPosition = $seedingPattern[$i];
            if ($seedPosition <= $teamCount) {
                $seededBracket[$i] = $teams[$seedPosition - 1];
            } else {
                $seededBracket[$i] = null; // Bye
            }
        }
        
        return array_filter($seededBracket); // Remove nulls
    }

    /**
     * Generate classic tournament seeding pattern
     */
    private function generateSeedingPattern($bracketSize)
    {
        $pattern = [1];
        
        while (count($pattern) < $bracketSize) {
            $newPattern = [];
            $max = count($pattern) * 2 + 1;
            
            foreach ($pattern as $seed) {
                $newPattern[] = $seed;
                $newPattern[] = $max - $seed;
            }
            
            $pattern = $newPattern;
        }
        
        return array_slice($pattern, 0, $bracketSize);
    }

    /**
     * Generate double elimination seeding
     */
    public function generateDoubleEliminationSeeds($teams)
    {
        // Same as single elimination for upper bracket
        return $this->generateSingleEliminationSeeds($teams);
    }

    /**
     * Generate Swiss system initial pairing
     */
    public function generateSwissInitialPairing($teams, $method = 'folding')
    {
        switch ($method) {
            case 'folding':
                return $this->generateFoldingPairing($teams);
            case 'adjacent':
                return $this->generateAdjacentPairing($teams);
            case 'random':
                return $this->generateRandomPairing($teams);
            default:
                return $this->generateFoldingPairing($teams);
        }
    }

    /**
     * Folding pairing: 1vs(n/2+1), 2vs(n/2+2), etc.
     */
    private function generateFoldingPairing($teams)
    {
        $teamCount = count($teams);
        $half = ceil($teamCount / 2);
        $pairings = [];
        
        for ($i = 0; $i < $half; $i++) {
            if (isset($teams[$i]) && isset($teams[$i + $half])) {
                $pairings[] = [
                    'team1' => $teams[$i],
                    'team2' => $teams[$i + $half]
                ];
            }
        }
        
        return $pairings;
    }

    /**
     * Adjacent pairing: 1vs2, 3vs4, etc.
     */
    private function generateAdjacentPairing($teams)
    {
        $pairings = [];
        
        for ($i = 0; $i < count($teams); $i += 2) {
            if (isset($teams[$i + 1])) {
                $pairings[] = [
                    'team1' => $teams[$i],
                    'team2' => $teams[$i + 1]
                ];
            }
        }
        
        return $pairings;
    }

    /**
     * Random pairing for Swiss first round
     */
    private function generateRandomPairing($teams)
    {
        $shuffled = $teams;
        shuffle($shuffled);
        return $this->generateAdjacentPairing($shuffled);
    }

    /**
     * Generate round-robin optimal scheduling
     */
    public function generateRoundRobinSchedule($teams)
    {
        $teamCount = count($teams);
        
        if ($teamCount % 2 !== 0) {
            // Add bye team for odd numbers
            $teams[] = ['id' => null, 'name' => 'BYE', 'is_bye' => true];
            $teamCount++;
        }
        
        $rounds = [];
        $schedule = $this->generateCircleMethod($teams);
        
        foreach ($schedule as $roundIndex => $roundMatches) {
            $rounds[$roundIndex + 1] = array_filter($roundMatches, function ($match) {
                return !($match['team1']['is_bye'] ?? false) && !($match['team2']['is_bye'] ?? false);
            });
        }
        
        return $rounds;
    }

    /**
     * Circle method for round-robin scheduling
     */
    private function generateCircleMethod($teams)
    {
        $teamCount = count($teams);
        $rounds = $teamCount - 1;
        $schedule = [];
        
        // Fix the first team, rotate others
        $fixed = array_shift($teams);
        $rotating = $teams;
        
        for ($round = 0; $round < $rounds; $round++) {
            $roundMatches = [];
            
            // First team vs last rotating team
            $roundMatches[] = [
                'team1' => $fixed,
                'team2' => $rotating[$teamCount - 2]
            ];
            
            // Pair up other teams
            $half = ($teamCount - 2) / 2;
            for ($i = 0; $i < $half; $i++) {
                $roundMatches[] = [
                    'team1' => $rotating[$i],
                    'team2' => $rotating[$teamCount - 3 - $i]
                ];
            }
            
            $schedule[] = $roundMatches;
            
            // Rotate teams (keep first and last, rotate middle)
            if (count($rotating) > 2) {
                $last = array_pop($rotating);
                array_unshift($rotating, $last);
            }
        }
        
        return $schedule;
    }

    /**
     * Validate seeding integrity
     */
    public function validateSeeding($teams, $format)
    {
        $issues = [];
        
        // Check for duplicate seeds
        $seeds = [];
        foreach ($teams as $team) {
            if (isset($team['seed'])) {
                if (in_array($team['seed'], $seeds)) {
                    $issues[] = "Duplicate seed found: {$team['seed']}";
                }
                $seeds[] = $team['seed'];
            }
        }
        
        // Check seed sequence
        sort($seeds);
        for ($i = 1; $i <= count($teams); $i++) {
            if (!in_array($i, $seeds)) {
                $issues[] = "Missing seed: $i";
            }
        }
        
        // Format-specific validations
        switch ($format) {
            case 'single_elimination':
            case 'double_elimination':
                if (count($teams) < 2) {
                    $issues[] = "Need at least 2 teams for elimination format";
                }
                break;
            case 'round_robin':
                if (count($teams) < 3) {
                    $issues[] = "Need at least 3 teams for round-robin";
                }
                if (count($teams) > 20) {
                    $issues[] = "Too many teams for round-robin (max 20)";
                }
                break;
            case 'swiss':
                if (count($teams) < 4) {
                    $issues[] = "Need at least 4 teams for Swiss system";
                }
                break;
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Get next power of two for bracket sizing
     */
    private function getNextPowerOfTwo($n)
    {
        return pow(2, ceil(log($n, 2)));
    }

    /**
     * Calculate bye distribution for elimination tournaments
     */
    public function calculateByeDistribution($teamCount)
    {
        $bracketSize = $this->getNextPowerOfTwo($teamCount);
        $byes = $bracketSize - $teamCount;
        
        if ($byes === 0) {
            return ['byes' => 0, 'positions' => []];
        }
        
        // Distribute byes optimally
        $byePositions = [];
        $step = $bracketSize / $byes;
        
        for ($i = 0; $i < $byes; $i++) {
            $position = floor($i * $step);
            $byePositions[] = $position;
        }
        
        return [
            'byes' => $byes,
            'positions' => $byePositions,
            'bracket_size' => $bracketSize
        ];
    }
}