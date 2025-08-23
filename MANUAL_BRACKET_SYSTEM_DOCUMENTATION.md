# Manual Bracket Management System Documentation

## Overview
The Manual Bracket Management System allows administrators to create and manage tournament brackets manually, with full control over team selection, match formats, and score progression. This system is specifically designed for Marvel Rivals tournaments following official competitive formats.

## Features

### 1. Marvel Rivals Tournament Formats
Based on official Marvel Rivals IGNITE 2025 competitive structure:

- **Play-in Stage (GSL Bracket)**: 4 teams compete in GSL format, top 2 advance
- **Open Qualifier**: Single elimination BO1 for 8-128 teams
- **Closed Qualifier**: Double elimination BO3 for 8-16 teams
- **Main Stage**: 8 teams in double elimination BO3
- **Championship Finals**: 4-8 teams double elimination with BO5 grand finals
- **Custom Tournament**: Fully configurable settings

### 2. Supported Bracket Types
- **Single Elimination**: Standard knockout format
- **Double Elimination**: Upper/Lower bracket with loser progression
- **GSL Bracket**: 4-team format with winners/losers/decider matches
- **Round Robin**: All teams play each other

### 3. Match Formats
- Best of 1 (BO1)
- Best of 3 (BO3)
- Best of 5 (BO5)
- Best of 7 (BO7)

### 4. Marvel Rivals Game Modes
Tracks individual game results within matches:
- Domination
- Convoy
- Convergence

## Implementation

### Backend Components

#### Controller: `ManualBracketController.php`
Location: `/var/www/mrvl-backend/app/Http/Controllers/ManualBracketController.php`

Key Methods:
- `getFormats()`: Returns available tournament formats
- `createManualBracket()`: Creates bracket with selected teams
- `updateMatchScore()`: Updates scores and advances winners
- `getBracketState()`: Returns current bracket status
- `resetBracket()`: Resets bracket to initial state

#### Database Schema
Uses existing tables:
- `bracket_stages`: Stores bracket configuration
- `bracket_matches`: Individual match data
- `bracket_seedings`: Team seeding information

### Frontend Component

#### Component: `ManualBracketManager.js`
Location: `/var/www/mrvl-frontend/frontend/src/components/admin/ManualBracketManager.js`

Features:
- Interactive team selection
- Format and match type configuration
- Real-time bracket visualization
- Score entry modal
- Automatic progression display

### API Endpoints

#### Admin Endpoints (Authentication Required)
```
POST   /api/admin/tournaments/{id}/manual-bracket     - Create manual bracket
GET    /api/admin/manual-bracket/formats              - Get available formats
GET    /api/admin/manual-bracket/{stageId}            - Get bracket state
PUT    /api/admin/manual-bracket/matches/{id}/score   - Update match score
POST   /api/admin/manual-bracket/{stageId}/reset      - Reset bracket
```

#### Public Endpoints
```
GET    /api/manual-bracket/{stageId}                  - View bracket (public)
GET    /api/manual-bracket/formats                    - Get formats (public)
```

## Usage Workflow

### 1. Creating a Bracket

1. Navigate to tournament admin page
2. Click "Manual Bracket Manager"
3. Select tournament format (e.g., "Main Stage")
4. Choose participating teams
5. Configure match settings (BO3, BO5, etc.)
6. Click "Create Bracket"

### 2. Managing Matches

1. View generated bracket structure
2. Click on any match with both teams assigned
3. Enter scores in the modal:
   - Team 1 Score: 0-7
   - Team 2 Score: 0-7
4. Optionally track individual game modes
5. Click "Update Score"
6. Winners automatically advance to next round

### 3. Bracket Progression

- **Automatic Advancement**: Winners move to next round automatically
- **Loser Bracket**: In double elimination, losers drop to lower bracket
- **GSL Format**: Complex progression with winners/losers/decider matches
- **Champion Display**: Final winner shown prominently

## Example: GSL Bracket Flow

```
Opening Matches:
  Match A: Team 1 vs Team 4
  Match B: Team 2 vs Team 3

Winners Match:
  Winner of A vs Winner of B → Advances to playoffs

Elimination Match:
  Loser of A vs Loser of B → Eliminated

Decider Match:
  Loser of Winners vs Winner of Elimination → 2nd place
```

## Admin Interface

The admin UI provides:
- **Bracket Settings Panel**: Configure format, type, and match settings
- **Team Selection Grid**: Visual team picker with logos
- **Bracket Visualization**: Interactive bracket display
- **Score Entry Modal**: Quick score updates with game details
- **Progress Tracking**: Shows completed/total matches
- **Reset Option**: Clear bracket and start over

## Testing

Run the test script to validate functionality:
```bash
chmod +x /var/www/mrvl-backend/test_manual_bracket.sh
./test_manual_bracket.sh
```

## Security

- All admin endpoints require authentication
- Role-based access control (admin/moderator only)
- Input validation on all requests
- Database transactions for atomic operations

## Performance

- Efficient bracket generation algorithms
- Minimal database queries
- Frontend state management for responsive UI
- Support for up to 128 teams

## Future Enhancements

1. **Bracket Templates**: Save and reuse bracket configurations
2. **Live Updates**: WebSocket integration for real-time score updates
3. **Bracket Export**: Generate images/PDFs of brackets
4. **Statistics**: Track match statistics and player performance
5. **Stream Integration**: Display brackets on streaming overlays

## Troubleshooting

### Common Issues

1. **Teams not appearing**: Ensure teams exist in database
2. **Scores not updating**: Check authentication token
3. **Bracket not progressing**: Verify match completion logic
4. **Format not available**: Check MARVEL_RIVALS_FORMATS constant

### Debug Commands

```php
# Check bracket stages
php artisan tinker
>>> App\Models\BracketStage::latest()->first();

# View matches
>>> App\Models\BracketMatch::where('bracket_stage_id', 1)->get();

# Test controller
>>> $controller = new App\Http\Controllers\ManualBracketController();
>>> $controller->getFormats();
```

## Conclusion

The Manual Bracket Management System provides complete control over tournament bracket creation and management, specifically tailored for Marvel Rivals competitive formats. It combines automated progression with manual score entry, ensuring flexibility while maintaining bracket integrity.

---

*System Status: ✅ FULLY OPERATIONAL*  
*Last Updated: August 20, 2025*  
*Version: 1.0*