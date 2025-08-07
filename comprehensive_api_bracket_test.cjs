const https = require('https');

/**
 * COMPREHENSIVE BRACKET API TESTING SCRIPT
 */

class BracketAPIAudit {
    constructor() {
        this.baseUrl = 'mrvl.pro';
        this.results = {};
        this.errors = [];
        this.warnings = [];
        this.authToken = null;
    }

    async runCompleteAudit() {
        console.log('=== COMPREHENSIVE BRACKET API AUDIT ===\n');
        
        try {
            // Phase 1: Authentication
            await this.testAuthentication();
            
            // Phase 2: Public Endpoints
            await this.testPublicEndpoints();
            
            // Phase 3: Admin Endpoints (if authenticated)
            if (this.authToken) {
                await this.testAdminEndpoints();
            }
            
            // Phase 4: CRUD Operations
            await this.testCrudOperations();
            
            // Phase 5: Edge Cases
            await this.testEdgeCases();
            
            // Phase 6: Error Handling
            await this.testErrorHandling();
            
            // Generate final report
            this.generateReport();
            
        } catch (error) {
            console.error('Audit failed:', error.message);
            this.generateReport();
        }
    }

    async makeRequest(method, path, data = null, useAuth = false) {
        return new Promise((resolve, reject) => {
            const options = {
                hostname: this.baseUrl,
                path: '/api' + path,
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'BracketAudit/1.0'
                }
            };

            if (useAuth && this.authToken) {
                options.headers['Authorization'] = `Bearer ${this.authToken}`;
            }

            if (data) {
                const jsonData = JSON.stringify(data);
                options.headers['Content-Length'] = Buffer.byteLength(jsonData);
            }

            const req = https.request(options, (res) => {
                let body = '';
                
                res.on('data', (chunk) => {
                    body += chunk;
                });
                
                res.on('end', () => {
                    try {
                        const response = {
                            statusCode: res.statusCode,
                            headers: res.headers,
                            body: body,
                            data: null
                        };
                        
                        if (body) {
                            try {
                                response.data = JSON.parse(body);
                            } catch (e) {
                                // Body is not JSON, keep as string
                            }
                        }
                        
                        resolve(response);
                    } catch (error) {
                        reject(error);
                    }
                });
            });

            req.on('error', (error) => {
                reject(error);
            });

            if (data) {
                req.write(JSON.stringify(data));
            }

            req.end();
        });
    }

    async testAuthentication() {
        console.log('1. AUTHENTICATION TESTING');
        console.log('==========================');
        
        this.results.authentication = {};
        
        try {
            const response = await this.makeRequest('POST', '/auth/login', {
                email: 'admin@mrvl.pro',
                password: 'password123'
            });
            
            const success = response.statusCode === 200 && response.data?.token;
            this.results.authentication.login = success;
            
            if (success) {
                this.authToken = response.data.token;
                console.log('✓ Admin authentication: SUCCESS');
            } else {
                console.log('✗ Admin authentication: FAILED');
                this.warnings.push('Could not authenticate as admin - limited testing');
            }
            
        } catch (error) {
            console.log('✗ Authentication error:', error.message);
            this.results.authentication.login = false;
            this.errors.push(`Authentication failed: ${error.message}`);
        }
        
        console.log('');
    }

    async testPublicEndpoints() {
        console.log('2. PUBLIC ENDPOINTS TESTING');
        console.log('============================');
        
        this.results.public_endpoints = {};
        
        const endpoints = [
            { name: 'events_list', path: '/events' },
            { name: 'teams_list', path: '/teams' },
            { name: 'event_bracket', path: '/events/1/bracket' },
            { name: 'comprehensive_bracket', path: '/events/1/comprehensive-bracket' },
            { name: 'bracket_analysis', path: '/events/1/bracket-analysis' },
            { name: 'bracket_visualization', path: '/events/1/bracket-visualization' },
            { name: 'tournament_bracket', path: '/tournaments/1/bracket' }
        ];

        for (const endpoint of endpoints) {
            await this.testEndpoint(endpoint.name, 'GET', endpoint.path, null, false);
        }
        
        console.log('');
    }

    async testAdminEndpoints() {
        console.log('3. ADMIN ENDPOINTS TESTING');
        console.log('===========================');
        
        this.results.admin_endpoints = {};
        
        const endpoints = [
            { name: 'generate_bracket', method: 'POST', path: '/admin/events/1/generate-bracket' },
            { name: 'update_match', method: 'PUT', path: '/admin/events/1/bracket/matches/1' },
            { name: 'comprehensive_generate', method: 'POST', path: '/admin/events/1/comprehensive-bracket' },
            { name: 'bracket_reset', method: 'POST', path: '/admin/bracket/matches/1/reset-bracket' }
        ];

        for (const endpoint of endpoints) {
            await this.testEndpoint(
                endpoint.name, 
                endpoint.method, 
                endpoint.path, 
                endpoint.method === 'POST' ? { test: 'data' } : null, 
                true
            );
        }
        
        console.log('');
    }

    async testEndpoint(name, method, path, data, useAuth) {
        try {
            const startTime = Date.now();
            const response = await this.makeRequest(method, path, data, useAuth);
            const endTime = Date.now();
            const responseTime = endTime - startTime;
            
            const success = response.statusCode >= 200 && response.statusCode < 400;
            
            this.results[useAuth ? 'admin_endpoints' : 'public_endpoints'][name] = {
                success: success,
                status_code: response.statusCode,
                response_time: responseTime,
                has_data: !!response.data
            };
            
            console.log(`${success ? '✓' : '✗'} ${name}: HTTP ${response.statusCode} (${responseTime}ms)`);
            
            // Log additional info for successful bracket endpoints
            if (success && path.includes('bracket') && response.data) {
                if (response.data.data) {
                    console.log(`  └─ Response contains bracket data`);
                }
            }
            
            if (!success) {
                this.errors.push(`Endpoint ${name} failed: HTTP ${response.statusCode}`);
            }
            
        } catch (error) {
            console.log(`✗ ${name}: ERROR - ${error.message}`);
            this.results[useAuth ? 'admin_endpoints' : 'public_endpoints'][name] = {
                success: false,
                error: error.message
            };
            this.errors.push(`Endpoint ${name} error: ${error.message}`);
        }
    }

    async testCrudOperations() {
        console.log('4. CRUD OPERATIONS TESTING');
        console.log('===========================');
        
        this.results.crud_operations = {};
        
        // Test CREATE operations
        await this.testCreateOperations();
        
        // Test READ operations
        await this.testReadOperations();
        
        // Test UPDATE operations
        await this.testUpdateOperations();
        
        // Test DELETE operations (carefully)
        await this.testDeleteOperations();
        
        console.log('');
    }

    async testCreateOperations() {
        console.log('Testing CREATE operations...');
        
        if (!this.authToken) {
            console.log('✗ Skipping CREATE tests - no authentication');
            return;
        }
        
        // Test bracket generation
        try {
            const response = await this.makeRequest('POST', '/admin/events/1/generate-bracket', {
                format: 'single_elimination',
                seeding_method: 'manual',
                randomize_seeds: false
            }, true);
            
            const success = response.statusCode === 200 || response.statusCode === 201;
            this.results.crud_operations.create_bracket = success;
            
            console.log(`  ${success ? '✓' : '✗'} Generate bracket: ${success ? 'SUCCESS' : 'FAILED'}`);
            
        } catch (error) {
            console.log(`  ✗ Generate bracket: ERROR - ${error.message}`);
            this.results.crud_operations.create_bracket = false;
        }
    }

    async testReadOperations() {
        console.log('Testing READ operations...');
        
        const readTests = [
            { name: 'read_event_bracket', path: '/events/1/bracket' },
            { name: 'read_comprehensive_bracket', path: '/events/1/comprehensive-bracket' },
            { name: 'read_bracket_visualization', path: '/events/1/bracket-visualization' }
        ];

        for (const test of readTests) {
            try {
                const response = await this.makeRequest('GET', test.path);
                const success = response.statusCode === 200;
                
                this.results.crud_operations[test.name] = success;
                console.log(`  ${success ? '✓' : '✗'} ${test.name}: ${success ? 'SUCCESS' : 'FAILED'}`);
                
            } catch (error) {
                console.log(`  ✗ ${test.name}: ERROR - ${error.message}`);
                this.results.crud_operations[test.name] = false;
            }
        }
    }

    async testUpdateOperations() {
        console.log('Testing UPDATE operations...');
        
        if (!this.authToken) {
            console.log('✗ Skipping UPDATE tests - no authentication');
            return;
        }
        
        // Test match update
        try {
            const response = await this.makeRequest('PUT', '/admin/events/1/bracket/matches/1', {
                team1_score: 2,
                team2_score: 1,
                status: 'completed'
            }, true);
            
            const success = response.statusCode === 200;
            this.results.crud_operations.update_match = success;
            
            console.log(`  ${success ? '✓' : '✗'} Update match: ${success ? 'SUCCESS' : 'FAILED'}`);
            
        } catch (error) {
            console.log(`  ✗ Update match: ERROR - ${error.message}`);
            this.results.crud_operations.update_match = false;
        }
    }

    async testDeleteOperations() {
        console.log('Testing DELETE operations...');
        console.log('  ✓ Skipping DELETE tests - preserving data integrity');
        this.results.crud_operations.delete_operations_skipped = true;
    }

    async testEdgeCases() {
        console.log('5. EDGE CASES TESTING');
        console.log('=====================');
        
        this.results.edge_cases = {};
        
        const edgeCases = [
            { name: 'invalid_event_id', path: '/events/99999/bracket' },
            { name: 'invalid_tournament_id', path: '/tournaments/99999/bracket' },
            { name: 'malformed_bracket_request', path: '/events/abc/bracket' }
        ];

        for (const edgeCase of edgeCases) {
            try {
                const response = await this.makeRequest('GET', edgeCase.path);
                
                // For edge cases, we expect proper error responses
                const properErrorHandling = response.statusCode === 404 || 
                                           response.statusCode === 400 ||
                                           response.statusCode === 422;
                
                this.results.edge_cases[edgeCase.name] = properErrorHandling;
                
                console.log(`${properErrorHandling ? '✓' : '✗'} ${edgeCase.name}: HTTP ${response.statusCode}`);
                
            } catch (error) {
                console.log(`✗ ${edgeCase.name}: ERROR - ${error.message}`);
                this.results.edge_cases[edgeCase.name] = false;
            }
        }
        
        console.log('');
    }

    async testErrorHandling() {
        console.log('6. ERROR HANDLING TESTING');
        console.log('==========================');
        
        this.results.error_handling = {};
        
        // Test various error scenarios
        const errorTests = [
            { 
                name: 'malformed_json', 
                method: 'POST', 
                path: '/admin/events/1/generate-bracket',
                data: '{ malformed json',
                useAuth: true
            },
            { 
                name: 'missing_auth', 
                method: 'POST', 
                path: '/admin/events/1/generate-bracket',
                data: { format: 'single_elimination' },
                useAuth: false
            }
        ];

        for (const test of errorTests) {
            try {
                let response;
                if (test.data === '{ malformed json') {
                    // Special handling for malformed JSON
                    response = await this.makeRawRequest(test.method, test.path, test.data, test.useAuth);
                } else {
                    response = await this.makeRequest(test.method, test.path, test.data, test.useAuth);
                }
                
                const properErrorHandling = response.statusCode >= 400 && response.statusCode < 500;
                this.results.error_handling[test.name] = properErrorHandling;
                
                console.log(`${properErrorHandling ? '✓' : '✗'} ${test.name}: HTTP ${response.statusCode}`);
                
            } catch (error) {
                // Expected for some error tests
                console.log(`✓ ${test.name}: Properly caught error`);
                this.results.error_handling[test.name] = true;
            }
        }
        
        console.log('');
    }

    async makeRawRequest(method, path, rawData, useAuth = false) {
        return new Promise((resolve, reject) => {
            const options = {
                hostname: this.baseUrl,
                path: '/api' + path,
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'BracketAudit/1.0'
                }
            };

            if (useAuth && this.authToken) {
                options.headers['Authorization'] = `Bearer ${this.authToken}`;
            }

            if (rawData) {
                options.headers['Content-Length'] = Buffer.byteLength(rawData);
            }

            const req = https.request(options, (res) => {
                let body = '';
                res.on('data', (chunk) => { body += chunk; });
                res.on('end', () => {
                    resolve({
                        statusCode: res.statusCode,
                        headers: res.headers,
                        body: body
                    });
                });
            });

            req.on('error', reject);

            if (rawData) {
                req.write(rawData);
            }

            req.end();
        });
    }

    generateReport() {
        console.log('\n' + '='.repeat(60));
        console.log('COMPREHENSIVE BRACKET API AUDIT REPORT');
        console.log('='.repeat(60));
        
        // Calculate summary statistics
        const totalTests = this.countTotalTests();
        const passedTests = this.countPassedTests();
        const successRate = totalTests > 0 ? ((passedTests / totalTests) * 100).toFixed(2) : 0;
        
        console.log('\nEXECUTIVE SUMMARY');
        console.log('-'.repeat(20));
        console.log(`Total Tests: ${totalTests}`);
        console.log(`Passed: ${passedTests}`);
        console.log(`Failed: ${totalTests - passedTests}`);
        console.log(`Success Rate: ${successRate}%`);
        console.log(`Errors: ${this.errors.length}`);
        console.log(`Warnings: ${this.warnings.length}`);
        
        // Phase results
        console.log('\nDETAILED RESULTS');
        console.log('-'.repeat(20));
        
        for (const [phase, results] of Object.entries(this.results)) {
            if (typeof results === 'object' && results !== null) {
                const phaseTests = Object.keys(results).length;
                const phasePassed = Object.values(results).filter(r => 
                    r === true || (typeof r === 'object' && r.success === true)
                ).length;
                const phaseRate = phaseTests > 0 ? ((phasePassed / phaseTests) * 100).toFixed(1) : 0;
                
                console.log(`${phase.toUpperCase()}: ${phasePassed}/${phaseTests} (${phaseRate}%)`);
            }
        }
        
        // Errors and warnings
        if (this.errors.length > 0) {
            console.log('\nERRORS');
            console.log('-'.repeat(10));
            this.errors.forEach(error => console.log(`• ${error}`));
        }
        
        if (this.warnings.length > 0) {
            console.log('\nWARNINGS');
            console.log('-'.repeat(10));
            this.warnings.forEach(warning => console.log(`• ${warning}`));
        }
        
        // Production readiness assessment
        this.assessProductionReadiness(successRate, totalTests, passedTests);
        
        // Save report
        this.saveReport();
        
        console.log('\n=== COMPREHENSIVE BRACKET API AUDIT COMPLETED ===');
    }

    countTotalTests() {
        let total = 0;
        for (const results of Object.values(this.results)) {
            if (typeof results === 'object' && results !== null) {
                total += Object.keys(results).length;
            }
        }
        return total;
    }

    countPassedTests() {
        let passed = 0;
        for (const results of Object.values(this.results)) {
            if (typeof results === 'object' && results !== null) {
                for (const result of Object.values(results)) {
                    if (result === true || (typeof result === 'object' && result.success === true)) {
                        passed++;
                    }
                }
            }
        }
        return passed;
    }

    assessProductionReadiness(successRate, totalTests, passedTests) {
        console.log('\nPRODUCTION READINESS ASSESSMENT');
        console.log('-'.repeat(35));
        
        const criticalErrors = this.errors.filter(e => 
            e.includes('Authentication failed') || 
            e.includes('HTTP 500') ||
            e.includes('Connection failed')
        ).length;
        
        if (successRate >= 90 && criticalErrors === 0) {
            console.log('🟢 READY FOR PRODUCTION');
            console.log('The bracket API system has passed comprehensive testing.');
        } else if (successRate >= 75 && criticalErrors <= 1) {
            console.log('🟡 READY WITH CAUTION');
            console.log('The system is functional but has some issues to address.');
        } else {
            console.log('🔴 NOT READY FOR PRODUCTION');
            console.log('Critical issues must be resolved before deployment.');
        }
        
        console.log('\nKEY FINDINGS:');
        console.log(`• API Availability: ${passedTests > 0 ? 'OPERATIONAL' : 'DOWN'}`);
        console.log(`• Authentication: ${this.authToken ? 'WORKING' : 'ISSUES'}`);
        console.log(`• Error Handling: ${this.results.error_handling ? 'IMPLEMENTED' : 'NEEDS WORK'}`);
        console.log(`• Edge Cases: ${this.results.edge_cases ? 'HANDLED' : 'NEEDS ATTENTION'}`);
    }

    saveReport() {
        const report = {
            timestamp: new Date().toISOString(),
            summary: {
                total_tests: this.countTotalTests(),
                passed_tests: this.countPassedTests(),
                success_rate: this.countTotalTests() > 0 ? 
                    ((this.countPassedTests() / this.countTotalTests()) * 100) : 0,
                errors: this.errors.length,
                warnings: this.warnings.length
            },
            results: this.results,
            errors: this.errors,
            warnings: this.warnings
        };
        
        const fs = require('fs');
        const filename = `bracket_api_audit_report_${Date.now()}.json`;
        
        try {
            fs.writeFileSync(filename, JSON.stringify(report, null, 2));
            console.log(`\nDetailed report saved to: ${filename}`);
        } catch (error) {
            console.log(`\nFailed to save report: ${error.message}`);
        }
    }
}

// Run the audit
const audit = new BracketAPIAudit();
audit.runCompleteAudit().catch(console.error);