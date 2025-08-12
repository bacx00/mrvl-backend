# Comprehensive Tournament System Documentation

## Overview

This document describes the comprehensive tournament system for the Marvel Rivals esports platform. The system implements all major tournament formats used in competitive gaming, following Liquipedia naming conventions and industry best practices.

## Tournament Formats Supported

### 1. Single Elimination
- **Description**: Standard bracket where teams are eliminated after one loss
- **Use Case**: Quick tournaments, qualifiers, time-limited events
- **Structure**: Power-of-2 bracket with proper seeding
- **Advancement**: Winner advances, loser eliminated
- **Seeding**: Rating-based with 1 vs lowest, 2 vs second-lowest pattern

### 2. Double Elimination
- **Description**: Teams have two lives - eliminated only after two losses
- **Use Case**: Major tournaments, championships where fairness is priority
- **Structure**: Upper bracket + Lower bracket + Grand Final
- **Advancement**: Winners stay in upper, losers drop to lower
- **Special Rules**: Bracket reset in grand final if lower bracket team wins
- **Seeding**: Same as single elimination for upper bracket

### 3. Swiss System
- **Description**: Point-based system where teams are paired by record
- **Use Case**: Large open tournaments, qualifying rounds
- **Structure**: Multiple rounds with dynamic pairing
- **Advancement**: Based on wins/losses and tiebreakers (Buchholz score)
- **Seeding**: Random for first round, then by record
- **Qualification**: Typically X wins qualifies, Y losses eliminates

### 4. Round Robin
- **Description**: Every team plays every other team once
- **Use Case**: Small group tournaments, league play
- **Structure**: Complete graph of matches
- **Advancement**: Point-based standings (3 wins, 1 tie, 0 loss)
- **Seeding**: Balanced for fair scheduling
- **Tiebreakers**: Head-to-head, map differential, round differential

### 5. Group Stage + Playoffs
- **Description**: Multiple round robin groups feeding into elimination playoffs
- **Use Case**: World championships, major tournaments
- **Structure**: Groups of 4 teams → Top 2 advance → Single/Double elimination
- **Advancement**: Group standings determine playoff seeding
- **Seeding**: Strong teams distributed across groups

### 6. GSL Format
- **Description**: Groups with dual-elimination mini-brackets
- **Use Case**: StarCraft-style tournaments, unique group dynamics
- **Structure**: Winners bracket + Losers bracket within each group
- **Advancement**: 2 teams qualify from each group (1 from winners, 1 from losers)
- **Seeding**: Rating-based within groups

## Key Components

### Models

#### Tournament
- **Primary Model**: Contains tournament metadata and settings
- **Key Fields**: format, status, bracket_data, seeding_data, qualification_settings
- **Relationships**: teams (many-to-many), phases, bracket stages, matches

#### BracketStage
- **Purpose**: Represents a stage of competition (group, upper bracket, etc.)
- **Key Fields**: type, stage_order, max_teams, current_round, total_rounds
- **Types**: single_elimination, upper_bracket, lower_bracket, swiss, round_robin, group_stage, gsl_winners, gsl_losers, grand_final

#### BracketMatch
- **Purpose**: Individual matches within tournaments
- **Key Fields**: team1_id, team2_id, winner_team_id, round_number, match_format
- **Status**: pending, ready, ongoing, completed, cancelled

#### BracketPosition
- **Purpose**: Defines bracket structure and advancement rules
- **Key Fields**: round, position, team_id, advancement_rule

### Services

#### ComprehensiveTournamentGenerator
- **Purpose**: Creates complete tournaments with all bracket structures
- **Key Methods**:
  - `createCompleteTournament()`: Main entry point
  - `generateTournamentSeeding()`: Applies seeding algorithms
  - `createBracketStructure()`: Creates bracket stages and positions
  - `generateInitialMatches()`: Creates first round matches

#### BracketProgressionService
- **Purpose**: Handles match completion and bracket advancement
- **Key Methods**:
  - `processMatchCompletion()`: Main match completion handler
  - `handleFormatSpecificProgression()`: Format-specific advancement logic
  - `checkTournamentCompletion()`: Determines if tournament is complete

#### SeedingService
- **Purpose**: Implements various seeding algorithms
- **Algorithms**: Rating-based, random, balanced distribution, snake seeding

## Tournament Creation Process

### 1. Configuration
```php
$config = [
    'name' => 'Tournament Name',
    'format' => 'double_elimination', // or any supported format
    'teams' => [...], // Array of team data
    'prize_pool' => 100000,
    'settings' => [...], // Format-specific settings
    'match_formats' => [...], // BO1, BO3, BO5 settings
    'map_pool' => [...], // Available maps
];
```

### 2. Tournament Generation
```php
$generator = new ComprehensiveTournamentGenerator();
$tournament = $generator->createCompleteTournament($config);
```

### 3. Generated Structure
- Tournament record with metadata
- Bracket stages for each competition phase
- Bracket positions defining structure
- Initial matches for first round
- Team registrations with seeding
- Tournament phases for progression tracking

## Seeding Algorithms

### Standard Tournament Seeding
1. Sort teams by rating/ranking
2. Apply bracket seeding pattern: 1v16, 8v9, 5v12, 4v13, 6v11, 3v14, 7v10, 2v15
3. Ensures strong teams don't meet until later rounds

### Swiss System Seeding
1. First round: Random or rating-based pairing
2. Subsequent rounds: Pair teams with similar records
3. Avoid repeat pairings when possible
4. Implement color balancing for competitive integrity

### Group Stage Seeding
1. Distribute strong teams across groups (snake seeding)
2. Consider regional distribution if applicable
3. Avoid putting teams from same organization in same group

## Match Progression Logic

### Single Elimination
1. Winner advances to next round match
2. Loser is eliminated from tournament
3. Match position determines next match placement

### Double Elimination
1. **Upper Bracket**: Winner advances, loser drops to lower bracket
2. **Lower Bracket**: Winner advances, loser eliminated
3. **Grand Final**: Lower bracket winner must win twice (bracket reset)

### Swiss System
1. Update team records (wins/losses/points)
2. Calculate tiebreaker scores (Buchholz)
3. Generate next round pairings if round complete
4. Check qualification/elimination thresholds

### Round Robin
1. Update team standings with points (3-1-0 system)
2. Calculate tiebreakers (head-to-head, map differential)
3. Track completion of all matches

## Bracket Reset Logic

In double elimination grand finals:
- If upper bracket team wins: Tournament complete
- If lower bracket team wins: Create bracket reset match
- Both teams start the reset match on equal terms
- Winner of reset match wins tournament

## Tiebreaker Systems

### Swiss System Tiebreakers
1. **Buchholz Score**: Sum of opponents' scores
2. **Sonneborn-Berger**: Weighted opponent scores
3. **Direct Comparison**: Head-to-head results
4. **Rating**: Pre-tournament rating

### Round Robin Tiebreakers
1. **Points**: Standard 3-1-0 point system
2. **Head-to-Head**: Direct matchup results
3. **Map Differential**: Maps won minus maps lost
4. **Round Differential**: Rounds won minus rounds lost

## Usage Examples

### Creating Different Tournament Types

```php
// Single Elimination Championship
$tournament = $generator->createCompleteTournament([
    'name' => 'Spring Championship',
    'format' => 'single_elimination',
    'teams' => $teams, // 16 teams
    'prize_pool' => 50000
]);

// Swiss System Open
$tournament = $generator->createCompleteTournament([
    'name' => 'Open Tournament',
    'format' => 'swiss',
    'teams' => $teams, // 64 teams
    'settings' => [
        'rounds' => 6,
        'wins_to_qualify' => 4,
        'losses_to_eliminate' => 3
    ]
]);

// GSL Format Tournament
$tournament = $generator->createCompleteTournament([
    'name' => 'GSL Season 1',
    'format' => 'gsl',
    'teams' => $teams, // 16 teams
    'gsl_group_size' => 4
]);
```

### Processing Match Results

```php
$progressionService = new BracketProgressionService();

// Complete a match
$match->update([
    'team1_score' => 2,
    'team2_score' => 1,
    'status' => 'completed'
]);

// Process bracket advancement
$progressionService->processMatchCompletion($match);
```

## Tournament Script Usage

Run the comprehensive tournament creation script:

```bash
# Create all tournament formats
php create_comprehensive_tournament.php all

# Create specific format
php create_comprehensive_tournament.php double
php create_comprehensive_tournament.php swiss
php create_comprehensive_tournament.php gsl

# List existing tournaments
php create_comprehensive_tournament.php list
```

## Database Schema

### Tournament Teams Pivot Table
```sql
tournament_teams:
- tournament_id
- team_id
- seed (seeding position)
- status (registered, checked_in, qualified, eliminated, winner)
- swiss_wins, swiss_losses, swiss_score, swiss_buchholz
- group_id (for group stages)
- bracket_position
- elimination_round
- placement (final ranking)
- points_earned
```

### Match Results
```sql
bracket_matches:
- tournament_id
- bracket_stage_id
- round_number
- match_number
- team1_id, team2_id
- team1_score, team2_score
- winner_team_id, loser_team_id
- status
- match_format (bo1, bo3, bo5)
- scheduled_at, completed_at
```

## Marvel Rivals Specific Features

### Map Pool
- 10 official Marvel Rivals maps
- Format-specific map selection rules
- Veto systems for different match formats

### Match Formats
- **BO1**: Swiss rounds, time-limited matches
- **BO3**: Standard competitive format
- **BO5**: Semifinals, finals, important matches
- **BO7**: Grand finals (optional)

### Hero Selection Rules
- No duplicate heroes within same team
- Standard Marvel Rivals competitive ruleset
- Pause and remake procedures

## Performance Considerations

### Caching
- Tournament bracket data cached for fast loading
- Standings calculations cached and invalidated on match completion
- Database queries optimized with proper indexing

### Scalability
- System handles tournaments from 4 to 1000+ teams
- Swiss system scales linearly with team count
- Match generation uses efficient algorithms

### Real-time Updates
- WebSocket integration for live bracket updates
- Event broadcasting for match completions
- Tournament progression notifications

## Error Handling

### Tournament Creation
- Validates team count against format requirements
- Checks for duplicate teams
- Ensures proper seeding data

### Match Progression
- Validates match results before advancing teams
- Handles edge cases (draws, forfeits, disqualifications)
- Maintains bracket integrity

### Data Consistency
- Transactional match completion processing
- Rollback capabilities for failed operations
- Audit logging for all tournament actions

## Future Enhancements

### Planned Features
1. **Multi-Stage Tournaments**: Qualifiers → Groups → Playoffs
2. **Regional Qualification**: Separate regional brackets feeding into global tournament
3. **Live Scoring Integration**: Real-time match data from game servers
4. **Advanced Analytics**: Detailed performance metrics and statistics
5. **Tournament Templates**: Pre-configured tournament formats for common use cases

### API Extensions
1. **Public Tournament API**: Allow third-party integrations
2. **Bracket Visualization**: JSON data for bracket rendering
3. **Statistics API**: Tournament and player performance data
4. **Live Updates**: WebSocket endpoints for real-time data

This comprehensive tournament system provides a robust foundation for hosting professional Marvel Rivals esports tournaments while maintaining competitive integrity and following established esports tournament standards.