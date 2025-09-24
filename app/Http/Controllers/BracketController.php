<?php

namespace App\Http\Controllers;

use App\Services\IndependentBracketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BracketController extends Controller
{
    private $bracketService;

    public function __construct(IndependentBracketService $bracketService)
    {
        $this->bracketService = $bracketService;
    }

    /**
     * Save or update bracket data for an event
     */
    public function saveBracket(Request $request, $eventId): JsonResponse
    {
        try {
            $bracketData = $request->input('bracket');
            $linkToMatches = $request->input('linkToMatches', false);

            if (!$bracketData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bracket data is required'
                ], 400);
            }

            $result = $this->bracketService->saveBracket($eventId, $bracketData, $linkToMatches);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bracket data for an event
     */
    public function getBracket($eventId): JsonResponse
    {
        try {
            $data = $this->bracketService->getBracket($eventId);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bracket not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a single match in the bracket
     */
    public function updateBracketMatch(Request $request, $eventId): JsonResponse
    {
        try {
            $stageId = $request->input('stageId');
            $matchId = $request->input('matchId');
            $updates = $request->input('updates');

            if (!$stageId || !$matchId || !$updates) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stage ID, match ID and updates are required'
                ], 400);
            }

            $result = $this->bracketService->updateBracketMatch($eventId, $stageId, $matchId, $updates);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear bracket data for an event
     */
    public function clearBracket($eventId): JsonResponse
    {
        try {
            $result = $this->bracketService->clearBracket($eventId);
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clone bracket from one event to another
     */
    public function cloneBracket(Request $request, $targetEventId): JsonResponse
    {
        try {
            $sourceEventId = $request->input('sourceEventId');

            if (!$sourceEventId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source event ID is required'
                ], 400);
            }

            $result = $this->bracketService->cloneBracket($sourceEventId, $targetEventId);
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clone bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export bracket data
     */
    public function exportBracket($eventId): JsonResponse
    {
        try {
            $data = $this->bracketService->exportBracket($eventId);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bracket not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import bracket data
     */
    public function importBracket(Request $request, $eventId): JsonResponse
    {
        try {
            $bracketData = $request->input('bracket');

            if (!$bracketData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bracket data is required'
                ], 400);
            }

            $result = $this->bracketService->importBracket($eventId, $bracketData);
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import bracket: ' . $e->getMessage()
            ], 500);
        }
    }
}