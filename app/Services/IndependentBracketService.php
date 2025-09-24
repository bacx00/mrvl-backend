<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class IndependentBracketService
{
    /**
     * Save or update bracket data for an event
     * Brackets are stored as JSON and are completely independent of matches
     */
    public function saveBracket($eventId, $bracketData, $linkToMatches = false)
    {
        DB::beginTransaction();

        try {
            // Store bracket as JSON in events table
            $event = DB::table('events')->where('id', $eventId)->first();

            if (!$event) {
                throw new \Exception('Event not found');
            }

            // Save bracket data
            DB::table('events')->where('id', $eventId)->update([
                'bracket_data' => json_encode($bracketData),
                'bracket_updated_at' => now(),
                'updated_at' => now()
            ]);

            // If linking to matches is requested, sync the data
            if ($linkToMatches && isset($bracketData['stages'])) {
                $this->syncBracketWithMatches($eventId, $bracketData);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Bracket saved successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get bracket data for an event
     */
    public function getBracket($eventId)
    {
        $event = DB::table('events')
            ->where('id', $eventId)
            ->first();

        if (!$event || !$event->bracket_data) {
            return null;
        }

        $bracket = json_decode($event->bracket_data, true);

        // Add event matches if available
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->get()
            ->map(function ($match) {
                // Get team names
                if ($match->team1_id) {
                    $team1 = DB::table('teams')->where('id', $match->team1_id)->first();
                    $match->team1_name = $team1 ? $team1->name : null;
                    $match->team1_logo = $team1 ? $team1->logo : null;
                }
                if ($match->team2_id) {
                    $team2 = DB::table('teams')->where('id', $match->team2_id)->first();
                    $match->team2_name = $team2 ? $team2->name : null;
                    $match->team2_logo = $team2 ? $team2->logo : null;
                }
                return $match;
            });

        return [
            'bracket' => $bracket,
            'event_matches' => $matches,
            'last_updated' => $event->bracket_updated_at
        ];
    }

    /**
     * Update a single match in the bracket
     */
    public function updateBracketMatch($eventId, $stageId, $matchId, $updates)
    {
        $event = DB::table('events')->where('id', $eventId)->first();

        if (!$event || !$event->bracket_data) {
            throw new \Exception('Bracket not found');
        }

        $bracket = json_decode($event->bracket_data, true);

        // Find and update the specific match
        foreach ($bracket['stages'] as &$stage) {
            if ($stage['id'] === $stageId) {
                foreach ($stage['matches'] as &$match) {
                    if ($match['id'] === $matchId) {
                        // Update match properties
                        foreach ($updates as $key => $value) {
                            $match[$key] = $value;
                        }
                        break;
                    }
                }
                break;
            }
        }

        // Save updated bracket
        DB::table('events')->where('id', $eventId)->update([
            'bracket_data' => json_encode($bracket),
            'bracket_updated_at' => now()
        ]);

        return [
            'success' => true,
            'message' => 'Match updated successfully'
        ];
    }

    /**
     * Sync bracket matches with event matches (optional)
     */
    private function syncBracketWithMatches($eventId, $bracketData)
    {
        foreach ($bracketData['stages'] as $stage) {
            foreach ($stage['matches'] as $match) {
                if (isset($match['linkedMatchId']) && $match['linkedMatchId']) {
                    // Update the linked match with bracket data
                    DB::table('matches')
                        ->where('id', $match['linkedMatchId'])
                        ->where('event_id', $eventId)
                        ->update([
                            'team1_id' => $match['team1']['id'] ?? null,
                            'team2_id' => $match['team2']['id'] ?? null,
                            'team1_score' => $match['score1'] ?? 0,
                            'team2_score' => $match['score2'] ?? 0,
                            'match_format' => $match['format'] ?? 'Bo3',
                            'status' => $match['status'] ?? 'pending',
                            'updated_at' => now()
                        ]);
                } else if (isset($match['createNewMatch']) && $match['createNewMatch']) {
                    // Create a new match in the matches table
                    DB::table('matches')->insert([
                        'event_id' => $eventId,
                        'team1_id' => $match['team1']['id'] ?? null,
                        'team2_id' => $match['team2']['id'] ?? null,
                        'team1_score' => $match['score1'] ?? 0,
                        'team2_score' => $match['score2'] ?? 0,
                        'match_format' => $match['format'] ?? 'Bo3',
                        'best_of' => $this->getBoNumber($match['format'] ?? 'Bo3'),
                        'round_name' => $match['roundName'] ?? null,
                        'status' => $match['status'] ?? 'pending',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }

    /**
     * Clear bracket data for an event
     */
    public function clearBracket($eventId)
    {
        DB::table('events')->where('id', $eventId)->update([
            'bracket_data' => null,
            'bracket_updated_at' => null
        ]);

        return [
            'success' => true,
            'message' => 'Bracket cleared successfully'
        ];
    }

    /**
     * Clone bracket from one event to another
     */
    public function cloneBracket($sourceEventId, $targetEventId)
    {
        $source = DB::table('events')->where('id', $sourceEventId)->first();

        if (!$source || !$source->bracket_data) {
            throw new \Exception('Source bracket not found');
        }

        // Clone the bracket structure but clear teams and scores
        $bracket = json_decode($source->bracket_data, true);

        foreach ($bracket['stages'] as &$stage) {
            foreach ($stage['matches'] as &$match) {
                $match['team1'] = null;
                $match['team2'] = null;
                $match['score1'] = 0;
                $match['score2'] = 0;
                $match['status'] = 'pending';
                $match['linkedMatchId'] = null;
            }
        }

        DB::table('events')->where('id', $targetEventId)->update([
            'bracket_data' => json_encode($bracket),
            'bracket_updated_at' => now()
        ]);

        return [
            'success' => true,
            'message' => 'Bracket cloned successfully'
        ];
    }

    /**
     * Export bracket data
     */
    public function exportBracket($eventId)
    {
        $data = $this->getBracket($eventId);

        if (!$data) {
            return null;
        }

        return [
            'event_id' => $eventId,
            'bracket' => $data['bracket'],
            'exported_at' => now()->toIso8601String()
        ];
    }

    /**
     * Import bracket data
     */
    public function importBracket($eventId, $bracketData)
    {
        return $this->saveBracket($eventId, $bracketData, false);
    }

    private function getBoNumber($format)
    {
        $formats = [
            'Bo1' => 1,
            'Bo2' => 2,
            'Bo3' => 3,
            'Bo4' => 4,
            'Bo5' => 5,
            'Bo6' => 6,
            'Bo7' => 7,
            'Bo8' => 8,
            'Bo9' => 9
        ];

        return $formats[$format] ?? 3;
    }
}