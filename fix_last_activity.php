<?php

// Fix all last_activity column references in AdminUsersController
$file = '/var/www/mrvl-backend/app/Http/Controllers/Admin/AdminUsersController.php';
$content = file_get_contents($file);

// Add column check helper at the beginning of generateReport method
$search = "'active_users_period' => User::whereBetween('last_activity', [\$dateFrom, \$dateTo])->count(),";
$replace = "'active_users_period' => Schema::hasColumn('users', 'last_activity') ? 
                            User::whereBetween('last_activity', [\$dateFrom, \$dateTo])->count() : 0,";
$content = str_replace($search, $replace, $content);

// Fix top_active_users
$search = "'top_active_users' => User::where('last_activity', '>=', \$dateFrom)
                                                  ->orderBy('last_activity', 'desc')
                                                  ->limit(10)
                                                  ->select(['id', 'name', 'last_activity'])
                                                  ->get()";
$replace = "'top_active_users' => Schema::hasColumn('users', 'last_activity') ?
                            User::where('last_activity', '>=', \$dateFrom)
                                ->orderBy('last_activity', 'desc')
                                ->limit(10)
                                ->select(['id', 'name', 'last_activity'])
                                ->get() : collect()";
$content = str_replace($search, $replace, $content);

// Fix inactive_accounts
$search = "'inactive_accounts' => User::where('last_activity', '<', Carbon::now()->subMonths(6))->count(),";
$replace = "'inactive_accounts' => Schema::hasColumn('users', 'last_activity') ?
                            User::where('last_activity', '<', Carbon::now()->subMonths(6))->count() : 0,";
$content = str_replace($search, $replace, $content);

// Fix engagement_metrics
$search = "'daily_active_users' => User::where('last_activity', '>', Carbon::now()->subDay())->count(),
                        'weekly_active_users' => User::where('last_activity', '>', Carbon::now()->subWeek())->count(),
                        'monthly_active_users' => User::where('last_activity', '>', Carbon::now()->subMonth())->count()";
$replace = "'daily_active_users' => Schema::hasColumn('users', 'last_activity') ?
                            User::where('last_activity', '>', Carbon::now()->subDay())->count() : 0,
                        'weekly_active_users' => Schema::hasColumn('users', 'last_activity') ?
                            User::where('last_activity', '>', Carbon::now()->subWeek())->count() : 0,
                        'monthly_active_users' => Schema::hasColumn('users', 'last_activity') ?
                            User::where('last_activity', '>', Carbon::now()->subMonth())->count() : 0";
$content = str_replace($search, $replace, $content);

// Fix new_registrations and active_users
$search = "'active_users' => User::where('last_activity', '>=', \$startDate)->count(),";
$replace = "'active_users' => Schema::hasColumn('users', 'last_activity') ?
                    User::where('last_activity', '>=', \$startDate)->count() : 0,";
$content = str_replace($search, $replace, $content);

file_put_contents($file, $content);
echo "Fixed all last_activity references\n";