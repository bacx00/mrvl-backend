-- Database Optimization: Fix Voting System Constraints
-- Generated: 2025-08-06
-- Purpose: Resolve duplicate entry issues in voting systems

-- ====================
-- FIX FORUM VOTES
-- ====================

-- Drop the problematic unique constraint that doesn't handle NULLs properly
DROP INDEX IF EXISTS forum_votes_user_thread_unique;

-- Create a new unique constraint that properly handles NULL post_id values
CREATE UNIQUE INDEX idx_forum_votes_user_thread_post 
ON forum_votes (user_id, thread_id, COALESCE(post_id, 0));

-- Add performance indexes for voting queries
CREATE INDEX IF NOT EXISTS idx_forum_votes_thread_type 
ON forum_votes (thread_id, vote_type);

CREATE INDEX IF NOT EXISTS idx_forum_votes_post_type 
ON forum_votes (post_id, vote_type) WHERE post_id IS NOT NULL;

-- ====================
-- FIX NEWS VOTES  
-- ====================

-- Drop the problematic unique constraint that doesn't handle NULLs properly
DROP INDEX IF EXISTS news_votes_user_id_news_id_unique;

-- Create a new unique constraint that properly handles NULL comment_id values
CREATE UNIQUE INDEX idx_news_votes_user_news_comment 
ON news_votes (user_id, news_id, COALESCE(comment_id, 0));

-- Add performance indexes for voting queries
CREATE INDEX IF NOT EXISTS idx_news_votes_news_type 
ON news_votes (news_id, vote_type);

CREATE INDEX IF NOT EXISTS idx_news_votes_comment_type 
ON news_votes (comment_id, vote_type) WHERE comment_id IS NOT NULL;

-- ====================
-- ADD PERFORMANCE INDEXES
-- ====================

-- Forum performance indexes
CREATE INDEX IF NOT EXISTS idx_forum_threads_status_pinned_last_reply 
ON forum_threads (status, pinned, last_reply_at DESC);

CREATE INDEX IF NOT EXISTS idx_forum_threads_category_status_last_reply 
ON forum_threads (category_id, status, last_reply_at DESC);

CREATE INDEX IF NOT EXISTS idx_forum_posts_thread_status_created 
ON forum_posts (thread_id, status, created_at);

CREATE INDEX IF NOT EXISTS idx_forum_posts_user_status_created 
ON forum_posts (user_id, status, created_at DESC);

-- News performance indexes  
CREATE INDEX IF NOT EXISTS idx_news_status_featured_published 
ON news (status, featured, published_at DESC);

CREATE INDEX IF NOT EXISTS idx_news_category_status_published 
ON news (category_id, status, published_at DESC);

CREATE INDEX IF NOT EXISTS idx_news_comments_news_status_created 
ON news_comments (news_id, status, created_at);

CREATE INDEX IF NOT EXISTS idx_news_comments_parent_status 
ON news_comments (parent_id, status) WHERE parent_id IS NOT NULL;

-- ====================
-- VERIFY CHANGES
-- ====================

-- Check forum votes constraints
SELECT 
    'forum_votes' as table_name,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'forum_votes' 
AND INDEX_NAME LIKE '%user%';

-- Check news votes constraints  
SELECT 
    'news_votes' as table_name,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'news_votes' 
AND INDEX_NAME LIKE '%user%';

-- Check for any remaining constraint violations
SELECT 
    'forum_vote_duplicates' as check_name,
    COUNT(*) - COUNT(DISTINCT CONCAT(user_id, '-', thread_id, '-', COALESCE(post_id, 0))) as violations
FROM forum_votes
UNION ALL
SELECT 
    'news_vote_duplicates' as check_name,
    COUNT(*) - COUNT(DISTINCT CONCAT(user_id, '-', news_id, '-', COALESCE(comment_id, 0))) as violations  
FROM news_votes;

-- Performance test query for forum threads
EXPLAIN SELECT ft.id, ft.title, ft.replies_count, ft.last_reply_at, u.name
FROM forum_threads ft
LEFT JOIN users u ON ft.user_id = u.id  
WHERE ft.status = 'active'
ORDER BY ft.pinned DESC, ft.last_reply_at DESC
LIMIT 20;

-- Performance test query for news
EXPLAIN SELECT n.id, n.title, n.published_at, u.name, nc.name as category
FROM news n
LEFT JOIN users u ON n.author_id = u.id
LEFT JOIN news_categories nc ON n.category_id = nc.id
WHERE n.status = 'published' 
ORDER BY n.featured DESC, n.published_at DESC
LIMIT 15;