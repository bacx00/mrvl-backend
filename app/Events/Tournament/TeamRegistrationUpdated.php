<?php

namespace App\Events\Tournament;

use App\Models\Tournament;
use App\Models\Team;
use App\Models\TournamentRegistration;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamRegistrationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $team;
    public $registration;
    public $updateType;
    public $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Tournament $tournament, Team $team, TournamentRegistration $registration, string $updateType, array $metadata = [])
    {
        $this->tournament = $tournament;
        $this->team = $team;
        $this->registration = $registration;
        $this->updateType = $updateType;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.' . $this->tournament->id),
            new Channel('tournament.' . $this->tournament->id . '.registrations'),
            new PrivateChannel('team.' . $this->team->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'tournament_name' => $this->tournament->name,
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'team_short_name' => $this->team->short_name,
            'registration_id' => $this->registration->id,
            'registration_status' => $this->registration->status,
            'check_in_status' => $this->registration->check_in_status,
            'registration_time' => $this->registration->created_at?->toISOString(),
            'check_in_time' => $this->registration->checked_in_at?->toISOString(),
            'update_type' => $this->updateType,
            'current_registrations' => $this->tournament->current_team_count,
            'max_teams' => $this->tournament->max_teams,
            'registration_full' => $this->tournament->current_team_count >= $this->tournament->max_teams,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'team.registration.updated';
    }
}