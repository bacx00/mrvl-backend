<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, check if tournament_registrations table exists
        if (!Schema::hasTable('tournament_registrations')) {
            echo "Tournament registrations table doesn't exist yet, skipping...\n";
            return;
        }

        // Create a system user for tournament registrations if it doesn't exist
        $systemUser = User::firstOrCreate(
            ['email' => 'system@tournament.internal'],
            [
                'name' => 'Tournament System',
                'password' => bcrypt('system_tournament_' . now()->timestamp),
                'role' => 'admin',
                'email_verified_at' => now()
            ]
        );

        echo "System user created/found: {$systemUser->name} (ID: {$systemUser->id})\n";

        // Check if user_id column exists and is not nullable
        $columns = Schema::getColumnListing('tournament_registrations');
        if (in_array('user_id', $columns)) {
            // Check if there are any NULL user_id records
            $nullUserIds = DB::table('tournament_registrations')
                ->whereNull('user_id')
                ->count();
            
            if ($nullUserIds > 0) {
                echo "Found {$nullUserIds} records with NULL user_id, updating...\n";
                
                // Update NULL user_id records to use system user
                DB::table('tournament_registrations')
                    ->whereNull('user_id')
                    ->update(['user_id' => $systemUser->id]);
                
                echo "Updated {$nullUserIds} records to use system user\n";
            }

            // Make the user_id column nullable for future registrations
            Schema::table('tournament_registrations', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });
            
            echo "Made user_id column nullable in tournament_registrations table\n";
        }

        // Add a default system user fallback setting to the database
        DB::table('settings')->updateOrInsert(
            ['key' => 'tournament_system_user_id'],
            ['value' => $systemUser->id, 'updated_at' => now()]
        );

        echo "Tournament registration schema fix completed successfully\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Make user_id required again (but don't delete system user as it might be in use)
        if (Schema::hasTable('tournament_registrations')) {
            Schema::table('tournament_registrations', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
            });
        }

        // Remove system setting
        DB::table('settings')->where('key', 'tournament_system_user_id')->delete();
    }
};