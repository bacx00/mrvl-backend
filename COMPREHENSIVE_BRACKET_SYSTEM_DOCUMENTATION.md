# Comprehensive Tournament Bracket System

## Overview

This is a complete tournament bracket system inspired by Liquipedia's professional tournament management capabilities. The system supports multiple tournament formats with advanced seeding algorithms, real-time updates, and comprehensive bracket visualization.

## Tournament Formats Supported

### 1. Single Elimination
- **Description**: Teams are eliminated after a single loss
- **Use Case**: Quick tournaments, time-constrained events
- **Features**: 
  - Automatic bye handling for non-power-of-2 team counts
  - Optional 3rd place playoff match
  - Optimal seeding to prevent early strong matchups
  - Bracket reset capability

### 2. Double Elimination
- **Description**: Teams have two lives - must lose twice to be eliminated
- **Use Case**: Competitive tournaments requiring fairness
- **Features**:
  - Upper bracket (winners stay)
  - Lower bracket (losers drop down)
  - Grand finals with optional bracket reset
  - Complex progression logic for fair elimination

### 3. Swiss System
- **Description**: Teams play multiple rounds against similarly skilled opponents
- **Use Case**: Large tournaments, qualification events
- **Features**:
  - Dynamic pairing based on current standings
  - Buchholz tiebreaker system
  - Avoids repeat matchups when possible
  - Qualification/elimination thresholds

### 4. Round Robin
- **Description**: Every team plays every other team once
- **Use Case**: Small tournaments, thorough competition
- **Features**:
  - Complete head-to-head matrix
  - Advanced tiebreaker calculations
  - Map differential tracking
  - Optimal scheduling algorithm

### 5. Group Stage + Playoffs
- **Description**: Teams divided into groups, top teams advance to playoffs
- **Use Case**: Large tournaments with regional divisions
- **Features**:
  - Multiple group configurations (2-8 groups)
  - Flexible advancement spots per group
  - Playoff bracket generation from group results
  - Regional seeding support

## Advanced Seeding Methods

### 1. Rating-Based Seeding
- Teams ranked by current rating/ELO
- Prevents early elimination of strong teams
- Supports rating decay over time

### 2. Performance-Based Seeding
- Recent match results weighted by recency
- Form-based rankings
- Adaptable performance windows (4-12 weeks)

### 3. Balanced Seeding
- Distributes team strength across bracket
- Prevents bracket imbalance
- Tier-based distribution algorithm

### 4. Regional Seeding
- Minimizes early regional conflicts
- Supports international tournaments
- Regional strength balancing

### 5. Manual/Custom Seeding
- Admin-controlled seed positions
- Tournament-specific requirements
- Historical seeding preservation

## Backend Architecture

### Controllers
- `BracketController.php` - Legacy bracket management
- `ComprehensiveBracketController.php` - Advanced bracket system
  - Format-agnostic bracket generation
  - Advanced seeding integration
  - Comprehensive match validation
  - Real-time bracket analysis

### Services
- `BracketGenerationService.php` - Tournament format implementations
- `BracketProgressionService.php` - Match completion and advancement
- `SeedingService.php` - Advanced seeding algorithms

### Models
- `Bracket.php` - Bracket metadata and structure
- `MatchModel.php` - Enhanced match tracking
- `Event.php` - Tournament event management

### Database Schema
```sql
-- Enhanced matches table with bracket support
matches:
  - id, event_id, team1_id, team2_id
  - round, bracket_position, bracket_type
  - team1_score, team2_score, status
  - format (bo1/bo3/bo5), scheduled_at, completed_at
  - maps_data (JSON), stream_url
  - forfeit, overtime, winner_by_forfeit

-- Event team participation with seeding
event_teams:
  - event_id, team_id, seed, group_id
  - registered_at, status

-- Comprehensive standings tracking
event_standings:
  - event_id, team_id, position, points
  - matches_played, matches_won, matches_lost
  - maps_won, maps_lost, map_differential
```

## Frontend Components

### Comprehensive Bracket Visualization
- `ComprehensiveBracketVisualization.js` - Main bracket component
- `BracketStyles.css` - Liquipedia-inspired styling
- Components:
  - `SingleEliminationBracket` - Clean elimination display
  - `DoubleEliminationBracket` - Upper/lower bracket layout
  - `SwissBracket` - Rounds + standings view
  - `RoundRobinBracket` - Matrix + standings
  - `GroupStageBracket` - Groups + playoff tree

### Admin Management
- `BracketManagementDashboard.js` - Tournament creation/management
- Features:
  - Real-time bracket generation
  - Format selection with validation
  - Advanced seeding options
  - Match management and scoring
  - Tournament progress tracking

## API Endpoints

### Public Access
```
GET /api/events/{eventId}/comprehensive-bracket
GET /api/events/{eventId}/bracket-analysis
```

### Admin Access (requires authentication)
```
POST /api/admin/events/{eventId}/comprehensive-bracket
PUT /api/admin/events/{eventId}/comprehensive-bracket/matches/{matchId}
POST /api/admin/events/{eventId}/swiss/next-round
```

## Key Features

### 1. Liquipedia-Style Design
- Professional tournament visualization
- Clean, readable bracket layout
- Team logos and seeding display
- Match status indicators (Live, Completed, Pending)
- Responsive design for all screen sizes

### 2. Advanced Match Management
- Multiple match formats (Bo1, Bo3, Bo5)
- Score validation based on format
- Forfeit and overtime support
- Map-by-map scoring
- Administrative controls

### 3. Real-Time Updates
- Live match scoring
- Automatic bracket progression
- Dynamic standings calculation
- Tournament status tracking

### 4. Competitive Integrity
- Validated bracket progression
- Anti-collision seeding
- Proper bye distribution
- Tiebreaker implementations
- Match scheduling optimization

### 5. Analytics and Insights
- Tournament format analysis
- Seeding effectiveness metrics
- Performance tracking
- Bracket integrity validation
- Progress estimation

## Tournament Creation Workflow

### 1. Event Setup
```javascript
// Create event with teams
const event = await api.post('/admin/events', {
  name: 'Tournament Name',
  format: 'double_elimination',
  max_teams: 16
});

// Add teams to event
await api.post(`/admin/events/${event.id}/teams/${teamId}`);
```

### 2. Bracket Generation
```javascript
// Generate comprehensive bracket
const bracket = await api.post(`/admin/events/${eventId}/comprehensive-bracket`, {
  format: 'double_elimination',
  seeding_method: 'rating',
  randomize_seeds: false,
  best_of: 'bo3',
  bracket_reset: true
});
```

### 3. Match Management
```javascript
// Update match scores
await api.put(`/admin/events/${eventId}/comprehensive-bracket/matches/${matchId}`, {
  team1_score: 2,
  team2_score: 1,
  status: 'completed',
  maps_data: [
    { team1_score: 13, team2_score: 11, map: 'Haven' },
    { team1_score: 8, team2_score: 13, map: 'Bind' },
    { team1_score: 13, team2_score: 7, map: 'Ascent' }
  ]
});
```

## Configuration Options

### Tournament Formats
- `single_elimination` - Single loss elimination
- `double_elimination` - Double loss elimination with bracket reset
- `swiss` - Swiss system with configurable rounds
- `round_robin` - Complete round-robin
- `group_stage` - Group stage + playoffs

### Seeding Methods
- `rating` - By team rating/ELO
- `performance` - By recent performance
- `manual` - Custom admin seeding
- `random` - Random seeding
- `balanced` - Balanced distribution
- `regional` - Regional conflict avoidance

### Match Formats
- `bo1` - Best of 1 (max score: 1)
- `bo3` - Best of 3 (max score: 2)  
- `bo5` - Best of 5 (max score: 3)

## Advanced Features

### 1. Swiss System Pairing Algorithm
- Folding method for first round
- Score-based pairing for subsequent rounds
- Avoids repeat matchups when possible
- Buchholz tiebreaker calculation

### 2. Double Elimination Progression
- Complex lower bracket routing
- Proper drop-down positioning
- Grand finals bracket reset logic
- Upper/lower bracket synchronization

### 3. Seeding Optimization
- Power-of-2 bracket sizing
- Optimal bye distribution
- Strength-based bracket balancing
- Regional conflict minimization

### 4. Real-Time Features
- Live match updates
- Automatic progression
- Dynamic standings
- Progress tracking

## Testing and Validation

### Unit Tests
- Seeding algorithm validation
- Bracket generation correctness
- Match progression logic
- Standings calculation accuracy

### Integration Tests
- End-to-end tournament workflows
- API endpoint validation
- Database integrity checks
- Frontend component testing

### Performance Tests
- Large tournament handling (64+ teams)
- Complex bracket calculations
- Real-time update performance
- Database query optimization

## Usage Examples

### Creating a 16-Team Double Elimination Tournament
```javascript
// 1. Create event and add teams
const event = await createEvent({
  name: 'Championship Tournament',
  format: 'double_elimination',
  prize_pool: 50000
});

// 2. Add 16 teams with seeding
for (let i = 0; i < 16; i++) {
  await addTeamToEvent(event.id, teams[i].id, i + 1);
}

// 3. Generate bracket with rating-based seeding
await generateBracket(event.id, {
  format: 'double_elimination',
  seeding_method: 'rating',
  best_of: 'bo3',
  bracket_reset: true
});

// 4. Tournament is ready for matches!
```

### Swiss Tournament for 32 Teams
```javascript
// Generate 5-round Swiss tournament
await generateBracket(event.id, {
  format: 'swiss',
  seeding_method: 'balanced',
  swiss_rounds: 5,
  best_of: 'bo3'
});

// Generate each round dynamically
for (let round = 1; round <= 5; round++) {
  if (round > 1) {
    await generateNextSwissRound(event.id);
  }
  // Play matches in round...
}
```

This comprehensive bracket system provides professional-grade tournament management with the flexibility and features needed for competitive esports tournaments of any scale.