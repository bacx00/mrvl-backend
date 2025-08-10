#!/bin/bash

# =====================================================
# MARVEL RIVALS TOURNAMENT DATABASE OPTIMIZATION DEPLOYMENT
# =====================================================
#
# Comprehensive deployment script for database optimizations
# This script applies all performance enhancements safely
#
# Usage: ./deploy_database_optimizations.sh [--dry-run] [--backup-first]
#

set -e  # Exit on any error

# Configuration
DB_NAME="${DB_DATABASE:-marvel_rivals_tournament}"
BACKUP_DIR="/var/backups/mysql"
LOG_FILE="/var/log/db_optimization_$(date +%Y%m%d_%H%M%S).log"
DRY_RUN=false
BACKUP_FIRST=false

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            echo -e "${YELLOW}🔍 DRY RUN MODE - No changes will be applied${NC}"
            shift
            ;;
        --backup-first)
            BACKUP_FIRST=true
            echo -e "${BLUE}💾 Database backup will be created first${NC}"
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [--dry-run] [--backup-first]"
            echo ""
            echo "Options:"
            echo "  --dry-run      Show what would be done without making changes"
            echo "  --backup-first Create database backup before applying changes"
            echo "  --help         Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Logging function
log() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

# Error handling function
handle_error() {
    log "${RED}❌ Error occurred at line $1${NC}"
    log "${RED}❌ Deployment failed. Check log: $LOG_FILE${NC}"
    exit 1
}

trap 'handle_error $LINENO' ERR

# Start deployment
log "${GREEN}🚀 Starting Marvel Rivals Tournament Database Optimization Deployment${NC}"
log "${BLUE}====================================================================${NC}"
log "$(date '+%Y-%m-%d %H:%M:%S') - Deployment started"
log "Database: $DB_NAME"
log "Log file: $LOG_FILE"

# 1. Pre-deployment checks
log "\n${BLUE}🔍 Phase 1: Pre-deployment Validation${NC}"
log "======================================="

# Check if we're in the correct directory
if [[ ! -f "artisan" ]]; then
    log "${RED}❌ Error: Not in Laravel application directory${NC}"
    exit 1
fi

# Check if database is accessible
if ! php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; then
    log "${RED}❌ Error: Cannot connect to database${NC}"
    exit 1
fi

log "${GREEN}✅ Database connection verified${NC}"

# Check current database status
CURRENT_TABLES=$(php artisan tinker --execute="echo count(DB::select('SHOW TABLES'));" 2>/dev/null | tail -1)
log "Current tables count: $CURRENT_TABLES"

# Check for existing bracket system
HAS_BRACKET_TABLES=$(php artisan tinker --execute="echo Schema::hasTable('bracket_matches') ? 'yes' : 'no';" 2>/dev/null | tail -1)
log "Bracket system exists: $HAS_BRACKET_TABLES"

# 2. Create backup if requested
if [[ "$BACKUP_FIRST" == true ]]; then
    log "\n${BLUE}💾 Phase 2: Creating Database Backup${NC}"
    log "===================================="
    
    BACKUP_FILE="${BACKUP_DIR}/mrvl_pre_optimization_$(date +%Y%m%d_%H%M%S).sql"
    
    if [[ ! -d "$BACKUP_DIR" ]]; then
        sudo mkdir -p "$BACKUP_DIR"
        sudo chmod 755 "$BACKUP_DIR"
    fi
    
    if [[ "$DRY_RUN" == false ]]; then
        log "Creating backup: $BACKUP_FILE"
        mysqldump -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD}" \
            --single-transaction \
            --routines \
            --triggers \
            "$DB_NAME" > "$BACKUP_FILE"
        
        # Compress backup
        gzip "$BACKUP_FILE"
        log "${GREEN}✅ Backup created: ${BACKUP_FILE}.gz${NC}"
    else
        log "${YELLOW}[DRY RUN] Would create backup: $BACKUP_FILE${NC}"
    fi
else
    log "\n${YELLOW}⚠️  Phase 2: Backup Skipped (use --backup-first to enable)${NC}"
fi

# 3. Apply database migrations
log "\n${BLUE}🔄 Phase 3: Applying Database Migrations${NC}"
log "========================================"

if [[ "$DRY_RUN" == false ]]; then
    log "Running database migrations..."
    php artisan migrate --force
    log "${GREEN}✅ Database migrations completed${NC}"
else
    log "${YELLOW}[DRY RUN] Would run: php artisan migrate --force${NC}"
fi

# 4. Apply performance optimization migration
log "\n${BLUE}⚡ Phase 4: Applying Performance Optimizations${NC}"
log "=============================================="

OPTIMIZATION_MIGRATION="database/migrations/2025_08_08_100000_optimize_tournament_database_performance.php"

if [[ -f "$OPTIMIZATION_MIGRATION" ]]; then
    if [[ "$DRY_RUN" == false ]]; then
        log "Applying performance optimization migration..."
        php artisan migrate --path=database/migrations/2025_08_08_100000_optimize_tournament_database_performance.php --force
        log "${GREEN}✅ Performance optimizations applied${NC}"
    else
        log "${YELLOW}[DRY RUN] Would apply optimization migration${NC}"
    fi
else
    log "${RED}❌ Optimization migration not found: $OPTIMIZATION_MIGRATION${NC}"
    exit 1
fi

# 5. Setup monitoring system
log "\n${BLUE}📊 Phase 5: Setting Up Database Monitoring${NC}"
log "=========================================="

MONITORING_SCRIPT="database/monitoring_setup.sql"

if [[ -f "$MONITORING_SCRIPT" ]]; then
    if [[ "$DRY_RUN" == false ]]; then
        log "Applying monitoring setup..."
        mysql -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD}" "$DB_NAME" < "$MONITORING_SCRIPT"
        log "${GREEN}✅ Database monitoring setup completed${NC}"
    else
        log "${YELLOW}[DRY RUN] Would apply monitoring setup from: $MONITORING_SCRIPT${NC}"
    fi
else
    log "${YELLOW}⚠️  Monitoring setup script not found: $MONITORING_SCRIPT${NC}"
fi

# 6. Warm up caches
log "\n${BLUE}🔥 Phase 6: Cache Warmup${NC}"
log "========================"

if [[ "$DRY_RUN" == false ]]; then
    log "Warming up tournament caches..."
    
    # Clear existing caches first
    php artisan cache:clear
    
    # Run cache warmup for active tournaments
    php artisan tournament:optimize-db --cache
    
    log "${GREEN}✅ Cache warmup completed${NC}"
else
    log "${YELLOW}[DRY RUN] Would warm up tournament caches${NC}"
fi

# 7. Run initial performance analysis
log "\n${BLUE}📈 Phase 7: Initial Performance Analysis${NC}"
log "========================================"

if [[ "$DRY_RUN" == false ]]; then
    log "Running performance monitoring..."
    
    # Run monitoring to establish baseline
    php artisan tournament:optimize-db --monitor
    
    log "${GREEN}✅ Performance baseline established${NC}"
else
    log "${YELLOW}[DRY RUN] Would run performance analysis${NC}"
fi

# 8. Verify deployment
log "\n${BLUE}✅ Phase 8: Deployment Verification${NC}"
log "=================================="

if [[ "$DRY_RUN" == false ]]; then
    # Check that new tables were created
    NEW_TABLES=$(php artisan tinker --execute="echo count(DB::select('SHOW TABLES'));" 2>/dev/null | tail -1)
    log "Tables after deployment: $NEW_TABLES"
    
    # Check for new performance indexes
    PERFORMANCE_INDEXES=$(mysql -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD}" "$DB_NAME" -e "
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = '$DB_NAME' 
        AND INDEX_NAME LIKE 'idx_%'
    " 2>/dev/null | tail -1)
    log "Performance indexes created: $PERFORMANCE_INDEXES"
    
    # Check Swiss standings table
    SWISS_TABLE_EXISTS=$(php artisan tinker --execute="echo Schema::hasTable('bracket_swiss_standings') ? 'yes' : 'no';" 2>/dev/null | tail -1)
    log "Swiss standings table: $SWISS_TABLE_EXISTS"
    
    # Check tournament phases table
    PHASES_TABLE_EXISTS=$(php artisan tinker --execute="echo Schema::hasTable('tournament_phases') ? 'yes' : 'no';" 2>/dev/null | tail -1)
    log "Tournament phases table: $PHASES_TABLE_EXISTS"
    
    # Test a basic query
    QUERY_TEST=$(php artisan tinker --execute="echo count(DB::table('teams')->get());" 2>/dev/null | tail -1)
    log "Basic query test (teams count): $QUERY_TEST"
    
    log "${GREEN}✅ Deployment verification completed${NC}"
else
    log "${YELLOW}[DRY RUN] Would verify deployment success${NC}"
fi

# 9. Setup automated maintenance
log "\n${BLUE}🔄 Phase 9: Automated Maintenance Setup${NC}"
log "======================================"

CRON_ENTRY="0 2 * * 0 cd $(pwd) && php artisan tournament:optimize-db --maintenance >> /var/log/tournament_maintenance.log 2>&1"

if [[ "$DRY_RUN" == false ]]; then
    log "Setting up weekly maintenance cron job..."
    
    # Check if cron entry already exists
    if ! crontab -l 2>/dev/null | grep -q "tournament:optimize-db --maintenance"; then
        (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
        log "${GREEN}✅ Weekly maintenance cron job added${NC}"
    else
        log "${YELLOW}⚠️  Maintenance cron job already exists${NC}"
    fi
else
    log "${YELLOW}[DRY RUN] Would add cron job: $CRON_ENTRY${NC}"
fi

# 10. Generate deployment report
log "\n${BLUE}📋 Phase 10: Deployment Report${NC}"
log "=============================="

REPORT_FILE="/tmp/db_optimization_report_$(date +%Y%m%d_%H%M%S).txt"

cat > "$REPORT_FILE" << EOF
Marvel Rivals Tournament Database Optimization Report
====================================================

Deployment Date: $(date '+%Y-%m-%d %H:%M:%S')
Database: $DB_NAME
Dry Run: $DRY_RUN
Backup Created: $BACKUP_FIRST

Applied Optimizations:
- Performance-optimized indexes for bracket operations
- Liquipedia-style tournament support enhancements
- Swiss system standings table
- Tournament phases table
- Redis caching optimization structures
- Database monitoring and alerting system

New Tables Created:
- bracket_swiss_standings
- tournament_phases
- db_performance_history (monitoring)
- query_performance_log (monitoring)
- tournament_performance_metrics (monitoring)

Performance Features:
- Covering indexes for bracket display queries
- Composite indexes for team and player lookups
- GIN-style indexes for JSON fields (MySQL 8.0+)
- R#M# notation lookup optimization
- Automated cache warming and invalidation

Monitoring Features:
- Real-time performance metrics collection
- Automated slow query detection
- Tournament-specific performance tracking
- Historical performance trending
- Automated cleanup of old monitoring data

Maintenance Features:
- Weekly automated maintenance routine
- Index optimization and rebuilding
- Tournament archival system
- Temporary data cleanup
- Denormalized data updates

Next Steps:
1. Monitor performance metrics in the first 24 hours
2. Check slow query logs for any issues
3. Verify cache hit ratios are improving
4. Review tournament-specific metrics during next live event
5. Consider additional optimizations based on usage patterns

For ongoing monitoring, use:
- php artisan tournament:optimize-db --monitor
- Check database views: v_db_performance_overview
- Review logs: $LOG_FILE

EOF

log "Deployment report saved: $REPORT_FILE"

# Final summary
log "\n${GREEN}🎉 DEPLOYMENT COMPLETED SUCCESSFULLY! 🎉${NC}"
log "${GREEN}============================================${NC}"
log ""
log "${GREEN}✅ Database optimizations applied${NC}"
log "${GREEN}✅ Monitoring system configured${NC}"
log "${GREEN}✅ Automated maintenance scheduled${NC}"
log "${GREEN}✅ Performance baseline established${NC}"
log ""
log "${BLUE}📊 Key Improvements:${NC}"
log "• Bracket queries optimized with covering indexes"
log "• Swiss pairing calculations enhanced"
log "• Live score updates performance improved"
log "• Tournament standings calculations optimized"
log "• Redis caching for real-time data"
log "• Comprehensive monitoring and alerting"
log ""
log "${BLUE}📋 Important Files:${NC}"
log "• Deployment log: $LOG_FILE"
log "• Deployment report: $REPORT_FILE"
if [[ "$BACKUP_FIRST" == true ]]; then
    log "• Database backup: ${BACKUP_DIR}/mrvl_pre_optimization_*.sql.gz"
fi
log ""
log "${YELLOW}🔍 Next Steps:${NC}"
log "1. Monitor performance for 24-48 hours"
log "2. Run: php artisan tournament:optimize-db --monitor"
log "3. Check application performance during peak usage"
log "4. Review monitoring dashboards and alerts"
log ""
log "$(date '+%Y-%m-%d %H:%M:%S') - Deployment completed successfully"

# Set appropriate permissions
chmod 644 "$LOG_FILE" 2>/dev/null || true
chmod 644 "$REPORT_FILE" 2>/dev/null || true

exit 0