# MRVL Forums Moderation Panel API Documentation

## Overview
This document outlines the comprehensive forum moderation API endpoints available at `/api/admin/forums-moderation/`. All endpoints require authentication and admin/moderator roles.

## Authentication
All endpoints require:
- Bearer token authentication
- User role: `admin` or `moderator`

## Base URL
```
/api/admin/forums-moderation/
```

---

## Dashboard and Overview

### GET /dashboard
Get moderation dashboard overview with key metrics and recent activity.

**Response:**
```json
{
    "success": true,
    "data": {
        "total_threads": 150,
        "total_posts": 1250,
        "total_categories": 8,
        "active_users": 45,
        "pending_reports": 3,
        "flagged_content": 2,
        "recent_activity": [...],
        "top_categories": [...],
        "moderator_actions_today": 12
    }
}
```

### GET /statistics
Get detailed forum statistics with customizable time periods.

**Parameters:**
- `period` (optional): Number of days to analyze (default: 7)

**Response:**
```json
{
    "success": true,
    "data": {
        "overview": {...},
        "recent_activity": {...},
        "moderation": {...},
        "top_categories": [...],
        "most_active_users": [...]
    },
    "period": 7
}
```

---

## Thread Management

### GET /threads
Get paginated list of forum threads with filtering and search.

**Parameters:**
- `page` (optional): Page number
- `per_page` (optional): Items per page (max 100)
- `status` (optional): Filter by status (`all`, `active`, `locked`, `pinned`, `reported`, `flagged`)
- `category_id` (optional): Filter by category ID
- `user_id` (optional): Filter by user ID
- `search` (optional): Search in title and content
- `sort_by` (optional): Sort field (`created_at`, `updated_at`, `views`, `replies`, `title`)
- `sort_direction` (optional): Sort direction (`asc`, `desc`)
- `date_from` (optional): Start date filter
- `date_to` (optional): End date filter

**Response:**
```json
{
    "success": true,
    "data": {
        "data": [...threads...],
        "pagination": {...}
    }
}
```

### GET /threads/{id}
Get detailed information about a specific thread including moderation history.

**Response:**
```json
{
    "success": true,
    "data": {
        "thread": {
            "id": 1,
            "title": "Thread Title",
            "content": "Thread content...",
            "user": {...},
            "category": {...},
            "posts": [...],
            "reports": [...]
        },
        "moderation_history": [...]
    }
}
```

### POST /threads
Create a new forum thread (admin action).

**Request Body:**
```json
{
    "title": "Thread Title",
    "content": "Thread content...",
    "category_id": 1,
    "user_id": 123,
    "pinned": false,
    "locked": false,
    "tags": ["tag1", "tag2"]
}
```

### PUT /threads/{id}
Update a forum thread with moderation capabilities.

**Request Body:**
```json
{
    "title": "Updated Title",
    "content": "Updated content...",
    "category_id": 2,
    "pinned": true,
    "locked": false,
    "is_flagged": false,
    "moderation_note": "Reason for changes"
}
```

### DELETE /threads/{id}
Delete a forum thread (soft delete with logging).

---

## Thread Control Actions

### POST /threads/{id}/pin
Pin a thread to the top of its category.

### POST /threads/{id}/unpin
Remove pin status from a thread.

### POST /threads/{id}/lock
Lock a thread to prevent new posts.

### POST /threads/{id}/unlock
Unlock a previously locked thread.

### POST /threads/{id}/sticky
Make a thread sticky (appears above pinned threads).

### POST /threads/{id}/unsticky
Remove sticky status from a thread.

---

## Category Management

### GET /categories
Get list of forum categories with optional statistics.

**Parameters:**
- `include_stats` (optional): Include thread/post counts
- `status` (optional): Filter by status (`all`, `active`, `inactive`)

### POST /categories
Create a new forum category.

**Request Body:**
```json
{
    "name": "Category Name",
    "slug": "category-slug",
    "description": "Category description",
    "color": "#3B82F6",
    "icon": "discussion",
    "is_active": true,
    "sort_order": 10
}
```

### PUT /categories/{id}
Update an existing category.

### DELETE /categories/{id}
Delete a category (requires no threads in category).

### POST /categories/reorder
Reorder categories by updating sort_order values.

**Request Body:**
```json
{
    "categories": [
        {"id": 1, "sort_order": 0},
        {"id": 2, "sort_order": 1},
        {"id": 3, "sort_order": 2}
    ]
}
```

---

## Posts Management

### GET /posts
Get paginated list of forum posts with filtering.

**Parameters:**
- `page`, `per_page`: Pagination
- `thread_id`: Filter by thread
- `user_id`: Filter by user
- `status`: Filter by status (`all`, `reported`, `flagged`)
- `search`: Search in post content
- `date_from`, `date_to`: Date range filters

### PUT /posts/{id}
Update a forum post with moderation capabilities.

**Request Body:**
```json
{
    "content": "Updated post content...",
    "is_flagged": false,
    "moderation_note": "Reason for edit"
}
```

### DELETE /posts/{id}
Delete a forum post (soft delete with logging).

---

## User Moderation

### GET /users
Get paginated list of users with moderation information.

**Parameters:**
- `search`: Search by name or email
- `status`: Filter by status (`all`, `active`, `banned`, `warned`, `muted`)
- `role`: Filter by role (`all`, `user`, `moderator`, `admin`)
- `sort_by`: Sort field (`name`, `email`, `created_at`, `last_activity`)

### POST /users/{userId}/warn
Issue a warning to a user.

**Request Body:**
```json
{
    "reason": "Warning reason",
    "severity": "medium",
    "expires_at": "2025-08-17T10:00:00Z"
}
```

### POST /users/{userId}/timeout
Temporarily mute a user.

**Request Body:**
```json
{
    "reason": "Timeout reason",
    "duration_minutes": 1440
}
```

### POST /users/{userId}/ban
Ban a user from the platform.

**Request Body:**
```json
{
    "reason": "Ban reason",
    "permanent": false,
    "expires_at": "2025-09-10T10:00:00Z"
}
```

### POST /users/{userId}/unban
Remove ban from a user.

---

## Bulk Moderation Actions

### POST /bulk-actions
Perform bulk actions on multiple items.

**Request Body:**
```json
{
    "action": "lock",
    "type": "threads",
    "ids": [1, 2, 3, 4],
    "category_id": 2,
    "reason": "Reason for bulk action"
}
```

**Supported Actions:**
- **Threads:** `delete`, `lock`, `unlock`, `pin`, `unpin`, `move_category`, `flag`, `unflag`
- **Posts:** `delete`, `flag`, `unflag`
- **Users:** `ban`, `unban`, `timeout`

---

## Advanced Search

### GET /search
Perform advanced search across forum content.

**Parameters:**
- `query`: Search term
- `type`: Content type (`threads`, `posts`, `users`, `all`)
- `category_ids[]`: Array of category IDs
- `user_ids[]`: Array of user IDs
- `date_from`, `date_to`: Date range
- `min_replies`, `max_replies`: Reply count range (threads)
- `min_views`, `max_views`: View count range (threads)
- `has_reports`: Filter content with reports
- `is_flagged`, `is_locked`, `is_pinned`: Status filters

**Response:**
```json
{
    "success": true,
    "data": {
        "threads": {...pagination...},
        "posts": {...pagination...},
        "users": {...pagination...}
    }
}
```

---

## Report Management

### GET /reports
Get paginated list of content reports.

**Parameters:**
- `status`: Filter by status (`all`, `pending`, `resolved`, `dismissed`)
- `type`: Filter by type (`all`, `thread`, `post`, `user`)
- `page`, `per_page`: Pagination

### POST /reports/{reportId}/resolve
Resolve a report with an action.

**Request Body:**
```json
{
    "action": "warn_user",
    "reason": "Resolution reason",
    "duration_minutes": 1440
}
```

**Available Actions:**
- `dismiss`: Dismiss without action
- `warn_user`: Issue warning to reported user
- `timeout_user`: Temporarily mute user
- `ban_user`: Ban the user
- `delete_content`: Delete reported content
- `flag_content`: Flag content for review

### POST /reports/{reportId}/dismiss
Dismiss a report without taking action.

**Request Body:**
```json
{
    "reason": "Dismissal reason"
}
```

---

## Moderation Logs

### GET /moderation-logs
Get paginated list of moderation actions with filtering.

**Parameters:**
- `page`, `per_page`: Pagination
- `moderator_id`: Filter by moderator
- `action_type`: Filter by action type
- `subject_type`: Filter by subject type
- `date_from`, `date_to`: Date range

**Response:**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "user": {"id": 1, "name": "Moderator"},
                "activity_type": "moderation.thread_locked",
                "subject_type": "forum_thread",
                "subject_id": 123,
                "metadata": {...},
                "created_at": "2025-08-10T10:00:00Z"
            }
        ],
        "pagination": {...}
    }
}
```

---

## Error Handling

All endpoints return consistent error responses:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {...validation_errors...}
}
```

**HTTP Status Codes:**
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `500`: Server Error

---

## Rate Limiting

Some sensitive endpoints have rate limiting:
- User moderation actions: 10 requests per minute
- Bulk operations: 5 requests per minute
- Report resolution: 20 requests per minute

---

## Examples

### Example: Search for flagged threads in specific category
```bash
GET /api/admin/forums-moderation/search?type=threads&category_ids[]=1&is_flagged=true
```

### Example: Bulk lock threads
```bash
POST /api/admin/forums-moderation/bulk-actions
{
    "action": "lock",
    "type": "threads",
    "ids": [1, 2, 3],
    "reason": "Locked due to violation"
}
```

### Example: Warn a user
```bash
POST /api/admin/forums-moderation/users/123/warn
{
    "reason": "Inappropriate behavior in forum",
    "severity": "medium",
    "expires_at": "2025-08-17T10:00:00Z"
}
```

This comprehensive moderation panel provides all the necessary tools for effective forum management while maintaining detailed logs and proper authorization controls.