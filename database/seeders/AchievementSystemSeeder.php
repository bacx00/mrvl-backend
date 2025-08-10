<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Achievement;
use App\Models\Challenge;
use App\Models\Leaderboard;
use Carbon\Carbon;

class AchievementSystemSeeder extends Seeder
{
    public function run()
    {
        // Create Achievements
        $this->createAchievements();
        
        // Create Challenges
        $this->createChallenges();
        
        // Create Leaderboards
        $this->createLeaderboards();
    }

    private function createAchievements()
    {
        $achievements = [
            // Social Achievements
            [
                'name' => 'First Comment',
                'slug' => 'first-comment',
                'description' => 'Post your first comment on MRVL',
                'icon' => '💬',
                'badge_color' => '#10B981',
                'category' => 'social',
                'rarity' => 'common',
                'points' => 5,
                'requirements' => [
                    ['type' => 'comment_posted', 'target' => 1]
                ],
                'order' => 1
            ],
            [
                'name' => 'Conversationalist',
                'slug' => 'conversationalist',
                'description' => 'Post 50 comments across news and matches',
                'icon' => '🗣️',
                'badge_color' => '#3B82F6',
                'category' => 'social',
                'rarity' => 'uncommon',
                'points' => 25,
                'requirements' => [
                    ['type' => 'comment_posted', 'target' => 50]
                ],
                'order' => 2
            ],
            [
                'name' => 'Forum Pioneer',
                'slug' => 'forum-pioneer',
                'description' => 'Create your first forum thread',
                'icon' => '🏴‍☠️',
                'badge_color' => '#8B5CF6',
                'category' => 'social',
                'rarity' => 'common',
                'points' => 10,
                'requirements' => [
                    ['type' => 'thread_created', 'target' => 1]
                ],
                'order' => 3
            ],
            [
                'name' => 'Community Leader',
                'slug' => 'community-leader',
                'description' => 'Create 25 forum threads and help build the community',
                'icon' => '👑',
                'badge_color' => '#F59E0B',
                'category' => 'social',
                'rarity' => 'rare',
                'points' => 100,
                'requirements' => [
                    ['type' => 'thread_created', 'target' => 25]
                ],
                'order' => 4
            ],

            // Activity Achievements
            [
                'name' => 'Daily Visitor',
                'slug' => 'daily-visitor',
                'description' => 'Log in to MRVL every day for a week',
                'icon' => '📅',
                'badge_color' => '#06B6D4',
                'category' => 'activity',
                'rarity' => 'common',
                'points' => 15,
                'requirements' => [
                    ['type' => 'login', 'target' => 7, 'consecutive' => true]
                ],
                'order' => 5
            ],
            [
                'name' => 'Dedicated Fan',
                'slug' => 'dedicated-fan',
                'description' => 'Log in every day for a full month',
                'icon' => '🔥',
                'badge_color' => '#EF4444',
                'category' => 'activity',
                'rarity' => 'epic',
                'points' => 200,
                'requirements' => [
                    ['type' => 'login', 'target' => 30, 'consecutive' => true]
                ],
                'order' => 6
            ],

            // Milestone Achievements
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Complete your first day on MRVL',
                'icon' => '🌟',
                'badge_color' => '#10B981',
                'category' => 'milestone',
                'rarity' => 'common',
                'points' => 5,
                'requirements' => [
                    ['type' => 'profile_complete', 'target' => 1]
                ],
                'order' => 7
            ],
            [
                'name' => 'Point Collector',
                'slug' => 'point-collector',
                'description' => 'Earn your first 100 achievement points',
                'icon' => '🎯',
                'badge_color' => '#3B82F6',
                'category' => 'milestone',
                'rarity' => 'uncommon',
                'points' => 50,
                'requirements' => [
                    ['type' => 'points_earned', 'target' => 100]
                ],
                'order' => 8
            ],
            [
                'name' => 'Achievement Hunter',
                'slug' => 'achievement-hunter',
                'description' => 'Unlock 10 different achievements',
                'icon' => '🏆',
                'badge_color' => '#F59E0B',
                'category' => 'milestone',
                'rarity' => 'rare',
                'points' => 100,
                'requirements' => [
                    ['type' => 'achievements_earned', 'target' => 10]
                ],
                'order' => 9
            ],

            // Streak Achievements
            [
                'name' => 'Week Warrior',
                'slug' => 'week-warrior',
                'description' => 'Maintain a 7-day activity streak',
                'icon' => '⚡',
                'badge_color' => '#F59E0B',
                'category' => 'streak',
                'rarity' => 'uncommon',
                'points' => 30,
                'requirements' => [
                    ['type' => 'streak_milestone', 'target' => 7]
                ],
                'order' => 10
            ],
            [
                'name' => 'Streak Master',
                'slug' => 'streak-master',
                'description' => 'Achieve a 30-day activity streak',
                'icon' => '🔥',
                'badge_color' => '#EF4444',
                'category' => 'streak',
                'rarity' => 'epic',
                'points' => 150,
                'requirements' => [
                    ['type' => 'streak_milestone', 'target' => 30]
                ],
                'order' => 11
            ],
            [
                'name' => 'Legendary Streaker',
                'slug' => 'legendary-streaker',
                'description' => 'Maintain a 100-day activity streak',
                'icon' => '🌟',
                'badge_color' => '#8B5CF6',
                'category' => 'streak',
                'rarity' => 'legendary',
                'points' => 500,
                'requirements' => [
                    ['type' => 'streak_milestone', 'target' => 100]
                ],
                'order' => 12
            ],

            // Engagement Achievements
            [
                'name' => 'First Vote',
                'slug' => 'first-vote',
                'description' => 'Cast your first vote on forum content',
                'icon' => '👍',
                'badge_color' => '#10B981',
                'category' => 'social',
                'rarity' => 'common',
                'points' => 5,
                'requirements' => [
                    ['type' => 'vote_cast', 'target' => 1]
                ],
                'order' => 13
            ],
            [
                'name' => 'Democracy Advocate',
                'slug' => 'democracy-advocate',
                'description' => 'Cast 100 votes on forum content',
                'icon' => '🗳️',
                'badge_color' => '#3B82F6',
                'category' => 'social',
                'rarity' => 'uncommon',
                'points' => 40,
                'requirements' => [
                    ['type' => 'vote_cast', 'target' => 100]
                ],
                'order' => 14
            ],

            // Special Achievements
            [
                'name' => 'Early Adopter',
                'slug' => 'early-adopter',
                'description' => 'One of the first 100 users to join MRVL',
                'icon' => '🥇',
                'badge_color' => '#F59E0B',
                'category' => 'special',
                'rarity' => 'legendary',
                'points' => 1000,
                'requirements' => [
                    ['type' => 'early_user', 'target' => 100]
                ],
                'is_secret' => true,
                'order' => 15
            ],
            [
                'name' => 'Beta Tester',
                'slug' => 'beta-tester',
                'description' => 'Participated in MRVL beta testing',
                'icon' => '🧪',
                'badge_color' => '#8B5CF6',
                'category' => 'special',
                'rarity' => 'epic',
                'points' => 250,
                'requirements' => [
                    ['type' => 'beta_participant', 'target' => 1]
                ],
                'is_secret' => true,
                'order' => 16
            ]
        ];

        foreach ($achievements as $achievementData) {
            Achievement::create($achievementData);
        }
    }

    private function createChallenges()
    {
        $now = Carbon::now();
        
        $challenges = [
            [
                'name' => 'New Year Engagement Challenge',
                'slug' => 'new-year-engagement-2025',
                'description' => 'Start the year strong by engaging with the community! Comment, create threads, and participate in discussions.',
                'icon' => '🎊',
                'requirements' => [
                    ['type' => 'comment_posted', 'target' => 10, 'points' => 2],
                    ['type' => 'thread_created', 'target' => 3, 'points' => 5],
                    ['type' => 'vote_cast', 'target' => 25, 'points' => 1]
                ],
                'rewards' => [
                    'points' => 100,
                    'title' => 'New Year Champion',
                    'badge' => 'special_2025_badge'
                ],
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays(30),
                'difficulty' => 'medium',
                'max_participants' => 1000
            ],
            [
                'name' => 'Weekly Forum Sprint',
                'slug' => 'weekly-forum-sprint',
                'description' => 'A weekly challenge to boost forum activity. Create threads, engage in discussions, and help build our community!',
                'icon' => '🏃‍♂️',
                'requirements' => [
                    ['type' => 'thread_created', 'target' => 2, 'points' => 10],
                    ['type' => 'comment_posted', 'target' => 15, 'points' => 2],
                    ['type' => 'vote_cast', 'target' => 20, 'points' => 1]
                ],
                'rewards' => [
                    'points' => 50,
                    'title' => 'Sprint Champion',
                    'achievement' => 'forum_sprinter'
                ],
                'starts_at' => $now->copy()->addDay(),
                'ends_at' => $now->copy()->addWeek(),
                'difficulty' => 'easy',
                'max_participants' => 500
            ],
            [
                'name' => 'Ultimate Engagement Challenge',
                'slug' => 'ultimate-engagement',
                'description' => 'The ultimate test of community engagement. Only the most dedicated members will complete this challenge.',
                'icon' => '👑',
                'requirements' => [
                    ['type' => 'comment_posted', 'target' => 100, 'points' => 1],
                    ['type' => 'thread_created', 'target' => 20, 'points' => 5],
                    ['type' => 'vote_cast', 'target' => 200, 'points' => 0.5],
                    ['type' => 'daily_login', 'target' => 25, 'points' => 2]
                ],
                'rewards' => [
                    'points' => 500,
                    'title' => 'Community Legend',
                    'badge' => 'legendary_engager',
                    'special_flair' => true
                ],
                'starts_at' => $now->copy()->addDays(7),
                'ends_at' => $now->copy()->addDays(37),
                'difficulty' => 'extreme',
                'max_participants' => 100
            ]
        ];

        foreach ($challenges as $challengeData) {
            Challenge::create($challengeData);
        }
    }

    private function createLeaderboards()
    {
        $leaderboards = [
            [
                'name' => 'Achievement Points',
                'slug' => 'achievement-points',
                'description' => 'Top users by total achievement points earned',
                'type' => 'points',
                'period' => 'all_time',
                'criteria' => [],
                'is_active' => true
            ],
            [
                'name' => 'Weekly Points',
                'slug' => 'weekly-points',
                'description' => 'Top achievement point earners this week',
                'type' => 'points',
                'period' => 'weekly',
                'criteria' => [],
                'is_active' => true,
                'reset_at' => Carbon::now()->addWeek()->startOfWeek()
            ],
            [
                'name' => 'Achievement Count',
                'slug' => 'achievement-count',
                'description' => 'Users with the most achievements unlocked',
                'type' => 'achievements',
                'period' => 'all_time',
                'criteria' => [],
                'is_active' => true
            ],
            [
                'name' => 'Login Streaks',
                'slug' => 'login-streaks',
                'description' => 'Longest current login streaks',
                'type' => 'streak',
                'period' => 'all_time',
                'criteria' => ['streak_type' => 'login'],
                'is_active' => true
            ],
            [
                'name' => 'Community Activity',
                'slug' => 'community-activity',
                'description' => 'Most active community members this month',
                'type' => 'activity',
                'period' => 'monthly',
                'criteria' => ['activity_types' => ['comment_posted', 'thread_created', 'vote_cast']],
                'is_active' => true,
                'reset_at' => Carbon::now()->addMonth()->startOfMonth()
            ]
        ];

        foreach ($leaderboards as $leaderboardData) {
            Leaderboard::create($leaderboardData);
        }
    }
}