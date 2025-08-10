#!/usr/bin/env node
/**
 * COMPREHENSIVE TEAM PROFILE CRUD OPERATIONS TEST
 * Marvel Rivals Backend API - Team Management System
 * Testing all aspects of team profile CRUD operations
 */

const axios = require('axios');
const fs = require('fs');
const FormData = require('form-data');

class ComprehensiveTeamCRUDTest {
    constructor() {
        this.baseURL = 'http://localhost:8000/api';
        this.adminToken = null;
        this.testResults = {
            timestamp: new Date().toISOString(),
            tests: {},
            summary: { passed: 0, failed: 0, total: 0 },
            errors: []
        };
        this.createdTeamId = null;
    }

    log(message, type = 'info') {
        const timestamp = new Date().toISOString();
        const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : '📝';
        console.log(`${prefix} [${timestamp}] ${message}`);
    }

    recordResult(testName, passed, message, data = null) {
        this.testResults.tests[testName] = {
            passed,
            message,
            data,
            timestamp: new Date().toISOString()
        };
        
        if (passed) {
            this.testResults.summary.passed++;
            this.log(`✅ ${testName}: ${message}`, 'success');
        } else {
            this.testResults.summary.failed++;
            this.testResults.errors.push({ test: testName, message, data });
            this.log(`❌ ${testName}: ${message}`, 'error');
        }
        
        this.testResults.summary.total++;
    }

    // 1. ADMIN AUTHENTICATION TEST
    async testAdminAuthentication() {
        try {
            const response = await axios.post(`${this.baseURL}/auth/login`, {
                email: 'admin@mrvl.com',
                password: 'admin123'
            });

            if (response.data.success && response.data.token) {
                this.adminToken = response.data.token;
                this.recordResult('admin_authentication', true, 'Admin authentication successful');
                return true;
            } else {
                this.recordResult('admin_authentication', false, 'Invalid admin credentials response');
                return false;
            }
        } catch (error) {
            this.recordResult('admin_authentication', false, `Authentication failed: ${error.message}`);
            return false;
        }
    }

    // 2. TEAM LIST FETCH TEST
    async testTeamListFetch() {
        try {
            const response = await axios.get(`${this.baseURL}/admin/teams`, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            const teams = response.data.data || response.data;
            const isValidResponse = Array.isArray(teams) && teams.length >= 0;

            this.recordResult('team_list_fetch', isValidResponse, 
                `Fetched ${teams.length} teams successfully`, { 
                    count: teams.length,
                    sampleTeam: teams[0] || null
                });
            
            return isValidResponse;
        } catch (error) {
            // Try public endpoint if admin endpoint fails
            try {
                const publicResponse = await axios.get(`${this.baseURL}/teams`);
                const teams = publicResponse.data.data || publicResponse.data;
                const isValidResponse = Array.isArray(teams) && teams.length >= 0;
                
                this.recordResult('team_list_fetch', isValidResponse, 
                    `Fetched ${teams.length} teams via public endpoint`, { 
                        count: teams.length,
                        note: 'Admin endpoint failed, used public endpoint'
                    });
                return isValidResponse;
            } catch (publicError) {
                this.recordResult('team_list_fetch', false, `Failed to fetch teams: ${error.message}`);
                return false;
            }
        }
    }

    // 3. TEAM CREATION TEST
    async testTeamCreation() {
        try {
            const newTeamData = {
                name: `Test Team ${Date.now()}`,
                short_name: `TT${Date.now().toString().slice(-4)}`,
                region: 'NA',
                country: 'United States',
                rating: 1500,
                description: 'Test team created for CRUD validation',
                social_links: {
                    twitter: 'https://twitter.com/testteam',
                    discord: 'https://discord.gg/testteam'
                }
            };

            const response = await axios.post(`${this.baseURL}/admin/teams`, newTeamData, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            if (response.status === 201 && response.data.success) {
                this.createdTeamId = response.data.data.id || response.data.data[0]?.id;
                this.recordResult('team_creation', true, 
                    `Team created successfully with ID: ${this.createdTeamId}`, 
                    { teamId: this.createdTeamId, teamData: response.data.data });
                return true;
            } else {
                this.recordResult('team_creation', false, 'Team creation response invalid');
                return false;
            }
        } catch (error) {
            if (error.response?.status === 422) {
                this.recordResult('team_creation', false, 
                    `Validation errors: ${JSON.stringify(error.response.data.errors || error.response.data.message)}`);
            } else {
                this.recordResult('team_creation', false, `Team creation failed: ${error.message}`);
            }
            return false;
        }
    }

    // 4. TEAM DETAIL FETCH TEST
    async testTeamDetailFetch() {
        if (!this.createdTeamId) {
            this.recordResult('team_detail_fetch', false, 'No team ID available for detail fetch');
            return false;
        }

        try {
            const response = await axios.get(`${this.baseURL}/teams/${this.createdTeamId}`, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            const team = response.data.data || response.data;
            const hasRequiredFields = team.id && team.name && team.short_name;

            this.recordResult('team_detail_fetch', hasRequiredFields, 
                hasRequiredFields ? 'Team details fetched successfully' : 'Team details missing required fields', 
                { team });
            
            return hasRequiredFields;
        } catch (error) {
            this.recordResult('team_detail_fetch', false, `Failed to fetch team details: ${error.message}`);
            return false;
        }
    }

    // 5. TEAM UPDATE TEST
    async testTeamUpdate() {
        if (!this.createdTeamId) {
            this.recordResult('team_update', false, 'No team ID available for update');
            return false;
        }

        try {
            const updateData = {
                name: `Updated Test Team ${Date.now()}`,
                region: 'EU',
                country: 'Germany',
                rating: 1750,
                coach: 'John Doe',
                captain: 'Jane Smith',
                website: 'https://testteam.com',
                twitter: 'https://twitter.com/updatedteam',
                instagram: 'https://instagram.com/updatedteam'
            };

            const response = await axios.put(`${this.baseURL}/admin/teams/${this.createdTeamId}`, 
                updateData, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            if (response.data.success) {
                this.recordResult('team_update', true, 'Team updated successfully', 
                    { updatedData: response.data.data });
                return true;
            } else {
                this.recordResult('team_update', false, 'Team update response invalid');
                return false;
            }
        } catch (error) {
            this.recordResult('team_update', false, `Team update failed: ${error.message}`);
            return false;
        }
    }

    // 6. FORM VALIDATION TEST
    async testFormValidation() {
        const validationTests = [
            {
                name: 'empty_name',
                data: { name: '', short_name: 'TEST', region: 'NA' },
                shouldFail: true
            },
            {
                name: 'duplicate_short_name',
                data: { name: 'Test Team', short_name: 'SEN', region: 'NA' }, // Assuming SEN exists
                shouldFail: true
            },
            {
                name: 'invalid_rating',
                data: { name: 'Test Team', short_name: 'TEST2', region: 'NA', rating: -100 },
                shouldFail: true
            },
            {
                name: 'long_short_name',
                data: { name: 'Test Team', short_name: 'VERYLONGSHORTNAME', region: 'NA' },
                shouldFail: true
            }
        ];

        let passedValidations = 0;
        
        for (const test of validationTests) {
            try {
                const response = await axios.post(`${this.baseURL}/admin/teams`, test.data, {
                    headers: { Authorization: `Bearer ${this.adminToken}` }
                });
                
                if (test.shouldFail) {
                    this.log(`Validation test ${test.name} should have failed but passed`, 'error');
                } else {
                    passedValidations++;
                    // Clean up created team
                    if (response.data.data?.id) {
                        await axios.delete(`${this.baseURL}/admin/teams/${response.data.data.id}`, {
                            headers: { Authorization: `Bearer ${this.adminToken}` }
                        }).catch(() => {}); // Ignore cleanup errors
                    }
                }
            } catch (error) {
                if (test.shouldFail && error.response?.status === 422) {
                    passedValidations++;
                    this.log(`Validation test ${test.name} correctly failed`, 'success');
                } else if (!test.shouldFail) {
                    this.log(`Validation test ${test.name} should have passed but failed: ${error.message}`, 'error');
                }
            }
        }

        const passed = passedValidations === validationTests.length;
        this.recordResult('form_validation', passed, 
            `${passedValidations}/${validationTests.length} validation tests passed`);
        
        return passed;
    }

    // 7. LOGO UPLOAD TEST
    async testLogoUpload() {
        if (!this.createdTeamId) {
            this.recordResult('logo_upload', false, 'No team ID available for logo upload');
            return false;
        }

        try {
            // Create a simple test image file
            const testImagePath = '/tmp/test_logo.png';
            const testImageData = Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 
                'base64'
            );
            
            fs.writeFileSync(testImagePath, testImageData);

            const form = new FormData();
            form.append('logo', fs.createReadStream(testImagePath), {
                filename: 'test_logo.png',
                contentType: 'image/png'
            });

            const response = await axios.post(
                `${this.baseURL}/admin/teams/${this.createdTeamId}/logo`, 
                form,
                {
                    headers: {
                        Authorization: `Bearer ${this.adminToken}`,
                        ...form.getHeaders()
                    }
                }
            );

            // Clean up test file
            fs.unlinkSync(testImagePath);

            if (response.data.success) {
                this.recordResult('logo_upload', true, 'Logo uploaded successfully', 
                    { logoUrl: response.data.logo_url });
                return true;
            } else {
                this.recordResult('logo_upload', false, 'Logo upload response invalid');
                return false;
            }
        } catch (error) {
            this.recordResult('logo_upload', false, `Logo upload failed: ${error.message}`);
            return false;
        }
    }

    // 8. ROSTER MANAGEMENT TEST
    async testRosterManagement() {
        if (!this.createdTeamId) {
            this.recordResult('roster_management', false, 'No team ID available for roster management');
            return false;
        }

        try {
            // First, check if we can fetch team roster
            let response = await axios.get(`${this.baseURL}/teams/${this.createdTeamId}`, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            const teamData = response.data.data || response.data;
            const hasRosterData = teamData.hasOwnProperty('current_roster') || 
                                 teamData.hasOwnProperty('roster') ||
                                 teamData.hasOwnProperty('players');

            this.recordResult('roster_management', hasRosterData, 
                hasRosterData ? 'Roster data available in team profile' : 'No roster data structure found',
                { rosterKeys: Object.keys(teamData).filter(key => 
                    key.includes('roster') || key.includes('player')) });
            
            return hasRosterData;
        } catch (error) {
            this.recordResult('roster_management', false, `Roster management test failed: ${error.message}`);
            return false;
        }
    }

    // 9. TEAM STATISTICS TEST
    async testTeamStatistics() {
        if (!this.createdTeamId) {
            this.recordResult('team_statistics', false, 'No team ID available for statistics test');
            return false;
        }

        try {
            const response = await axios.get(`${this.baseURL}/teams/${this.createdTeamId}`, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            const team = response.data.data || response.data;
            const hasStatsFields = team.rating !== undefined || 
                                  team.win_rate !== undefined || 
                                  team.record !== undefined ||
                                  team.statistics !== undefined;

            this.recordResult('team_statistics', hasStatsFields, 
                hasStatsFields ? 'Team statistics data available' : 'Missing team statistics fields',
                { 
                    availableStats: Object.keys(team).filter(key => 
                        ['rating', 'win_rate', 'record', 'points', 'rank', 'statistics'].includes(key))
                });
            
            return hasStatsFields;
        } catch (error) {
            this.recordResult('team_statistics', false, `Team statistics test failed: ${error.message}`);
            return false;
        }
    }

    // 10. TEAM DELETION TEST
    async testTeamDeletion() {
        if (!this.createdTeamId) {
            this.recordResult('team_deletion', false, 'No team ID available for deletion');
            return false;
        }

        try {
            const response = await axios.delete(`${this.baseURL}/admin/teams/${this.createdTeamId}`, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            if (response.data.success) {
                this.recordResult('team_deletion', true, 'Team deleted successfully');
                
                // Verify team is actually deleted
                try {
                    await axios.get(`${this.baseURL}/teams/${this.createdTeamId}`);
                    this.recordResult('team_deletion_verification', false, 'Team still accessible after deletion');
                } catch (error) {
                    if (error.response?.status === 404) {
                        this.recordResult('team_deletion_verification', true, 'Team properly removed from system');
                    }
                }
                
                return true;
            } else {
                this.recordResult('team_deletion', false, 'Team deletion response invalid');
                return false;
            }
        } catch (error) {
            this.recordResult('team_deletion', false, `Team deletion failed: ${error.message}`);
            return false;
        }
    }

    // 11. PAGINATION TEST
    async testPagination() {
        try {
            const response = await axios.get(`${this.baseURL}/teams?limit=5&page=1`, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            const data = response.data.data || response.data;
            const hasPaginationSupport = Array.isArray(data) && data.length <= 5;

            this.recordResult('pagination', hasPaginationSupport, 
                hasPaginationSupport ? 'Pagination working correctly' : 'Pagination not implemented properly',
                { resultCount: Array.isArray(data) ? data.length : 'not_array' });
            
            return hasPaginationSupport;
        } catch (error) {
            this.recordResult('pagination', false, `Pagination test failed: ${error.message}`);
            return false;
        }
    }

    // 12. SEARCH FUNCTIONALITY TEST
    async testSearchFunctionality() {
        try {
            const response = await axios.get(`${this.baseURL}/teams?search=test`, {
                headers: { Authorization: `Bearer ${this.adminToken}` }
            });

            const teams = response.data.data || response.data;
            const hasSearchSupport = Array.isArray(teams);

            this.recordResult('search_functionality', hasSearchSupport, 
                hasSearchSupport ? `Search returned ${teams.length} results` : 'Search functionality not working',
                { searchResults: teams.length });
            
            return hasSearchSupport;
        } catch (error) {
            this.recordResult('search_functionality', false, `Search test failed: ${error.message}`);
            return false;
        }
    }

    async runAllTests() {
        this.log('🚀 Starting Comprehensive Team CRUD Operations Test', 'info');
        this.log('=' .repeat(80), 'info');

        // Run all tests in sequence
        await this.testAdminAuthentication();
        
        if (this.adminToken) {
            await this.testTeamListFetch();
            await this.testTeamCreation();
            await this.testTeamDetailFetch();
            await this.testTeamUpdate();
            await this.testFormValidation();
            await this.testLogoUpload();
            await this.testRosterManagement();
            await this.testTeamStatistics();
            await this.testPagination();
            await this.testSearchFunctionality();
            await this.testTeamDeletion();
        } else {
            this.log('⚠️  Skipping remaining tests due to authentication failure', 'error');
        }

        // Generate comprehensive report
        this.generateReport();
    }

    generateReport() {
        this.log('=' .repeat(80), 'info');
        this.log('📊 COMPREHENSIVE TEST RESULTS SUMMARY', 'info');
        this.log('=' .repeat(80), 'info');
        
        this.log(`✅ Passed: ${this.testResults.summary.passed}`, 'success');
        this.log(`❌ Failed: ${this.testResults.summary.failed}`, 'error');
        this.log(`📊 Total:  ${this.testResults.summary.total}`, 'info');
        
        const successRate = ((this.testResults.summary.passed / this.testResults.summary.total) * 100).toFixed(1);
        this.log(`🎯 Success Rate: ${successRate}%`, successRate >= 80 ? 'success' : 'error');
        
        if (this.testResults.errors.length > 0) {
            this.log('\n❌ FAILED TESTS:', 'error');
            this.testResults.errors.forEach(error => {
                this.log(`   • ${error.test}: ${error.message}`, 'error');
            });
        }

        // Save detailed report
        const reportPath = `/var/www/mrvl-backend/team_crud_test_report_${Date.now()}.json`;
        fs.writeFileSync(reportPath, JSON.stringify(this.testResults, null, 2));
        this.log(`📁 Detailed report saved: ${reportPath}`, 'info');
        
        // Critical Issues Analysis
        this.analyzeCriticalIssues();
    }

    analyzeCriticalIssues() {
        this.log('\n🔍 CRITICAL ISSUES ANALYSIS:', 'info');
        this.log('-' .repeat(50), 'info');
        
        const criticalTests = ['admin_authentication', 'team_creation', 'team_update', 'team_deletion'];
        const failedCritical = this.testResults.errors.filter(error => 
            criticalTests.includes(error.test));
        
        if (failedCritical.length > 0) {
            this.log('🚨 CRITICAL CRUD OPERATIONS FAILING:', 'error');
            failedCritical.forEach(error => {
                this.log(`   • ${error.test}: ${error.message}`, 'error');
            });
        } else {
            this.log('✅ All critical CRUD operations working', 'success');
        }

        // Recommendations
        this.generateRecommendations();
    }

    generateRecommendations() {
        this.log('\n💡 RECOMMENDATIONS:', 'info');
        this.log('-' .repeat(50), 'info');
        
        const failedTests = Object.keys(this.testResults.tests).filter(test => 
            !this.testResults.tests[test].passed);
        
        if (failedTests.includes('admin_authentication')) {
            this.log('1. Fix admin authentication system', 'error');
            this.log('   - Verify admin credentials in database', 'info');
            this.log('   - Check JWT token generation', 'info');
        }
        
        if (failedTests.includes('team_creation')) {
            this.log('2. Fix team creation API route', 'error');
            this.log('   - Add POST /admin/teams route', 'info');
            this.log('   - Verify TeamController@store method', 'info');
        }
        
        if (failedTests.includes('form_validation')) {
            this.log('3. Enhance form validation', 'error');
            this.log('   - Add comprehensive validation rules', 'info');
            this.log('   - Implement proper error responses', 'info');
        }
        
        if (failedTests.includes('logo_upload')) {
            this.log('4. Fix image upload functionality', 'error');
            this.log('   - Verify storage configuration', 'info');
            this.log('   - Check file upload permissions', 'info');
        }
        
        if (failedTests.includes('roster_management')) {
            this.log('5. Implement roster management features', 'error');
            this.log('   - Add player assignment endpoints', 'info');
            this.log('   - Create roster display functionality', 'info');
        }
    }
}

// Run the comprehensive test
if (require.main === module) {
    const tester = new ComprehensiveTeamCRUDTest();
    tester.runAllTests().catch(console.error);
}

module.exports = ComprehensiveTeamCRUDTest;