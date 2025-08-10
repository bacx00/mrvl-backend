<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;

class TournamentRegistrationController extends Controller
{
    /**
     * Display tournament registrations
     */
    public function index(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $query = $tournament->registrations()
                               ->with(['team:id,name,short_name,logo,region', 'user:id,name,email']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->byStatus($request->status);
            }

            if ($request->has('payment_status') && $request->payment_status !== 'all') {
                $query->byPaymentStatus($request->payment_status);
            }

            if ($request->has('registered_from')) {
                $query->where('registered_at', '>=', $request->registered_from);
            }

            if ($request->has('registered_to')) {
                $query->where('registered_at', '<=', $request->registered_to);
            }

            // Search by team name
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('team', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('short_name', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'registered_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSorts = ['registered_at', 'status', 'payment_status', 'seed'];
            
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $perPage = min($request->get('per_page', 20), 100);
            $registrations = $query->paginate($perPage);

            $registrations->getCollection()->transform(function ($registration) {
                return $this->formatRegistrationData($registration);
            });

            return response()->json([
                'success' => true,
                'data' => $registrations,
                'stats' => TournamentRegistration::getRegistrationStats($tournament->id),
                'tournament' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'registration_open' => $tournament->registration_open,
                    'check_in_open' => $tournament->check_in_open,
                    'max_teams' => $tournament->max_teams,
                    'current_teams' => $tournament->current_team_count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament registrations index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament registrations',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Register a team for tournament
     */
    public function register(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'team_id' => 'required|exists:teams,id',
                'registration_data' => 'nullable|array',
                'emergency_contact' => 'nullable|array',
                'special_requirements' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $team = Team::find($request->team_id);
            $user = Auth::user();

            // Check if registration is open
            if (!$tournament->registration_open) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration is not open for this tournament'
                ], 422);
            }

            // Check if tournament has space
            if (!$tournament->canRegisterTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is full'
                ], 422);
            }

            // Check if team is already registered
            $existingRegistration = $tournament->registrations()
                                             ->where('team_id', $request->team_id)
                                             ->whereNotIn('status', ['rejected', 'withdrawn'])
                                             ->first();

            if ($existingRegistration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is already registered for this tournament',
                    'registration' => $this->formatRegistrationData($existingRegistration)
                ], 422);
            }

            DB::beginTransaction();

            // Create registration
            $registration = TournamentRegistration::create([
                'tournament_id' => $tournament->id,
                'team_id' => $request->team_id,
                'user_id' => $user->id,
                'registration_data' => $request->registration_data ?? [],
                'emergency_contact' => $request->emergency_contact ?? [],
                'special_requirements' => $request->special_requirements ?? [],
                'registered_at' => now(),
                'submission_ip' => $request->ip()
            ]);

            // Validate registration requirements
            $validationErrors = $registration->validateRegistrationData();
            if (!empty($validationErrors)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Registration validation failed',
                    'errors' => $validationErrors
                ], 422);
            }

            $registration->load(['team', 'user']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team registered successfully',
                'data' => $this->formatRegistrationData($registration)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament registration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to register team',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check in a team for tournament
     */
    public function checkIn(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find user's team registration
            $registration = $tournament->registrations()
                                     ->where('user_id', $user->id)
                                     ->where('status', 'approved')
                                     ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approved registration found for your team'
                ], 404);
            }

            if (!$registration->can_check_in) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team cannot check in at this time',
                    'details' => [
                        'check_in_open' => $tournament->check_in_open,
                        'payment_status' => $registration->payment_status,
                        'status' => $registration->status
                    ]
                ], 422);
            }

            DB::beginTransaction();

            $checkedIn = $registration->checkIn();

            if (!$checkedIn) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to check in team'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team checked in successfully',
                'data' => $this->formatRegistrationData($registration->refresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament check-in error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check in team',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Withdraw team from tournament
     */
    public function withdrawTeam(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find user's team registration
            $registration = $tournament->registrations()
                                     ->where('user_id', $user->id)
                                     ->whereIn('status', ['pending', 'approved'])
                                     ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active registration found for your team'
                ], 404);
            }

            if (!$registration->can_withdraw) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team cannot withdraw at this time'
                ], 422);
            }

            DB::beginTransaction();

            $withdrawn = $registration->withdraw();

            if (!$withdrawn) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to withdraw team'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team withdrawn successfully',
                'data' => $this->formatRegistrationData($registration->refresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament withdrawal error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to withdraw team',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Approve a registration (Admin)
     */
    public function approve(Request $request, Tournament $tournament, TournamentRegistration $registration): JsonResponse
    {
        try {
            if ($registration->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration does not belong to this tournament'
                ], 404);
            }

            if ($registration->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending registrations can be approved'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string',
                'seed' => 'nullable|integer|min:1',
                'group_assignment' => 'nullable|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $approved = $registration->approve([
                'notes' => $request->notes,
                'seed' => $request->seed,
                'group_assignment' => $request->group_assignment
            ]);

            if (!$approved) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve registration. Tournament may be full.'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration approved successfully',
                'data' => $this->formatRegistrationData($registration->refresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament registration approval error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve registration',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reject a registration (Admin)
     */
    public function reject(Request $request, Tournament $tournament, TournamentRegistration $registration): JsonResponse
    {
        try {
            if ($registration->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration does not belong to this tournament'
                ], 404);
            }

            if ($registration->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending registrations can be rejected'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $rejected = $registration->reject($request->reason);

            if (!$rejected) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject registration'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration rejected successfully',
                'data' => $this->formatRegistrationData($registration->refresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament registration rejection error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject registration',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified registration (Admin)
     */
    public function destroy(Tournament $tournament, TournamentRegistration $registration): JsonResponse
    {
        try {
            if ($registration->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration does not belong to this tournament'
                ], 404);
            }

            // Check if registration can be deleted
            if (in_array($registration->status, ['checked_in']) && $tournament->hasStarted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete registration from started tournament'
                ], 422);
            }

            DB::beginTransaction();

            // Remove from tournament teams if approved
            if ($registration->status === 'approved') {
                $tournament->teams()->detach($registration->team_id);
                $tournament->decrement('team_count');
            }

            $registration->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament registration deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete registration',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get registration statistics
     */
    public function getStats(Tournament $tournament): JsonResponse
    {
        try {
            $stats = TournamentRegistration::getRegistrationStats($tournament->id);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'tournament' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'max_teams' => $tournament->max_teams,
                    'current_teams' => $tournament->current_team_count,
                    'spots_remaining' => $tournament->max_teams - $tournament->current_team_count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament registration stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch registration statistics',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get user's tournament registrations
     */
    public function getUserRegistrations(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = TournamentRegistration::where('user_id', $user->id)
                                         ->with(['tournament:id,name,type,status,start_date,end_date,logo,prize_pool,currency', 'team:id,name,short_name,logo']);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->byStatus($request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereHas('tournament', function($q) use ($request) {
                    $q->where('start_date', '>=', $request->from_date);
                });
            }

            $sortBy = $request->get('sort_by', 'registered_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $query->orderBy($sortBy, $sortDirection);

            $perPage = min($request->get('per_page', 15), 50);
            $registrations = $query->paginate($perPage);

            $registrations->getCollection()->transform(function ($registration) {
                return $this->formatRegistrationData($registration);
            });

            return response()->json([
                'success' => true,
                'data' => $registrations
            ]);

        } catch (\Exception $e) {
            Log::error('User tournament registrations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user registrations',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get user's specific tournament registration
     */
    public function getUserRegistration(Tournament $tournament): JsonResponse
    {
        try {
            $user = Auth::user();

            $registration = $tournament->registrations()
                                     ->where('user_id', $user->id)
                                     ->with(['team:id,name,short_name,logo', 'tournament'])
                                     ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'No registration found for this tournament'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatRegistrationData($registration),
                'summary' => $registration->getRegistrationSummary()
            ]);

        } catch (\Exception $e) {
            Log::error('User tournament registration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament registration',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Format registration data for API response
     */
    private function formatRegistrationData(TournamentRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'tournament_id' => $registration->tournament_id,
            'team_id' => $registration->team_id,
            'user_id' => $registration->user_id,
            'status' => $registration->status,
            'formatted_status' => $registration->formatted_status,
            'payment_status' => $registration->payment_status,
            'formatted_payment_status' => $registration->formatted_payment_status,
            'registered_at' => $registration->registered_at?->toISOString(),
            'checked_in_at' => $registration->checked_in_at?->toISOString(),
            'approved_at' => $registration->approved_at?->toISOString(),
            'rejected_at' => $registration->rejected_at?->toISOString(),
            'registration_time' => $registration->registration_time,
            'is_late_registration' => $registration->is_late_registration,
            'rejection_reason' => $registration->rejection_reason,
            'approval_notes' => $registration->approval_notes,
            'seed' => $registration->seed,
            'group_assignment' => $registration->group_assignment,
            'bracket_position' => $registration->bracket_position,
            'registration_data' => $registration->registration_data,
            'emergency_contact' => $registration->emergency_contact,
            'special_requirements' => $registration->special_requirements,
            'can_check_in' => $registration->can_check_in,
            'can_withdraw' => $registration->can_withdraw,
            'team' => $registration->team,
            'user' => $registration->user ? [
                'id' => $registration->user->id,
                'name' => $registration->user->name,
                'email' => $registration->user->email
            ] : null,
            'tournament' => $registration->tournament ? [
                'id' => $registration->tournament->id,
                'name' => $registration->tournament->name,
                'type' => $registration->tournament->type,
                'status' => $registration->tournament->status,
                'start_date' => $registration->tournament->start_date?->toISOString(),
                'end_date' => $registration->tournament->end_date?->toISOString(),
                'formatted_prize_pool' => $registration->tournament->formatted_prize_pool
            ] : null,
            'created_at' => $registration->created_at->toISOString(),
            'updated_at' => $registration->updated_at->toISOString()
        ];
    }
}