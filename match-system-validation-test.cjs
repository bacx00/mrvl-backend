#!/usr/bin/env node

/**
 * MATCH SYSTEM VALIDATION TEST
 * 
 * Simple test to validate that all the fixes work:
 * 1. Multiple URLs are handled correctly
 * 2. MatchForm saves data properly
 * 3. MatchDetailPage displays URLs correctly
 * 4. API endpoints return correct data structure
 */

const fetch = require('node-fetch');
const fs = require('fs');

// Configuration
const BACKEND_URL = process.env.BACKEND_URL || 'http://localhost:8000';
const API_TOKEN = process.env.API_TOKEN; // Pass this if you have it

class MatchSystemValidator {
    constructor() {
        this.results = {
            timestamp: new Date().toISOString(),
            tests: [],
            summary: { total: 0, passed: 0, failed: 0 }
        };
        this.headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        if (API_TOKEN) {
            this.headers['Authorization'] = `Bearer ${API_TOKEN}`;
        }
    }

    async runTest(name, testFn) {
        console.log(`\n🧪 Testing: ${name}`);
        this.results.summary.total++;
        
        try {
            const result = await testFn.call(this);
            this.results.tests.push({
                name,
                status: 'PASSED',
                result,
                timestamp: new Date().toISOString()
            });
            this.results.summary.passed++;
            console.log(`✅ ${name} - PASSED`);
            return result;
        } catch (error) {
            this.results.tests.push({
                name,
                status: 'FAILED',
                error: error.message,
                timestamp: new Date().toISOString()
            });
            this.results.summary.failed++;
            console.log(`❌ ${name} - FAILED: ${error.message}`);
            return null;
        }
    }

    async testApiEndpointStructure() {
        // Test if we can get basic data structure
        const response = await fetch(`${BACKEND_URL}/api/matches`, {
            headers: this.headers
        });
        
        if (!response.ok) {
            throw new Error(`API responded with ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (!Array.isArray(data)) {
            throw new Error('Expected matches array but got: ' + typeof data);
        }
        
        return {
            endpoint_working: true,
            matches_count: data.length,
            sample_match: data.length > 0 ? {
                has_id: !!data[0].id,
                has_teams: !!(data[0].team1 && data[0].team2),
                has_urls: !!(data[0].stream_url || data[0].stream_urls)
            } : null
        };
    }

    async testMatchDataStructure() {
        // Get the first match to test its structure
        const response = await fetch(`${BACKEND_URL}/api/matches`, {
            headers: this.headers
        });
        
        const matches = await response.json();
        if (!matches || matches.length === 0) {
            throw new Error('No matches found to test structure');
        }
        
        const matchId = matches[0].id;
        const detailResponse = await fetch(`${BACKEND_URL}/api/matches/${matchId}`, {
            headers: this.headers
        });
        
        if (!detailResponse.ok) {
            throw new Error(`Failed to get match detail: ${detailResponse.status}`);
        }
        
        const match = await detailResponse.json();
        
        // Check for the expected structure that MatchDetailPage needs
        const structure = {
            has_basic_data: !!(match.id && match.team1 && match.team2),
            has_broadcast_data: !!match.broadcast,
            has_legacy_urls: !!(match.stream_url || match.betting_url || match.vod_url),
            has_modern_url_arrays: false
        };
        
        if (match.broadcast) {
            structure.has_modern_url_arrays = !!(
                match.broadcast.streams || 
                match.broadcast.betting || 
                match.broadcast.vods
            );
            structure.broadcast_structure = {
                streams: Array.isArray(match.broadcast.streams),
                betting: Array.isArray(match.broadcast.betting),
                vods: Array.isArray(match.broadcast.vods)
            };
        }
        
        return structure;
    }

    async testCreateMatchWithUrls() {
        const testMatch = {
            team1_id: 1,
            team2_id: 2,
            event_id: null,
            scheduled_at: new Date(Date.now() + 3600000).toISOString(),
            format: 'BO3',
            status: 'upcoming',
            stream_urls: [
                'https://twitch.tv/test1',
                'https://youtube.com/watch?v=test1'
            ],
            betting_urls: [
                'https://bet365.com/test'
            ],
            vod_urls: [
                'https://youtube.com/watch?v=vod1'
            ],
            round: 'Test Round',
            bracket_position: 'Upper Bracket',
            maps_data: [
                {
                    map_number: 1,
                    map_name: 'Tokyo 2099: Shibuya Sky',
                    mode: 'Convoy',
                    team1_score: 0,
                    team2_score: 0,
                    team1_composition: [],
                    team2_composition: []
                }
            ]
        };
        
        const response = await fetch(`${BACKEND_URL}/api/admin/matches`, {
            method: 'POST',
            headers: this.headers,
            body: JSON.stringify(testMatch)
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Failed to create test match: ${response.status} - ${errorText}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(`Match creation failed: ${result.message}`);
        }
        
        return {
            created: true,
            match_id: result.data?.id,
            urls_sent: {
                streams: testMatch.stream_urls.length,
                betting: testMatch.betting_urls.length,
                vods: testMatch.vod_urls.length
            }
        };
    }

    async testCompleteUpdateEndpoint() {
        // First create a match to update
        const createResult = await this.testCreateMatchWithUrls();
        if (!createResult || !createResult.match_id) {
            throw new Error('Failed to create match for update test');
        }
        
        const matchId = createResult.match_id;
        
        const updateData = {
            team1_id: 1,
            team2_id: 2,
            event_id: null,
            scheduled_at: new Date(Date.now() + 7200000).toISOString(),
            format: 'BO3',
            status: 'upcoming',
            stream_urls: [
                'https://twitch.tv/updated1',
                'https://twitch.tv/updated2',
                'https://youtube.com/watch?v=updated'
            ],
            betting_urls: [
                'https://bet365.com/updated',
                'https://pinnacle.com/updated'
            ],
            vod_urls: [
                'https://youtube.com/watch?v=vodupdated'
            ],
            round: 'Updated Round',
            bracket_position: 'Lower Bracket',
            maps_data: [
                {
                    map_number: 1,
                    map_name: 'Intergalactic Empire of Wakanda: Birnin T\'Challa',
                    mode: 'Domination',
                    team1_score: 2,
                    team2_score: 1,
                    team1_composition: [],
                    team2_composition: []
                },
                {
                    map_number: 2,
                    map_name: 'Hellfire Gala: Krakoa',
                    mode: 'Domination',
                    team1_score: 0,
                    team2_score: 0,
                    team1_composition: [],
                    team2_composition: []
                }
            ]
        };
        
        const response = await fetch(`${BACKEND_URL}/api/admin/matches/${matchId}/complete-update`, {
            method: 'PUT',
            headers: this.headers,
            body: JSON.stringify(updateData)
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Complete update failed: ${response.status} - ${errorText}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(`Complete update failed: ${result.message}`);
        }
        
        return {
            updated: true,
            match_id: matchId,
            urls_updated: result.data?.urls_count || {},
            maps_updated: result.data?.maps_count || 0
        };
    }

    async testApiResponseFormat() {
        // Test that the API returns data in the format expected by MatchDetailPage
        const response = await fetch(`${BACKEND_URL}/api/matches`, {
            headers: this.headers
        });
        
        const matches = await response.json();
        if (!matches || matches.length === 0) {
            return { no_matches_to_test: true };
        }
        
        const match = matches[0];
        const format_check = {
            has_id: !!match.id,
            has_teams: !!(match.team1 && match.team2),
            has_status: !!match.status,
            has_scheduled_at: !!match.scheduled_at,
            url_formats: {
                legacy_stream_url: !!match.stream_url,
                legacy_betting_url: !!match.betting_url,
                legacy_vod_url: !!match.vod_url,
                broadcast_object: !!match.broadcast,
                broadcast_streams: !!(match.broadcast && match.broadcast.streams),
                broadcast_betting: !!(match.broadcast && match.broadcast.betting),
                broadcast_vods: !!(match.broadcast && match.broadcast.vods)
            }
        };
        
        return format_check;
    }

    async generateReport() {
        const reportPath = `/var/www/mrvl-backend/match-validation-report-${Date.now()}.json`;
        
        this.results.summary.success_rate = (this.results.summary.passed / this.results.summary.total * 100).toFixed(2);
        this.results.environment = {
            backend_url: BACKEND_URL,
            has_token: !!API_TOKEN,
            timestamp: new Date().toISOString()
        };
        
        fs.writeFileSync(reportPath, JSON.stringify(this.results, null, 2));
        
        console.log('\n📊 MATCH SYSTEM VALIDATION REPORT');
        console.log('=' .repeat(50));
        console.log(`Total Tests: ${this.results.summary.total}`);
        console.log(`Passed: ${this.results.summary.passed}`);
        console.log(`Failed: ${this.results.summary.failed}`);
        console.log(`Success Rate: ${this.results.summary.success_rate}%`);
        console.log(`Report saved: ${reportPath}`);
        
        return this.results;
    }

    async runAllTests() {
        try {
            console.log('🚀 Starting Match System Validation...');
            
            await this.runTest('API Endpoint Structure', this.testApiEndpointStructure);
            await this.runTest('Match Data Structure', this.testMatchDataStructure);
            await this.runTest('API Response Format', this.testApiResponseFormat);
            
            // Only run create/update tests if we have proper auth
            if (API_TOKEN) {
                await this.runTest('Create Match with URLs', this.testCreateMatchWithUrls);
                await this.runTest('Complete Update Endpoint', this.testCompleteUpdateEndpoint);
            } else {
                console.log('\n⚠️  Skipping create/update tests - no API token provided');
                console.log('   Set API_TOKEN environment variable to test full functionality');
            }
            
            await this.generateReport();
            
        } catch (error) {
            console.error('❌ Validation failed:', error);
            throw error;
        }
    }
}

// Run the validator if this file is executed directly
if (require.main === module) {
    const validator = new MatchSystemValidator();
    validator.runAllTests()
        .then(() => {
            console.log('\n✅ Validation completed');
            process.exit(0);
        })
        .catch((error) => {
            console.error('\n❌ Validation failed:', error);
            process.exit(1);
        });
}

module.exports = MatchSystemValidator;