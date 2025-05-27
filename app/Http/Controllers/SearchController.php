<?php
namespace App\Http\Controllers;

use App\Models\{Team, Player, Match, Event, ForumThread};
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q');
        $category = $request->get('category', 'all');

        if (!$query || strlen(trim($query)) < 2) {
            return response()->json([
                'query' => $query,
                'category' => $category,
                'results' => [
                    'teams' => [],
                    'players' => [],
                    'matches' => [],
                    'events' => [],
                    'forums' => []
                ],
                'total' => 0,
                'success' => true
            ]);
        }

        $results = [];

        if ($category === 'all' || $category === 'teams') {
            $results['teams'] = Team::where('name', 'LIKE', "%{$query}%")
                                   ->orWhere('short_name', 'LIKE', "%{$query}%")
                                   ->with(['players'])
                                   ->limit(10)
                                   ->get();
        }

        if ($category === 'all' || $category === 'players') {
            $results['players'] = Player::where('username', 'LIKE', "%{$query}%")
                                       ->orWhere('real_name', 'LIKE', "%{$query}%")
                                       ->with('team')
                                       ->limit(10)
                                       ->get();
        }

        if ($category === 'all' || $category === 'matches') {
            $results['matches'] = Match::whereHas('team1', function($q) use ($query) {
                                        $q->where('name', 'LIKE', "%{$query}%");
                                    })
                                    ->orWhereHas('team2', function($q) use ($query) {
                                        $q->where('name', 'LIKE', "%{$query}%");
                                    })
                                    ->with(['team1', 'team2', 'event'])
                                    ->limit(10)
                                    ->get();
        }

        if ($category === 'all' || $category === 'events') {
            $results['events'] = Event::where('name', 'LIKE', "%{$query}%")
                                     ->limit(10)
                                     ->get();
        }

        if ($category === 'all' || $category === 'forums') {
            $results['forums'] = ForumThread::where('title', 'LIKE', "%{$query}%")
                                           ->with('user')
                                           ->limit(10)
                                           ->get();
        }

        $total = collect($results)->sum(fn($items) => count($items));

        return response()->json([
            'query' => $query,
            'category' => $category,
            'results' => $results,
            'total' => $total,
            'success' => true
        ]);
    }
}
