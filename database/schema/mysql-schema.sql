/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competitive_timers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitive_timers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `round_id` bigint unsigned DEFAULT NULL,
  `timer_type` enum('preparation','match','overtime','break','tactical_pause','hero_selection') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'match',
  `duration_seconds` int NOT NULL DEFAULT '600',
  `remaining_seconds` int NOT NULL DEFAULT '600',
  `status` enum('running','paused','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paused_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `timer_config` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `competitive_timers_round_id_foreign` (`round_id`),
  KEY `competitive_timers_match_id_timer_type_index` (`match_id`,`timer_type`),
  KEY `competitive_timers_status_started_at_index` (`status`,`started_at`),
  CONSTRAINT `competitive_timers_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competitive_timers_round_id_foreign` FOREIGN KEY (`round_id`) REFERENCES `match_rounds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_standings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `points` int NOT NULL DEFAULT '0',
  `matches_played` int NOT NULL DEFAULT '0',
  `matches_won` int NOT NULL DEFAULT '0',
  `matches_lost` int NOT NULL DEFAULT '0',
  `maps_won` int NOT NULL DEFAULT '0',
  `maps_lost` int NOT NULL DEFAULT '0',
  `rounds_won` int NOT NULL DEFAULT '0',
  `rounds_lost` int NOT NULL DEFAULT '0',
  `wins` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `map_differential` int NOT NULL DEFAULT '0',
  `win_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','eliminated','qualified') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `prize_won` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_standings_event_id_team_id_unique` (`event_id`,`team_id`),
  KEY `event_standings_team_id_foreign` (`team_id`),
  KEY `event_standings_event_id_position_index` (`event_id`,`position`),
  CONSTRAINT `event_standings_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_standings_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_team` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `joined_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registered',
  `placement` int DEFAULT NULL,
  `seed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_team_event_id_team_id_unique` (`event_id`,`team_id`),
  KEY `event_team_event_id_status_index` (`event_id`,`status`),
  KEY `event_team_team_id_status_index` (`team_id`,`status`),
  CONSTRAINT `event_team_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `seed` int DEFAULT NULL,
  `status` enum('registered','confirmed','eliminated','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registered',
  `placement` int DEFAULT NULL,
  `prize_money` decimal(15,2) DEFAULT NULL,
  `points` int DEFAULT NULL,
  `registered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_teams_event_id_team_id_unique` (`event_id`,`team_id`),
  KEY `event_teams_team_id_foreign` (`team_id`),
  CONSTRAINT `event_teams_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('championship','tournament','scrim','qualifier','regional','international','invitational','community','friendly','practice','exhibition') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tournament',
  `tier` enum('S','A','B','C') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'B',
  `format` enum('single_elimination','double_elimination','round_robin','swiss','group_stage','bo1','bo3','bo5') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single_elimination',
  `tournament_format` enum('marvel_rivals_championship','marvel_rivals_ignite','marvel_rivals_invitational','double_elimination_playoff','regional_qualifier','open_qualifier','closed_qualifier','group_stage_playoffs','round_robin_league','swiss_system','single_elimination','third_party_tournament','cross_platform_event') COLLATE utf8mb4_unicode_ci DEFAULT 'double_elimination_playoff',
  `tournament_series` enum('marvel_rivals_championship','marvel_rivals_ignite','marvel_rivals_invitational','esports_gaming_league','third_party','community_tournament','custom_event') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phases` json DEFAULT NULL,
  `current_phase` enum('registration','group_stage','swiss_stage','playoffs','quarterfinals','semifinals','grand_final','consolation_final','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registration',
  `mr_tournament_stage` enum('registration_open','open_qualifiers','closed_qualifiers','group_stage','double_elimination_upper','double_elimination_lower','regional_finals','mid_season_finals','grand_finals','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registration_open',
  `minimum_rank` enum('bronze','silver','gold','platinum','diamond','vibranium','grandmaster_i','grandmaster_ii','grandmaster_iii','one_above_all') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mr_region` enum('americas','emea','china','asia','oceania','global') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_category` enum('pc_only','console_only','cross_platform','platform_mixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cross_platform',
  `game_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upcoming',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `phase_dates` json DEFAULT NULL,
  `match_schedule` json DEFAULT NULL,
  `registration_start` datetime DEFAULT NULL,
  `registration_end` datetime DEFAULT NULL,
  `timezone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `max_teams` int NOT NULL DEFAULT '16',
  `group_count` int DEFAULT NULL,
  `teams_per_group` int DEFAULT NULL,
  `teams_advance_per_group` int NOT NULL DEFAULT '2',
  `swiss_rounds` int DEFAULT NULL,
  `swiss_win_threshold` decimal(3,1) DEFAULT NULL,
  `has_lower_bracket` tinyint(1) NOT NULL DEFAULT '0',
  `has_consolation_final` tinyint(1) NOT NULL DEFAULT '0',
  `final_format` enum('bo3','bo5','bo7') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bo3',
  `mr_match_format` enum('bo3','bo5','bo7') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bo3',
  `seeding_rules` json DEFAULT NULL,
  `tiebreaker_rules` json DEFAULT NULL,
  `advancement_rules` json DEFAULT NULL,
  `organizer_id` bigint unsigned NOT NULL,
  `prize_pool` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `prize_distribution` json DEFAULT NULL,
  `rules` text COLLATE utf8mb4_unicode_ci,
  `registration_requirements` json DEFAULT NULL,
  `streams` json DEFAULT NULL,
  `social_links` json DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `views` int NOT NULL DEFAULT '0',
  `bracket_data` json DEFAULT NULL,
  `seeding_data` json DEFAULT NULL,
  `current_round` int NOT NULL DEFAULT '0',
  `total_rounds` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_slug_unique` (`slug`),
  KEY `events_status_start_date_index` (`status`,`start_date`),
  KEY `events_type_region_index` (`type`,`region`),
  KEY `events_featured_public_index` (`featured`,`public`),
  KEY `events_slug_index` (`slug`),
  KEY `events_organizer_id_foreign` (`organizer_id`),
  CONSTRAINT `events_organizer_id_foreign` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `follows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `follows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` bigint unsigned NOT NULL,
  `followable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `followable_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `follows_follower_id_followable_type_followable_id_unique` (`follower_id`,`followable_type`,`followable_id`),
  KEY `follows_followable_type_followable_id_index` (`followable_type`,`followable_id`),
  KEY `follows_follower_id_index` (`follower_id`),
  CONSTRAINT `follows_follower_id_foreign` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forum_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_post_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_post_votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `vote_type` enum('upvote','downvote') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forum_post_votes_post_id_user_id_unique` (`post_id`,`user_id`),
  KEY `forum_post_votes_user_id_foreign` (`user_id`),
  CONSTRAINT `forum_post_votes_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_post_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `likes` int NOT NULL DEFAULT '0',
  `dislikes` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `upvotes` int NOT NULL DEFAULT '0',
  `downvotes` int NOT NULL DEFAULT '0',
  `score` int NOT NULL DEFAULT '0',
  `is_edited` tinyint(1) NOT NULL DEFAULT '0',
  `edited_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','deleted','moderated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `forum_posts_user_id_foreign` (`user_id`),
  KEY `forum_posts_thread_id_created_at_index` (`thread_id`,`created_at`),
  KEY `forum_posts_parent_id_index` (`parent_id`),
  CONSTRAINT `forum_posts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_thread_id_foreign` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_thread_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_thread_votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `vote_type` enum('upvote','downvote') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forum_thread_votes_thread_id_user_id_unique` (`thread_id`,`user_id`),
  KEY `forum_thread_votes_user_id_foreign` (`user_id`),
  CONSTRAINT `forum_thread_votes_thread_id_foreign` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_thread_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
  `category_id` bigint unsigned DEFAULT NULL,
  `replies` int NOT NULL DEFAULT '0',
  `views` int NOT NULL DEFAULT '0',
  `replies_count` int NOT NULL DEFAULT '0',
  `upvotes` int NOT NULL DEFAULT '0',
  `downvotes` int NOT NULL DEFAULT '0',
  `score` int NOT NULL DEFAULT '0',
  `pinned` tinyint(1) NOT NULL DEFAULT '0',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','moderated','reported') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `reported` tinyint(1) NOT NULL DEFAULT '0',
  `moderated_at` timestamp NULL DEFAULT NULL,
  `moderated_by` bigint unsigned DEFAULT NULL,
  `moderation_reason` text COLLATE utf8mb4_unicode_ci,
  `likes` int NOT NULL DEFAULT '0',
  `dislikes` int NOT NULL DEFAULT '0',
  `last_reply_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `forum_threads_user_id_foreign` (`user_id`),
  KEY `forum_threads_category_pinned_created_at_index` (`category`,`pinned`,`created_at`),
  KEY `forum_threads_moderated_by_foreign` (`moderated_by`),
  KEY `forum_threads_category_id_foreign` (`category_id`),
  CONSTRAINT `forum_threads_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forum_threads_moderated_by_foreign` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forum_threads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `thread_id` bigint unsigned DEFAULT NULL,
  `post_id` bigint unsigned DEFAULT NULL,
  `vote_type` enum('upvote','downvote') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forum_votes_user_id_post_id_unique` (`user_id`,`post_id`),
  UNIQUE KEY `forum_votes_user_thread_post_unique` (`user_id`,`thread_id`,`post_id`),
  KEY `forum_votes_thread_id_vote_type_index` (`thread_id`,`vote_type`),
  KEY `forum_votes_post_id_vote_type_index` (`post_id`,`vote_type`),
  CONSTRAINT `forum_votes_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_votes_thread_id_foreign` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `game_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `game_modes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preparation_time` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rules` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_modes_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `live_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `live_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `round_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `player_id` bigint unsigned DEFAULT NULL,
  `hero_involved` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_data` json DEFAULT NULL,
  `event_timestamp` timestamp NOT NULL,
  `match_time_seconds` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_events_match_id_foreign` (`match_id`),
  KEY `live_events_round_id_foreign` (`round_id`),
  KEY `live_events_player_id_foreign` (`player_id`),
  CONSTRAINT `live_events_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_events_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_events_round_id_foreign` FOREIGN KEY (`round_id`) REFERENCES `match_rounds` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marvel_heroes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marvel_heroes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Duelist','Tank','Support') COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `difficulty` int NOT NULL DEFAULT '3',
  `stats` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marvel_heroes_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marvel_rivals_heroes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marvel_rivals_heroes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Vanguard','Duelist','Strategist') COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `lore` text COLLATE utf8mb4_unicode_ci,
  `voice_actor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `universe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Marvel',
  `abilities` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `season_added` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Launch',
  `is_new` tinyint(1) NOT NULL DEFAULT '0',
  `release_date` date DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Medium',
  `usage_rate` double NOT NULL DEFAULT '0',
  `win_rate` double NOT NULL DEFAULT '0',
  `pick_rate` double NOT NULL DEFAULT '0',
  `ban_rate` double NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marvel_rivals_heroes_name_unique` (`name`),
  UNIQUE KEY `marvel_rivals_heroes_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marvel_rivals_maps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marvel_rivals_maps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `game_mode` enum('Domination','Convoy','Convergence') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_competitive` tinyint(1) NOT NULL DEFAULT '0',
  `season` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','removed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marvel_rivals_maps_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `likes` int NOT NULL DEFAULT '0',
  `dislikes` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_match_comments_match_id` (`match_id`),
  KEY `idx_match_comments_user_id` (`user_id`),
  KEY `match_comments_parent_id_index` (`parent_id`),
  CONSTRAINT `match_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `match_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `timestamp` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_match_events_match_id` (`match_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `player_id` bigint unsigned DEFAULT NULL,
  `result` enum('win','loss','draw') COLLATE utf8mb4_unicode_ci NOT NULL,
  `performance_data` json DEFAULT NULL,
  `performance_rating` decimal(8,2) DEFAULT NULL,
  `mvp` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_history_match_id_foreign` (`match_id`),
  KEY `match_history_team_id_foreign` (`team_id`),
  KEY `match_history_player_id_foreign` (`player_id`),
  CONSTRAINT `match_history_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_history_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_history_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `data` json DEFAULT NULL,
  `performed_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_logs_performed_by_foreign` (`performed_by`),
  KEY `match_logs_match_id_action_index` (`match_id`,`action`),
  CONSTRAINT `match_logs_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_logs_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_player`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_player` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `player_id` bigint unsigned NOT NULL,
  `kills` int NOT NULL DEFAULT '0',
  `deaths` int NOT NULL DEFAULT '0',
  `assists` int NOT NULL DEFAULT '0',
  `damage` int NOT NULL DEFAULT '0',
  `healing` int NOT NULL DEFAULT '0',
  `hero_used` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `damage_blocked` int NOT NULL DEFAULT '0',
  `team_ups` int NOT NULL DEFAULT '0',
  `objective_time` int NOT NULL DEFAULT '0',
  `first_bloods` int NOT NULL DEFAULT '0',
  `final_blows` int NOT NULL DEFAULT '0',
  `accuracy` decimal(5,2) NOT NULL DEFAULT '0.00',
  `mvp` tinyint(1) NOT NULL DEFAULT '0',
  `map_index` int NOT NULL DEFAULT '0',
  `map_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `game_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_duration` int DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `team_side` enum('team1','team2') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `match_player_map_unique` (`match_id`,`player_id`,`map_index`),
  KEY `match_player_team_id_foreign` (`team_id`),
  KEY `match_player_match_id_player_id_index` (`match_id`,`player_id`),
  KEY `match_player_player_id_created_at_index` (`player_id`,`created_at`),
  KEY `match_player_match_id_map_index_index` (`match_id`,`map_index`),
  CONSTRAINT `match_player_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_player_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_player_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_rounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_rounds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `round_number` int NOT NULL DEFAULT '1',
  `map_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `game_mode` enum('Domination','Convoy','Convergence','Conquest','Doom Match') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Domination',
  `status` enum('upcoming','live','paused','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upcoming',
  `team1_score` int NOT NULL DEFAULT '0',
  `team2_score` int NOT NULL DEFAULT '0',
  `round_duration` int NOT NULL DEFAULT '0',
  `overtime_used` tinyint(1) NOT NULL DEFAULT '0',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `winner_team_id` bigint unsigned DEFAULT NULL,
  `team1_composition` json DEFAULT NULL,
  `team2_composition` json DEFAULT NULL,
  `objective_progress` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `match_rounds_match_id_round_number_unique` (`match_id`,`round_number`),
  KEY `match_rounds_winner_team_id_foreign` (`winner_team_id`),
  KEY `match_rounds_match_id_round_number_index` (`match_id`,`round_number`),
  KEY `match_rounds_status_started_at_index` (`status`,`started_at`),
  CONSTRAINT `match_rounds_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_rounds_winner_team_id_foreign` FOREIGN KEY (`winner_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `match_id` bigint unsigned DEFAULT NULL,
  `comment_id` bigint unsigned DEFAULT NULL,
  `vote_type` enum('upvote','downvote') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `match_votes_user_id_comment_id_unique` (`user_id`,`comment_id`),
  KEY `match_votes_match_id_foreign` (`match_id`),
  KEY `match_votes_comment_id_vote_type_index` (`comment_id`,`vote_type`),
  CONSTRAINT `match_votes_comment_id_foreign` FOREIGN KEY (`comment_id`) REFERENCES `match_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_votes_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team1_id` bigint unsigned NOT NULL,
  `team2_id` bigint unsigned NOT NULL,
  `event_id` bigint unsigned DEFAULT NULL,
  `bracket_type` enum('main','upper','lower','grand_final','round_robin','swiss') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `scheduled_at` datetime NOT NULL,
  `actual_start_time` timestamp NULL DEFAULT NULL,
  `actual_end_time` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `status` enum('upcoming','live','paused','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'upcoming',
  `is_paused` tinyint(1) NOT NULL DEFAULT '0',
  `paused_at` timestamp NULL DEFAULT NULL,
  `format` enum('BO1','BO3','BO5','BO7','BO9') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BO3',
  `best_of` int NOT NULL DEFAULT '3',
  `maps_required_to_win` int NOT NULL DEFAULT '2',
  `total_maps_played` int NOT NULL DEFAULT '0',
  `team1_score` int NOT NULL DEFAULT '0',
  `team2_score` int NOT NULL DEFAULT '0',
  `winner_team_id` bigint unsigned DEFAULT NULL,
  `match_format` enum('BO1','BO3','BO5') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BO1',
  `current_map` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_round` int NOT NULL DEFAULT '1',
  `current_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series_completed` tinyint(1) NOT NULL DEFAULT '0',
  `viewers` int NOT NULL DEFAULT '0',
  `stream_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stream_urls` json DEFAULT NULL,
  `maps_data` json DEFAULT NULL,
  `map_pool` json DEFAULT NULL,
  `map_picks_bans` json DEFAULT NULL,
  `game_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tournament_round` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importance_level` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `timer_data` json DEFAULT NULL,
  `competitive_settings` json DEFAULT NULL,
  `preparation_phase` json DEFAULT NULL,
  `overtime_data` json DEFAULT NULL,
  `prize_pool` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `broadcast` json DEFAULT NULL,
  `maps` json DEFAULT NULL,
  `series_winner_id` bigint unsigned DEFAULT NULL,
  `current_timer` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '0:00',
  `timer_running` tinyint(1) DEFAULT '0',
  `live_start_time` timestamp NULL DEFAULT NULL,
  `current_map_index` int DEFAULT '0',
  `winning_team` int DEFAULT NULL,
  `final_score` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_duration` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_duration` int DEFAULT NULL,
  `last_live_update` timestamp NULL DEFAULT NULL,
  `live_timer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '00:00',
  `timer_start_time` timestamp NULL DEFAULT NULL,
  `team1_composition` json DEFAULT NULL,
  `team2_composition` json DEFAULT NULL,
  `is_preparation_phase` tinyint(1) NOT NULL DEFAULT '0',
  `preparation_timer` int NOT NULL DEFAULT '45',
  `description` text COLLATE utf8mb4_unicode_ci,
  `betting_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `betting_urls` json DEFAULT NULL,
  `vod_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vod_urls` json DEFAULT NULL,
  `replay_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_notes` text COLLATE utf8mb4_unicode_ci,
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `winner_id` bigint unsigned DEFAULT NULL,
  `winner_side` enum('team1','team2') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series_score_team1` int DEFAULT '0',
  `series_score_team2` int DEFAULT '0',
  `current_map_number` int DEFAULT '1',
  `current_game_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `round` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bracket_position` int DEFAULT NULL,
  `live_data` json DEFAULT NULL,
  `player_stats` json DEFAULT NULL,
  `match_timer` json DEFAULT NULL,
  `overtime` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `allow_past_date` tinyint(1) DEFAULT '0',
  `hero_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `matches_team1_id_foreign` (`team1_id`),
  KEY `matches_team2_id_foreign` (`team2_id`),
  KEY `matches_status_scheduled_at_index` (`status`,`scheduled_at`),
  KEY `matches_series_winner_id_foreign` (`series_winner_id`),
  KEY `matches_status_current_round_index` (`status`,`current_round`),
  KEY `matches_match_format_series_completed_index` (`match_format`,`series_completed`),
  KEY `matches_winner_team_id_foreign` (`winner_team_id`),
  KEY `matches_status_importance_level_index` (`status`,`importance_level`),
  KEY `matches_winner_id_completed_at_index` (`winner_id`,`completed_at`),
  KEY `matches_format_status_index` (`format`,`status`),
  KEY `bracket_lookup_idx` (`event_id`,`bracket_type`,`round`,`bracket_position`),
  CONSTRAINT `matches_series_winner_id_foreign` FOREIGN KEY (`series_winner_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `matches_team1_id_foreign` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_team2_id_foreign` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `matches_winner_team_id_foreign` FOREIGN KEY (`winner_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mentionable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mentionable_id` bigint unsigned NOT NULL,
  `mentioned_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mentioned_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `context` text COLLATE utf8mb4_unicode_ci,
  `position` int DEFAULT NULL,
  `notification_sent` tinyint(1) NOT NULL DEFAULT '0',
  `mentioned_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mention` (`mentionable_type`,`mentionable_id`,`mentioned_type`,`mentioned_id`,`user_id`),
  KEY `mentions_mentionable_type_mentionable_id_index` (`mentionable_type`,`mentionable_id`),
  KEY `mentions_mentioned_type_mentioned_id_index` (`mentioned_type`,`mentioned_id`),
  KEY `mentions_user_id_foreign` (`user_id`),
  CONSTRAINT `mentions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `moderation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `moderator_id` bigint unsigned NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `duration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_moderation_logs_user_id` (`user_id`),
  KEY `idx_moderation_logs_moderator_id` (`moderator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `featured_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gallery` json DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `category_id` bigint unsigned DEFAULT NULL,
  `region` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INTL',
  `tags` json DEFAULT NULL,
  `mentions` json DEFAULT NULL,
  `author_id` bigint unsigned NOT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `views` int NOT NULL DEFAULT '0',
  `comments_count` int NOT NULL DEFAULT '0',
  `upvotes` int NOT NULL DEFAULT '0',
  `downvotes` int NOT NULL DEFAULT '0',
  `score` int NOT NULL DEFAULT '0',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `breaking` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `meta_data` json DEFAULT NULL,
  `related_articles` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `rejected_by` bigint unsigned DEFAULT NULL,
  `moderator_note` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `news_slug_unique` (`slug`),
  KEY `news_status_published_at_index` (`status`,`published_at`),
  KEY `news_category_status_index` (`category`,`status`),
  KEY `news_featured_status_index` (`featured`,`status`),
  KEY `news_author_id_index` (`author_id`),
  KEY `news_category_id_foreign` (`category_id`),
  FULLTEXT KEY `news_title_content_excerpt_fulltext` (`title`,`content`,`excerpt`),
  CONSTRAINT `news_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `news_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6b7280',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `news_categories_slug_unique` (`slug`),
  KEY `news_categories_created_by_foreign` (`created_by`),
  KEY `news_categories_slug_index` (`slug`),
  KEY `news_categories_is_default_sort_order_index` (`is_default`,`sort_order`),
  CONSTRAINT `news_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `news_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `likes` int NOT NULL DEFAULT '0',
  `dislikes` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `news_comments_user_id_foreign` (`user_id`),
  KEY `news_comments_news_id_created_at_index` (`news_id`,`created_at`),
  KEY `news_comments_parent_id_index` (`parent_id`),
  CONSTRAINT `news_comments_news_id_foreign` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `news_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news_votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `news_id` bigint unsigned DEFAULT NULL,
  `comment_id` bigint unsigned DEFAULT NULL,
  `vote_type` enum('upvote','downvote') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `news_votes_user_id_news_id_unique` (`user_id`,`news_id`),
  UNIQUE KEY `news_votes_user_id_comment_id_unique` (`user_id`,`comment_id`),
  KEY `news_votes_news_id_vote_type_index` (`news_id`,`vote_type`),
  KEY `news_votes_comment_id_vote_type_index` (`comment_id`,`vote_type`),
  CONSTRAINT `news_votes_comment_id_foreign` FOREIGN KEY (`comment_id`) REFERENCES `news_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_votes_news_id_foreign` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_auth_codes_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_personal_access_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `player_hero_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_hero_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint unsigned NOT NULL,
  `hero_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matches_played` int NOT NULL DEFAULT '0',
  `wins` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `win_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `rating` decimal(4,2) NOT NULL DEFAULT '0.00',
  `acs` decimal(5,1) NOT NULL DEFAULT '0.0',
  `kd_ratio` decimal(4,2) NOT NULL DEFAULT '0.00',
  `kpr` decimal(4,2) NOT NULL DEFAULT '0.00',
  `apr` decimal(4,2) NOT NULL DEFAULT '0.00',
  `dpr` decimal(4,2) NOT NULL DEFAULT '0.00',
  `adr` decimal(5,1) NOT NULL DEFAULT '0.0',
  `ahr` decimal(5,1) NOT NULL DEFAULT '0.0',
  `kast` decimal(5,2) NOT NULL DEFAULT '0.00',
  `fkpr` decimal(4,2) NOT NULL DEFAULT '0.00',
  `fdpr` decimal(4,2) NOT NULL DEFAULT '0.00',
  `total_kills` int NOT NULL DEFAULT '0',
  `total_deaths` int NOT NULL DEFAULT '0',
  `total_assists` int NOT NULL DEFAULT '0',
  `total_damage` bigint NOT NULL DEFAULT '0',
  `total_healing` bigint NOT NULL DEFAULT '0',
  `total_damage_blocked` bigint NOT NULL DEFAULT '0',
  `total_ultimate_usage` int NOT NULL DEFAULT '0',
  `total_objective_time` int NOT NULL DEFAULT '0',
  `total_rounds_played` int NOT NULL DEFAULT '0',
  `hero_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usage_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `last_played` timestamp NULL DEFAULT NULL,
  `first_played` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_hero_stats_player_id_hero_name_unique` (`player_id`,`hero_name`),
  KEY `player_hero_stats_hero_name_index` (`hero_name`),
  KEY `player_hero_stats_matches_played_index` (`matches_played`),
  KEY `player_hero_stats_win_rate_index` (`win_rate`),
  KEY `player_hero_stats_rating_index` (`rating`),
  CONSTRAINT `player_hero_stats_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `player_match_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_match_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint unsigned NOT NULL,
  `match_id` bigint unsigned NOT NULL,
  `eliminations` int NOT NULL DEFAULT '0',
  `final_blows` int NOT NULL DEFAULT '0',
  `solo_kills` int NOT NULL DEFAULT '0',
  `best_killstreak` int NOT NULL DEFAULT '0',
  `environmental_kills` int NOT NULL DEFAULT '0',
  `melee_final_blows` int NOT NULL DEFAULT '0',
  `accuracy_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `shots_fired` int NOT NULL DEFAULT '0',
  `shots_hit` int NOT NULL DEFAULT '0',
  `critical_hits` int NOT NULL DEFAULT '0',
  `deaths` int NOT NULL DEFAULT '0',
  `assists` int NOT NULL DEFAULT '0',
  `kda` decimal(5,2) DEFAULT NULL,
  `damage` int NOT NULL DEFAULT '0',
  `damage_taken` int NOT NULL DEFAULT '0',
  `healing` int NOT NULL DEFAULT '0',
  `damage_blocked` int NOT NULL DEFAULT '0',
  `team_damage_amplified` int NOT NULL DEFAULT '0',
  `cc_time_applied` int NOT NULL DEFAULT '0',
  `ultimate_usage` int NOT NULL DEFAULT '0',
  `ultimates_earned` int NOT NULL DEFAULT '0',
  `ultimates_used` int NOT NULL DEFAULT '0',
  `ultimate_eliminations` int NOT NULL DEFAULT '0',
  `objective_time` int NOT NULL DEFAULT '0',
  `hero_played` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_playtime_seconds` int NOT NULL DEFAULT '0',
  `time_played_seconds` int NOT NULL DEFAULT '0',
  `role_played` enum('Vanguard','Duelist','Strategist') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Duelist',
  `hero_switches` json DEFAULT NULL,
  `performance_rating` decimal(4,2) DEFAULT NULL,
  `player_of_the_match` tinyint(1) NOT NULL DEFAULT '0',
  `player_of_the_map` tinyint(1) NOT NULL DEFAULT '0',
  `current_map` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_specific_stats` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `round_id` bigint unsigned DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_match_stats_player_id_match_id_unique` (`player_id`,`match_id`),
  KEY `player_match_stats_match_id_player_id_index` (`match_id`,`player_id`),
  KEY `player_match_stats_round_id_foreign` (`round_id`),
  KEY `player_match_stats_player_id_hero_played_index` (`player_id`,`hero_played`),
  KEY `player_match_stats_team_id_match_id_index` (`team_id`,`match_id`),
  KEY `player_match_stats_match_id_performance_rating_index` (`match_id`,`performance_rating`),
  CONSTRAINT `player_match_stats_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_match_stats_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_match_stats_round_id_foreign` FOREIGN KEY (`round_id`) REFERENCES `match_rounds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_match_stats_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `player_team_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_team_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint unsigned NOT NULL,
  `from_team_id` bigint unsigned DEFAULT NULL,
  `to_team_id` bigint unsigned DEFAULT NULL,
  `change_date` timestamp NOT NULL,
  `change_type` enum('joined','left','transferred','released','retired','loan_start','loan_end') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `transfer_fee` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `is_official` tinyint(1) NOT NULL DEFAULT '1',
  `source_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `announced_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_team_history_from_team_id_foreign` (`from_team_id`),
  KEY `player_team_history_to_team_id_foreign` (`to_team_id`),
  KEY `player_team_history_announced_by_foreign` (`announced_by`),
  KEY `player_team_history_player_id_change_date_index` (`player_id`,`change_date`),
  KEY `player_team_history_change_date_index` (`change_date`),
  CONSTRAINT `player_team_history_announced_by_foreign` FOREIGN KEY (`announced_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `player_team_history_from_team_id_foreign` FOREIGN KEY (`from_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `player_team_history_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_team_history_to_team_id_foreign` FOREIGN KEY (`to_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `player_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint unsigned NOT NULL,
  `from_team_id` bigint unsigned DEFAULT NULL,
  `to_team_id` bigint unsigned NOT NULL,
  `transfer_date` date NOT NULL,
  `transfer_type` enum('signed','released','traded','loaned','retired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'signed',
  `transfer_fee` decimal(10,2) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_transfers_from_team_id_foreign` (`from_team_id`),
  KEY `player_transfers_player_id_transfer_date_index` (`player_id`,`transfer_date`),
  KEY `player_transfers_to_team_id_is_active_index` (`to_team_id`,`is_active`),
  KEY `player_transfers_transfer_date_index` (`transfer_date`),
  CONSTRAINT `player_transfers_from_team_id_foreign` FOREIGN KEY (`from_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `player_transfers_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_transfers_to_team_id_foreign` FOREIGN KEY (`to_team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `players` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `real_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `past_teams` json DEFAULT NULL,
  `role` enum('Vanguard','Duelist','Strategist','Tank','Support','Flex','Sub') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_preferences` json DEFAULT NULL,
  `skill_rating` int NOT NULL DEFAULT '1500',
  `main_hero` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_heroes` json DEFAULT NULL,
  `region` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nationality` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT 'US',
  `team_country` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT 'US',
  `rank` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` double NOT NULL DEFAULT '0',
  `peak_rating` double NOT NULL DEFAULT '0',
  `age` int DEFAULT NULL,
  `earnings` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_media` json DEFAULT NULL,
  `biography` text COLLATE utf8mb4_unicode_ci,
  `event_placements` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hero_pool` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `players_username_unique` (`username`),
  KEY `players_team_id_rating_index` (`team_id`,`rating`),
  CONSTRAINT `players_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rankings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_id` bigint unsigned DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `type` enum('player','team') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank` int NOT NULL DEFAULT '0',
  `rating` int NOT NULL DEFAULT '1000',
  `wins` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `matches_played` int NOT NULL DEFAULT '0',
  `win_rate` double NOT NULL DEFAULT '0',
  `current_rank` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bronze_iii',
  `rank_points` int NOT NULL DEFAULT '0',
  `hero_stats` json DEFAULT NULL,
  `performance_stats` json DEFAULT NULL,
  `season` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rankings_player_id_index` (`player_id`),
  KEY `rankings_team_id_index` (`team_id`),
  KEY `rankings_type_rating_index` (`type`,`rating`),
  KEY `rankings_season_index` (`season`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reportable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reportable_id` bigint unsigned NOT NULL,
  `reporter_id` bigint unsigned NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','resolved','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reports_reportable_type_reportable_id_index` (`reportable_type`,`reportable_id`),
  KEY `reports_reporter_id_foreign` (`reporter_id`),
  KEY `reports_resolved_by_foreign` (`resolved_by`),
  CONSTRAINT `reports_reporter_id_foreign` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` enum('PC','Console') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PC',
  `game` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Marvel Rivals',
  `division` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recent_form` json DEFAULT NULL,
  `player_count` int NOT NULL DEFAULT '0',
  `elo_rating` int NOT NULL DEFAULT '1500',
  `ranking` int DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` int NOT NULL DEFAULT '0',
  `rank` int NOT NULL DEFAULT '0',
  `win_rate` double NOT NULL DEFAULT '0',
  `points` int NOT NULL DEFAULT '0',
  `record` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wins` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `peak` int NOT NULL DEFAULT '0',
  `streak` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_match` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `founded` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `captain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coach` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coach_picture` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `earnings` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_media` json DEFAULT NULL,
  `achievements` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `country_flag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_short_name_unique` (`short_name`),
  KEY `teams_region_rating_index` (`region`,`rating`),
  KEY `teams_region_platform_rating_index` (`region`,`platform`,`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_brackets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_brackets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `round_number` int NOT NULL,
  `match_number` int NOT NULL,
  `match_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team1_id` bigint unsigned DEFAULT NULL,
  `team2_id` bigint unsigned DEFAULT NULL,
  `winner_id` bigint unsigned DEFAULT NULL,
  `match_id` bigint unsigned DEFAULT NULL,
  `team1_score` int NOT NULL DEFAULT '0',
  `team2_score` int NOT NULL DEFAULT '0',
  `bracket_type` enum('single_elimination','double_elimination','round_robin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'double_elimination',
  `bracket_side` enum('winners','losers','main') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `position_x` int DEFAULT NULL,
  `position_y` int DEFAULT NULL,
  `next_match_id` int DEFAULT NULL,
  `previous_match1_id` int DEFAULT NULL,
  `previous_match2_id` int DEFAULT NULL,
  `status` enum('upcoming','in_progress','completed','bye') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upcoming',
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bracket_position` (`event_id`,`round_number`,`match_number`,`bracket_side`),
  KEY `tournament_brackets_team1_id_foreign` (`team1_id`),
  KEY `tournament_brackets_team2_id_foreign` (`team2_id`),
  KEY `tournament_brackets_winner_id_foreign` (`winner_id`),
  KEY `tournament_brackets_match_id_foreign` (`match_id`),
  KEY `tournament_brackets_event_id_round_number_match_number_index` (`event_id`,`round_number`,`match_number`),
  KEY `tournament_brackets_event_id_bracket_type_bracket_side_index` (`event_id`,`bracket_type`,`bracket_side`),
  CONSTRAINT `tournament_brackets_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tournament_brackets_team1_id_foreign` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tournament_brackets_team2_id_foreign` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tournament_brackets_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tournament_formats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_formats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `maps_to_win` int NOT NULL,
  `max_maps` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournament_formats_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_warnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_warnings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `moderator_id` bigint unsigned NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_days` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_warnings_user_id` (`user_id`),
  KEY `idx_user_warnings_moderator_id` (`moderator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_flair` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Selected Marvel Rivals hero for flair display',
  `team_flair_id` bigint unsigned DEFAULT NULL COMMENT 'Selected team for flair display',
  `show_hero_flair` tinyint(1) NOT NULL DEFAULT '1',
  `show_team_flair` tinyint(1) NOT NULL DEFAULT '0',
  `use_hero_as_avatar` tinyint(1) NOT NULL DEFAULT '0',
  `hero_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favorite_team_id` bigint unsigned DEFAULT NULL,
  `manual_star_rating` int DEFAULT NULL,
  `star_rating_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `star_rating_assigned_by` bigint unsigned DEFAULT NULL,
  `star_rating_assigned_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive','banned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `muted_until` timestamp NULL DEFAULT NULL,
  `mute_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_favorite_team_id_foreign` (`favorite_team_id`),
  KEY `users_star_rating_assigned_by_foreign` (`star_rating_assigned_by`),
  KEY `users_team_flair_id_foreign` (`team_flair_id`),
  CONSTRAINT `users_favorite_team_id_foreign` FOREIGN KEY (`favorite_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_star_rating_assigned_by_foreign` FOREIGN KEY (`star_rating_assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_team_flair_id_foreign` FOREIGN KEY (`team_flair_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2024_01_01_000001_enhance_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2024_01_01_000002_create_teams_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_01_01_000003_create_players_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_01_01_000004_create_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2024_01_01_000005_create_matches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2024_01_01_000006_create_forum_threads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_05_28_003348_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_05_28_003351_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2024_12_28_000001_create_news_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2024_12_28_000002_add_avatar_to_players_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_06_02_193935_fix_events_type_column_size',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_05_31_070657_create_personal_access_tokens_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_05_31_071233_create_permission_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_06_17_212930_update_players_role_enum_values',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_06_19_create_marvel_rivals_tables',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_06_19_fix_event_type_enum',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_06_27_add_timer_data_to_matches',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_06_27_create_player_match_stats_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_06_27_fix_player_roles_safe',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_06_27_update_player_roles_enum',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_06_29_012600_fix_competitive_architecture',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_07_02_213921_add_url_fields_to_matches_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_07_03_030000_add_live_scoring_columns_to_matches_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_07_04_200610_add_extended_fields_to_teams_and_players_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_07_04_000001_add_likes_dislikes_to_forum_threads',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_07_04_000002_create_forum_posts_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_07_04_000003_create_forum_votes_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_07_04_100001_create_news_comments_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_07_04_100002_create_news_votes_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_07_04_120000_add_hero_name_to_users_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_07_04_210000_add_event_image_fields',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_06_19_create_forum_categories_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_07_06_074830_add_parent_id_to_match_comments_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_07_06_075400_add_likes_dislikes_to_match_comments_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_07_06_070001_create_match_votes_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_07_06_100000_update_players_role_to_marvel_rivals',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_01_07_000001_ensure_admin_user',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_07_06_223019_add_vod_url_to_matches_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_07_07_000001_add_related_articles_to_news_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_07_07_000001_add_favorite_team_id_to_users_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_07_07_120000_add_manual_star_rating_to_users_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_07_07_130000_create_follows_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_07_07_180800_create_news_categories_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_07_07_200000_create_match_player_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_07_07_200001_enhance_matches_for_bo9_support',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_07_08_063434_add_stream_url_to_events_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_07_10_100000_create_tournament_brackets_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_07_10_000000_create_mentions_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_07_11_000001_create_player_transfers_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_07_11_000002_add_event_placements_to_players_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_07_11_120000_add_current_map_index_to_matches_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_07_11_130000_add_url_fields_to_matches_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_07_11_231532_drop_stream_url_from_events_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_07_11_231739_create_event_team_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_07_13_000001_add_user_flairs',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_07_13_000002_create_marvel_rivals_heroes_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_07_13_000003_fix_database_schema_conflicts',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_07_13_220249_add_platform_and_update_regions_to_teams_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_07_14_031734_create_oauth_clients_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_07_14_031735_create_oauth_personal_access_clients_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_07_14_031732_create_oauth_access_tokens_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_07_14_031733_create_oauth_refresh_tokens_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_07_14_031731_create_oauth_auth_codes_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_07_13_add_missing_match_columns',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_07_13_add_missing_news_columns',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2025_07_13_add_past_teams_to_players',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_07_13_add_wins_losses_to_teams',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_07_13_complete_all_missing_columns',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_07_13_create_forum_votes_tables',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_07_13_create_marvel_rivals_extended_tables',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_07_14_060321_create_player_team_history_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_07_14_create_unified_forum_votes_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_07_14_create_news_votes_comments_tables',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_07_13_update_forum_threads_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_07_14_200134_add_tier_column_to_events_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_07_14_200712_make_teams_country_nullable',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_07_13_create_news_categories_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_07_13_update_news_comments_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_07_14_061658_create_mentions_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_07_14_205931_add_role_column_to_users_table',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2024_12_15_000001_create_comprehensive_events_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_07_13_fix_user_flairs_and_match_system',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_07_14_comprehensive_match_system',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_07_14_create_user_activities_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_07_15_fix_forum_votes_unique_constraint',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_07_15_053151_update_player_match_stats_table',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_07_16_213426_add_registered_at_to_event_teams_table',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2025_07_17_create_player_hero_stats_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_07_17_ensure_bracket_columns',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_07_17_add_missing_match_url_columns',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_07_19_add_status_to_teams_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_07_19_224242_add_use_hero_as_avatar_to_users_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_07_19_233649_fix_hero_avatar_paths',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_07_20_001343_add_missing_columns_to_event_teams_table',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_07_20_create_event_standings_table',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_07_20_fix_matches_table_columns',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2025_07_20_add_score_to_news_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2025_07_22_015617_add_icon_and_color_to_news_categories_table',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2025_07_22_add_coach_picture_to_teams_table',74);
