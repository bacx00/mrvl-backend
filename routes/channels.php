<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that
| your application supports.  To learn more about broadcasting,
| see the documentation at https://laravel.com/docs/broadcasting
|
*/

use Illuminate\Support\Facades\Broadcast;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\BracketMatch;

// Public tournament channels - anyone can listen to tournament updates
Broadcast::channel('tournaments.public', function () {
    return true; // Public tournament list updates
});

Broadcast::channel('tournament.{tournamentId}', function ($user = null, $tournamentId) {
    return true; // Public tournament updates
});

Broadcast::channel('tournament.{tournamentId}.matches', function ($user = null, $tournamentId) {
    return true; // Public tournament match updates
});

Broadcast::channel('tournament.{tournamentId}.bracket', function ($user = null, $tournamentId) {
    return true; // Public bracket updates
});

Broadcast::channel('tournament.{tournamentId}.registrations', function ($user = null, $tournamentId) {
    return true; // Public registration updates
});

Broadcast::channel('tournament.{tournamentId}.live', function ($user = null, $tournamentId) {
    return true; // Public live score updates
});

Broadcast::channel('tournament.{tournamentId}.chat', function ($user, $tournamentId) {
    // General tournament chat - requires authentication
    return $user ? ['id' => $user->id, 'name' => $user->name] : false;
});

Broadcast::channel('tournament.{tournamentId}.phase.{phaseId}', function ($user = null, $tournamentId, $phaseId) {
    return true; // Public phase updates
});

// Public match channels - anyone can listen to match data
Broadcast::channel('match.{matchId}', function ($user = null, $matchId) {
    return true; // Public match updates
});

Broadcast::channel('match.{matchId}.live', function ($user = null, $matchId) {
    return true; // Public live match data
});

// Private match chat - only participating teams and tournament admins
Broadcast::channel('match.{matchId}.chat', function ($user, $matchId) {
    if (!$user) return false;

    $match = BracketMatch::find($matchId);
    if (!$match) return false;

    // Tournament admins can access any match chat
    if ($user->isAdmin() || $user->isModerator()) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role
        ];
    }

    // Check if user is part of either team
    $userTeams = $user->teams()->pluck('teams.id');
    if ($userTeams->contains($match->team1_id) || $userTeams->contains($match->team2_id)) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'team_role' => 'participant'
        ];
    }

    return false;
});

// Private team channels - only team members can access
Broadcast::channel('team.{teamId}', function ($user, $teamId) {
    if (!$user) return false;

    // Check if user is part of the team
    $userTeams = $user->teams()->pluck('teams.id');
    if ($userTeams->contains($teamId)) {
        return [
            'id' => $user->id,
            'name' => $user->name
        ];
    }

    // Tournament admins can access team channels
    if ($user->isAdmin() || $user->isModerator()) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role
        ];
    }

    return false;
});

Broadcast::channel('team.{teamId}.chat', function ($user, $teamId) {
    if (!$user) return false;

    // Check if user is part of the team
    $userTeams = $user->teams()->pluck('teams.id');
    if ($userTeams->contains($teamId)) {
        return [
            'id' => $user->id,
            'name' => $user->name
        ];
    }

    return false;
});

// Admin channels - only tournament organizers and admins
Broadcast::channel('tournament.{tournamentId}.admin', function ($user, $tournamentId) {
    if (!$user) return false;

    $tournament = Tournament::find($tournamentId);
    if (!$tournament) return false;

    // Check if user is tournament organizer or admin
    if ($user->isAdmin() || $user->isModerator() || $tournament->organizer_id === $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role
        ];
    }

    return false;
});
