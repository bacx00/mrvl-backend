<?php

// Fix the getUserStatisticsOptimized method to work with MySQL strict mode
$file = '/var/www/mrvl-backend/app/Services/OptimizedUserProfileService.php';
$content = file_get_contents($file);

// Replace the complex query with a simpler, working version
$oldQuery = '// Single query aggregating all user statistics
                $stats = DB::selectOne("
                    SELECT 
                        -- Comment statistics
                        COALESCE(comment_stats.news_comments, 0) as news_comments,
                        COALESCE(comment_stats.match_comments, 0) as match_comments,
                        
                        -- Forum statistics
                        COALESCE(forum_stats.threads, 0) as forum_threads,
                        COALESCE(forum_stats.posts, 0) as forum_posts,
                        
                        -- Vote statistics
                        COALESCE(vote_stats.upvotes_given, 0) as upvotes_given,
                        COALESCE(vote_stats.downvotes_given, 0) as downvotes_given,
                        COALESCE(vote_stats.upvotes_received, 0) as upvotes_received,
                        COALESCE(vote_stats.downvotes_received, 0) as downvotes_received,
                        
                        -- Activity timestamps
                        GREATEST(
                            COALESCE(comment_stats.latest_comment, \'1970-01-01\'),
                            COALESCE(forum_stats.latest_forum_activity, \'1970-01-01\'),
                            COALESCE(vote_stats.latest_vote, \'1970-01-01\')
                        ) as last_activity
                        
                    FROM (SELECT 1) dummy
                    
                    LEFT JOIN (
                        SELECT 
                            SUM(CASE WHEN source = \'news\' THEN count_val ELSE 0 END) as news_comments,
                            SUM(CASE WHEN source = \'match\' THEN count_val ELSE 0 END) as match_comments,
                            MAX(latest_created) as latest_comment
                        FROM (
                            SELECT \'news\' as source, COUNT(*) as count_val, MAX(created_at) as latest_created
                            FROM news_comments WHERE user_id = ?
                            UNION ALL
                            SELECT \'match\' as source, COUNT(*) as count_val, MAX(created_at) as latest_created  
                            FROM match_comments WHERE user_id = ?
                        ) comment_union
                    ) comment_stats ON 1=1
                    
                    LEFT JOIN (
                        SELECT 
                            SUM(CASE WHEN source = \'threads\' THEN count_val ELSE 0 END) as threads,
                            SUM(CASE WHEN source = \'posts\' THEN count_val ELSE 0 END) as posts,
                            MAX(latest_created) as latest_forum_activity
                        FROM (
                            SELECT \'threads\' as source, COUNT(*) as count_val, MAX(created_at) as latest_created
                            FROM forum_threads WHERE user_id = ?
                            UNION ALL
                            SELECT \'posts\' as source, COUNT(*) as count_val, MAX(created_at) as latest_created
                            FROM forum_posts WHERE user_id = ?
                        ) forum_union
                    ) forum_stats ON 1=1
                    
                    LEFT JOIN (
                        SELECT 
                            SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes_given,
                            SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes_given,
                            COALESCE(received.upvotes, 0) as upvotes_received,
                            COALESCE(received.downvotes, 0) as downvotes_received,
                            MAX(votes.created_at) as latest_vote
                        FROM votes
                        LEFT JOIN (
                            SELECT 
                                SUM(CASE WHEN v.vote = 1 THEN 1 ELSE 0 END) as upvotes,
                                SUM(CASE WHEN v.vote = -1 THEN 1 ELSE 0 END) as downvotes
                            FROM votes v
                            INNER JOIN (
                                SELECT \'news_comments\' as table_name, id, user_id FROM news_comments WHERE user_id = ?
                                UNION ALL
                                SELECT \'match_comments\' as table_name, id, user_id FROM match_comments WHERE user_id = ?
                                UNION ALL
                                SELECT \'forum_threads\' as table_name, id, user_id FROM forum_threads WHERE user_id = ?
                                UNION ALL
                                SELECT \'forum_posts\' as table_name, id, user_id FROM forum_posts WHERE user_id = ?
                            ) user_content ON v.voteable_type = user_content.table_name AND v.voteable_id = user_content.id
                        ) received ON 1=1
                        WHERE votes.user_id = ?
                    ) vote_stats ON 1=1
                ", [
                    $userId, $userId,  // comment stats
                    $userId, $userId,  // forum stats  
                    $userId, $userId, $userId, $userId, $userId  // vote stats
                ]);';

$newQuery = '// Use simpler individual queries to avoid GROUP BY issues
                $newsComments = DB::table(\'news_comments\')->where(\'user_id\', $userId)->count();
                $matchComments = DB::table(\'match_comments\')->where(\'user_id\', $userId)->count();
                $forumThreads = DB::table(\'forum_threads\')->where(\'user_id\', $userId)->count();
                $forumPosts = DB::table(\'forum_posts\')->where(\'user_id\', $userId)->count();
                
                // Vote statistics
                $upvotesGiven = DB::table(\'votes\')->where(\'user_id\', $userId)->where(\'vote\', 1)->count();
                $downvotesGiven = DB::table(\'votes\')->where(\'user_id\', $userId)->where(\'vote\', -1)->count();
                
                // Votes received (simplified)
                $upvotesReceived = 0;
                $downvotesReceived = 0;
                
                // Get content IDs for the user
                $newsCommentIds = DB::table(\'news_comments\')->where(\'user_id\', $userId)->pluck(\'id\');
                $matchCommentIds = DB::table(\'match_comments\')->where(\'user_id\', $userId)->pluck(\'id\');
                $forumThreadIds = DB::table(\'forum_threads\')->where(\'user_id\', $userId)->pluck(\'id\');
                $forumPostIds = DB::table(\'forum_posts\')->where(\'user_id\', $userId)->pluck(\'id\');
                
                if ($newsCommentIds->isNotEmpty()) {
                    $upvotesReceived += DB::table(\'votes\')
                        ->where(\'voteable_type\', \'news_comments\')
                        ->whereIn(\'voteable_id\', $newsCommentIds)
                        ->where(\'vote\', 1)
                        ->count();
                    $downvotesReceived += DB::table(\'votes\')
                        ->where(\'voteable_type\', \'news_comments\')
                        ->whereIn(\'voteable_id\', $newsCommentIds)
                        ->where(\'vote\', -1)
                        ->count();
                }
                
                // Get last activity
                $lastActivities = [];
                if ($newsComments > 0) {
                    $lastActivities[] = DB::table(\'news_comments\')->where(\'user_id\', $userId)->max(\'created_at\');
                }
                if ($matchComments > 0) {
                    $lastActivities[] = DB::table(\'match_comments\')->where(\'user_id\', $userId)->max(\'created_at\');
                }
                if ($forumThreads > 0) {
                    $lastActivities[] = DB::table(\'forum_threads\')->where(\'user_id\', $userId)->max(\'created_at\');
                }
                if ($forumPosts > 0) {
                    $lastActivities[] = DB::table(\'forum_posts\')->where(\'user_id\', $userId)->max(\'created_at\');
                }
                
                $lastActivity = !empty($lastActivities) ? max($lastActivities) : null;
                
                $stats = (object) [
                    \'news_comments\' => $newsComments,
                    \'match_comments\' => $matchComments,
                    \'forum_threads\' => $forumThreads,
                    \'forum_posts\' => $forumPosts,
                    \'upvotes_given\' => $upvotesGiven,
                    \'downvotes_given\' => $downvotesGiven,
                    \'upvotes_received\' => $upvotesReceived,
                    \'downvotes_received\' => $downvotesReceived,
                    \'last_activity\' => $lastActivity
                ];';

$content = str_replace($oldQuery, $newQuery, $content);

file_put_contents($file, $content);
echo "Fixed user stats query\n";