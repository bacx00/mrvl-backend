<?php

namespace App\Helpers;

class MatchFormatHelper
{
    /**
     * Get wins needed for a match format
     */
    public static function getWinsNeeded($format)
    {
        $winsNeeded = [
            'Bo1' => 1,
            'Bo2' => 2,  // Must win both games
            'Bo3' => 2,
            'Bo4' => 3,  // Rare format
            'Bo5' => 3,
            'Bo6' => 4,  // Rare format
            'Bo7' => 4,
            'Bo8' => 5,  // Rare format
            'Bo9' => 5
        ];

        if (isset($winsNeeded[$format])) {
            return $winsNeeded[$format];
        }

        // Extract number from format (e.g., "Bo11" -> 11)
        $games = intval(str_replace('Bo', '', $format));
        return $games > 0 ? ceil($games / 2) : 2;
    }

    /**
     * Check if a match is complete based on scores
     */
    public static function isMatchComplete($format, $score1, $score2)
    {
        $winsNeeded = self::getWinsNeeded($format);
        return $score1 >= $winsNeeded || $score2 >= $winsNeeded;
    }

    /**
     * Get the winner of a match
     */
    public static function getMatchWinner($format, $team1Id, $team2Id, $score1, $score2)
    {
        if (!self::isMatchComplete($format, $score1, $score2)) {
            return null;
        }

        $winsNeeded = self::getWinsNeeded($format);

        if ($score1 >= $winsNeeded) {
            return $team1Id;
        }

        if ($score2 >= $winsNeeded) {
            return $team2Id;
        }

        return null;
    }

    /**
     * Validate match scores for a format
     */
    public static function validateScores($format, $score1, $score2)
    {
        $winsNeeded = self::getWinsNeeded($format);
        $maxGames = intval(str_replace('Bo', '', $format));

        // Check if scores are non-negative
        if ($score1 < 0 || $score2 < 0) {
            return [
                'valid' => false,
                'message' => 'Scores cannot be negative'
            ];
        }

        // Check if total games played exceeds maximum
        $totalGames = $score1 + $score2;
        if ($totalGames > $maxGames) {
            return [
                'valid' => false,
                'message' => "Total games ($totalGames) exceeds maximum for $format ($maxGames)"
            ];
        }

        // For completed matches, verify winner has required wins
        if (self::isMatchComplete($format, $score1, $score2)) {
            // Check that the winner has exactly the wins needed
            // and loser has less than wins needed
            if ($score1 >= $winsNeeded) {
                if ($score2 >= $winsNeeded) {
                    return [
                        'valid' => false,
                        'message' => "Both teams cannot have $winsNeeded or more wins in $format"
                    ];
                }
            }
        }

        return [
            'valid' => true,
            'complete' => self::isMatchComplete($format, $score1, $score2),
            'winner' => self::isMatchComplete($format, $score1, $score2)
                        ? ($score1 >= $winsNeeded ? 'team1' : 'team2')
                        : null
        ];
    }

    /**
     * Get all available match formats
     */
    public static function getAllFormats()
    {
        return [
            'Bo1' => ['name' => 'Best of 1', 'games' => 1, 'wins_needed' => 1],
            'Bo2' => ['name' => 'Best of 2', 'games' => 2, 'wins_needed' => 2],
            'Bo3' => ['name' => 'Best of 3', 'games' => 3, 'wins_needed' => 2],
            'Bo4' => ['name' => 'Best of 4', 'games' => 4, 'wins_needed' => 3],
            'Bo5' => ['name' => 'Best of 5', 'games' => 5, 'wins_needed' => 3],
            'Bo6' => ['name' => 'Best of 6', 'games' => 6, 'wins_needed' => 4],
            'Bo7' => ['name' => 'Best of 7', 'games' => 7, 'wins_needed' => 4],
            'Bo8' => ['name' => 'Best of 8', 'games' => 8, 'wins_needed' => 5],
            'Bo9' => ['name' => 'Best of 9', 'games' => 9, 'wins_needed' => 5]
        ];
    }

    /**
     * Get format display name
     */
    public static function getFormatDisplayName($format)
    {
        $formats = self::getAllFormats();
        return $formats[$format]['name'] ?? $format;
    }

    /**
     * Calculate match progress percentage
     */
    public static function getMatchProgress($format, $score1, $score2)
    {
        $winsNeeded = self::getWinsNeeded($format);
        $maxProgress = max($score1, $score2);

        if ($winsNeeded > 0) {
            return min(100, round(($maxProgress / $winsNeeded) * 100));
        }

        return 0;
    }
}