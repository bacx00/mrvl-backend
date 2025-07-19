<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Team, Player, GameMatch, Event, User};
use Illuminate\Support\Facades\DB;

class BulkOperationController extends Controller
{
    public function bulkDelete(Request $request, $type)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        $model = $this->getModel($type);
        if (!$model) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        try {
            DB::beginTransaction();
            
            $deleted = $model::whereIn('id', $request->ids)->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "$deleted items deleted successfully",
                'deleted_count' => $deleted
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function bulkUpdate(Request $request, $type)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'data' => 'required|array'
        ]);

        $model = $this->getModel($type);
        if (!$model) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        try {
            DB::beginTransaction();
            
            $updated = $model::whereIn('id', $request->ids)->update($request->data);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "$updated items updated successfully",
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function bulkArchive(Request $request, $type)
    {
        return $this->bulkUpdate($request->merge(['data' => ['status' => 'archived']]), $type);
    }

    public function bulkActivate(Request $request, $type)
    {
        return $this->bulkUpdate($request->merge(['data' => ['status' => 'active']]), $type);
    }

    public function bulkDeactivate(Request $request, $type)
    {
        return $this->bulkUpdate($request->merge(['data' => ['status' => 'inactive']]), $type);
    }

    private function getModel($type)
    {
        return match($type) {
            'teams' => Team::class,
            'players' => Player::class,
            'matches' => GameMatch::class,
            'events' => Event::class,
            'users' => User::class,
            default => null
        };
    }
}