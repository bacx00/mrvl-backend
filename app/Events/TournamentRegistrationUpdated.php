<?php

namespace App\Events;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentRegistrationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $registration;
    public $updateType;
    public $eventData;

    /**
     * Create a new event instance.
     */
    public function __construct(Tournament $tournament, TournamentRegistration $registration, string $updateType = 'updated', array $eventData = [])
    {
        $this->tournament = $tournament;
        $this->registration = $registration;
        $this->updateType = $updateType;
        $this->eventData = $eventData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("tournament.{$this->tournament->id}"),
            new Channel("tournament.{$this->tournament->id}.registrations"),
            new Channel("team.{$this->registration->team_id}.tournaments"),
            new Channel("user.{$this->registration->user_id}.tournaments")
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'registration_updated',
            'update_type' => $this->updateType,
            'tournament' => [
                'id' => $this->tournament->id,
                'name' => $this->tournament->name,
                'slug' => $this->tournament->slug,
                'status' => $this->tournament->status,
                'registration_open' => $this->tournament->registration_open,
                'check_in_open' => $this->tournament->check_in_open,
                'current_team_count' => $this->tournament->current_team_count,
                'max_teams' => $this->tournament->max_teams,
                'spots_remaining' => $this->tournament->max_teams - $this->tournament->current_team_count
            ],
            'registration' => [
                'id' => $this->registration->id,
                'status' => $this->registration->status,
                'team' => [
                    'id' => $this->registration->team->id,
                    'name' => $this->registration->team->name,
                    'short_name' => $this->registration->team->short_name,
                    'logo' => $this->registration->team->logo,
                    'region' => $this->registration->team->region
                ],
                'user' => [
                    'id' => $this->registration->user->id,
                    'name' => $this->registration->user->name
                ],
                'registered_at' => $this->registration->registered_at->toISOString(),
                'checked_in_at' => $this->registration->checked_in_at?->toISOString(),
                'seed' => $this->registration->seed,
                'payment_status' => $this->registration->payment_status
            ],
            'statistics' => [
                'total_registrations' => TournamentRegistration::where('tournament_id', $this->tournament->id)->count(),
                'pending_registrations' => TournamentRegistration::where('tournament_id', $this->tournament->id)->where('status', 'pending')->count(),
                'approved_registrations' => TournamentRegistration::where('tournament_id', $this->tournament->id)->where('status', 'approved')->count(),
                'checked_in_teams' => TournamentRegistration::where('tournament_id', $this->tournament->id)->where('status', 'checked_in')->count()
            ],
            'event_data' => $this->eventData,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return "tournament.registration.{$this->updateType}";
    }
}