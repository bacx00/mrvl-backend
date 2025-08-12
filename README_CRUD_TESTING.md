# Team/Player CRUD Testing & Database Monitoring Suite

This comprehensive suite provides monitoring, testing, and cleanup tools for team/player CRUD operations with focus on database integrity, performance, and safe data management.

## Overview

The suite consists of five main components:

1. **Database Activity Monitor** - Real-time monitoring of CRUD operations
2. **Query Performance Analyzer** - Query optimization and performance analysis
3. **Test Data Cleanup** - Safe removal of test data while preserving integrity
4. **Database Integrity Validator** - Comprehensive database validation
5. **Comprehensive Testing Suite** - Integrated testing framework

## Components

### 1. Database Activity Monitor (`database_activity_monitor.php`)

Monitors all database operations during testing with real-time constraint validation.

**Features:**
- Real-time CRUD operation monitoring
- Foreign key constraint validation
- Orphaned record detection
- Performance metrics tracking
- Referential integrity checks

**Usage:**
```bash
# Start interactive monitoring
php database_activity_monitor.php start

# Test team operations
php database_activity_monitor.php test-team

# Test player operations
php database_activity_monitor.php test-player

# Validate database integrity
php database_activity_monitor.php validate

# Generate performance report
php database_activity_monitor.php report
```

### 2. Query Performance Analyzer (`query_performance_analyzer.php`)

Analyzes query performance and provides optimization recommendations.

**Features:**
- Query pattern analysis
- Slow query detection (configurable threshold)
- N+1 query detection
- Missing index identification
- Performance scoring
- Optimization recommendations

**Usage:**
```bash
# Start analysis with default 100ms threshold
php query_performance_analyzer.php

# Start with custom slow query threshold (200ms)
php query_performance_analyzer.php 200
```

### 3. Test Data Cleanup (`test_data_cleanup.php`)

Safely removes test data while maintaining referential integrity.

**Features:**
- Pattern-based test data identification
- Dependency checking before deletion
- Safe deletion order (histories → mentions → players → teams)
- Dry run mode for validation
- Post-cleanup integrity verification

**Usage:**
```bash
# Identify test data (dry run)
php test_data_cleanup.php identify

# Dry run cleanup (no actual deletions)
php test_data_cleanup.php cleanup

# Live cleanup (permanent deletions)
php test_data_cleanup.php cleanup --live

# Validate cleanup results
php test_data_cleanup.php validate
```

**Test Data Patterns:**
- Teams: `TEST_%`, `%_TEST_%`, `test-%`
- Players: `TEST_%`, `%_TEST_%`, `test_%`
- Mentions: Content containing `%TEST%`

### 4. Database Integrity Validator (`database_integrity_validator.php`)

Comprehensive database validation and integrity checking.

**Features:**
- Schema validation
- Foreign key constraint validation
- Orphaned record detection
- Data consistency checks
- Constraint violation detection
- Performance issue identification

**Usage:**
```bash
php database_integrity_validator.php
```

**Validation Categories:**
- **Foreign Key Violations** - Invalid references between tables
- **Orphaned Records** - Records without valid parent references
- **Data Inconsistencies** - Duplicate names, mismatched counts
- **Constraint Violations** - Unique constraints, data type issues
- **Schema Issues** - Missing tables/columns
- **Performance Issues** - Missing indexes, large tables

### 5. Comprehensive Testing Suite (`comprehensive_crud_testing_suite.php`)

Integrated testing framework that combines all components.

**Features:**
- Pre/post-test validation
- Comprehensive CRUD testing
- Relationship integrity testing
- Edge case validation
- Performance monitoring
- Automated cleanup
- Detailed reporting

**Usage:**
```bash
# Run full test suite with cleanup
php comprehensive_crud_testing_suite.php

# Run tests without cleanup
php comprehensive_crud_testing_suite.php --no-cleanup

# Show help
php comprehensive_crud_testing_suite.php --help
```

## Testing Workflow

### 1. Pre-Test Setup
```bash
# Validate initial database state
php database_integrity_validator.php

# Start monitoring (optional)
php database_activity_monitor.php start &
```

### 2. Execute Tests
```bash
# Run comprehensive test suite
php comprehensive_crud_testing_suite.php
```

### 3. Monitor Performance
```bash
# Analyze query performance during tests
php query_performance_analyzer.php
```

### 4. Post-Test Cleanup
```bash
# Clean up test data (if not done automatically)
php test_data_cleanup.php cleanup --live

# Validate cleanup
php test_data_cleanup.php validate
```

## Database Schema Requirements

The tools expect the following tables and relationships:

### Teams Table
- `id` (Primary Key)
- `name` (Required, should be unique)
- `slug`, `short_name`, `region`, `country`, etc.
- Timestamps: `created_at`, `updated_at`

### Players Table
- `id` (Primary Key)
- `name` (Required)
- `team_id` (Foreign Key to teams.id)
- `username`, `real_name`, `region`, etc.
- Timestamps: `created_at`, `updated_at`

### Mentions Table (Polymorphic)
- `id` (Primary Key)
- `mentioned_type` (Model class name)
- `mentioned_id` (Referenced entity ID)
- `mentioned_by` (User ID)
- `content`, `is_active`, etc.
- Timestamps: `mentioned_at`, `created_at`, `updated_at`

### Player Team Histories Table
- `id` (Primary Key)
- `player_id` (Foreign Key to players.id)
- `from_team_id` (Foreign Key to teams.id)
- `to_team_id` (Foreign Key to teams.id)
- `change_date`, `change_type`, etc.

## Configuration

### Environment Variables
- `DB_CONNECTION` - Database type (mysql, sqlite, etc.)
- `DB_HOST`, `DB_PORT`, `DB_DATABASE` - Connection details
- `DB_USERNAME`, `DB_PASSWORD` - Credentials

### Customizable Settings

**Performance Thresholds:**
- Slow query threshold: Default 100ms
- Large table threshold: Default 100,000 rows

**Test Patterns:** Modify `$testPatterns` in cleanup script to match your naming conventions.

**Validation Rules:** Customize `$validationRules` in integrity validator for your schema.

## Reports and Logs

All tools generate detailed logs and reports:

### Log Files
- `crud_testing_suite_{session_id}.log` - Main test suite log
- `test_monitoring_{session_id}.log` - Activity monitor log
- `query_performance_{session_id}.log` - Performance analyzer log
- `test_cleanup_{session_id}.log` - Cleanup operations log
- `integrity_validation_{session_id}.log` - Validation log

### Report Files
- `comprehensive_test_report_{session_id}.json` - Complete test results
- `performance_report_{session_id}.json` - Performance metrics
- `optimization_report_{session_id}.json` - Query optimization recommendations
- `cleanup_report_{session_id}.json` - Cleanup statistics
- `integrity_report_{session_id}.json` - Database validation results

## Safety Features

### Data Protection
- **Dry Run Mode**: All destructive operations default to dry run
- **Pattern Matching**: Only removes data matching specific test patterns
- **Dependency Checking**: Validates all relationships before deletion
- **Rollback Support**: Uses database transactions where possible

### Integrity Verification
- Pre/post operation validation
- Foreign key constraint checking
- Orphaned record detection
- Referential integrity verification

### Performance Monitoring
- Query execution time tracking
- Slow query identification
- Resource usage monitoring
- Optimization recommendations

## Troubleshooting

### Common Issues

**"Table doesn't exist" errors:**
- Ensure all required tables are created
- Check database connection settings
- Verify migrations have been run

**Foreign key constraint violations:**
- Check that referenced tables exist
- Verify foreign key columns are properly defined
- Ensure test data follows referential integrity rules

**Permission errors:**
- Verify database user has required permissions
- Check file system permissions for log files
- Ensure Laravel configuration is properly loaded

**Performance issues:**
- Use lower slow query thresholds for development
- Add indexes to foreign key columns
- Consider pagination for large result sets

### Debug Mode
Add debug logging by modifying the log level in any script:
```php
$this->log("Debug message", 'DEBUG');
```

## Best Practices

### Testing
1. Always run integrity validation before and after tests
2. Use dry run mode first to verify operations
3. Monitor performance during high-volume tests
4. Clean up test data regularly to prevent accumulation

### Production Usage
1. Never run cleanup scripts on production without extensive testing
2. Use read-only operations for monitoring in production
3. Set appropriate slow query thresholds for your environment
4. Regularly validate database integrity

### Performance
1. Add indexes to frequently queried columns
2. Monitor and optimize slow queries
3. Use pagination for large result sets
4. Consider archiving old test data

## Extension Points

The suite is designed to be extensible:

### Adding New Entity Types
1. Update test patterns in cleanup script
2. Add validation rules in integrity validator
3. Extend CRUD testing in comprehensive suite

### Custom Validation Rules
1. Extend `$validationRules` array
2. Add new validation methods
3. Update report generation

### Additional Monitoring
1. Extend activity monitor with new operations
2. Add custom performance metrics
3. Integrate with external monitoring tools

## Support

For issues or questions:
1. Check log files for detailed error information
2. Verify database schema matches expectations
3. Ensure all dependencies are properly installed
4. Test with minimal data first before scaling up