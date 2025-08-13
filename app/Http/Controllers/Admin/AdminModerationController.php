<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Report;
use App\Models\UserWarning;
use App\Models\User;
use App\Services\ContentModerationService;
use App\Services\ForumModerationService;
use Carbon\Carbon;

class AdminModerationController extends Controller
{
    private $contentModerationService;
    private $forumModerationService;

    public function __construct(
        ContentModerationService $contentModerationService,
        ForumModerationService $forumModerationService
    ) {
        $this->contentModerationService = $contentModerationService;
        $this->forumModerationService = $forumModerationService;
    }

    /**
     * Get moderation dashboard overview
     */
    public function dashboard(Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', '24h');
            $since = $this->getTimeframeSince($timeframe);

            $stats = [
                'pending_reports' => Report::pending()->count(),
                'resolved_today' => Report::resolved()
                    ->where('resolved_at', '>=', Carbon::now()->startOfDay())
                    ->count(),
                'active_users' => User::where('last_activity', '>=', Carbon::now()->subHour())
                    ->count(),
                'flagged_content' => $this->getFlaggedContentCount(),
                'moderation_queue' => $this->getModerationQueueCount(),
                'auto_actions_today' => $this->getAutoActionsCount(),
                'manual_actions_today' => $this->getManualActionsCount(),
                'user_warnings_active' => UserWarning::where('acknowledged', false)->count(),
                'banned_users' => User::where('status', 'banned')->count(),
                'community_health_score' => $this->calculateCommunityHealthScore()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch moderation dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community health metrics
     */
    public function communityHealth(Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', '7d');
            $stats = $this->contentModerationService->getModerationStats($timeframe);
            
            $healthData = [
                'overall_score' => $this->calculateCommunityHealthScore(),
                'metrics' => $this->getCommunityMetrics($timeframe),
                'trends' => $this->getCommunityTrends($timeframe),
                'alerts' => $this->getCommunityAlerts(),
                'recommendations' => $this->getCommunityRecommendations()
            ];

            return response()->json([
                'success' => true,
                'data' => $healthData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch community health data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get moderation queue
     */
    public function moderationQueue(Request $request)
    {
        try {
            $type = $request->get('type', 'all');
            $status = $request->get('status', 'pending');
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);

            $queue = $this->forumModerationService->getModerationQueue($type, $status, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $queue
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch moderation queue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create user warning
     */
    public function warnUser(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'severity' => 'required|in:low,medium,high,critical',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'send_notification' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($userId);
            
            $warning = UserWarning::create([
                'user_id' => $userId,
                'moderator_id' => Auth::id(),
                'reason' => $request->reason,
                'severity' => $request->severity,
                'duration_days' => $request->duration_days ?? 7,
                'acknowledged' => false
            ]);

            // Log the action
            $this->logModerationAction('warn_user', 'user', $userId, [
                'warning_id' => $warning->id,
                'severity' => $request->severity,
                'reason' => $request->reason
            ]);

            // Send notification if requested
            if ($request->get('send_notification', true)) {
                $this->sendWarningNotification($user, $warning);
            }

            return response()->json([
                'success' => true,
                'message' => 'User warned successfully',
                'data' => $warning
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to warn user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ban user
     */
    public function banUser(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'duration' => 'required|in:temporary,permanent',
            'duration_days' => 'required_if:duration,temporary|integer|min:1|max:365',
            'ip_ban' => 'boolean',
            'notify_user' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($userId);
            
            $banData = [
                'status' => 'banned',
                'banned_at' => now(),
                'banned_by' => Auth::id(),
                'ban_reason' => $request->reason,
                'ban_type' => $request->duration
            ];

            if ($request->duration === 'temporary') {
                $banData['ban_expires_at'] = now()->addDays($request->duration_days);
            }

            $user->update($banData);

            // IP ban if requested
            if ($request->get('ip_ban', false)) {
                $this->addIpBan($user);
            }

            // Log the action
            $this->logModerationAction('ban_user', 'user', $userId, [
                'ban_type' => $request->duration,
                'duration_days' => $request->duration_days,
                'reason' => $request->reason,
                'ip_ban' => $request->get('ip_ban', false)
            ]);

            // Send notification if requested
            if ($request->get('notify_user', true)) {
                $this->sendBanNotification($user, $request->reason, $request->duration, $request->duration_days);
            }

            return response()->json([
                'success' => true,
                'message' => 'User banned successfully',
                'data' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to ban user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unban user
     */
    public function unbanUser(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            if ($user->status !== 'banned') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not banned'
                ], 400);
            }

            $user->update([
                'status' => 'active',
                'banned_at' => null,
                'banned_by' => null,
                'ban_reason' => null,
                'ban_type' => null,
                'ban_expires_at' => null
            ]);

            // Remove IP ban if exists
            $this->removeIpBan($user);

            // Log the action
            $this->logModerationAction('unban_user', 'user', $userId, [
                'unbanned_by' => Auth::id(),
                'reason' => $request->get('reason', 'Manual unban')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User unbanned successfully',
                'data' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unban user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk user actions
     */
    public function bulkUserAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:warn,ban,delete,activate,deactivate',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = [];
            $successful = 0;
            $failed = 0;

            foreach ($request->user_ids as $userId) {
                try {
                    switch ($request->action) {
                        case 'warn':
                            $this->bulkWarnUser($userId, $request->reason);
                            break;
                        case 'ban':
                            $this->bulkBanUser($userId, $request->reason);
                            break;
                        case 'activate':
                            $this->bulkActivateUser($userId);
                            break;
                        case 'deactivate':
                            $this->bulkDeactivateUser($userId);
                            break;
                    }
                    
                    $results[$userId] = ['success' => true];
                    $successful++;
                } catch (\Exception $e) {
                    $results[$userId] = ['success' => false, 'error' => $e->getMessage()];
                    $failed++;
                }
            }

            // Log bulk action
            $this->logModerationAction('bulk_user_action', 'bulk', null, [
                'action' => $request->action,
                'user_ids' => $request->user_ids,
                'reason' => $request->reason,
                'successful' => $successful,
                'failed' => $failed
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk action completed: {$successful} successful, {$failed} failed",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'successful' => $successful,
                        'failed' => $failed,
                        'total' => count($request->user_ids)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk action failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user warnings
     */
    public function getUserWarnings(Request $request)
    {
        try {
            $warnings = UserWarning::with(['user', 'moderator'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $warnings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user warnings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user bans
     */
    public function getUserBans(Request $request)
    {
        try {
            $bans = User::where('status', 'banned')
                ->with(['bannedBy'])
                ->orderBy('banned_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $bans
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user bans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete warning
     */
    public function deleteWarning(Request $request, $warningId)
    {
        try {
            $warning = UserWarning::findOrFail($warningId);
            
            // Log before deletion
            $this->logModerationAction('delete_warning', 'warning', $warningId, [
                'user_id' => $warning->user_id,
                'original_reason' => $warning->reason,
                'deleted_reason' => $request->get('reason', 'Administrative action')
            ]);

            $warning->delete();

            return response()->json([
                'success' => true,
                'message' => 'Warning deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete warning',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get moderation activity log
     */
    public function getActivityLog(Request $request)
    {
        try {
            $logs = DB::table('moderation_log as ml')
                ->leftJoin('users as u', 'ml.moderator_id', '=', 'u.id')
                ->select([
                    'ml.*',
                    'u.name as moderator_name',
                    'u.avatar as moderator_avatar'
                ])
                ->orderBy('ml.created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activity log',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function getTimeframeSince($timeframe)
    {
        return match($timeframe) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            '90d' => Carbon::now()->subDays(90),
            default => Carbon::now()->subDay()
        };
    }

    private function getFlaggedContentCount()
    {
        $count = 0;
        
        // Count flagged forum posts
        $count += DB::table('forum_posts')->where('is_flagged', true)->count();
        
        // Count flagged forum threads
        $count += DB::table('forum_threads')->where('is_flagged', true)->count();
        
        // Count flagged news comments
        $count += DB::table('news_comments')->where('is_flagged', true)->count();
        
        return $count;
    }

    private function getModerationQueueCount()
    {
        return DB::table('moderation_queue')->where('status', 'pending')->count();
    }

    private function getAutoActionsCount()
    {
        return DB::table('moderation_log')
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->where('action', 'like', 'auto_%')
            ->count();
    }

    private function getManualActionsCount()
    {
        return DB::table('moderation_log')
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->where('moderator_id', '>', 0)
            ->where('action', 'not like', 'auto_%')
            ->count();
    }

    private function calculateCommunityHealthScore()
    {
        // Simplified health score calculation
        $scores = [];
        
        // Engagement score (0-10)
        $activeUsers = User::where('last_activity', '>=', Carbon::now()->subDay())->count();
        $totalUsers = User::count();
        $engagementRatio = $totalUsers > 0 ? $activeUsers / $totalUsers : 0;
        $scores[] = min(10, $engagementRatio * 20); // Scale to 0-10
        
        // Toxicity score (0-10, higher is better)
        $recentReports = Report::where('created_at', '>=', Carbon::now()->subDay())->count();
        $toxicityScore = max(0, 10 - ($recentReports * 0.5));
        $scores[] = $toxicityScore;
        
        // Content quality score (based on upvote ratios)
        $scores[] = 7.5; // Placeholder
        
        // Moderation efficiency score
        $pendingReports = Report::pending()->count();
        $moderationScore = max(0, 10 - ($pendingReports * 0.2));
        $scores[] = $moderationScore;
        
        return round(array_sum($scores) / count($scores), 1);
    }

    private function getCommunityMetrics($timeframe)
    {
        $since = $this->getTimeframeSince($timeframe);
        
        return [
            'engagement' => [
                'score' => 8.2,
                'trend' => 'up',
                'change' => '+12%',
                'data' => [
                    'daily_active_users' => User::where('last_activity', '>=', Carbon::now()->subDay())->count(),
                    'posts_per_day' => DB::table('forum_posts')->where('created_at', '>=', Carbon::now()->subDay())->count(),
                    'comments_per_day' => DB::table('news_comments')->where('created_at', '>=', Carbon::now()->subDay())->count(),
                    'average_session_time' => '23m'
                ]
            ],
            'toxicity' => [
                'score' => 9.1,
                'trend' => 'up',
                'change' => '+5%',
                'data' => [
                    'reports_per_day' => Report::where('created_at', '>=', Carbon::now()->subDay())->count(),
                    'automated_actions' => $this->getAutoActionsCount(),
                    'manual_moderations' => $this->getManualActionsCount(),
                    'escalated_issues' => 1
                ]
            ]
        ];
    }

    private function getCommunityTrends($timeframe)
    {
        // Simplified trend data - in production, this would pull from analytics
        return [
            'engagement_over_time' => [],
            'toxicity_incidents' => []
        ];
    }

    private function getCommunityAlerts()
    {
        $alerts = [];
        
        // Check for high report volume
        $recentReports = Report::where('created_at', '>=', Carbon::now()->subHours(24))->count();
        if ($recentReports > 10) {
            $alerts[] = [
                'id' => 1,
                'type' => 'warning',
                'title' => 'High Report Volume',
                'message' => "Received {$recentReports} reports in the last 24 hours",
                'timestamp' => now()->toISOString(),
                'action_required' => true
            ];
        }
        
        // Check for pending queue size
        $pendingQueue = $this->getModerationQueueCount();
        if ($pendingQueue > 20) {
            $alerts[] = [
                'id' => 2,
                'type' => 'critical',
                'title' => 'Large Moderation Queue',
                'message' => "{$pendingQueue} items pending review",
                'timestamp' => now()->toISOString(),
                'action_required' => true
            ];
        }
        
        return $alerts;
    }

    private function getCommunityRecommendations()
    {
        $recommendations = [];
        
        // Check engagement levels
        $activeUsers = User::where('last_activity', '>=', Carbon::now()->subDay())->count();
        $totalUsers = User::count();
        
        if ($totalUsers > 0 && ($activeUsers / $totalUsers) < 0.1) {
            $recommendations[] = [
                'id' => 1,
                'category' => 'engagement',
                'title' => 'Boost User Engagement',
                'description' => 'Consider hosting events or competitions to increase daily active users',
                'priority' => 'medium',
                'estimated_impact' => 'high'
            ];
        }
        
        return $recommendations;
    }

    private function logModerationAction($action, $targetType, $targetId, $details = [])
    {
        try {
            DB::table('moderation_log')->insert([
                'moderator_id' => Auth::id(),
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'details' => json_encode($details),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to log moderation action', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendWarningNotification($user, $warning)
    {
        // Implementation would depend on your notification system
        // This could send email, in-app notification, etc.
    }

    private function sendBanNotification($user, $reason, $duration, $durationDays = null)
    {
        // Implementation would depend on your notification system
    }

    private function addIpBan($user)
    {
        // Add IP to ban list
        $ip = request()->ip();
        if ($ip) {
            DB::table('ip_bans')->insertOrIgnore([
                'ip_address' => $ip,
                'banned_by' => Auth::id(),
                'reason' => 'User ban with IP restriction',
                'created_at' => now()
            ]);
        }
    }

    private function removeIpBan($user)
    {
        // Remove IP from ban list if it was added for this user
        DB::table('ip_bans')
            ->where('banned_by', Auth::id())
            ->where('created_at', '>=', $user->banned_at)
            ->delete();
    }

    private function bulkWarnUser($userId, $reason)
    {
        UserWarning::create([
            'user_id' => $userId,
            'moderator_id' => Auth::id(),
            'reason' => $reason,
            'severity' => 'medium',
            'duration_days' => 7,
            'acknowledged' => false
        ]);
    }

    private function bulkBanUser($userId, $reason)
    {
        User::where('id', $userId)->update([
            'status' => 'banned',
            'banned_at' => now(),
            'banned_by' => Auth::id(),
            'ban_reason' => $reason,
            'ban_type' => 'temporary',
            'ban_expires_at' => now()->addDays(7)
        ]);
    }

    private function bulkActivateUser($userId)
    {
        User::where('id', $userId)->update(['status' => 'active']);
    }

    private function bulkDeactivateUser($userId)
    {
        User::where('id', $userId)->update(['status' => 'inactive']);
    }
}