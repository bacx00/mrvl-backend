# 🗄️ **DATABASE SCHEMA UPDATE - ADD MISSING COLUMNS**

## **ADD MISSING COLUMNS TO `matches` TABLE:**

```sql
-- Add live match state columns
ALTER TABLE matches ADD COLUMN current_timer VARCHAR(10) DEFAULT '0:00';
ALTER TABLE matches ADD COLUMN timer_running BOOLEAN DEFAULT FALSE;
ALTER TABLE matches ADD COLUMN live_start_time TIMESTAMP NULL DEFAULT NULL;

-- Add current map/mode columns  
ALTER TABLE matches ADD COLUMN current_map VARCHAR(100) DEFAULT 'Tokyo 2099: Shibuya Sky';
ALTER TABLE matches ADD COLUMN current_mode VARCHAR(50) DEFAULT 'Convoy';
ALTER TABLE matches ADD COLUMN current_map_index INT DEFAULT 0;

-- Add match completion columns
ALTER TABLE matches ADD COLUMN winning_team INT DEFAULT NULL;
ALTER TABLE matches ADD COLUMN final_score VARCHAR(20) DEFAULT NULL;
ALTER TABLE matches ADD COLUMN match_duration VARCHAR(20) DEFAULT NULL;
ALTER TABLE matches ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL;
```

## **EXECUTE ON MySQL:**

```bash
# Connect to MySQL
mysql -u root -p

# Use the correct database
USE your_database_name;

# Execute the ALTER statements
ALTER TABLE matches ADD COLUMN current_timer VARCHAR(10) DEFAULT '0:00';
ALTER TABLE matches ADD COLUMN timer_running BOOLEAN DEFAULT FALSE;
ALTER TABLE matches ADD COLUMN live_start_time TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE matches ADD COLUMN current_map VARCHAR(100) DEFAULT 'Tokyo 2099: Shibuya Sky';
ALTER TABLE matches ADD COLUMN current_mode VARCHAR(50) DEFAULT 'Convoy';
ALTER TABLE matches ADD COLUMN current_map_index INT DEFAULT 0;
ALTER TABLE matches ADD COLUMN winning_team INT DEFAULT NULL;
ALTER TABLE matches ADD COLUMN final_score VARCHAR(20) DEFAULT NULL;
ALTER TABLE matches ADD COLUMN match_duration VARCHAR(20) DEFAULT NULL;
ALTER TABLE matches ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL;

# Verify the changes
DESCRIBE matches;
```

## **LARAVEL MIGRATION (Alternative):**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLiveMatchColumnsToMatchesTable extends Migration
{
    public function up()
    {
        Schema::table('matches', function (Blueprint $table) {
            // Live match state
            $table->string('current_timer', 10)->default('0:00');
            $table->boolean('timer_running')->default(false);
            $table->timestamp('live_start_time')->nullable();
            
            // Current map/mode
            $table->string('current_map', 100)->default('Tokyo 2099: Shibuya Sky');
            $table->string('current_mode', 50)->default('Convoy');
            $table->integer('current_map_index')->default(0);
            
            // Match completion
            $table->integer('winning_team')->nullable();
            $table->string('final_score', 20)->nullable();
            $table->string('match_duration', 20)->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'current_timer', 'timer_running', 'live_start_time',
                'current_map', 'current_mode', 'current_map_index',
                'winning_team', 'final_score', 'match_duration', 'completed_at'
            ]);
        });
    }
}
```

## **QUICK EXECUTION STEPS:**

1. **Option A - Direct SQL:**
   ```bash
   mysql -u root -p your_database_name < schema_update.sql
   ```

2. **Option B - Laravel Migration:**
   ```bash
   php artisan make:migration add_live_match_columns_to_matches_table
   # Copy the migration code above
   php artisan migrate
   ```

3. **Option C - Laravel Tinker:**
   ```bash
   php artisan tinker
   ```
   ```php
   DB::statement("ALTER TABLE matches ADD COLUMN current_timer VARCHAR(10) DEFAULT '0:00'");
   DB::statement("ALTER TABLE matches ADD COLUMN timer_running BOOLEAN DEFAULT FALSE");
   DB::statement("ALTER TABLE matches ADD COLUMN live_start_time TIMESTAMP NULL DEFAULT NULL");
   DB::statement("ALTER TABLE matches ADD COLUMN current_map VARCHAR(100) DEFAULT 'Tokyo 2099: Shibuya Sky'");
   DB::statement("ALTER TABLE matches ADD COLUMN current_mode VARCHAR(50) DEFAULT 'Convoy'");
   DB::statement("ALTER TABLE matches ADD COLUMN current_map_index INT DEFAULT 0");
   DB::statement("ALTER TABLE matches ADD COLUMN winning_team INT DEFAULT NULL");
   DB::statement("ALTER TABLE matches ADD COLUMN final_score VARCHAR(20) DEFAULT NULL");
   DB::statement("ALTER TABLE matches ADD COLUMN match_duration VARCHAR(20) DEFAULT NULL");
   DB::statement("ALTER TABLE matches ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL");
   ```

## **VERIFY THE UPDATE:**
```sql
DESCRIBE matches;
SELECT COUNT(*) as total_columns FROM information_schema.columns WHERE table_name = 'matches';
```

**Expected**: Should show all new columns added to the matches table.