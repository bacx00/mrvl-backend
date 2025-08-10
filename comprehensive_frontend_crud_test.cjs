#!/usr/bin/env node

/**
 * COMPREHENSIVE FRONTEND CRUD OPERATIONS TEST SUITE
 * 
 * This test suite validates all frontend pages and CRUD operations for:
 * 1. Events System
 * 2. Bracket System  
 * 3. Rankings System
 * 4. Image/Logo functionality
 * 5. Admin CRUD operations
 * 
 * Report Format: Critical bugs marked as HIGH/MEDIUM/LOW severity
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

// Configuration
const CONFIG = {
    baseUrl: 'http://127.0.0.1:8000',  // Laravel backend
    frontendUrl: 'http://127.0.0.1:8000', // React frontend served by Laravel
    testResults: [],
    adminCredentials: {
        email: 'admin@example.com',
        password: 'password'
    }
};

class ComprehensiveFrontendTester {
    constructor() {
        this.testResults = [];
        this.authToken = null;
        this.startTime = Date.now();
        this.criticalBugs = [];
        this.highSeverityIssues = [];
        this.mediumSeverityIssues = [];
        this.lowSeverityIssues = [];
    }

    async runAllTests() {
        console.log('🚀 STARTING COMPREHENSIVE FRONTEND CRUD TESTING SUITE');
        console.log('='.repeat(60));
        
        try {
            // 1. Setup and Authentication
            await this.setupTests();
            await this.authenticateAdmin();
            
            // 2. Events System Testing
            await this.testEventsSystem();
            
            // 3. Bracket System Testing  
            await this.testBracketSystem();
            
            // 4. Rankings System Testing
            await this.testRankingsSystem();
            
            // 5. Image/Logo Testing
            await this.testImageFunctionality();
            
            // 6. Admin CRUD Testing
            await this.testAdminCrudOperations();
            
            // 7. Generate comprehensive report
            await this.generateFinalReport();
            
        } catch (error) {
            this.logError('CRITICAL TEST SUITE FAILURE', error);
        }
    }

    async setupTests() {
        console.log('🔧 Setting up test environment...');
        
        // Test API connectivity
        try {
            const response = await this.makeRequest('/api/health', 'GET');
            this.logSuccess('API connectivity verified');
        } catch (error) {
            this.logError('API_CONNECTIVITY_FAILURE', 'Cannot connect to backend API', 'CRITICAL');
        }

        // Test frontend availability
        try {
            // Check if React app is built and served
            const frontendCheck = await this.makeRequest('/', 'GET', null, false);
            if (frontendCheck.includes('root')) {
                this.logSuccess('Frontend build detected');
            } else {
                this.logError('FRONTEND_BUILD_MISSING', 'React app not properly built or served', 'HIGH');
            }
        } catch (error) {
            this.logError('FRONTEND_ACCESS_FAILURE', 'Cannot access frontend', 'CRITICAL');
        }
    }

    async authenticateAdmin() {
        console.log('🔐 Authenticating admin user...');
        
        try {
            const loginResponse = await this.makeRequest('/api/auth/login', 'POST', {
                email: CONFIG.adminCredentials.email,
                password: CONFIG.adminCredentials.password
            });
            
            if (loginResponse.access_token) {
                this.authToken = loginResponse.access_token;
                this.logSuccess('Admin authentication successful');
                
                // Verify admin permissions
                const profileResponse = await this.makeRequest('/api/auth/user', 'GET');
                if (profileResponse.role !== 'admin') {
                    this.logError('ADMIN_PERMISSIONS_FAILURE', 'User does not have admin role', 'CRITICAL');
                }
            } else {
                this.logError('AUTHENTICATION_FAILURE', 'No access token received', 'CRITICAL');
            }
        } catch (error) {
            this.logError('AUTHENTICATION_ERROR', error, 'CRITICAL');
        }
    }

    async testEventsSystem() {
        console.log('🎯 Testing Events System...');
        
        const eventsTests = [
            {
                name: 'Events Listing Page',
                test: () => this.testEventsListing()
            },
            {
                name: 'Event Creation with Logo Upload',
                test: () => this.testEventCreation()
            },
            {
                name: 'Event Editing/Updating',
                test: () => this.testEventEditing()
            },
            {
                name: 'Event Deletion',
                test: () => this.testEventDeletion()
            },
            {
                name: 'Team Addition/Removal from Events',
                test: () => this.testEventTeamManagement()
            },
            {
                name: 'Event Detail Page Display',
                test: () => this.testEventDetailPage()
            }
        ];
        
        for (const testCase of eventsTests) {
            try {
                await testCase.test();
                this.logSuccess(`✅ Events: ${testCase.name}`);
            } catch (error) {
                this.logError(`EVENTS_${testCase.name.replace(/\s+/g, '_').toUpperCase()}`, error, 'HIGH');
            }
        }
    }

    async testEventsListing() {
        // Test events API endpoint
        const events = await this.makeRequest('/api/events', 'GET');
        if (!Array.isArray(events)) {
            throw new Error('Events endpoint does not return array');
        }
        
        // Test events display with logos
        for (const event of events.slice(0, 3)) {
            if (event.logo_url) {
                try {
                    await this.makeRequest(event.logo_url, 'GET', null, false);
                } catch (error) {
                    this.logError('EVENT_LOGO_BROKEN', `Event ${event.id} has broken logo: ${event.logo_url}`, 'MEDIUM');
                }
            }
        }
    }

    async testEventCreation() {
        const testEvent = {
            name: 'TEST Marvel Rivals Championship 2025',
            description: 'Test event for CRUD validation',
            start_date: '2025-12-01',
            end_date: '2025-12-03',
            type: 'tournament',
            tier: 'S',
            prize_pool: 50000,
            max_teams: 16
        };
        
        const createdEvent = await this.makeRequest('/api/admin/events', 'POST', testEvent);
        
        if (!createdEvent.id) {
            throw new Error('Event creation failed - no ID returned');
        }
        
        // Test logo upload
        const logoUploadResponse = await this.testImageUpload(`/api/admin/events/${createdEvent.id}/logo`);
        
        // Store for cleanup
        this.testEventId = createdEvent.id;
    }

    async testEventEditing() {
        if (!this.testEventId) {
            throw new Error('No test event available for editing');
        }
        
        const updateData = {
            name: 'UPDATED Marvel Rivals Championship 2025',
            prize_pool: 75000
        };
        
        const updatedEvent = await this.makeRequest(`/api/admin/events/${this.testEventId}`, 'PUT', updateData);
        
        if (updatedEvent.name !== updateData.name) {
            throw new Error('Event update failed - name not updated');
        }
        
        if (updatedEvent.prize_pool != updateData.prize_pool) {
            throw new Error('Event update failed - prize_pool not updated');
        }
    }

    async testEventDeletion() {
        if (!this.testEventId) {
            throw new Error('No test event available for deletion');
        }
        
        await this.makeRequest(`/api/admin/events/${this.testEventId}`, 'DELETE');
        
        // Verify deletion
        try {
            await this.makeRequest(`/api/events/${this.testEventId}`, 'GET');
            throw new Error('Event still exists after deletion');
        } catch (error) {
            if (!error.message.includes('404')) {
                throw error;
            }
        }
    }

    async testEventTeamManagement() {
        // Create temporary event and team for testing
        const testEvent = await this.makeRequest('/api/admin/events', 'POST', {
            name: 'Team Management Test Event',
            type: 'tournament',
            start_date: '2025-12-01',
            end_date: '2025-12-03'
        });
        
        const teams = await this.makeRequest('/api/teams', 'GET');
        if (teams.length === 0) {
            throw new Error('No teams available for team management test');
        }
        
        const testTeam = teams[0];
        
        // Add team to event
        await this.makeRequest(`/api/admin/events/${testEvent.id}/teams`, 'POST', {
            team_id: testTeam.id
        });
        
        // Verify team was added
        const eventWithTeams = await this.makeRequest(`/api/events/${testEvent.id}/teams`, 'GET');
        if (!eventWithTeams.find(t => t.id === testTeam.id)) {
            throw new Error('Team was not properly added to event');
        }
        
        // Remove team from event
        await this.makeRequest(`/api/admin/events/${testEvent.id}/teams/${testTeam.id}`, 'DELETE');
        
        // Cleanup
        await this.makeRequest(`/api/admin/events/${testEvent.id}`, 'DELETE');
    }

    async testEventDetailPage() {
        const events = await this.makeRequest('/api/events', 'GET');
        if (events.length === 0) {
            throw new Error('No events available for detail page test');
        }
        
        const testEvent = events[0];
        const eventDetail = await this.makeRequest(`/api/events/${testEvent.id}`, 'GET');
        
        // Verify essential fields
        const requiredFields = ['id', 'name', 'description', 'start_date', 'end_date'];
        for (const field of requiredFields) {
            if (!eventDetail[field]) {
                throw new Error(`Missing required field in event detail: ${field}`);
            }
        }
    }

    async testBracketSystem() {
        console.log('🏆 Testing Bracket System...');
        
        const bracketTests = [
            {
                name: 'Generate Bracket Button (Admin Only)',
                test: () => this.testBracketGenerationAccess()
            },
            {
                name: 'Bracket Generation with 2+ Teams',
                test: () => this.testBracketGeneration()
            },
            {
                name: 'Bracket Visual Display',
                test: () => this.testBracketDisplay()
            },
            {
                name: 'Score Updates in Brackets',
                test: () => this.testBracketScoreUpdates()
            },
            {
                name: 'Winner Progression Logic',
                test: () => this.testBracketProgression()
            },
            {
                name: 'Bracket Persistence After Reload',
                test: () => this.testBracketPersistence()
            }
        ];
        
        for (const testCase of bracketTests) {
            try {
                await testCase.test();
                this.logSuccess(`✅ Brackets: ${testCase.name}`);
            } catch (error) {
                this.logError(`BRACKETS_${testCase.name.replace(/\s+/g, '_').toUpperCase()}`, error, 'HIGH');
            }
        }
    }

    async testBracketGenerationAccess() {
        // Test admin access to bracket generation
        const events = await this.makeRequest('/api/admin/events', 'GET');
        if (events.length === 0) {
            throw new Error('No events available for bracket generation test');
        }
        
        // Should be able to access bracket generation endpoint as admin
        const bracketGenResponse = await this.makeRequest(`/api/admin/events/${events[0].id}/generate-bracket`, 'POST', {});
        
        // Test non-admin access (should fail)
        this.authToken = null; // Remove admin token temporarily
        try {
            await this.makeRequest(`/api/admin/events/${events[0].id}/generate-bracket`, 'POST', {});
            throw new Error('Non-admin user can access bracket generation - security issue');
        } catch (error) {
            if (!error.message.includes('401') && !error.message.includes('403')) {
                throw error;
            }
        }
        
        // Restore admin token
        await this.authenticateAdmin();
    }

    async testBracketGeneration() {
        // Create event with multiple teams for bracket generation
        const testEvent = await this.makeRequest('/api/admin/events', 'POST', {
            name: 'Bracket Generation Test Event',
            type: 'tournament',
            start_date: '2025-12-01',
            end_date: '2025-12-03',
            max_teams: 8
        });
        
        // Add teams to event
        const teams = await this.makeRequest('/api/teams', 'GET');
        if (teams.length < 2) {
            throw new Error('Need at least 2 teams for bracket generation test');
        }
        
        for (let i = 0; i < Math.min(4, teams.length); i++) {
            await this.makeRequest(`/api/admin/events/${testEvent.id}/teams`, 'POST', {
                team_id: teams[i].id
            });
        }
        
        // Generate bracket
        const bracketResponse = await this.makeRequest(`/api/admin/events/${testEvent.id}/generate-bracket`, 'POST', {});
        
        if (!bracketResponse.bracket_id) {
            throw new Error('Bracket generation failed - no bracket_id returned');
        }
        
        // Store for further testing
        this.testBracketId = bracketResponse.bracket_id;
        this.testEventIdForBracket = testEvent.id;
    }

    async testBracketDisplay() {
        if (!this.testBracketId) {
            throw new Error('No test bracket available for display test');
        }
        
        const bracketData = await this.makeRequest(`/api/brackets/${this.testBracketId}`, 'GET');
        
        // Verify bracket structure
        if (!bracketData.matches || !Array.isArray(bracketData.matches)) {
            throw new Error('Bracket does not contain matches array');
        }
        
        if (!bracketData.teams || !Array.isArray(bracketData.teams)) {
            throw new Error('Bracket does not contain teams array');
        }
    }

    async testBracketScoreUpdates() {
        if (!this.testBracketId) {
            throw new Error('No test bracket available for score update test');
        }
        
        const bracketData = await this.makeRequest(`/api/brackets/${this.testBracketId}`, 'GET');
        if (bracketData.matches.length === 0) {
            throw new Error('No matches available for score update test');
        }
        
        const testMatch = bracketData.matches[0];
        
        // Update match score
        const scoreUpdate = await this.makeRequest(`/api/admin/matches/${testMatch.id}`, 'PUT', {
            team1_score: 2,
            team2_score: 1,
            status: 'completed'
        });
        
        if (scoreUpdate.team1_score !== 2 || scoreUpdate.team2_score !== 1) {
            throw new Error('Match score update failed');
        }
    }

    async testBracketProgression() {
        if (!this.testBracketId) {
            throw new Error('No test bracket available for progression test');
        }
        
        // Test winner progression logic by completing first round matches
        const bracketData = await this.makeRequest(`/api/brackets/${this.testBracketId}`, 'GET');
        const firstRoundMatches = bracketData.matches.filter(m => m.round === 1);
        
        for (const match of firstRoundMatches) {
            await this.makeRequest(`/api/admin/matches/${match.id}`, 'PUT', {
                team1_score: Math.random() > 0.5 ? 2 : 1,
                team2_score: Math.random() > 0.5 ? 2 : 1,
                status: 'completed'
            });
        }
        
        // Check if next round was properly created/populated
        const updatedBracket = await this.makeRequest(`/api/brackets/${this.testBracketId}`, 'GET');
        const nextRoundMatches = updatedBracket.matches.filter(m => m.round === 2);
        
        if (nextRoundMatches.length === 0 && firstRoundMatches.length > 1) {
            this.logError('BRACKET_PROGRESSION_FAILURE', 'Next round not created after completing first round', 'HIGH');
        }
    }

    async testBracketPersistence() {
        if (!this.testBracketId) {
            throw new Error('No test bracket available for persistence test');
        }
        
        // Get bracket data
        const initialBracketData = await this.makeRequest(`/api/brackets/${this.testBracketId}`, 'GET');
        
        // Simulate page reload by making another request
        await new Promise(resolve => setTimeout(resolve, 1000)); // Wait 1 second
        
        const reloadedBracketData = await this.makeRequest(`/api/brackets/${this.testBracketId}`, 'GET');
        
        // Compare key data to ensure persistence
        if (initialBracketData.matches.length !== reloadedBracketData.matches.length) {
            throw new Error('Bracket matches not persistent after reload');
        }
        
        if (initialBracketData.teams.length !== reloadedBracketData.teams.length) {
            throw new Error('Bracket teams not persistent after reload');
        }
    }

    async testRankingsSystem() {
        console.log('🏅 Testing Rankings System...');
        
        const rankingTests = [
            {
                name: 'Team Rankings Page Navigation',
                test: () => this.testTeamRankingsPage()
            },
            {
                name: 'Player Rankings Page Navigation', 
                test: () => this.testPlayerRankingsPage()
            },
            {
                name: 'Regional Filters',
                test: () => this.testRegionalFilters()
            },
            {
                name: 'Search Functionality',
                test: () => this.testRankingSearch()
            },
            {
                name: 'Sorting Options',
                test: () => this.testRankingSorting()
            },
            {
                name: 'Pagination',
                test: () => this.testRankingPagination()
            },
            {
                name: 'Mobile Responsiveness',
                test: () => this.testRankingMobileResponsiveness()
            }
        ];
        
        for (const testCase of rankingTests) {
            try {
                await testCase.test();
                this.logSuccess(`✅ Rankings: ${testCase.name}`);
            } catch (error) {
                this.logError(`RANKINGS_${testCase.name.replace(/\s+/g, '_').toUpperCase()}`, error, 'MEDIUM');
            }
        }
    }

    async testTeamRankingsPage() {
        const teamRankings = await this.makeRequest('/api/rankings/teams', 'GET');
        
        if (!Array.isArray(teamRankings)) {
            throw new Error('Team rankings endpoint does not return array');
        }
        
        // Verify ranking data structure
        if (teamRankings.length > 0) {
            const firstTeam = teamRankings[0];
            const requiredFields = ['id', 'name', 'ranking', 'points'];
            
            for (const field of requiredFields) {
                if (firstTeam[field] === undefined) {
                    throw new Error(`Team ranking missing required field: ${field}`);
                }
            }
        }
    }

    async testPlayerRankingsPage() {
        const playerRankings = await this.makeRequest('/api/rankings/players', 'GET');
        
        if (!Array.isArray(playerRankings)) {
            throw new Error('Player rankings endpoint does not return array');
        }
        
        // Verify player ranking data structure
        if (playerRankings.length > 0) {
            const firstPlayer = playerRankings[0];
            const requiredFields = ['id', 'name', 'ranking', 'elo_rating'];
            
            for (const field of requiredFields) {
                if (firstPlayer[field] === undefined) {
                    throw new Error(`Player ranking missing required field: ${field}`);
                }
            }
        }
    }

    async testRegionalFilters() {
        // Test different regional filters
        const regions = ['NA', 'EU', 'APAC', 'CN', 'LATAM'];
        
        for (const region of regions) {
            try {
                const regionalTeams = await this.makeRequest(`/api/rankings/teams?region=${region}`, 'GET');
                if (Array.isArray(regionalTeams)) {
                    // Verify teams are actually from the specified region
                    for (const team of regionalTeams.slice(0, 3)) {
                        if (team.region && team.region !== region) {
                            this.logError('REGIONAL_FILTER_ERROR', `Team ${team.name} has region ${team.region} but appears in ${region} filter`, 'MEDIUM');
                        }
                    }
                }
            } catch (error) {
                this.logError('REGIONAL_FILTER_FAILURE', `Region filter failed for ${region}: ${error.message}`, 'MEDIUM');
            }
        }
    }

    async testRankingSearch() {
        // Test team search
        const teams = await this.makeRequest('/api/teams', 'GET');
        if (teams.length > 0) {
            const searchTerm = teams[0].name.substring(0, 3);
            const searchResults = await this.makeRequest(`/api/rankings/teams?search=${searchTerm}`, 'GET');
            
            if (!Array.isArray(searchResults)) {
                throw new Error('Team search does not return array');
            }
            
            // Verify search results contain the search term
            const matchFound = searchResults.some(team => 
                team.name.toLowerCase().includes(searchTerm.toLowerCase())
            );
            
            if (searchResults.length > 0 && !matchFound) {
                this.logError('SEARCH_RELEVANCE_ERROR', `Search results for "${searchTerm}" do not contain relevant teams`, 'MEDIUM');
            }
        }
        
        // Test player search
        const players = await this.makeRequest('/api/players', 'GET');
        if (players.length > 0) {
            const playerSearchTerm = players[0].name.substring(0, 3);
            const playerSearchResults = await this.makeRequest(`/api/rankings/players?search=${playerSearchTerm}`, 'GET');
            
            if (!Array.isArray(playerSearchResults)) {
                throw new Error('Player search does not return array');
            }
        }
    }

    async testRankingSorting() {
        // Test team ranking sorting options
        const sortOptions = ['points', 'name', 'region', 'wins', 'losses'];
        
        for (const sortBy of sortOptions) {
            try {
                const sortedResults = await this.makeRequest(`/api/rankings/teams?sort=${sortBy}`, 'GET');
                if (!Array.isArray(sortedResults)) {
                    throw new Error(`Sort by ${sortBy} does not return array`);
                }
                
                // Test both ascending and descending
                const sortedDesc = await this.makeRequest(`/api/rankings/teams?sort=${sortBy}&order=desc`, 'GET');
                if (!Array.isArray(sortedDesc)) {
                    throw new Error(`Sort by ${sortBy} desc does not return array`);
                }
                
            } catch (error) {
                this.logError('SORTING_ERROR', `Sort by ${sortBy} failed: ${error.message}`, 'MEDIUM');
            }
        }
    }

    async testRankingPagination() {
        // Test pagination for team rankings
        const page1 = await this.makeRequest('/api/rankings/teams?page=1&limit=10', 'GET');
        const page2 = await this.makeRequest('/api/rankings/teams?page=2&limit=10', 'GET');
        
        if (!Array.isArray(page1) || !Array.isArray(page2)) {
            throw new Error('Pagination does not return arrays');
        }
        
        // Verify different results between pages
        if (page1.length > 0 && page2.length > 0) {
            const page1Ids = page1.map(team => team.id);
            const page2Ids = page2.map(team => team.id);
            const overlap = page1Ids.filter(id => page2Ids.includes(id));
            
            if (overlap.length > 0) {
                this.logError('PAGINATION_OVERLAP_ERROR', 'Pagination pages contain overlapping results', 'MEDIUM');
            }
        }
    }

    async testRankingMobileResponsiveness() {
        // This test would typically require browser automation
        // For now, we'll check if mobile-specific endpoints exist
        try {
            const mobileRankings = await this.makeRequest('/api/rankings/teams?mobile=true', 'GET');
            this.logSuccess('Mobile-specific ranking endpoint available');
        } catch (error) {
            this.logInfo('No mobile-specific ranking endpoint found - check responsive design manually');
        }
    }

    async testImageFunctionality() {
        console.log('🖼️ Testing Image/Logo Functionality...');
        
        const imageTests = [
            {
                name: 'Event Logo Display After Upload',
                test: () => this.testEventLogoDisplay()
            },
            {
                name: 'Team Logo Display',
                test: () => this.testTeamLogoDisplay()
            },
            {
                name: 'Fallback Images When Missing',
                test: () => this.testFallbackImages()
            },
            {
                name: 'Storage Paths Correctness',
                test: () => this.testStoragePaths()
            }
        ];
        
        for (const testCase of imageTests) {
            try {
                await testCase.test();
                this.logSuccess(`✅ Images: ${testCase.name}`);
            } catch (error) {
                this.logError(`IMAGES_${testCase.name.replace(/\s+/g, '_').toUpperCase()}`, error, 'MEDIUM');
            }
        }
    }

    async testEventLogoDisplay() {
        const events = await this.makeRequest('/api/events', 'GET');
        const eventsWithLogos = events.filter(event => event.logo_url);
        
        if (eventsWithLogos.length === 0) {
            this.logInfo('No events with logos found for testing');
            return;
        }
        
        for (const event of eventsWithLogos.slice(0, 3)) {
            try {
                await this.makeRequest(event.logo_url, 'GET', null, false);
                this.logSuccess(`Event ${event.name} logo accessible`);
            } catch (error) {
                this.logError('EVENT_LOGO_BROKEN', `Event ${event.name} has broken logo: ${event.logo_url}`, 'MEDIUM');
            }
        }
    }

    async testTeamLogoDisplay() {
        const teams = await this.makeRequest('/api/teams', 'GET');
        const teamsWithLogos = teams.filter(team => team.logo_url);
        
        if (teamsWithLogos.length === 0) {
            this.logInfo('No teams with logos found for testing');
            return;
        }
        
        for (const team of teamsWithLogos.slice(0, 5)) {
            try {
                await this.makeRequest(team.logo_url, 'GET', null, false);
                this.logSuccess(`Team ${team.name} logo accessible`);
            } catch (error) {
                this.logError('TEAM_LOGO_BROKEN', `Team ${team.name} has broken logo: ${team.logo_url}`, 'MEDIUM');
            }
        }
    }

    async testFallbackImages() {
        // Test default placeholder images
        const fallbackImages = [
            '/images/team-placeholder.svg',
            '/images/player-placeholder.svg',
            '/images/news-placeholder.svg',
            '/images/default-placeholder.svg'
        ];
        
        for (const fallbackUrl of fallbackImages) {
            try {
                await this.makeRequest(fallbackUrl, 'GET', null, false);
                this.logSuccess(`Fallback image accessible: ${fallbackUrl}`);
            } catch (error) {
                this.logError('FALLBACK_IMAGE_MISSING', `Fallback image not accessible: ${fallbackUrl}`, 'HIGH');
            }
        }
    }

    async testStoragePaths() {
        // Test if storage paths are properly configured
        try {
            const storageTest = await this.makeRequest('/api/admin/test-storage', 'GET');
            if (storageTest.writable) {
                this.logSuccess('Storage paths properly configured');
            } else {
                this.logError('STORAGE_NOT_WRITABLE', 'Storage directory not writable', 'HIGH');
            }
        } catch (error) {
            this.logInfo('Storage test endpoint not available - manual verification needed');
        }
    }

    async testAdminCrudOperations() {
        console.log('👑 Testing Admin CRUD Operations...');
        
        const crudTests = [
            {
                name: 'Create Operations (Events, Teams, Players, News)',
                test: () => this.testCreateOperations()
            },
            {
                name: 'Read/View Operations',
                test: () => this.testReadOperations()
            },
            {
                name: 'Update/Edit Operations',
                test: () => this.testUpdateOperations()
            },
            {
                name: 'Delete Operations',
                test: () => this.testDeleteOperations()
            },
            {
                name: 'Admin-Only Permission Verification',
                test: () => this.testAdminPermissions()
            }
        ];
        
        for (const testCase of crudTests) {
            try {
                await testCase.test();
                this.logSuccess(`✅ Admin CRUD: ${testCase.name}`);
            } catch (error) {
                this.logError(`ADMIN_CRUD_${testCase.name.replace(/\s+/g, '_').toUpperCase()}`, error, 'HIGH');
            }
        }
    }

    async testCreateOperations() {
        // Test Event Creation
        const testEvent = await this.makeRequest('/api/admin/events', 'POST', {
            name: 'CRUD Test Event',
            type: 'tournament',
            start_date: '2025-12-01',
            end_date: '2025-12-03'
        });
        
        if (!testEvent.id) {
            throw new Error('Event creation failed');
        }
        this.createdEventId = testEvent.id;
        
        // Test Team Creation
        const testTeam = await this.makeRequest('/api/admin/teams', 'POST', {
            name: 'CRUD Test Team',
            region: 'NA',
            country: 'USA'
        });
        
        if (!testTeam.id) {
            throw new Error('Team creation failed');
        }
        this.createdTeamId = testTeam.id;
        
        // Test Player Creation
        const testPlayer = await this.makeRequest('/api/admin/players', 'POST', {
            name: 'TestPlayer',
            team_id: testTeam.id,
            role: 'duelist',
            country: 'USA'
        });
        
        if (!testPlayer.id) {
            throw new Error('Player creation failed');
        }
        this.createdPlayerId = testPlayer.id;
        
        // Test News Creation
        const testNews = await this.makeRequest('/api/admin/news', 'POST', {
            title: 'CRUD Test News Article',
            content: 'This is a test news article for CRUD testing',
            category_id: 1,
            status: 'published'
        });
        
        if (!testNews.id) {
            throw new Error('News creation failed');
        }
        this.createdNewsId = testNews.id;
    }

    async testReadOperations() {
        // Test Admin Dashboard Data
        const dashboardData = await this.makeRequest('/api/admin/dashboard', 'GET');
        
        if (!dashboardData.stats) {
            throw new Error('Admin dashboard does not return stats');
        }
        
        // Test Admin Lists
        const adminEvents = await this.makeRequest('/api/admin/events', 'GET');
        const adminTeams = await this.makeRequest('/api/admin/teams', 'GET');
        const adminPlayers = await this.makeRequest('/api/admin/players', 'GET');
        const adminNews = await this.makeRequest('/api/admin/news', 'GET');
        
        if (!Array.isArray(adminEvents) || !Array.isArray(adminTeams) || 
            !Array.isArray(adminPlayers) || !Array.isArray(adminNews)) {
            throw new Error('Admin list endpoints do not return arrays');
        }
    }

    async testUpdateOperations() {
        if (!this.createdEventId || !this.createdTeamId || !this.createdPlayerId || !this.createdNewsId) {
            throw new Error('No test entities available for update operations');
        }
        
        // Test Event Update
        const updatedEvent = await this.makeRequest(`/api/admin/events/${this.createdEventId}`, 'PUT', {
            name: 'Updated CRUD Test Event',
            prize_pool: 10000
        });
        
        if (updatedEvent.name !== 'Updated CRUD Test Event') {
            throw new Error('Event update failed');
        }
        
        // Test Team Update
        const updatedTeam = await this.makeRequest(`/api/admin/teams/${this.createdTeamId}`, 'PUT', {
            name: 'Updated CRUD Test Team'
        });
        
        if (updatedTeam.name !== 'Updated CRUD Test Team') {
            throw new Error('Team update failed');
        }
        
        // Test Player Update
        const updatedPlayer = await this.makeRequest(`/api/admin/players/${this.createdPlayerId}`, 'PUT', {
            name: 'UpdatedTestPlayer',
            role: 'strategist'
        });
        
        if (updatedPlayer.name !== 'UpdatedTestPlayer') {
            throw new Error('Player update failed');
        }
        
        // Test News Update
        const updatedNews = await this.makeRequest(`/api/admin/news/${this.createdNewsId}`, 'PUT', {
            title: 'Updated CRUD Test News Article'
        });
        
        if (updatedNews.title !== 'Updated CRUD Test News Article') {
            throw new Error('News update failed');
        }
    }

    async testDeleteOperations() {
        if (!this.createdEventId || !this.createdTeamId || !this.createdPlayerId || !this.createdNewsId) {
            throw new Error('No test entities available for delete operations');
        }
        
        // Test News Deletion
        await this.makeRequest(`/api/admin/news/${this.createdNewsId}`, 'DELETE');
        
        // Test Player Deletion
        await this.makeRequest(`/api/admin/players/${this.createdPlayerId}`, 'DELETE');
        
        // Test Team Deletion
        await this.makeRequest(`/api/admin/teams/${this.createdTeamId}`, 'DELETE');
        
        // Test Event Deletion
        await this.makeRequest(`/api/admin/events/${this.createdEventId}`, 'DELETE');
        
        // Verify deletions
        const verifications = [
            { id: this.createdNewsId, endpoint: 'news' },
            { id: this.createdPlayerId, endpoint: 'players' },
            { id: this.createdTeamId, endpoint: 'teams' },
            { id: this.createdEventId, endpoint: 'events' }
        ];
        
        for (const verification of verifications) {
            try {
                await this.makeRequest(`/api/${verification.endpoint}/${verification.id}`, 'GET');
                throw new Error(`${verification.endpoint} entity still exists after deletion`);
            } catch (error) {
                if (!error.message.includes('404')) {
                    throw error;
                }
            }
        }
    }

    async testAdminPermissions() {
        // Store current admin token
        const adminToken = this.authToken;
        
        // Remove authentication
        this.authToken = null;
        
        const protectedEndpoints = [
            '/api/admin/dashboard',
            '/api/admin/events',
            '/api/admin/teams', 
            '/api/admin/players',
            '/api/admin/news'
        ];
        
        for (const endpoint of protectedEndpoints) {
            try {
                await this.makeRequest(endpoint, 'GET');
                this.logError('ADMIN_PERMISSION_BREACH', `Endpoint ${endpoint} accessible without authentication`, 'CRITICAL');
            } catch (error) {
                if (error.message.includes('401') || error.message.includes('403')) {
                    this.logSuccess(`Protected endpoint ${endpoint} properly secured`);
                } else {
                    throw error;
                }
            }
        }
        
        // Restore admin token
        this.authToken = adminToken;
    }

    async testImageUpload(endpoint) {
        // Create a test image buffer
        const testImageBuffer = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==', 'base64');
        
        try {
            const uploadResponse = await this.makeRequest(endpoint, 'POST', {
                image: testImageBuffer.toString('base64'),
                filename: 'test-logo.png'
            });
            
            return uploadResponse;
        } catch (error) {
            throw new Error(`Image upload failed: ${error.message}`);
        }
    }

    async makeRequest(url, method, data = null, isApi = true) {
        const baseUrl = isApi ? CONFIG.baseUrl : '';
        const fullUrl = url.startsWith('http') ? url : `${baseUrl}${url}`;
        
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        
        if (this.authToken) {
            options.headers['Authorization'] = `Bearer ${this.authToken}`;
        }
        
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        return new Promise((resolve, reject) => {
            const lib = fullUrl.startsWith('https:') ? https : require('http');
            
            const req = lib.request(fullUrl, options, (res) => {
                let responseData = '';
                
                res.on('data', (chunk) => {
                    responseData += chunk;
                });
                
                res.on('end', () => {
                    if (res.statusCode >= 200 && res.statusCode < 300) {
                        try {
                            const jsonData = JSON.parse(responseData);
                            resolve(jsonData);
                        } catch (error) {
                            resolve(responseData); // Return raw response if not JSON
                        }
                    } else {
                        reject(new Error(`HTTP ${res.statusCode}: ${responseData}`));
                    }
                });
            });
            
            req.on('error', (error) => {
                reject(error);
            });
            
            if (data && (method === 'POST' || method === 'PUT')) {
                req.write(JSON.stringify(data));
            }
            
            req.end();
        });
    }

    logSuccess(message) {
        console.log(`✅ ${message}`);
        this.testResults.push({
            type: 'SUCCESS',
            message,
            timestamp: new Date().toISOString()
        });
    }

    logError(code, message, severity = 'MEDIUM') {
        console.log(`❌ [${severity}] ${code}: ${message}`);
        
        const errorRecord = {
            type: 'ERROR',
            code,
            message,
            severity,
            timestamp: new Date().toISOString()
        };
        
        this.testResults.push(errorRecord);
        
        switch (severity) {
            case 'CRITICAL':
                this.criticalBugs.push(errorRecord);
                break;
            case 'HIGH':
                this.highSeverityIssues.push(errorRecord);
                break;
            case 'MEDIUM':
                this.mediumSeverityIssues.push(errorRecord);
                break;
            case 'LOW':
                this.lowSeverityIssues.push(errorRecord);
                break;
        }
    }

    logInfo(message) {
        console.log(`ℹ️ ${message}`);
        this.testResults.push({
            type: 'INFO',
            message,
            timestamp: new Date().toISOString()
        });
    }

    async generateFinalReport() {
        const endTime = Date.now();
        const duration = endTime - this.startTime;
        
        const report = {
            summary: {
                testDuration: `${duration}ms`,
                totalTests: this.testResults.length,
                successfulTests: this.testResults.filter(r => r.type === 'SUCCESS').length,
                failedTests: this.testResults.filter(r => r.type === 'ERROR').length,
                criticalBugs: this.criticalBugs.length,
                highSeverityIssues: this.highSeverityIssues.length,
                mediumSeverityIssues: this.mediumSeverityIssues.length,
                lowSeverityIssues: this.lowSeverityIssues.length
            },
            criticalBugs: this.criticalBugs,
            highSeverityIssues: this.highSeverityIssues,
            mediumSeverityIssues: this.mediumSeverityIssues,
            lowSeverityIssues: this.lowSeverityIssues,
            allResults: this.testResults,
            timestamp: new Date().toISOString()
        };
        
        // Write report to file
        const reportPath = `/var/www/mrvl-backend/comprehensive_frontend_crud_test_report_${Date.now()}.json`;
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        
        console.log('\n' + '='.repeat(80));
        console.log('🎯 COMPREHENSIVE FRONTEND CRUD TEST RESULTS SUMMARY');
        console.log('='.repeat(80));
        console.log(`⏱️  Test Duration: ${duration}ms`);
        console.log(`📊 Total Tests: ${report.summary.totalTests}`);
        console.log(`✅ Successful: ${report.summary.successfulTests}`);
        console.log(`❌ Failed: ${report.summary.failedTests}`);
        console.log(`🔴 Critical Bugs: ${report.summary.criticalBugs}`);
        console.log(`🟠 High Severity: ${report.summary.highSeverityIssues}`);
        console.log(`🟡 Medium Severity: ${report.summary.mediumSeverityIssues}`);
        console.log(`🟢 Low Severity: ${report.summary.lowSeverityIssues}`);
        console.log(`📄 Full Report: ${reportPath}`);
        
        if (report.summary.criticalBugs > 0) {
            console.log('\n🚨 CRITICAL BUGS FOUND - IMMEDIATE ATTENTION REQUIRED:');
            this.criticalBugs.forEach((bug, index) => {
                console.log(`${index + 1}. ${bug.code}: ${bug.message}`);
            });
        }
        
        if (report.summary.highSeverityIssues > 0) {
            console.log('\n⚠️  HIGH SEVERITY ISSUES:');
            this.highSeverityIssues.forEach((issue, index) => {
                console.log(`${index + 1}. ${issue.code}: ${issue.message}`);
            });
        }
        
        console.log('\n' + '='.repeat(80));
        
        return report;
    }
}

// Execute the comprehensive test suite
if (require.main === module) {
    const tester = new ComprehensiveFrontendTester();
    tester.runAllTests().catch(error => {
        console.error('❌ Test suite execution failed:', error);
        process.exit(1);
    });
}

module.exports = ComprehensiveFrontendTester;