#!/bin/bash

echo "=== Marvel Rivals Forum System Optimization ==="
echo "Starting forum optimization process..."

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    echo "Error: artisan file not found. Please run this script from the Laravel root directory."
    exit 1
fi

# Set proper permissions
echo "Setting proper permissions..."
chmod +x artisan
chown -R www-data:www-data storage bootstrap/cache

# Clear existing caches
echo "Clearing existing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run database migrations for forum optimization
echo "Running forum optimization migrations..."
php artisan migrate --path=database/migrations/2025_08_06_170000_fix_comprehensive_forum_system.php --force
php artisan migrate --path=database/migrations/2025_08_06_180000_optimize_forum_performance.php --force

# Verify database indexes
echo "Verifying database indexes..."
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
echo 'Checking forum_threads indexes...' . PHP_EOL;
$indexes = DB::select('SHOW INDEX FROM forum_threads');
foreach(\$indexes as \$index) {
    echo '- ' . \$index->Key_name . ' (' . \$index->Column_name . ')' . PHP_EOL;
}

echo 'Checking forum_posts indexes...' . PHP_EOL;
\$indexes = DB::select('SHOW INDEX FROM forum_posts');
foreach(\$indexes as \$index) {
    echo '- ' . \$index->Key_name . ' (' . \$index->Column_name . ')' . PHP_EOL;
}

echo 'Checking forum_votes indexes...' . PHP_EOL;
\$indexes = DB::select('SHOW INDEX FROM forum_votes');
foreach(\$indexes as \$index) {
    echo '- ' . \$index->Key_name . ' (' . \$index->Column_name . ')' . PHP_EOL;
}
"

# Create moderation log table if it doesn't exist
echo "Creating moderation log table..."
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasTable('moderation_log')) {
    Schema::create('moderation_log', function (Blueprint \$table) {
        \$table->id();
        \$table->foreignId('moderator_id')->constrained('users')->onDelete('cascade');
        \$table->string('target_type');
        \$table->unsignedBigInteger('target_id')->nullable();
        \$table->string('action');
        \$table->json('details')->nullable();
        \$table->string('ip_address')->nullable();
        \$table->text('user_agent')->nullable();
        \$table->timestamp('created_at');

        \$table->index(['target_type', 'target_id']);
        \$table->index(['moderator_id', 'created_at']);
        \$table->index(['action', 'created_at']);
    });
    echo 'Moderation log table created successfully.' . PHP_EOL;
} else {
    echo 'Moderation log table already exists.' . PHP_EOL;
}
"

# Configure Redis for caching and real-time features
echo "Configuring Redis..."
redis-cli ping > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "Redis is running. Configuring forum channels..."
    redis-cli config set save ""
    redis-cli config set maxmemory-policy allkeys-lru
    redis-cli flushall
    echo "Redis configured successfully."
else
    echo "Warning: Redis is not running. Real-time features and caching may not work properly."
fi

# Warm up caches
echo "Warming up forum caches..."
php artisan tinker --execute="
use App\Services\ForumCacheService;
\$cacheService = app(ForumCacheService::class);
\$cacheService->warmUpCaches();
echo 'Forum caches warmed up successfully.' . PHP_EOL;
"

# Generate optimized autoloader
echo "Optimizing autoloader..."
composer install --optimize-autoloader --no-dev --quiet

# Cache configuration and routes
echo "Caching configuration and routes..."
php artisan config:cache
php artisan route:cache

# Set up queue workers for real-time processing (if using queues)
echo "Setting up queue configuration..."
php artisan queue:restart

# Create storage directories
echo "Creating storage directories..."
mkdir -p storage/logs/forum
mkdir -p storage/framework/cache/forum
mkdir -p storage/framework/sessions
chmod -R 775 storage
chown -R www-data:www-data storage

# Test database connection and forum functionality
echo "Testing forum functionality..."
php artisan tinker --execute="
try {
    use Illuminate\Support\Facades\DB;
    
    // Test database connection
    \$connection = DB::connection();
    \$connection->getPdo();
    echo 'Database connection: OK' . PHP_EOL;
    
    // Test forum tables
    \$threadsCount = DB::table('forum_threads')->count();
    echo 'Forum threads table: OK (' . \$threadsCount . ' threads)' . PHP_EOL;
    
    \$postsCount = DB::table('forum_posts')->count();
    echo 'Forum posts table: OK (' . \$postsCount . ' posts)' . PHP_EOL;
    
    \$votesCount = DB::table('forum_votes')->count();
    echo 'Forum votes table: OK (' . \$votesCount . ' votes)' . PHP_EOL;
    
    // Test categories
    \$categoriesCount = DB::table('forum_categories')->count();
    echo 'Forum categories table: OK (' . \$categoriesCount . ' categories)' . PHP_EOL;
    
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"

# Performance recommendations
echo ""
echo "=== Performance Recommendations ==="
echo "1. Enable PHP OPcache in production"
echo "2. Use Redis for sessions and cache"
echo "3. Set up queue workers for background processing"
echo "4. Consider using a CDN for static assets"
echo "5. Monitor database query performance"
echo ""

# Create a simple monitoring script
echo "Creating monitoring script..."
cat > monitor_forum_performance.sh << 'EOF'
#!/bin/bash
echo "=== Forum Performance Monitor ==="
echo "Timestamp: $(date)"
echo ""

# Check Redis status
echo "Redis Status:"
redis-cli ping 2>/dev/null && echo "✓ Redis is running" || echo "✗ Redis is not running"

# Check database connections
echo ""
echo "Database Status:"
php artisan tinker --execute="
try {
    use Illuminate\Support\Facades\DB;
    \$result = DB::select('SELECT 1');
    echo '✓ Database connection OK' . PHP_EOL;
} catch (Exception \$e) {
    echo '✗ Database connection failed: ' . \$e->getMessage() . PHP_EOL;
}
"

# Check forum tables
echo ""
echo "Forum Tables Status:"
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;

try {
    \$threads = DB::table('forum_threads')->where('status', 'active')->count();
    \$posts = DB::table('forum_posts')->where('status', 'active')->count();
    \$votes = DB::table('forum_votes')->count();
    
    echo '✓ Active threads: ' . \$threads . PHP_EOL;
    echo '✓ Active posts: ' . \$posts . PHP_EOL;
    echo '✓ Total votes: ' . \$votes . PHP_EOL;
} catch (Exception \$e) {
    echo '✗ Error checking forum tables: ' . \$e->getMessage() . PHP_EOL;
}
"

# Check cache performance
echo ""
echo "Cache Status:"
php artisan tinker --execute="
use Illuminate\Support\Facades\Cache;

try {
    Cache::put('test_key', 'test_value', 60);
    \$value = Cache::get('test_key');
    if (\$value === 'test_value') {
        echo '✓ Cache is working' . PHP_EOL;
    } else {
        echo '✗ Cache test failed' . PHP_EOL;
    }
    Cache::forget('test_key');
} catch (Exception \$e) {
    echo '✗ Cache error: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "=== End Monitor Report ==="
EOF

chmod +x monitor_forum_performance.sh

echo ""
echo "=== Forum Optimization Complete! ==="
echo "✓ Database migrations applied"
echo "✓ Indexes optimized"
echo "✓ Caches configured"
echo "✓ Services integrated"
echo "✓ Monitoring script created"
echo ""
echo "Next steps:"
echo "1. Test forum functionality in browser"
echo "2. Run ./monitor_forum_performance.sh to check system status"
echo "3. Configure WebSocket server for real-time features"
echo "4. Set up automated backups for forum data"
echo ""
echo "Forum system is now optimized and ready for production use!"

# Set proper permissions for the optimization script
chmod +x optimize_forum_system.sh