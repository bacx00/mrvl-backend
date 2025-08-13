<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Report;
use App\Models\UserWarning;

class ContentModerationService
{
    private $spamKeywords = [
        'viagra', 'casino', 'lottery', 'winner', 'congratulations', 'million dollars',
        'click here', 'limited time', 'act now', 'free money', 'get rich quick',
        'work from home', 'make money fast', 'guaranteed income', 'no experience required'
    ];

    private $toxicPatterns = [
        '/\b(kill\s+yourself|kys)\b/i',
        '/\b(retard|retarded)\b/i',
        '/\b(f[4@]gg[o0]t|f[4@]g)\b/i',
        '/\b(n[i1!]gg[e3]r|n[i1!]gg[4@])\b/i',
        '/\b(cancer|aids)\s+(player|user|noob)\b/i',
        '/\b(uninstall|delete)\s+(game|life)\b/i'
    ];

    /**
     * Analyze content for potential violations
     */
    public function analyzeContent($content, $type = 'post', $userId = null)
    {
        $violations = [];
        $riskScore = 0;
        
        // Spam detection
        $spamResult = $this->detectSpam($content);
        if ($spamResult['isSpam']) {
            $violations[] = [
                'type' => 'spam',
                'severity' => $spamResult['severity'],
                'confidence' => $spamResult['confidence'],
                'details' => $spamResult['details']
            ];
            $riskScore += $spamResult['confidence'] * 0.7;
        }

        // Toxic language detection
        $toxicResult = $this->detectToxicLanguage($content);
        if ($toxicResult['isToxic']) {
            $violations[] = [
                'type' => 'toxic_language',
                'severity' => $toxicResult['severity'],
                'confidence' => $toxicResult['confidence'],
                'details' => $toxicResult['details']
            ];
            $riskScore += $toxicResult['confidence'] * 0.9;
        }

        // User behavior analysis
        if ($userId) {
            $behaviorResult = $this->analyzeUserBehavior($userId, $type);
            if ($behaviorResult['suspicious']) {
                $violations[] = [
                    'type' => 'suspicious_behavior',
                    'severity' => $behaviorResult['severity'],
                    'confidence' => $behaviorResult['confidence'],
                    'details' => $behaviorResult['details']
                ];
                $riskScore += $behaviorResult['confidence'] * 0.5;
            }
        }

        // Content patterns analysis
        $patternResult = $this->analyzeContentPatterns($content);
        if ($patternResult['violations']) {
            foreach ($patternResult['violations'] as $violation) {
                $violations[] = $violation;
                $riskScore += $violation['confidence'] * 0.6;
            }
        }

        return [
            'violations' => $violations,
            'risk_score' => min($riskScore, 1.0),
            'action_recommended' => $this->getRecommendedAction($riskScore, $violations),
            'requires_review' => $riskScore > 0.3 || !empty($violations)
        ];
    }

    /**
     * Detect spam content
     */
    private function detectSpam($content)
    {
        $spamScore = 0;
        $indicators = [];
        $content = strtolower($content);

        // Check for spam keywords
        foreach ($this->spamKeywords as $keyword) {
            if (strpos($content, strtolower($keyword)) !== false) {
                $spamScore += 0.2;
                $indicators[] = "Contains spam keyword: {$keyword}";
            }
        }

        // Check for excessive capitalization
        $upperChars = strlen(preg_replace('/[^A-Z]/', '', $content));
        $totalChars = strlen(preg_replace('/[^A-Za-z]/', '', $content));
        if ($totalChars > 0 && ($upperChars / $totalChars) > 0.7) {
            $spamScore += 0.3;
            $indicators[] = "Excessive capitalization";
        }

        // Check for repeated characters
        if (preg_match('/(.)\1{4,}/', $content)) {
            $spamScore += 0.2;
            $indicators[] = "Repeated characters";
        }

        // Check for excessive URLs
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/', $content);
        if ($urlCount > 2) {
            $spamScore += 0.4;
            $indicators[] = "Multiple URLs detected";
        }

        // Check for phone numbers
        if (preg_match('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', $content)) {
            $spamScore += 0.3;
            $indicators[] = "Phone number detected";
        }

        return [
            'isSpam' => $spamScore > 0.5,
            'confidence' => min($spamScore, 1.0),
            'severity' => $this->getSeverity($spamScore),
            'details' => $indicators
        ];
    }

    /**
     * Detect toxic language
     */
    private function detectToxicLanguage($content)
    {
        $toxicScore = 0;
        $indicators = [];

        // Check toxic patterns
        foreach ($this->toxicPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $toxicScore += 0.8;
                $indicators[] = "Matched toxic pattern";
            }
        }

        // Check for excessive profanity
        $profanityWords = ['fuck', 'shit', 'damn', 'bitch', 'asshole', 'bastard'];
        $profanityCount = 0;
        foreach ($profanityWords as $word) {
            $profanityCount += substr_count(strtolower($content), $word);
        }
        
        if ($profanityCount > 2) {
            $toxicScore += 0.4;
            $indicators[] = "Excessive profanity";
        } elseif ($profanityCount > 0) {
            $toxicScore += 0.1;
            $indicators[] = "Contains profanity";
        }

        // Check for aggressive language
        $aggressiveWords = ['hate', 'kill', 'die', 'stupid', 'idiot', 'moron', 'trash', 'noob'];
        $aggressiveCount = 0;
        foreach ($aggressiveWords as $word) {
            if (strpos(strtolower($content), $word) !== false) {
                $aggressiveCount++;
            }
        }
        
        if ($aggressiveCount > 3) {
            $toxicScore += 0.3;
            $indicators[] = "Aggressive language";
        }

        return [
            'isToxic' => $toxicScore > 0.4,
            'confidence' => min($toxicScore, 1.0),
            'severity' => $this->getSeverity($toxicScore),
            'details' => $indicators
        ];
    }

    /**
     * Analyze user behavior patterns
     */
    private function analyzeUserBehavior($userId, $contentType)
    {
        $user = User::find($userId);
        if (!$user) {
            return ['suspicious' => false];
        }

        $suspiciousScore = 0;
        $indicators = [];

        // Check account age
        $accountAge = Carbon::now()->diffInDays($user->created_at);
        if ($accountAge < 1) {
            $suspiciousScore += 0.3;
            $indicators[] = "Very new account (less than 1 day)";
        } elseif ($accountAge < 7) {
            $suspiciousScore += 0.1;
            $indicators[] = "New account (less than 1 week)";
        }

        // Check posting frequency (last 24 hours)
        $recentPosts = $this->getRecentUserContent($userId, $contentType, 24);
        if ($recentPosts > 50) {
            $suspiciousScore += 0.5;
            $indicators[] = "Excessive posting (>50 posts in 24h)";
        } elseif ($recentPosts > 20) {
            $suspiciousScore += 0.2;
            $indicators[] = "High posting frequency (>20 posts in 24h)";
        }

        // Check for similar content
        $similarContent = $this->checkSimilarContent($userId, $contentType);
        if ($similarContent > 0.8) {
            $suspiciousScore += 0.4;
            $indicators[] = "High content similarity detected";
        }

        // Check warning history
        $warningCount = UserWarning::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();
        
        if ($warningCount > 3) {
            $suspiciousScore += 0.3;
            $indicators[] = "Multiple recent warnings";
        }

        return [
            'suspicious' => $suspiciousScore > 0.3,
            'confidence' => min($suspiciousScore, 1.0),
            'severity' => $this->getSeverity($suspiciousScore),
            'details' => $indicators
        ];
    }

    /**
     * Analyze content patterns
     */
    private function analyzeContentPatterns($content)
    {
        $violations = [];
        
        // Check content length
        if (strlen($content) < 5) {
            $violations[] = [
                'type' => 'low_quality',
                'severity' => 'low',
                'confidence' => 0.6,
                'details' => 'Very short content'
            ];
        }

        // Check for excessive emojis
        $emojiCount = preg_match_all('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]/u', $content);
        if ($emojiCount > 10) {
            $violations[] = [
                'type' => 'spam',
                'severity' => 'medium',
                'confidence' => 0.5,
                'details' => 'Excessive emoji usage'
            ];
        }

        // Check for duplicate content patterns
        if (preg_match('/(.{10,})\1{2,}/', $content)) {
            $violations[] = [
                'type' => 'spam',
                'severity' => 'medium',
                'confidence' => 0.7,
                'details' => 'Repeated content patterns'
            ];
        }

        return ['violations' => $violations];
    }

    /**
     * Get recommended action based on analysis
     */
    private function getRecommendedAction($riskScore, $violations)
    {
        if ($riskScore > 0.8) {
            return 'auto_delete';
        } elseif ($riskScore > 0.6) {
            return 'auto_flag';
        } elseif ($riskScore > 0.4) {
            return 'queue_review';
        } elseif ($riskScore > 0.2) {
            return 'soft_flag';
        }
        
        return 'allow';
    }

    /**
     * Execute automated moderation action
     */
    public function executeAutomatedAction($contentType, $contentId, $action, $analysis)
    {
        try {
            DB::beginTransaction();

            switch ($action) {
                case 'auto_delete':
                    $this->deleteContent($contentType, $contentId);
                    $this->logModerationAction('auto_delete', $contentType, $contentId, $analysis);
                    break;

                case 'auto_flag':
                    $this->flagContent($contentType, $contentId, 'auto_flagged');
                    $this->logModerationAction('auto_flag', $contentType, $contentId, $analysis);
                    break;

                case 'queue_review':
                    $this->queueForReview($contentType, $contentId, $analysis);
                    $this->logModerationAction('queue_review', $contentType, $contentId, $analysis);
                    break;

                case 'soft_flag':
                    $this->softFlag($contentType, $contentId, $analysis);
                    $this->logModerationAction('soft_flag', $contentType, $contentId, $analysis);
                    break;
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to execute automated moderation action', [
                'action' => $action,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get content moderation statistics
     */
    public function getModerationStats($period = '24h')
    {
        $since = match($period) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay()
        };

        return Cache::remember("moderation_stats_{$period}", 300, function () use ($since) {
            return [
                'total_analyzed' => DB::table('moderation_log')
                    ->where('created_at', '>=', $since)
                    ->where('action', 'like', 'analyze_%')
                    ->count(),
                
                'auto_actions' => DB::table('moderation_log')
                    ->where('created_at', '>=', $since)
                    ->where('action', 'like', 'auto_%')
                    ->count(),
                
                'flagged_content' => DB::table('moderation_log')
                    ->where('created_at', '>=', $since)
                    ->whereIn('action', ['auto_flag', 'flag_content'])
                    ->count(),
                
                'deleted_content' => DB::table('moderation_log')
                    ->where('created_at', '>=', $since)
                    ->whereIn('action', ['auto_delete', 'delete_content'])
                    ->count(),
                
                'pending_review' => DB::table('moderation_queue')
                    ->where('status', 'pending')
                    ->count(),
                
                'high_risk_users' => DB::table('users')
                    ->whereExists(function ($query) use ($since) {
                        $query->select(DB::raw(1))
                            ->from('moderation_log')
                            ->whereColumn('moderation_log.target_id', 'users.id')
                            ->where('moderation_log.target_type', 'user')
                            ->where('moderation_log.created_at', '>=', $since)
                            ->havingRaw('COUNT(*) > 3');
                    })
                    ->count(),
                
                'spam_detected' => DB::table('moderation_log')
                    ->where('created_at', '>=', $since)
                    ->where('details', 'like', '%spam%')
                    ->count(),
                
                'toxic_language' => DB::table('moderation_log')
                    ->where('created_at', '>=', $since)
                    ->where('details', 'like', '%toxic_language%')
                    ->count()
            ];
        });
    }

    /**
     * Helper methods
     */
    private function getSeverity($score)
    {
        if ($score > 0.8) return 'critical';
        if ($score > 0.6) return 'high';
        if ($score > 0.4) return 'medium';
        if ($score > 0.2) return 'low';
        return 'minimal';
    }

    private function getRecentUserContent($userId, $contentType, $hours)
    {
        $since = Carbon::now()->subHours($hours);
        
        switch ($contentType) {
            case 'forum_post':
                return DB::table('forum_posts')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $since)
                    ->count();
                    
            case 'forum_thread':
                return DB::table('forum_threads')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $since)
                    ->count();
                    
            case 'news_comment':
                return DB::table('news_comments')
                    ->where('user_id', $userId)
                    ->where('created_at', '>=', $since)
                    ->count();
                    
            default:
                return 0;
        }
    }

    private function checkSimilarContent($userId, $contentType)
    {
        // Simplified similarity check - in production, use more sophisticated algorithm
        return 0.0;
    }

    private function deleteContent($contentType, $contentId)
    {
        switch ($contentType) {
            case 'forum_post':
                DB::table('forum_posts')
                    ->where('id', $contentId)
                    ->update([
                        'status' => 'deleted',
                        'deleted_at' => now(),
                        'deleted_by' => 0, // System user
                        'deletion_reason' => 'Automated moderation'
                    ]);
                break;
                
            case 'forum_thread':
                DB::table('forum_threads')
                    ->where('id', $contentId)
                    ->update([
                        'status' => 'deleted',
                        'deleted_at' => now(),
                        'deleted_by' => 0,
                        'deletion_reason' => 'Automated moderation'
                    ]);
                break;
                
            case 'news_comment':
                DB::table('news_comments')
                    ->where('id', $contentId)
                    ->update([
                        'status' => 'deleted',
                        'deleted_at' => now(),
                        'deleted_by' => 0,
                        'deletion_reason' => 'Automated moderation'
                    ]);
                break;
        }
    }

    private function flagContent($contentType, $contentId, $flag)
    {
        switch ($contentType) {
            case 'forum_post':
                DB::table('forum_posts')
                    ->where('id', $contentId)
                    ->update(['is_flagged' => true, 'flag_reason' => $flag]);
                break;
                
            case 'forum_thread':
                DB::table('forum_threads')
                    ->where('id', $contentId)
                    ->update(['is_flagged' => true, 'flag_reason' => $flag]);
                break;
                
            case 'news_comment':
                DB::table('news_comments')
                    ->where('id', $contentId)
                    ->update(['is_flagged' => true, 'flag_reason' => $flag]);
                break;
        }
    }

    private function queueForReview($contentType, $contentId, $analysis)
    {
        DB::table('moderation_queue')->insert([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'risk_score' => $analysis['risk_score'],
            'violations' => json_encode($analysis['violations']),
            'status' => 'pending',
            'priority' => $this->getPriority($analysis['risk_score']),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function softFlag($contentType, $contentId, $analysis)
    {
        // Mark for monitoring but don't take immediate action
        DB::table('content_monitoring')->insert([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'monitoring_reason' => 'soft_flag',
            'analysis_data' => json_encode($analysis),
            'created_at' => now()
        ]);
    }

    private function getPriority($riskScore)
    {
        if ($riskScore > 0.8) return 'critical';
        if ($riskScore > 0.6) return 'high';
        if ($riskScore > 0.4) return 'medium';
        return 'low';
    }

    private function logModerationAction($action, $targetType, $targetId, $details = [])
    {
        try {
            DB::table('moderation_log')->insert([
                'moderator_id' => 0, // System user for automated actions
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'details' => json_encode($details),
                'ip_address' => request()->ip(),
                'user_agent' => 'AutoModerationSystem',
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log moderation action', [
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'error' => $e->getMessage()
            ]);
        }
    }
}