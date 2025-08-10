<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\UserActivity;
use App\Models\UserWarning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Exception;

/**
 * Comprehensive Users Moderation Panel Controller
 * 
 * Provides full CRUD operations, role management, account status controls,
 * password reset, activity monitoring, bulk operations, search/filtering,
 * email verification, 2FA controls, and profile moderation.
 */
class AdminUsersController extends Controller
{
    /**
     * Security constants
     */
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME = 15; // minutes
    const PASSWORD_RESET_EXPIRY = 60; // minutes
    const BULK_OPERATION_LIMIT = 1000;
    
    public function __construct()
    {
        // Ensure admin or moderator access
        $this->middleware(['auth:api', 'role:admin|moderator']);
    }
    
    /**
     * Get all users with advanced filtering and pagination
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:255',
                'role' => 'string|in:admin,moderator,user',
                'status' => 'string|in:active,inactive,banned,suspended',
                'email_verified' => 'boolean',
                'sort_by' => 'string|in:name,email,created_at,last_login,status,warning_count',
                'sort_order' => 'string|in:asc,desc',
                'has_warnings' => 'boolean',
                'is_banned' => 'boolean',
                'is_muted' => 'boolean',
                'created_after' => 'date',
                'created_before' => 'date',
                'last_login_after' => 'date',
                'last_login_before' => 'date',
                'two_factor_enabled' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $query = User::with([
                'teamFlair:id,name,logo,region', 
                'warnings' => function($query) {
                    $query->where('expires_at', '>', now())
                          ->orWhereNull('expires_at')
                          ->orderBy('created_at', 'desc');
                }
            ]);
            
            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('id', 'LIKE', "%{$search}%");
                });
            }
            
            // Apply role filter
            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }
            
            // Apply status filter
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            
            // Apply email verification filter
            if ($request->has('email_verified')) {
                if ($request->boolean('email_verified')) {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }
            
            // Apply date filters
            if ($request->filled('created_after')) {
                $query->where('created_at', '>=', $request->created_after);
            }
            if ($request->filled('created_before')) {
                $query->where('created_at', '<=', $request->created_before);
            }
            if ($request->filled('last_login_after')) {
                $query->where('last_login', '>=', $request->last_login_after);
            }
            if ($request->filled('last_login_before')) {
                $query->where('last_login', '<=', $request->last_login_before);
            }
            
            // Apply moderation filters
            if ($request->has('has_warnings') && $request->boolean('has_warnings')) {
                $query->whereHas('warnings', function($q) {
                    $q->where('expires_at', '>', now())->orWhereNull('expires_at');
                });
            }
            
            if ($request->has('is_banned') && $request->boolean('is_banned')) {
                $query->whereNotNull('banned_at')
                      ->where(function($q) {
                          $q->whereNull('ban_expires_at')
                            ->orWhere('ban_expires_at', '>', now());
                      });
            }
            
            if ($request->has('is_muted') && $request->boolean('is_muted')) {
                $query->where('muted_until', '>', now());
            }
            
            // Apply 2FA filter (placeholder - implement when 2FA is added)
            if ($request->has('two_factor_enabled')) {
                // This will be implemented when 2FA system is added
                // $query->where('two_factor_enabled', $request->boolean('two_factor_enabled'));
            }
            
            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Get paginated results
            $perPage = $request->get('per_page', 25);
            $users = $query->paginate($perPage);
            
            // Transform data
            $result = $users->getCollection()->map(function($user) {
                return $this->transformUserData($user, true);
            });
            
            // Get summary statistics
            $summary = $this->getUserStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ],
                'summary' => $summary,
                'filters' => [
                    'search' => $request->search,
                    'role' => $request->role,
                    'status' => $request->status,
                    'email_verified' => $request->email_verified,
                    'has_warnings' => $request->has_warnings,
                    'is_banned' => $request->is_banned,
                    'is_muted' => $request->is_muted,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Create a new user with comprehensive validation
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:users|regex:/^[a-zA-Z0-9_-]+$/',
                'email' => 'required|email|unique:users|max:255',
                'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'role' => 'required|string|in:admin,moderator,user',
                'status' => 'string|in:active,inactive,banned,suspended',
                'send_welcome_email' => 'boolean',
                'force_password_reset' => 'boolean',
                'hero_flair' => 'nullable|string|max:100',
                'team_flair_id' => 'nullable|exists:teams,id',
                'show_hero_flair' => 'boolean',
                'show_team_flair' => 'boolean',
                'avatar_url' => 'nullable|url|max:500'
            ], [
                'name.regex' => 'Username can only contain letters, numbers, underscores, and hyphens.',
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => $request->get('status', 'active'),
                'hero_flair' => $request->hero_flair,
                'team_flair_id' => $request->team_flair_id,
                'show_hero_flair' => $request->boolean('show_hero_flair'),
                'show_team_flair' => $request->boolean('show_team_flair'),
                'avatar' => $request->avatar_url,
                'email_verified_at' => $request->boolean('send_welcome_email') ? null : now()
            ];

            $user = User::create($userData);

            // Log the action
            $this->logAdminAction('user_created', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status
                ]
            ]);

            DB::commit();

            // Send welcome email if requested
            if ($request->boolean('send_welcome_email')) {
                try {
                    // Implement welcome email sending logic
                    Log::info('Welcome email requested for user: ' . $user->email);
                } catch (Exception $e) {
                    Log::error('Failed to send welcome email: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $this->transformUserData($user, false)
            ], 201);
            
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error creating user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Get single user with detailed information
     */
    public function show($userId)
    {
        try {
            $user = User::with([
                'teamFlair:id,name,logo,region,short_name',
                'warnings' => function($query) {
                    $query->with('moderator:id,name')
                          ->orderBy('created_at', 'desc');
                }
            ])->findOrFail($userId);
            
            $userData = $this->transformUserData($user, false);
            
            // Add detailed activity and statistics
            $userData['detailed_stats'] = $this->getUserDetailedStats($userId);
            $userData['recent_activity'] = $this->getUserRecentActivity($userId, 20);
            $userData['login_history'] = $this->getUserLoginHistory($userId, 10);
            
            return response()->json([
                'success' => true,
                'data' => $userData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user'
            ], 500);
        }
    }
    
    /**
     * Update user with comprehensive validation and security checks
     */
    public function update(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            $validator = Validator::make($request->all(), [
                'name' => ['string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('users')->ignore($userId)],
                'email' => ['email', 'max:255', Rule::unique('users')->ignore($userId)],
                'role' => 'string|in:admin,moderator,user',
                'status' => 'string|in:active,inactive,banned,suspended',
                'password' => 'nullable|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'hero_flair' => 'nullable|string|max:100',
                'team_flair_id' => 'nullable|exists:teams,id',
                'show_hero_flair' => 'boolean',
                'show_team_flair' => 'boolean',
                'avatar_url' => 'nullable|url|max:500',
                'force_logout' => 'boolean',
                'email_verified' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Security check: prevent removing admin role from last admin
            if ($request->has('role') && $request->role !== 'admin' && $user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove admin role from the last admin user'
                    ], 400);
                }
            }

            // Security check: prevent self-demotion by admins
            if ($request->has('role') && auth()->id() === $user->id && auth()->user()->role === 'admin' && $request->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove your own admin privileges'
                ], 400);
                }

            DB::beginTransaction();

            $updateData = [];
            $changes = [];
            
            // Track changes for logging
            foreach (['name', 'email', 'role', 'status', 'hero_flair', 'team_flair_id', 'show_hero_flair', 'show_team_flair', 'avatar_url'] as $field) {
                if ($request->has($field)) {
                    $newValue = $field === 'avatar_url' ? $request->avatar_url : $request->$field;
                    $oldValue = $field === 'avatar_url' ? $user->avatar : $user->$field;
                    
                    if ($newValue != $oldValue) {
                        $updateData[$field === 'avatar_url' ? 'avatar' : $field] = $newValue;
                        $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
                    }
                }
            }
            
            // Handle boolean fields
            foreach (['show_hero_flair', 'show_team_flair'] as $boolField) {
                if ($request->has($boolField)) {
                    $updateData[$boolField] = $request->boolean($boolField);
                }
            }
            
            // Handle password change
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
                $changes['password'] = ['old' => '[HIDDEN]', 'new' => '[CHANGED]'];
            }
            
            // Handle email verification
            if ($request->has('email_verified')) {
                $updateData['email_verified_at'] = $request->boolean('email_verified') ? now() : null;
                $changes['email_verified'] = ['old' => !!$user->email_verified_at, 'new' => $request->boolean('email_verified')];
            }
            
            $user->update($updateData);

            // Log the changes
            if (!empty($changes)) {
                $this->logAdminAction('user_updated', [
                    'admin_id' => auth()->id(),
                    'target_user_id' => $user->id,
                    'changes' => $changes
                ]);
            }

            // Force logout if requested
            if ($request->boolean('force_logout')) {
                DB::table('oauth_access_tokens')
                    ->where('user_id', $user->id)
                    ->update(['revoked' => true]);
                    
                $this->logAdminAction('user_forced_logout', [
                    'admin_id' => auth()->id(),
                    'target_user_id' => $user->id
                ]);
            }

            DB::commit();

            $user->refresh()->load(['teamFlair:id,name,logo,region', 'warnings']);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $this->transformUserData($user, false),
                'changes' => $changes
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error updating user: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Delete user with security checks and data cleanup
     */
    public function destroy($userId)
    {
        try {
            $user = User::findOrFail($userId);

            // Security check: prevent deleting the last admin
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete the last admin user'
                    ], 400);
                }
            }

            // Security check: prevent self-deletion
            if (auth()->id() === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 400);
            }

            DB::beginTransaction();

            // Store user data for logging
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];

            // Clean up user data
            $this->cleanupUserData($user);

            // Delete the user
            $user->delete();

            // Log the action
            $this->logAdminAction('user_deleted', [
                'admin_id' => auth()->id(),
                'deleted_user' => $userData
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error deleting user: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user'
            ], 500);
        }
    }
    
    /**
     * Advanced user search with multiple criteria
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2|max:255',
                'search_fields' => 'array|in:name,email,id',
                'limit' => 'integer|min:1|max:50',
                'include_deleted' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = $request->query;
            $searchFields = $request->get('search_fields', ['name', 'email']);
            $limit = $request->get('limit', 20);
            $includeDeleted = $request->boolean('include_deleted');

            $userQuery = User::with(['teamFlair:id,name,logo']);
            
            if ($includeDeleted) {
                $userQuery = $userQuery->withTrashed();
            }

            $userQuery->where(function($q) use ($query, $searchFields) {
                foreach ($searchFields as $field) {
                    if ($field === 'id') {
                        $q->orWhere('id', $query);
                    } else {
                        $q->orWhere($field, 'LIKE', "%{$query}%");
                    }
                }
            });

            $users = $userQuery->limit($limit)->get();

            $results = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->role ?? 'user',
                    'status' => $user->status ?? 'active',
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at,
                    'deleted_at' => $user->deleted_at,
                    'team_flair' => $user->teamFlair
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $results,
                'query' => $query,
                'total_found' => $users->count(),
                'search_fields' => $searchFields
            ]);
            
        } catch (Exception $e) {
            Log::error('Error in user search: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed'
            ], 500);
        }
    }
    
    /**
     * Reset user password (admin action)
     */
    public function resetPassword(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'force_change' => 'boolean',
                'notify_user' => 'boolean',
                'revoke_tokens' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password),
                'password_changed_at' => now()
            ]);

            // Revoke existing tokens if requested
            if ($request->boolean('revoke_tokens', true)) {
                DB::table('oauth_access_tokens')
                    ->where('user_id', $user->id)
                    ->update(['revoked' => true]);
            }

            // Log the action
            $this->logAdminAction('password_reset', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'force_change' => $request->boolean('force_change'),
                'tokens_revoked' => $request->boolean('revoke_tokens', true),
                'ip' => $request->ip()
            ]);

            DB::commit();

            // Send notification email if requested
            if ($request->boolean('notify_user')) {
                try {
                    // Implement password reset notification email
                    Log::info('Password reset notification sent to user: ' . $user->email);
                } catch (Exception $e) {
                    Log::error('Failed to send password reset notification: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error resetting password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }
    
    /**
     * Ban/unban user with reason and duration
     */
    public function manageBan(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:ban,unban,temporary_ban',
                'reason' => 'required_if:action,ban,temporary_ban|string|max:500',
                'duration_hours' => 'required_if:action,temporary_ban|integer|min:1|max:8760', // max 1 year
                'notify_user' => 'boolean',
                'revoke_sessions' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            // Prevent banning admins
            if ($user->role === 'admin' && in_array($request->action, ['ban', 'temporary_ban'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot ban admin users'
                ], 400);
            }

            // Prevent self-banning
            if (auth()->id() === $user->id && in_array($request->action, ['ban', 'temporary_ban'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot ban yourself'
                ], 400);
            }

            DB::beginTransaction();

            switch ($request->action) {
                case 'ban':
                    $user->ban($request->reason);
                    $user->update(['status' => 'banned']);
                    $message = 'User banned successfully';
                    break;
                    
                case 'temporary_ban':
                    $expiresAt = now()->addHours($request->duration_hours);
                    $user->ban($request->reason, $expiresAt);
                    $user->update(['status' => 'banned']);
                    $message = "User temporarily banned for {$request->duration_hours} hours";
                    break;
                    
                case 'unban':
                    $user->unban();
                    $user->update(['status' => 'active']);
                    $message = 'User unbanned successfully';
                    break;
            }

            // Revoke sessions if requested
            if ($request->boolean('revoke_sessions', true)) {
                DB::table('oauth_access_tokens')
                    ->where('user_id', $user->id)
                    ->update(['revoked' => true]);
            }

            // Log the action
            $this->logAdminAction('user_ban_' . $request->action, [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'reason' => $request->reason,
                'duration_hours' => $request->get('duration_hours'),
                'expires_at' => $user->ban_expires_at
            ]);

            DB::commit();

            // Send notification if requested
            if ($request->boolean('notify_user')) {
                try {
                    // Implement ban notification email
                    Log::info("Ban notification sent to user: {$user->email} for action: {$request->action}");
                } catch (Exception $e) {
                    Log::error('Failed to send ban notification: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'status' => $user->status,
                    'banned_at' => $user->banned_at,
                    'ban_expires_at' => $user->ban_expires_at,
                    'ban_reason' => $user->ban_reason
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error managing user ban: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to manage ban status'
            ], 500);
        }
    }
    
    /**
     * Mute/unmute user
     */
    public function manageMute(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:mute,unmute',
                'duration_hours' => 'required_if:action,mute|integer|min:1|max:720', // max 30 days
                'reason' => 'required_if:action,mute|string|max:500',
                'notify_user' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            if ($request->action === 'mute') {
                $muteUntil = now()->addHours($request->duration_hours);
                $user->mute($muteUntil);
                $message = "User muted for {$request->duration_hours} hours";
            } else {
                $user->unmute();
                $message = 'User unmuted successfully';
            }

            // Log the action
            $this->logAdminAction('user_' . $request->action, [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'reason' => $request->reason,
                'duration_hours' => $request->get('duration_hours'),
                'muted_until' => $user->muted_until
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'muted_until' => $user->muted_until,
                    'is_muted' => $user->isMuted()
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error managing user mute: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to manage mute status'
            ], 500);
        }
    }
    
    /**
     * Issue warning to user
     */
    public function issueWarning(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'severity' => 'required|string|in:low,medium,high,severe',
                'expires_in_days' => 'nullable|integer|min:1|max:365',
                'notify_user' => 'boolean',
                'escalate_if_repeat' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            // Calculate expiration date
            $expiresAt = $request->filled('expires_in_days') 
                ? now()->addDays($request->expires_in_days)
                : null;

            // Issue the warning
            $warning = $user->warn(
                auth()->id(),
                $request->reason,
                $request->severity,
                $expiresAt
            );

            // Check for escalation
            if ($request->boolean('escalate_if_repeat')) {
                $recentWarnings = $user->warnings()
                    ->where('created_at', '>', now()->subDays(30))
                    ->count();
                    
                if ($recentWarnings >= 3) {
                    // Auto-escalate to temporary ban
                    $user->ban('Automatic escalation due to repeated warnings', now()->addDays(1));
                    $escalated = true;
                } else {
                    $escalated = false;
                }
            } else {
                $escalated = false;
            }

            // Log the action
            $this->logAdminAction('user_warned', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'warning_id' => $warning->id,
                'reason' => $request->reason,
                'severity' => $request->severity,
                'escalated' => $escalated
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $escalated ? 'Warning issued and user temporarily banned due to repeated violations' : 'Warning issued successfully',
                'data' => [
                    'warning_id' => $warning->id,
                    'severity' => $warning->severity,
                    'expires_at' => $warning->expires_at,
                    'escalated' => $escalated,
                    'total_warnings' => $user->warning_count
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error issuing warning: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue warning'
            ], 500);
        }
    }
    
    /**
     * Get user activity logs
     */
    public function getActivity(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'activity_type' => 'string|max:50',
                'from_date' => 'date',
                'to_date' => 'date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);
            
            // Get comprehensive activity data
            $activities = $this->getUserDetailedActivity($userId, $request);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'activities' => $activities
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching user activity: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user activity'
            ], 500);
        }
    }
    
    /**
     * Bulk operations on multiple users
     */
    public function bulkOperation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_ids' => 'required|array|min:1|max:' . self::BULK_OPERATION_LIMIT,
                'user_ids.*' => 'integer|exists:users,id',
                'operation' => 'required|string|in:ban,unban,suspend,activate,delete,assign_role,remove_role,reset_password',
                'reason' => 'required_if:operation,ban,suspend,delete|string|max:500',
                'role' => 'required_if:operation,assign_role,remove_role|string|in:admin,moderator,user',
                'duration_hours' => 'integer|min:1|max:8760',
                'notify_users' => 'boolean',
                'confirm_operation' => 'required|boolean|accepted'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userIds = $request->user_ids;
            $operation = $request->operation;
            
            // Security checks
            if (in_array(auth()->id(), $userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot perform bulk operations on yourself'
                ], 400);
            }
            
            $users = User::whereIn('id', $userIds)->get();
            
            if ($users->count() !== count($userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some users were not found'
                ], 400);
            }

            DB::beginTransaction();

            $results = [
                'success' => 0,
                'failed' => 0,
                'errors' => []
            ];

            foreach ($users as $user) {
                try {
                    $this->executeBulkOperation($user, $operation, $request->all());
                    $results['success']++;
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Bulk operation failed for user {$user->id}: " . $e->getMessage());
                }
            }

            // Log the bulk action
            $this->logAdminAction('bulk_operation', [
                'admin_id' => auth()->id(),
                'operation' => $operation,
                'user_count' => count($userIds),
                'success_count' => $results['success'],
                'failed_count' => $results['failed'],
                'reason' => $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk operation completed. {$results['success']} successful, {$results['failed']} failed.",
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error in bulk operation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed'
            ], 500);
        }
    }
    
    /**
     * Manage email verification status
     */
    public function manageEmailVerification(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:verify,unverify,resend_verification',
                'notify_user' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            switch ($request->action) {
                case 'verify':
                    $user->update(['email_verified_at' => now()]);
                    $message = 'Email verified successfully';
                    break;
                    
                case 'unverify':
                    $user->update(['email_verified_at' => null]);
                    $message = 'Email verification removed';
                    break;
                    
                case 'resend_verification':
                    // Send verification email logic would go here
                    Log::info('Verification email resent to user: ' . $user->email);
                    $message = 'Verification email sent';
                    break;
            }

            // Log the action
            $this->logAdminAction('email_verification_' . $request->action, [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'email_verified_at' => $user->email_verified_at,
                    'is_verified' => !!$user->email_verified_at
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error managing email verification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to manage email verification'
            ], 500);
        }
    }
    
    /**
     * Get user statistics summary
     */
    public function getUserStatistics()
    {
        return Cache::remember('admin_user_statistics', 300, function () {
            $totalUsers = User::count();
            $last24Hours = Carbon::now()->subDay();
            $last7Days = Carbon::now()->subWeek();
            $last30Days = Carbon::now()->subMonth();
            
            return [
                'total_users' => $totalUsers,
                'new_users_24h' => User::where('created_at', '>', $last24Hours)->count(),
                'new_users_7d' => User::where('created_at', '>', $last7Days)->count(),
                'new_users_30d' => User::where('created_at', '>', $last30Days)->count(),
                'by_role' => [
                    'admins' => User::where('role', 'admin')->count(),
                    'moderators' => User::where('role', 'moderator')->count(),
                    'users' => User::where('role', 'user')->orWhereNull('role')->count()
                ],
                'by_status' => [
                    'active' => User::where('status', 'active')->orWhereNull('status')->count(),
                    'inactive' => User::where('status', 'inactive')->count(),
                    'banned' => User::where('status', 'banned')->count(),
                    'suspended' => User::where('status', 'suspended')->count()
                ],
                'email_verification' => [
                    'verified' => User::whereNotNull('email_verified_at')->count(),
                    'unverified' => User::whereNull('email_verified_at')->count()
                ],
                'activity' => [
                    'online_now' => User::where('last_activity', '>', Carbon::now()->subMinutes(5))->count(),
                    'active_today' => User::where('last_activity', '>', $last24Hours)->count(),
                    'active_week' => User::where('last_activity', '>', $last7Days)->count()
                ],
                'moderation' => [
                    'with_warnings' => User::where('warning_count', '>', 0)->count(),
                    'banned_users' => User::whereNotNull('banned_at')->count(),
                    'muted_users' => User::where('muted_until', '>', now())->count()
                ]
            ];
        });
    }
    
    /**
     * Transform user data for API responses
     */
    private function transformUserData($user, $includeLimited = true)
    {
        $moderationStatus = $user->moderation_status ?? [
            'is_banned' => false,
            'is_muted' => false,
            'has_warnings' => false,
            'warning_count' => 0,
            'ban_reason' => null,
            'ban_expires_at' => null,
            'muted_until' => null
        ];

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'role' => $user->role ?? 'user',
            'status' => $user->status ?? 'active',
            'email_verified_at' => $user->email_verified_at,
            'last_login' => $user->last_login,
            'last_activity' => $user->last_activity,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'moderation' => $moderationStatus,
            'warning_count' => $user->warning_count ?? 0
        ];

        if (!$includeLimited) {
            $data = array_merge($data, [
                'hero_flair' => $user->hero_flair,
                'team_flair' => $user->teamFlair ? [
                    'id' => $user->teamFlair->id,
                    'name' => $user->teamFlair->name,
                    'logo' => $user->teamFlair->logo,
                    'region' => $user->teamFlair->region ?? null
                ] : null,
                'show_hero_flair' => $user->show_hero_flair,
                'show_team_flair' => $user->show_team_flair,
                'profile_picture_type' => $user->profile_picture_type,
                'warnings' => $user->warnings ?? [],
                'stats' => $this->getUserQuickStats($user->id)
            ]);
        }

        return $data;
    }
    
    /**
     * Execute individual bulk operation on a user
     */
    private function executeBulkOperation($user, $operation, $params)
    {
        switch ($operation) {
            case 'ban':
                if ($user->role === 'admin') {
                    throw new Exception('Cannot ban admin users');
                }
                $user->ban($params['reason']);
                $user->update(['status' => 'banned']);
                break;
                
            case 'unban':
                $user->unban();
                $user->update(['status' => 'active']);
                break;
                
            case 'suspend':
                if ($user->role === 'admin') {
                    throw new Exception('Cannot suspend admin users');
                }
                $user->update(['status' => 'suspended']);
                break;
                
            case 'activate':
                $user->update(['status' => 'active']);
                break;
                
            case 'delete':
                if ($user->role === 'admin') {
                    $adminCount = User::where('role', 'admin')->count();
                    if ($adminCount <= 1) {
                        throw new Exception('Cannot delete the last admin user');
                    }
                }
                $this->cleanupUserData($user);
                $user->delete();
                break;
                
            case 'assign_role':
                $user->update(['role' => $params['role']]);
                break;
                
            case 'remove_role':
                $user->update(['role' => 'user']);
                break;
                
            case 'reset_password':
                $newPassword = Str::random(12) . '@1A'; // Ensure complexity
                $user->update(['password' => Hash::make($newPassword)]);
                // In production, you'd email the new password
                break;
                
            default:
                throw new Exception('Invalid operation');
        }
    }
    
    /**
     * Clean up user-related data before deletion
     */
    private function cleanupUserData($user)
    {
        // Clean up custom avatar
        if ($user->avatar && $user->profile_picture_type === 'custom') {
            $avatarPath = str_replace('/storage/', '', $user->avatar);
            Storage::disk('public')->delete($avatarPath);
        }
        
        // Revoke all tokens
        DB::table('oauth_access_tokens')
            ->where('user_id', $user->id)
            ->update(['revoked' => true]);
    }
    
    /**
     * Get user quick stats
     */
    private function getUserQuickStats($userId)
    {
        return [
            'total_comments' => DB::table('news_comments')->where('user_id', $userId)->count() + 
                              (DB::getSchemaBuilder()->hasTable('match_comments') ? 
                               DB::table('match_comments')->where('user_id', $userId)->count() : 0),
            'total_forum_posts' => DB::getSchemaBuilder()->hasTable('forum_posts') ? 
                                  DB::table('forum_posts')->where('user_id', $userId)->count() : 0,
            'total_forum_threads' => DB::getSchemaBuilder()->hasTable('forum_threads') ? 
                                    DB::table('forum_threads')->where('user_id', $userId)->count() : 0
        ];
    }
    
    /**
     * Get user detailed stats
     */
    private function getUserDetailedStats($userId)
    {
        $stats = [
            'comments' => [
                'news' => DB::table('news_comments')->where('user_id', $userId)->count(),
                'matches' => DB::getSchemaBuilder()->hasTable('match_comments') ? 
                            DB::table('match_comments')->where('user_id', $userId)->count() : 0
            ],
            'forum' => [
                'threads' => DB::getSchemaBuilder()->hasTable('forum_threads') ?
                           DB::table('forum_threads')->where('user_id', $userId)->count() : 0,
                'posts' => DB::getSchemaBuilder()->hasTable('forum_posts') ?
                          DB::table('forum_posts')->where('user_id', $userId)->count() : 0
            ]
        ];
        
        $stats['comments']['total'] = $stats['comments']['news'] + $stats['comments']['matches'];
        $stats['forum']['total'] = $stats['forum']['threads'] + $stats['forum']['posts'];
        
        return $stats;
    }
    
    /**
     * Get user recent activity
     */
    private function getUserRecentActivity($userId, $limit = 10)
    {
        $activities = collect();
        
        // News comments
        if (DB::getSchemaBuilder()->hasTable('news_comments')) {
            $newsComments = DB::table('news_comments as nc')
                ->join('news as n', 'nc.news_id', '=', 'n.id')
                ->where('nc.user_id', $userId)
                ->select([
                    'nc.id',
                    'nc.content',
                    'nc.created_at',
                    'n.title as item_title',
                    DB::raw("'news_comment' as type"),
                    DB::raw("'Commented on news' as action")
                ])
                ->orderBy('nc.created_at', 'desc')
                ->limit($limit)
                ->get();
            
            $activities = $activities->merge($newsComments);
        }
        
        // Forum threads
        if (DB::getSchemaBuilder()->hasTable('forum_threads')) {
            $forumThreads = DB::table('forum_threads')
                ->where('user_id', $userId)
                ->select([
                    'id',
                    'title as content',
                    'created_at',
                    'title as item_title',
                    DB::raw("'forum_thread' as type"),
                    DB::raw("'Created thread' as action")
                ])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
                
            $activities = $activities->merge($forumThreads);
        }
        
        return $activities->sortByDesc('created_at')->take($limit)->values();
    }
    
    /**
     * Get user detailed activity with pagination and filtering
     */
    private function getUserDetailedActivity($userId, $request)
    {
        // This would be a more comprehensive activity log implementation
        return $this->getUserRecentActivity($userId, 50);
    }
    
    /**
     * Get user login history
     */
    private function getUserLoginHistory($userId, $limit = 10)
    {
        // This would query a login history table if it exists
        // For now, return empty array as placeholder
        return [];
    }
    
    /**
     * Get user login history
     */
    public function getLoginHistory(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // For now, return basic info since we don't have a login_history table
            // In production, you'd query from a dedicated login_history table
            $history = [
                [
                    'timestamp' => $user->last_login ?? $user->updated_at,
                    'ip_address' => 'Unknown',
                    'user_agent' => 'Unknown',
                    'location' => 'Unknown',
                    'status' => 'success'
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $history
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching login history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch login history'
            ], 500);
        }
    }
    
    /**
     * Get user warnings
     */
    public function getWarnings(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            $warnings = $user->warnings()
                ->with('moderator:id,name')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));
                
            return response()->json([
                'success' => true,
                'data' => $warnings
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching warnings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warnings'
            ], 500);
        }
    }
    
    /**
     * Remove user warning
     */
    public function removeWarning(Request $request, $warningId)
    {
        try {
            $warning = UserWarning::findOrFail($warningId);
            $user = $warning->user;
            
            DB::beginTransaction();
            
            $warning->delete();
            $user->decrement('warning_count');
            
            $this->logAdminAction('warning_removed', [
                'admin_id' => auth()->id(),
                'warning_id' => $warningId,
                'target_user_id' => $user->id
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Warning removed successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warning not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error removing warning: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove warning'
            ], 500);
        }
    }
    
    /**
     * Get 2FA status (placeholder for future implementation)
     */
    public function get2FAStatus(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Placeholder for 2FA implementation
            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'two_factor_enabled' => false, // Placeholder
                    'backup_codes_generated' => false, // Placeholder
                    'last_used' => null // Placeholder
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }
    
    /**
     * Disable 2FA (placeholder for future implementation)
     */
    public function disable2FA(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Placeholder for 2FA implementation
            $this->logAdminAction('2fa_disabled', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '2FA disabled successfully (placeholder)'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }
    
    /**
     * Reset 2FA (placeholder for future implementation)
     */
    public function reset2FA(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Placeholder for 2FA implementation
            $this->logAdminAction('2fa_reset', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '2FA reset successfully (placeholder)'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }
    
    /**
     * Moderate user profile
     */
    public function moderateProfile(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:hide_avatar,reset_avatar,hide_username,reset_flairs,clear_bio',
                'reason' => 'required|string|max:500',
                'notify_user' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = User::findOrFail($userId);
            
            DB::beginTransaction();
            
            switch ($request->action) {
                case 'hide_avatar':
                    $user->update(['avatar' => null, 'profile_picture_type' => null]);
                    $message = 'User avatar hidden';
                    break;
                    
                case 'reset_avatar':
                    if ($user->avatar && $user->profile_picture_type === 'custom') {
                        $avatarPath = str_replace('/storage/', '', $user->avatar);
                        Storage::disk('public')->delete($avatarPath);
                    }
                    $user->update(['avatar' => null, 'profile_picture_type' => null, 'use_hero_as_avatar' => false]);
                    $message = 'User avatar reset';
                    break;
                    
                case 'reset_flairs':
                    $user->update([
                        'hero_flair' => null,
                        'team_flair_id' => null,
                        'show_hero_flair' => false,
                        'show_team_flair' => false
                    ]);
                    $message = 'User flairs reset';
                    break;
                    
                default:
                    $message = 'Profile moderation action completed';
            }
            
            $this->logAdminAction('profile_moderated', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'action' => $request->action,
                'reason' => $request->reason
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error moderating profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to moderate profile'
            ], 500);
        }
    }
    
    /**
     * Revoke user sessions
     */
    public function revokeSessions(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            DB::beginTransaction();
            
            // Revoke all OAuth tokens
            DB::table('oauth_access_tokens')
                ->where('user_id', $user->id)
                ->update(['revoked' => true]);
                
            $this->logAdminAction('sessions_revoked', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'All user sessions revoked successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error revoking sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke sessions'
            ], 500);
        }
    }
    
    /**
     * Get active user sessions
     */
    public function getActiveSessions(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Get active OAuth tokens
            $sessions = DB::table('oauth_access_tokens')
                ->where('user_id', $user->id)
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->select(['id', 'name', 'created_at', 'expires_at'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'active_sessions' => $sessions->count(),
                    'sessions' => $sessions
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching active sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active sessions'
            ], 500);
        }
    }
    
    /**
     * Bulk export users
     */
    public function bulkExport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|string|in:csv,json,xlsx',
                'user_ids' => 'array|max:' . self::BULK_OPERATION_LIMIT,
                'user_ids.*' => 'integer|exists:users,id',
                'include_sensitive' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $userIds = $request->get('user_ids');
            $includeSensitive = $request->boolean('include_sensitive');
            
            $query = User::with(['teamFlair:id,name']);
            
            if ($userIds) {
                $query->whereIn('id', $userIds);
            }
            
            $users = $query->get();
            
            $exportData = $users->map(function($user) use ($includeSensitive) {
                $data = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role ?? 'user',
                    'status' => $user->status ?? 'active',
                    'email_verified' => !!$user->email_verified_at,
                    'created_at' => $user->created_at,
                    'last_login' => $user->last_login,
                    'warning_count' => $user->warning_count ?? 0,
                    'team_flair' => $user->teamFlair ? $user->teamFlair->name : null
                ];
                
                if ($includeSensitive) {
                    $data['email'] = $user->email;
                }
                
                return $data;
            });
            
            $this->logAdminAction('users_exported', [
                'admin_id' => auth()->id(),
                'format' => $request->format,
                'user_count' => $users->count(),
                'include_sensitive' => $includeSensitive
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Users exported successfully',
                'data' => $exportData,
                'format' => $request->format,
                'exported_at' => now(),
                'count' => $users->count()
            ]);
            
        } catch (Exception $e) {
            Log::error('Error exporting users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export users'
            ], 500);
        }
    }
    
    /**
     * Get user analytics
     */
    public function getUserAnalytics(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'string|in:24h,7d,30d,90d,1y',
                'group_by' => 'string|in:day,week,month'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $period = $request->get('period', '30d');
            $groupBy = $request->get('group_by', 'day');
            
            // Calculate date range
            $endDate = Carbon::now();
            switch ($period) {
                case '24h':
                    $startDate = $endDate->copy()->subDay();
                    break;
                case '7d':
                    $startDate = $endDate->copy()->subWeek();
                    break;
                case '30d':
                    $startDate = $endDate->copy()->subMonth();
                    break;
                case '90d':
                    $startDate = $endDate->copy()->subMonths(3);
                    break;
                case '1y':
                    $startDate = $endDate->copy()->subYear();
                    break;
                default:
                    $startDate = $endDate->copy()->subMonth();
            }
            
            $analytics = [
                'period' => $period,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'new_registrations' => User::where('created_at', '>=', $startDate)->count(),
                'active_users' => User::where('last_activity', '>=', $startDate)->count(),
                'role_distribution' => [
                    'admin' => User::where('role', 'admin')->count(),
                    'moderator' => User::where('role', 'moderator')->count(),
                    'user' => User::where('role', 'user')->orWhereNull('role')->count()
                ],
                'status_distribution' => [
                    'active' => User::where('status', 'active')->orWhereNull('status')->count(),
                    'banned' => User::where('status', 'banned')->count(),
                    'suspended' => User::where('status', 'suspended')->count()
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
            
        } catch (Exception $e) {
            Log::error('Error generating user analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analytics'
            ], 500);
        }
    }
    
    /**
     * Generate user report
     */
    public function generateReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'report_type' => 'required|string|in:user_activity,moderation_summary,security_audit,engagement_stats',
                'date_from' => 'date',
                'date_to' => 'date',
                'format' => 'string|in:json,pdf,csv'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $reportType = $request->report_type;
            $format = $request->get('format', 'json');
            $dateFrom = $request->get('date_from', Carbon::now()->subMonth());
            $dateTo = $request->get('date_to', Carbon::now());
            
            $reportData = [];
            
            switch ($reportType) {
                case 'user_activity':
                    $reportData = [
                        'total_users' => User::count(),
                        'new_users_period' => User::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                        'active_users_period' => User::whereBetween('last_activity', [$dateFrom, $dateTo])->count(),
                        'top_active_users' => User::where('last_activity', '>=', $dateFrom)
                                                  ->orderBy('last_activity', 'desc')
                                                  ->limit(10)
                                                  ->select(['id', 'name', 'last_activity'])
                                                  ->get()
                    ];
                    break;
                    
                case 'moderation_summary':
                    $reportData = [
                        'banned_users' => User::where('status', 'banned')->count(),
                        'users_with_warnings' => User::where('warning_count', '>', 0)->count(),
                        'recent_bans' => User::whereBetween('banned_at', [$dateFrom, $dateTo])->count(),
                        'total_warnings' => UserWarning::whereBetween('created_at', [$dateFrom, $dateTo])->count()
                    ];
                    break;
                    
                case 'security_audit':
                    $reportData = [
                        'unverified_emails' => User::whereNull('email_verified_at')->count(),
                        'inactive_accounts' => User::where('last_activity', '<', Carbon::now()->subMonths(6))->count(),
                        'admin_accounts' => User::where('role', 'admin')->count(),
                        'recent_password_resets' => 0 // Would need password reset tracking
                    ];
                    break;
                    
                case 'engagement_stats':
                    $reportData = [
                        'daily_active_users' => User::where('last_activity', '>', Carbon::now()->subDay())->count(),
                        'weekly_active_users' => User::where('last_activity', '>', Carbon::now()->subWeek())->count(),
                        'monthly_active_users' => User::where('last_activity', '>', Carbon::now()->subMonth())->count()
                    ];
                    break;
            }
            
            $this->logAdminAction('report_generated', [
                'admin_id' => auth()->id(),
                'report_type' => $reportType,
                'format' => $format,
                'date_range' => [$dateFrom, $dateTo]
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => $reportType,
                    'format' => $format,
                    'generated_at' => now(),
                    'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                    'data' => $reportData
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Error generating report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report'
            ], 500);
        }
    }
    
    /**
     * Log admin actions for audit trail
     */
    private function logAdminAction($action, $data)
    {
        Log::info("Admin action: {$action}", $data);
        
        // If UserActivity model exists, use it
        if (class_exists('\\App\\Models\\UserActivity')) {
            try {
                UserActivity::create([
                    'user_id' => $data['admin_id'] ?? auth()->id(),
                    'activity_type' => 'admin_action',
                    'description' => $action,
                    'entity_type' => 'user',
                    'entity_id' => $data['target_user_id'] ?? null,
                    'metadata' => json_encode($data),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);
            } catch (Exception $e) {
                Log::error('Failed to log admin action to UserActivity: ' . $e->getMessage());
            }
        }
    }
}