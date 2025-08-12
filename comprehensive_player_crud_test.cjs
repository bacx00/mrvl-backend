const https = require('https');
const http = require('http');
const fs = require('fs');

/**
 * COMPREHENSIVE PLAYER CRUD OPERATIONS TEST
 * ==========================================
 * This test suite performs exhaustive testing of player CRUD operations
 * to identify any bugs in field validation, data persistence, API responses,
 * profile page display, update mechanisms, and social media handling.
 */

class PlayerCRUDTester {
    constructor() {
        this.baseUrl = 'http://localhost:8000/api';
        this.httpBaseUrl = 'http://localhost:8000/api';
        this.authToken = null;
        this.testPlayerId = null;
        this.bugs = [];
        this.results = {
            createTest: null,
            readTest: null,
            updateTest: null,
            deleteTest: null,
            errorHandlingTest: null,
            validationTest: null
        };
        
        // Comprehensive test data for initial player creation
        this.initialPlayerData = {
            username: "test_player_alpha",
            real_name: "Test Player Alpha",
            name: "test_player_alpha", // Some systems use this as the main name field
            role: "Duelist",
            nationality: "United States",
            country: "United States",
            country_code: "US",
            team_id: 74, // Apex Legends team
            earnings: 5000.00,
            earnings_amount: 5000.00,
            earnings_currency: "USD",
            total_earnings: 5000.00,
            rating: 1250.50,
            elo_rating: 1250.50,
            peak_rating: 1300.75,
            peak_elo: 1300.75,
            rank: "Diamond",
            age: 22,
            birth_date: "2002-03-15",
            wins: 145,
            losses: 78,
            kda: 1.85,
            total_matches: 223,
            tournaments_played: 12,
            // Social media links
            twitter: "https://twitter.com/testplayeralpha",
            instagram: "https://instagram.com/testplayeralpha",
            youtube: "https://youtube.com/@testplayeralpha",
            twitch: "https://twitch.tv/testplayeralpha",
            discord: "TestPlayerAlpha#1234",
            tiktok: "https://tiktok.com/@testplayeralpha",
            facebook: "https://facebook.com/testplayeralpha",
            // Additional fields
            biography: "Professional Marvel Rivals player specializing in Duelist role with exceptional aim and game sense.",
            hero_preferences: ["Spider-Man", "Iron Man", "Doctor Strange"],
            main_hero: "Spider-Man",
            alt_heroes: ["Iron Man", "Doctor Strange", "Star-Lord"],
            skill_rating: 1250,
            jersey_number: 7,
            position_order: 1,
            team_position: "Entry Fragger",
            region: "NA",
            flag: "us.png",
            country_flag: "us.png",
            status: "active",
            total_eliminations: 2890,
            total_deaths: 1560,
            total_assists: 1245,
            overall_kda: 1.85,
            average_damage_per_match: 3500.25,
            average_healing_per_match: 1250.80,
            average_damage_blocked_per_match: 2100.45,
            most_played_hero: "Spider-Man",
            best_winrate_hero: "Doctor Strange",
            longest_win_streak: 12,
            current_win_streak: 5,
            hero_pool: ["Spider-Man", "Iron Man", "Doctor Strange", "Star-Lord", "Scarlet Witch"],
            achievements: ["First Place ESL Marvel Championship", "MVP Spring Split 2024"],
            liquipedia_url: "https://liquipedia.net/marvelrivals/Test_Player_Alpha"
        };
        
        // Updated data for testing updates
        this.updatedPlayerData = {
            username: "updated_test_player",
            real_name: "Updated Test Player",
            name: "updated_test_player",
            role: "Vanguard",
            nationality: "Canada",
            country: "Canada",
            country_code: "CA",
            team_id: 81, // Dark Phantoms team
            earnings: 7500.00,
            earnings_amount: 7500.00,
            earnings_currency: "USD",
            total_earnings: 7500.00,
            rating: 1350.75,
            elo_rating: 1350.75,
            peak_rating: 1400.25,
            peak_elo: 1400.25,
            rank: "Master",
            age: 23,
            birth_date: "2001-08-22",
            wins: 167,
            losses: 82,
            kda: 2.15,
            total_matches: 249,
            tournaments_played: 15,
            // Updated social media links
            twitter: "https://twitter.com/updatedplayer",
            instagram: "https://instagram.com/updatedplayer",
            youtube: "https://youtube.com/@updatedplayer",
            twitch: "https://twitch.tv/updatedplayer",
            discord: "UpdatedPlayer#5678",
            tiktok: "https://tiktok.com/@updatedplayer",
            facebook: "https://facebook.com/updatedplayer",
            // Updated additional fields
            biography: "Elite Marvel Rivals Vanguard player known for exceptional tank gameplay and team coordination.",
            hero_preferences: ["Hulk", "Thor", "Captain America"],
            main_hero: "Hulk",
            alt_heroes: ["Thor", "Captain America", "Groot"],
            skill_rating: 1350,
            jersey_number: 12,
            position_order: 1,
            team_position: "Main Tank",
            region: "NA",
            flag: "ca.png",
            country_flag: "ca.png",
            status: "active",
            total_eliminations: 3200,
            total_deaths: 1650,
            total_assists: 1450,
            overall_kda: 2.15,
            average_damage_per_match: 4200.50,
            average_healing_per_match: 1500.25,
            average_damage_blocked_per_match: 5500.75,
            most_played_hero: "Hulk",
            best_winrate_hero: "Thor",
            longest_win_streak: 15,
            current_win_streak: 8,
            hero_pool: ["Hulk", "Thor", "Captain America", "Groot", "Rocket Raccoon"],
            achievements: ["First Place Marvel World Championship", "MVP Summer Split 2024", "Best Tank Player 2024"],
            liquipedia_url: "https://liquipedia.net/marvelrivals/Updated_Test_Player"
        };
    }

    log(message, type = 'INFO') {
        const timestamp = new Date().toISOString();
        const logMessage = `[${timestamp}] [${type}] ${message}`;
        console.log(logMessage);
        return logMessage;
    }

    addBug(severity, classification, description, reproduction, fix) {
        const bug = {
            severity,
            classification,
            description,
            reproduction,
            recommended_fix: fix,
            discovered_at: new Date().toISOString()
        };
        this.bugs.push(bug);
        this.log(`🐛 BUG FOUND [${severity}] ${classification}: ${description}`, 'BUG');
    }

    async makeRequest(method, endpoint, data = null, useHttp = false) {
        return new Promise((resolve, reject) => {
            const url = useHttp ? this.httpBaseUrl : this.baseUrl;
            const fullUrl = `${url}${endpoint}`;
            
            this.log(`Making ${method} request to: ${fullUrl}`);
            
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'User-Agent': 'PlayerCRUDTester/1.0'
                }
            };

            if (this.authToken) {
                options.headers['Authorization'] = `Bearer ${this.authToken}`;
            }

            if (data) {
                options.body = JSON.stringify(data);
                options.headers['Content-Length'] = Buffer.byteLength(options.body);
            }

            const client = useHttp ? http : https;
            
            if (!useHttp) {
                // For HTTPS, disable SSL verification in test environment
                options.rejectUnauthorized = false;
            }

            const req = client.request(fullUrl, options, (res) => {
                let responseData = '';
                
                res.on('data', (chunk) => {
                    responseData += chunk;
                });
                
                res.on('end', () => {
                    try {
                        const parsedData = responseData ? JSON.parse(responseData) : {};
                        resolve({
                            statusCode: res.statusCode,
                            headers: res.headers,
                            data: parsedData,
                            rawData: responseData
                        });
                    } catch (e) {
                        resolve({
                            statusCode: res.statusCode,
                            headers: res.headers,
                            data: null,
                            rawData: responseData,
                            parseError: e.message
                        });
                    }
                });
            });

            req.on('error', (error) => {
                this.log(`Request error: ${error.message}`, 'ERROR');
                reject(error);
            });

            if (data) {
                req.write(options.body);
            }
            
            req.end();
        });
    }

    async authenticate() {
        this.log('🔑 Attempting authentication...');
        
        const authData = {
            email: 'testadmin@mrvl.com',
            password: 'testpassword123'
        };

        try {
            // Use HTTP for local development
            let response = await this.makeRequest('POST', '/auth/login', authData, true);

            if (response.statusCode === 200 && response.data && (response.data.access_token || response.data.token)) {
                this.authToken = response.data.access_token || response.data.token;
                this.log('✅ Authentication successful');
                return true;
            } else {
                this.log(`❌ Authentication failed: ${JSON.stringify(response)}`, 'ERROR');
                this.addBug('HIGH', 'Authentication', 
                    'Unable to authenticate with admin credentials',
                    '1. POST /auth/login with admin@mrvl.com / password123\n2. Check response status and token',
                    'Verify admin user exists and credentials are correct');
                return false;
            }
        } catch (error) {
            this.log(`❌ Authentication error: ${error.message}`, 'ERROR');
            this.addBug('CRITICAL', 'Authentication', 
                `Authentication endpoint unreachable: ${error.message}`,
                '1. POST /auth/login\n2. Network error occurs',
                'Check API server status and network connectivity');
            return false;
        }
    }

    async testCreatePlayer() {
        this.log('🧪 Testing player creation with comprehensive data...');
        
        const testCases = [
            { data: this.initialPlayerData, useHttp: false },
            { data: this.initialPlayerData, useHttp: true }
        ];

        for (const testCase of testCases) {
            try {
                const response = await this.makeRequest('POST', '/admin/players', testCase.data, testCase.useHttp);
                const protocol = testCase.useHttp ? 'HTTP' : 'HTTPS';
                
                this.log(`${protocol} Create Response: Status ${response.statusCode}`);
                this.log(`${protocol} Response Data: ${JSON.stringify(response.data, null, 2)}`);

                if (response.statusCode === 201 || response.statusCode === 200) {
                    if (response.data && (response.data.id || response.data.player?.id || response.data.data?.id)) {
                        this.testPlayerId = response.data.id || response.data.player?.id || response.data.data?.id;
                        this.log(`✅ Player created successfully via ${protocol} with ID: ${this.testPlayerId}`);
                        
                        // Validate all fields are present
                        const playerData = response.data.player || response.data.data || response.data;
                        this.validatePlayerFields(playerData, this.initialPlayerData, 'CREATE');
                        
                        this.results.createTest = {
                            success: true,
                            playerId: this.testPlayerId,
                            protocol: protocol,
                            responseTime: Date.now(),
                            fieldsValidated: Object.keys(this.initialPlayerData).length
                        };
                        
                        return true; // Success, break out of loop
                    } else {
                        this.addBug('HIGH', 'Functional', 
                            `${protocol}: Player creation response missing ID field`,
                            `1. POST /admin/players with valid data\n2. Check response structure`,
                            'Ensure API returns player ID in response');
                    }
                } else if (response.statusCode === 422) {
                    this.log(`${protocol} Validation errors:`, 'WARN');
                    if (response.data && response.data.errors) {
                        Object.entries(response.data.errors).forEach(([field, errors]) => {
                            this.log(`  - ${field}: ${errors.join(', ')}`, 'WARN');
                            this.addBug('MEDIUM', 'Functional', 
                                `${protocol}: Field validation error for ${field}`,
                                `1. POST /admin/players with field ${field}\n2. Validation error: ${errors.join(', ')}`,
                                `Review validation rules for ${field} field`);
                        });
                    }
                } else {
                    this.addBug('HIGH', 'Functional', 
                        `${protocol}: Player creation failed with status ${response.statusCode}`,
                        `1. POST /admin/players\n2. Response: ${JSON.stringify(response.data)}`,
                        'Check endpoint implementation and database constraints');
                }
            } catch (error) {
                this.log(`❌ Player creation error via ${testCase.useHttp ? 'HTTP' : 'HTTPS'}: ${error.message}`, 'ERROR');
                this.addBug('CRITICAL', 'Integration', 
                    `Player creation endpoint error: ${error.message}`,
                    '1. POST /admin/players\n2. Network/server error occurs',
                    'Check API endpoint availability and server logs');
            }
        }

        this.results.createTest = {
            success: false,
            error: 'Failed to create player via both HTTP and HTTPS'
        };
        
        return false;
    }

    validatePlayerFields(actualData, expectedData, operation) {
        this.log(`🔍 Validating player fields for ${operation} operation...`);
        
        const missingFields = [];
        const incorrectFields = [];
        const typeErrors = [];

        Object.entries(expectedData).forEach(([field, expectedValue]) => {
            if (!(field in actualData)) {
                missingFields.push(field);
                return;
            }

            const actualValue = actualData[field];
            
            // Type validation
            if (typeof expectedValue !== typeof actualValue && actualValue !== null) {
                typeErrors.push({
                    field,
                    expected: typeof expectedValue,
                    actual: typeof actualValue,
                    expectedValue,
                    actualValue
                });
            }
            
            // Value validation for specific types
            if (field.includes('earnings') && typeof expectedValue === 'number') {
                const numericActual = parseFloat(actualValue);
                if (Math.abs(numericActual - expectedValue) > 0.01) {
                    incorrectFields.push({
                        field,
                        expected: expectedValue,
                        actual: numericActual,
                        note: 'Earnings precision mismatch'
                    });
                }
            } else if (Array.isArray(expectedValue)) {
                if (!Array.isArray(actualValue)) {
                    typeErrors.push({
                        field,
                        expected: 'array',
                        actual: typeof actualValue,
                        expectedValue,
                        actualValue
                    });
                }
            } else if (expectedValue !== actualValue && actualValue !== null) {
                // Allow some flexibility for auto-generated or computed fields
                const flexibleFields = ['id', 'created_at', 'updated_at', 'avatar', 'mention_count'];
                if (!flexibleFields.includes(field)) {
                    incorrectFields.push({
                        field,
                        expected: expectedValue,
                        actual: actualValue
                    });
                }
            }
        });

        // Report validation issues
        if (missingFields.length > 0) {
            this.addBug('HIGH', 'Functional', 
                `${operation}: Missing fields in response`,
                `1. Perform ${operation} operation\n2. Check response for fields: ${missingFields.join(', ')}`,
                'Ensure all fields are included in API response');
        }

        if (incorrectFields.length > 0) {
            incorrectFields.forEach(issue => {
                this.addBug('MEDIUM', 'Functional', 
                    `${operation}: Incorrect field value for ${issue.field}`,
                    `1. Perform ${operation} operation\n2. Expected: ${issue.expected}\n3. Actual: ${issue.actual}`,
                    `Verify ${issue.field} field processing and storage`);
            });
        }

        if (typeErrors.length > 0) {
            typeErrors.forEach(issue => {
                this.addBug('MEDIUM', 'Functional', 
                    `${operation}: Type mismatch for ${issue.field}`,
                    `1. Perform ${operation} operation\n2. Expected type: ${issue.expected}\n3. Actual type: ${issue.actual}`,
                    `Ensure proper type casting for ${issue.field} field`);
            });
        }

        this.log(`Field validation complete: ${missingFields.length} missing, ${incorrectFields.length} incorrect, ${typeErrors.length} type errors`);
    }

    async testReadPlayer() {
        if (!this.testPlayerId) {
            this.log('❌ Cannot test player read - no player ID available', 'ERROR');
            return false;
        }

        this.log(`🔍 Testing player read operations for ID: ${this.testPlayerId}...`);

        const endpoints = [
            `/admin/players/${this.testPlayerId}`,
            `/public/players/${this.testPlayerId}`,
            `/players/${this.testPlayerId}`,
            `/public/player-profile/${this.testPlayerId}`
        ];

        let successfulReads = 0;

        for (const endpoint of endpoints) {
            try {
                const response = await this.makeRequest('GET', endpoint, null, true);
                
                this.log(`GET ${endpoint}: Status ${response.statusCode}`);

                if (response.statusCode === 200) {
                    successfulReads++;
                    const playerData = response.data.player || response.data.data || response.data;
                    
                    if (playerData && playerData.id == this.testPlayerId) {
                        this.log(`✅ Successfully read player via ${endpoint}`);
                        this.validatePlayerFields(playerData, this.initialPlayerData, 'READ');
                    } else {
                        this.addBug('HIGH', 'Functional', 
                            `Read endpoint returns incorrect player data`,
                            `1. GET ${endpoint}\n2. Check player ID in response`,
                            'Verify player lookup and response formatting');
                    }
                } else if (response.statusCode === 404) {
                    this.addBug('HIGH', 'Functional', 
                        `Player not found via ${endpoint}`,
                        `1. Create player\n2. GET ${endpoint}\n3. Receives 404`,
                        'Check player storage and endpoint routing');
                } else {
                    this.addBug('MEDIUM', 'Functional', 
                        `Read endpoint error: ${response.statusCode}`,
                        `1. GET ${endpoint}\n2. Unexpected status code`,
                        'Check endpoint implementation and error handling');
                }
            } catch (error) {
                this.addBug('HIGH', 'Integration', 
                    `Read endpoint network error: ${error.message}`,
                    `1. GET ${endpoint}\n2. Network error occurs`,
                    'Check endpoint availability and network connectivity');
            }
        }

        this.results.readTest = {
            success: successfulReads > 0,
            successfulEndpoints: successfulReads,
            totalEndpoints: endpoints.length
        };

        return successfulReads > 0;
    }

    async testUpdatePlayer() {
        if (!this.testPlayerId) {
            this.log('❌ Cannot test player update - no player ID available', 'ERROR');
            return false;
        }

        this.log(`✏️ Testing player update operations for ID: ${this.testPlayerId}...`);

        try {
            const response = await this.makeRequest('PUT', `/admin/players/${this.testPlayerId}`, this.updatedPlayerData, true);
            
            this.log(`Update Response: Status ${response.statusCode}`);
            this.log(`Update Response Data: ${JSON.stringify(response.data, null, 2)}`);

            if (response.statusCode === 200) {
                const updatedPlayer = response.data.player || response.data.data || response.data;
                
                if (updatedPlayer) {
                    this.log('✅ Player updated successfully');
                    this.validatePlayerFields(updatedPlayer, this.updatedPlayerData, 'UPDATE');
                    
                    // Verify changes were applied immediately
                    await this.verifyImmediateUpdates();
                    
                    this.results.updateTest = {
                        success: true,
                        fieldsUpdated: Object.keys(this.updatedPlayerData).length
                    };
                    
                    return true;
                } else {
                    this.addBug('HIGH', 'Functional', 
                        'Update response missing player data',
                        '1. PUT /admin/players/{id}\n2. Check response structure',
                        'Ensure update endpoint returns updated player data');
                }
            } else if (response.statusCode === 422) {
                this.log('Update validation errors:', 'WARN');
                if (response.data && response.data.errors) {
                    Object.entries(response.data.errors).forEach(([field, errors]) => {
                        this.log(`  - ${field}: ${errors.join(', ')}`, 'WARN');
                        this.addBug('MEDIUM', 'Functional', 
                            `Update validation error for ${field}`,
                            `1. PUT /admin/players/{id}\n2. Validation error: ${errors.join(', ')}`,
                            `Review update validation rules for ${field} field`);
                    });
                }
            } else {
                this.addBug('HIGH', 'Functional', 
                    `Player update failed with status ${response.statusCode}`,
                    `1. PUT /admin/players/{id}\n2. Response: ${JSON.stringify(response.data)}`,
                    'Check update endpoint implementation and constraints');
            }
        } catch (error) {
            this.addBug('CRITICAL', 'Integration', 
                `Player update error: ${error.message}`,
                '1. PUT /admin/players/{id}\n2. Network/server error occurs',
                'Check update endpoint availability and server logs');
        }

        this.results.updateTest = {
            success: false,
            error: 'Update operation failed'
        };

        return false;
    }

    async verifyImmediateUpdates() {
        this.log('🔄 Verifying immediate update propagation...');
        
        try {
            // Wait a moment for any async processing
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            const response = await this.makeRequest('GET', `/admin/players/${this.testPlayerId}`, null, true);
            
            if (response.statusCode === 200) {
                const currentPlayer = response.data.player || response.data.data || response.data;
                
                // Check key fields that should have been updated
                const keyFields = ['username', 'real_name', 'role', 'nationality', 'team_id', 'earnings'];
                const staleFields = [];
                
                keyFields.forEach(field => {
                    if (currentPlayer[field] != this.updatedPlayerData[field]) {
                        staleFields.push({
                            field,
                            expected: this.updatedPlayerData[field],
                            actual: currentPlayer[field]
                        });
                    }
                });
                
                if (staleFields.length > 0) {
                    this.addBug('HIGH', 'Performance', 
                        'Updates not immediately reflected in read operations',
                        '1. Update player\n2. Immediately read player\n3. Changes not visible',
                        'Check for caching issues or async update processing');
                    
                    staleFields.forEach(issue => {
                        this.log(`  Stale field ${issue.field}: expected ${issue.expected}, got ${issue.actual}`, 'WARN');
                    });
                } else {
                    this.log('✅ All updates immediately visible');
                }
            }
        } catch (error) {
            this.addBug('MEDIUM', 'Performance', 
                `Unable to verify immediate updates: ${error.message}`,
                '1. Update player\n2. Read player to verify changes\n3. Error occurs',
                'Check read endpoint availability after updates');
        }
    }

    async testErrorHandling() {
        this.log('🚨 Testing error handling scenarios...');

        const errorTests = [
            {
                name: 'Invalid team_id',
                data: { ...this.initialPlayerData, team_id: 99999, username: 'invalid_team_test' },
                expectedStatus: 422
            },
            {
                name: 'Missing required fields',
                data: { username: 'missing_fields_test' },
                expectedStatus: 422
            },
            {
                name: 'Invalid earnings format',
                data: { ...this.initialPlayerData, earnings: 'not_a_number', username: 'invalid_earnings_test' },
                expectedStatus: 422
            },
            {
                name: 'Invalid social media URLs',
                data: { ...this.initialPlayerData, twitter: 'not_a_url', username: 'invalid_social_test' },
                expectedStatus: 422
            },
            {
                name: 'Duplicate username',
                data: { ...this.initialPlayerData, username: 'duplicate_user_test' },
                expectedStatus: 422
            }
        ];

        let passedTests = 0;

        for (const test of errorTests) {
            try {
                this.log(`Testing: ${test.name}`);
                
                // For duplicate username test, create the user first
                if (test.name === 'Duplicate username') {
                    await this.makeRequest('POST', '/admin/players', test.data, true);
                }
                
                const response = await this.makeRequest('POST', '/admin/players', test.data, true);
                
                if (response.statusCode === test.expectedStatus) {
                    this.log(`✅ ${test.name}: Correctly handled with status ${response.statusCode}`);
                    passedTests++;
                } else {
                    this.addBug('MEDIUM', 'Functional', 
                        `Error handling test failed: ${test.name}`,
                        `1. POST /admin/players with invalid data\n2. Expected status ${test.expectedStatus}, got ${response.statusCode}`,
                        'Review validation rules and error responses');
                }
                
                // Clean up duplicate test user
                if (test.name === 'Duplicate username' && response.data && response.data.id) {
                    await this.makeRequest('DELETE', `/admin/players/${response.data.id}`, null, true);
                }
                
            } catch (error) {
                this.addBug('MEDIUM', 'Integration', 
                    `Error handling test failed: ${test.name} - ${error.message}`,
                    `1. POST /admin/players with invalid data\n2. Network error occurs`,
                    'Check endpoint availability and error handling');
            }
        }

        this.results.errorHandlingTest = {
            success: passedTests === errorTests.length,
            passedTests,
            totalTests: errorTests.length
        };

        return passedTests === errorTests.length;
    }

    async testProfilePageDisplay() {
        if (!this.testPlayerId) {
            this.log('❌ Cannot test profile page - no player ID available', 'ERROR');
            return false;
        }

        this.log('🖥️ Testing player profile page display...');

        const profileEndpoints = [
            `/public/player-profile/${this.testPlayerId}`,
            `/public/players/${this.testPlayerId}`,
            `/public/players/${this.testPlayerId}/matches`,
            `/public/players/${this.testPlayerId}/stats`,
            `/public/players/${this.testPlayerId}/team-history`
        ];

        let workingEndpoints = 0;

        for (const endpoint of profileEndpoints) {
            try {
                const response = await this.makeRequest('GET', endpoint, null, true);
                
                if (response.statusCode === 200) {
                    workingEndpoints++;
                    this.log(`✅ Profile endpoint working: ${endpoint}`);
                    
                    // Check for display-critical fields
                    const data = response.data.player || response.data.data || response.data;
                    if (data) {
                        const criticalFields = ['username', 'real_name', 'role', 'team_id'];
                        const missingCritical = criticalFields.filter(field => !data[field]);
                        
                        if (missingCritical.length > 0) {
                            this.addBug('HIGH', 'Usability', 
                                `Profile missing critical display fields: ${missingCritical.join(', ')}`,
                                `1. GET ${endpoint}\n2. Check response for display fields`,
                                'Ensure critical fields are included in profile responses');
                        }
                    }
                } else {
                    this.addBug('MEDIUM', 'Usability', 
                        `Profile endpoint not accessible: ${endpoint}`,
                        `1. GET ${endpoint}\n2. Status: ${response.statusCode}`,
                        'Check profile endpoint implementation and routing');
                }
            } catch (error) {
                this.addBug('HIGH', 'Usability', 
                    `Profile endpoint error: ${endpoint} - ${error.message}`,
                    `1. GET ${endpoint}\n2. Network error occurs`,
                    'Check profile endpoint availability');
            }
        }

        this.results.profileDisplayTest = {
            success: workingEndpoints >= profileEndpoints.length / 2,
            workingEndpoints,
            totalEndpoints: profileEndpoints.length
        };

        return workingEndpoints >= profileEndpoints.length / 2;
    }

    async cleanup() {
        if (this.testPlayerId) {
            this.log(`🧹 Cleaning up test player ID: ${this.testPlayerId}...`);
            
            try {
                const response = await this.makeRequest('DELETE', `/admin/players/${this.testPlayerId}`, null, true);
                
                if (response.statusCode === 200 || response.statusCode === 204) {
                    this.log('✅ Test player cleaned up successfully');
                } else {
                    this.log(`⚠️ Failed to cleanup test player: Status ${response.statusCode}`, 'WARN');
                }
            } catch (error) {
                this.log(`⚠️ Cleanup error: ${error.message}`, 'WARN');
            }
        }
    }

    generateReport() {
        const report = {
            test_summary: {
                total_bugs_found: this.bugs.length,
                critical_bugs: this.bugs.filter(b => b.severity === 'CRITICAL').length,
                high_bugs: this.bugs.filter(b => b.severity === 'HIGH').length,
                medium_bugs: this.bugs.filter(b => b.severity === 'MEDIUM').length,
                low_bugs: this.bugs.filter(b => b.severity === 'LOW').length,
                test_execution_time: new Date().toISOString(),
                test_player_id: this.testPlayerId
            },
            test_results: this.results,
            bugs_by_category: {
                functional: this.bugs.filter(b => b.classification === 'Functional'),
                security: this.bugs.filter(b => b.classification === 'Security'),
                performance: this.bugs.filter(b => b.classification === 'Performance'),
                usability: this.bugs.filter(b => b.classification === 'Usability'),
                integration: this.bugs.filter(b => b.classification === 'Integration')
            },
            detailed_bugs: this.bugs,
            recommendations: this.generateRecommendations()
        };

        const reportFile = `/var/www/mrvl-backend/comprehensive_player_crud_test_report_${Date.now()}.json`;
        
        try {
            fs.writeFileSync(reportFile, JSON.stringify(report, null, 2));
            this.log(`📊 Comprehensive test report saved to: ${reportFile}`);
        } catch (error) {
            this.log(`❌ Failed to save report: ${error.message}`, 'ERROR');
        }

        return report;
    }

    generateRecommendations() {
        const recommendations = [];

        if (this.bugs.some(b => b.classification === 'Authentication')) {
            recommendations.push({
                priority: 'HIGH',
                category: 'Security',
                action: 'Fix authentication issues immediately',
                description: 'Authentication problems prevent proper testing and indicate security vulnerabilities'
            });
        }

        if (this.bugs.some(b => b.description.includes('Missing fields'))) {
            recommendations.push({
                priority: 'HIGH',
                category: 'Data Integrity',
                action: 'Ensure all fields are properly stored and retrieved',
                description: 'Missing fields in API responses can break frontend functionality'
            });
        }

        if (this.bugs.some(b => b.description.includes('validation'))) {
            recommendations.push({
                priority: 'MEDIUM',
                category: 'Input Validation',
                action: 'Review and strengthen input validation rules',
                description: 'Proper validation prevents data corruption and security issues'
            });
        }

        if (this.bugs.some(b => b.classification === 'Performance')) {
            recommendations.push({
                priority: 'MEDIUM',
                category: 'Performance',
                action: 'Address caching and immediate update issues',
                description: 'Users expect changes to be immediately visible after updates'
            });
        }

        return recommendations;
    }

    async runComprehensiveTest() {
        this.log('🚀 Starting comprehensive player CRUD operations test...');
        
        const startTime = Date.now();
        
        try {
            // Step 1: Authentication
            if (!await this.authenticate()) {
                throw new Error('Authentication failed - cannot proceed with tests');
            }

            // Step 2: Create Player
            if (!await this.testCreatePlayer()) {
                this.log('❌ Player creation failed - proceeding with available tests', 'WARN');
            }

            // Step 3: Read Player
            await this.testReadPlayer();

            // Step 4: Update Player
            await this.testUpdatePlayer();

            // Step 5: Test Error Handling
            await this.testErrorHandling();

            // Step 6: Test Profile Display
            await this.testProfilePageDisplay();

            // Step 7: Cleanup
            await this.cleanup();

        } catch (error) {
            this.log(`❌ Test execution error: ${error.message}`, 'ERROR');
            this.addBug('CRITICAL', 'Integration', 
                `Test execution failed: ${error.message}`,
                '1. Run comprehensive test suite\n2. Execution error occurs',
                'Check test environment setup and API availability');
        }

        const endTime = Date.now();
        const executionTime = (endTime - startTime) / 1000;
        
        this.log(`🏁 Test execution completed in ${executionTime} seconds`);
        this.log(`📊 Total bugs found: ${this.bugs.length}`);
        
        // Generate and return report
        const report = this.generateReport();
        
        // Print summary
        console.log('\n' + '='.repeat(80));
        console.log('COMPREHENSIVE PLAYER CRUD TEST SUMMARY');
        console.log('='.repeat(80));
        console.log(`Test Player ID: ${this.testPlayerId || 'N/A'}`);
        console.log(`Total Bugs Found: ${this.bugs.length}`);
        console.log(`Critical: ${report.test_summary.critical_bugs}`);
        console.log(`High: ${report.test_summary.high_bugs}`);
        console.log(`Medium: ${report.test_summary.medium_bugs}`);
        console.log(`Low: ${report.test_summary.low_bugs}`);
        console.log(`Execution Time: ${executionTime} seconds`);
        console.log('='.repeat(80));
        
        if (this.bugs.length > 0) {
            console.log('\nTOP CRITICAL ISSUES:');
            this.bugs.filter(b => b.severity === 'CRITICAL' || b.severity === 'HIGH')
                    .slice(0, 5)
                    .forEach((bug, index) => {
                        console.log(`${index + 1}. [${bug.severity}] ${bug.classification}: ${bug.description}`);
                    });
        }
        
        return report;
    }
}

// Run the test
const tester = new PlayerCRUDTester();
tester.runComprehensiveTest().then(report => {
    console.log('\n✅ Comprehensive player CRUD test completed successfully');
    process.exit(0);
}).catch(error => {
    console.error('❌ Test execution failed:', error.message);
    process.exit(1);
});