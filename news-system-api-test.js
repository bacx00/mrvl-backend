#!/usr/bin/env node

/**
 * Comprehensive News System API Test
 * Tests all news functionality including display, comments, mentions, and admin features
 */

const axios = require('axios');
const fs = require('fs');
const path = require('path');

// Configuration
const API_BASE = 'http://localhost:8000/api';
const TEST_RESULTS_FILE = path.join(__dirname, 'news-system-test-results.json');

class NewsSystemTester {
    constructor() {
        this.results = {
            timestamp: new Date().toISOString(),
            summary: {
                total: 0,
                passed: 0,
                failed: 0,
                warnings: 0
            },
            tests: {
                newsDisplay: [],
                comments: [],
                mentions: [],
                admin: [],
                mobile: []
            },
            recommendations: [],
            criticalIssues: []
        };
    }

    log(category, test, status, message, details = null) {
        const result = {
            test,
            status, // 'passed', 'failed', 'warning'
            message,
            details,
            timestamp: new Date().toISOString()
        };
        
        this.results.tests[category].push(result);
        this.results.summary.total++;
        this.results.summary[status]++;
        
        // Console output with colors
        const colors = {
            passed: '\x1b[32m✅',
            failed: '\x1b[31m❌',
            warning: '\x1b[33m⚠️'
        };
        
        console.log(`${colors[status]} [${category.toUpperCase()}] ${test}: ${message}\x1b[0m`);
        if (details) {
            console.log(`   Details: ${details}`);
        }
        
        // Track critical issues
        if (status === 'failed') {
            this.results.criticalIssues.push({
                category,
                test,
                message,
                details
            });
        }
    }

    async apiRequest(method, endpoint, data = null, headers = {}) {
        try {
            const config = {
                method: method.toUpperCase(),
                url: `${API_BASE}${endpoint}`,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...headers
                }
            };
            
            if (data) {
                config.data = data;
            }
            
            const response = await axios(config);
            return { 
                success: true, 
                data: response.data, 
                status: response.status,
                headers: response.headers
            };
        } catch (error) {
            return { 
                success: false, 
                error: error.response?.data?.message || error.message,
                status: error.response?.status,
                details: error.response?.data
            };
        }
    }

    // News Display Tests
    async testNewsListLoading() {
        console.log('\n🔍 Testing News List Loading...');
        
        const result = await this.apiRequest('GET', '/news');
        
        if (result.success) {
            const newsData = result.data?.data || result.data || [];
            
            if (Array.isArray(newsData)) {
                this.log('newsDisplay', 'News List API Response', 'passed', 
                    `Successfully loaded ${newsData.length} news articles`);
                
                if (newsData.length > 0) {
                    // Test article structure
                    const article = newsData[0];
                    const requiredFields = ['id', 'title', 'content'];
                    const optionalFields = ['featured_image', 'excerpt', 'author', 'category', 'created_at'];
                    
                    const missingRequired = requiredFields.filter(field => !article[field]);
                    const presentOptional = optionalFields.filter(field => article[field]);
                    
                    if (missingRequired.length === 0) {
                        this.log('newsDisplay', 'Article Data Structure', 'passed', 
                            `All required fields present. Optional fields: ${presentOptional.join(', ')}`);
                    } else {
                        this.log('newsDisplay', 'Article Data Structure', 'failed', 
                            `Missing required fields: ${missingRequired.join(', ')}`);
                    }
                    
                    // Test featured images
                    const withImages = newsData.filter(a => a.featured_image);
                    if (withImages.length > 0) {
                        this.log('newsDisplay', 'Featured Images', 'passed', 
                            `${withImages.length} articles have featured images`);
                    } else {
                        this.log('newsDisplay', 'Featured Images', 'warning', 
                            'No articles with featured images found');
                    }
                    
                    return newsData;
                } else {
                    this.log('newsDisplay', 'News Content', 'warning', 
                        'News list loaded but no articles found');
                }
            } else {
                this.log('newsDisplay', 'News List Format', 'failed', 
                    'News response is not an array');
            }
        } else {
            this.log('newsDisplay', 'News List API Response', 'failed', 
                `API request failed: ${result.error}`, result.details);
        }
        
        return [];
    }

    async testNewsDetailLoading(articleId) {
        console.log('\n🔍 Testing News Detail Loading...');
        
        if (!articleId) {
            this.log('newsDisplay', 'News Detail Loading', 'warning', 
                'No article ID available for detail testing');
            return null;
        }
        
        const result = await this.apiRequest('GET', `/news/${articleId}`);
        
        if (result.success) {
            const article = result.data?.data || result.data;
            
            if (article && article.id) {
                this.log('newsDisplay', 'News Detail API Response', 'passed', 
                    `Successfully loaded article: "${article.title}"`);
                
                // Test comments loading
                if (article.comments !== undefined) {
                    if (Array.isArray(article.comments)) {
                        this.log('newsDisplay', 'Comments in Article', 'passed', 
                            `Article has ${article.comments.length} comments`);
                    } else {
                        this.log('newsDisplay', 'Comments in Article', 'warning', 
                            'Comments field exists but is not an array');
                    }
                } else {
                    this.log('newsDisplay', 'Comments in Article', 'warning', 
                        'Comments field not present in article response');
                }
                
                // Test mentions
                if (article.mentions) {
                    if (Array.isArray(article.mentions)) {
                        this.log('newsDisplay', 'Mentions in Article', 'passed', 
                            `Article has ${article.mentions.length} mentions`);
                    } else {
                        this.log('newsDisplay', 'Mentions in Article', 'warning', 
                            'Mentions field exists but is not an array');
                    }
                }
                
                // Test video embeds
                if (article.videos) {
                    if (Array.isArray(article.videos)) {
                        this.log('newsDisplay', 'Video Embeds in Article', 'passed', 
                            `Article has ${article.videos.length} video embeds`);
                    } else {
                        this.log('newsDisplay', 'Video Embeds in Article', 'warning', 
                            'Videos field exists but is not an array');
                    }
                }
                
                return article;
            } else {
                this.log('newsDisplay', 'News Detail Data', 'failed', 
                    'Article detail response is incomplete or invalid');
            }
        } else {
            this.log('newsDisplay', 'News Detail API Response', 'failed', 
                `Failed to load article detail: ${result.error}`, result.details);
        }
        
        return null;
    }

    async testNewsCategories() {
        console.log('\n🔍 Testing News Categories...');
        
        const result = await this.apiRequest('GET', '/news/categories');
        
        if (result.success) {
            const categories = result.data?.data || result.data || [];
            
            if (Array.isArray(categories) && categories.length > 0) {
                this.log('newsDisplay', 'News Categories API', 'passed', 
                    `Found ${categories.length} news categories`);
                
                // Test category structure
                const category = categories[0];
                if (category.name && category.slug) {
                    this.log('newsDisplay', 'Category Data Structure', 'passed', 
                        'Categories have required name and slug fields');
                } else {
                    this.log('newsDisplay', 'Category Data Structure', 'failed', 
                        'Categories missing required fields (name, slug)');
                }
            } else {
                this.log('newsDisplay', 'News Categories Content', 'warning', 
                    'No news categories found - create categories in admin panel');
            }
        } else {
            this.log('newsDisplay', 'News Categories API', 'failed', 
                `Failed to load categories: ${result.error}`, result.details);
        }
    }

    // Comments System Tests
    async testCommentsAPI(articleId) {
        console.log('\n💬 Testing Comments System...');
        
        if (!articleId) {
            this.log('comments', 'Comments Testing', 'warning', 
                'No article ID available for comment testing');
            return;
        }
        
        // Test comment posting (will fail without auth, but we test the endpoint)
        const testComment = {
            content: "Test comment from automated system"
        };
        
        const postResult = await this.apiRequest('POST', `/news/${articleId}/comments`, testComment);
        
        if (postResult.success) {
            this.log('comments', 'Comment Posting API', 'passed', 
                'Comment posting endpoint works');
        } else {
            if (postResult.status === 401) {
                this.log('comments', 'Comment Posting Authentication', 'passed', 
                    'Comment posting correctly requires authentication');
            } else if (postResult.status === 422) {
                this.log('comments', 'Comment Posting Validation', 'passed', 
                    'Comment posting has proper validation');
            } else {
                this.log('comments', 'Comment Posting API', 'failed', 
                    `Unexpected error in comment posting: ${postResult.error}`, postResult.details);
            }
        }
        
        // Test comment editing endpoint structure
        const editResult = await this.apiRequest('PUT', `/news/comments/999`, testComment);
        if (editResult.status === 401 || editResult.status === 404) {
            this.log('comments', 'Comment Editing Endpoint', 'passed', 
                'Comment editing endpoint exists and has proper error handling');
        }
        
        // Test comment deletion endpoint structure  
        const deleteResult = await this.apiRequest('DELETE', `/news/comments/999`);
        if (deleteResult.status === 401 || deleteResult.status === 404) {
            this.log('comments', 'Comment Deletion Endpoint', 'passed', 
                'Comment deletion endpoint exists and has proper error handling');
        }
    }

    // Mentions System Tests
    async testMentionsSystem() {
        console.log('\n@ Testing Mentions System...');
        
        // Test user search for mentions
        const userSearchResult = await this.apiRequest('GET', '/search/users?query=test');
        if (userSearchResult.success) {
            this.log('mentions', 'User Search for Mentions', 'passed', 
                'User search endpoint works for mention autocomplete');
        } else {
            this.log('mentions', 'User Search for Mentions', 'warning', 
                'User search endpoint not available - mentions autocomplete may not work');
        }
        
        // Test team search for mentions
        const teamSearchResult = await this.apiRequest('GET', '/teams');
        if (teamSearchResult.success) {
            const teams = teamSearchResult.data?.data || teamSearchResult.data || [];
            if (teams.length > 0) {
                this.log('mentions', 'Team Data for Mentions', 'passed', 
                    `Found ${teams.length} teams available for @team: mentions`);
            } else {
                this.log('mentions', 'Team Data for Mentions', 'warning', 
                    'No teams found - @team: mentions will not work');
            }
        }
        
        // Test player search for mentions
        const playerSearchResult = await this.apiRequest('GET', '/players');
        if (playerSearchResult.success) {
            const players = playerSearchResult.data?.data || playerSearchResult.data || [];
            if (players.length > 0) {
                this.log('mentions', 'Player Data for Mentions', 'passed', 
                    `Found ${players.length} players available for @player: mentions`);
            } else {
                this.log('mentions', 'Player Data for Mentions', 'warning', 
                    'No players found - @player: mentions will not work');
            }
        }
    }

    // Admin Features Tests
    async testAdminFeatures() {
        console.log('\n⚙️ Testing Admin Features...');
        
        // Test admin news creation endpoint
        const testNews = {
            title: "Test Article",
            content: "Test content",
            status: "draft"
        };
        
        const createResult = await this.apiRequest('POST', '/admin/news', testNews);
        if (createResult.status === 401 || createResult.status === 403) {
            this.log('admin', 'News Creation Authentication', 'passed', 
                'News creation correctly requires admin authentication');
        } else if (createResult.success) {
            this.log('admin', 'News Creation API', 'passed', 
                'News creation endpoint works');
        } else {
            this.log('admin', 'News Creation API', 'failed', 
                `News creation failed unexpectedly: ${createResult.error}`, createResult.details);
        }
        
        // Test admin news list endpoint
        const adminListResult = await this.apiRequest('GET', '/admin/news');
        if (adminListResult.status === 401 || adminListResult.status === 403) {
            this.log('admin', 'Admin News List Authentication', 'passed', 
                'Admin news list correctly requires authentication');
        } else if (adminListResult.success) {
            this.log('admin', 'Admin News List API', 'passed', 
                'Admin news list endpoint works');
        }
        
        // Test image upload endpoint exists
        const uploadResult = await this.apiRequest('POST', '/admin/news/upload');
        if (uploadResult.status === 401 || uploadResult.status === 422) {
            this.log('admin', 'Image Upload Endpoint', 'passed', 
                'Image upload endpoint exists with proper validation');
        }
    }

    // Generate comprehensive recommendations
    generateRecommendations() {
        const { criticalIssues, tests } = this.results;
        
        // Critical issues recommendations
        if (criticalIssues.length > 0) {
            this.results.recommendations.push({
                priority: 'critical',
                category: 'Bug Fixes',
                items: criticalIssues.map(issue => `Fix ${issue.test}: ${issue.message}`)
            });
        }
        
        // Feature completeness recommendations
        const warningTests = [];
        Object.values(tests).forEach(category => {
            category.forEach(test => {
                if (test.status === 'warning') {
                    warningTests.push(test);
                }
            });
        });
        
        if (warningTests.length > 0) {
            const contentRecommendations = warningTests
                .filter(t => t.message.includes('No') || t.message.includes('not found'))
                .map(t => t.message);
                
            if (contentRecommendations.length > 0) {
                this.results.recommendations.push({
                    priority: 'high',
                    category: 'Content & Data',
                    items: contentRecommendations
                });
            }
        }
        
        // Manual testing recommendations
        this.results.recommendations.push({
            priority: 'medium',
            category: 'Manual Testing Required',
            items: [
                'Test comment posting with authenticated user',
                'Test mention autocomplete while typing',
                'Test image uploads in admin panel',
                'Test video embed display and playback',
                'Test mobile responsive design on actual devices',
                'Verify no [object Object] errors in comment system',
                'Test mention links click navigation',
                'Test admin moderation features'
            ]
        });
        
        // Performance recommendations
        this.results.recommendations.push({
            priority: 'low',
            category: 'Performance & Monitoring',
            items: [
                'Monitor API response times',
                'Test with large datasets (many comments, articles)',
                'Verify image loading performance',
                'Test concurrent user comment posting',
                'Monitor memory usage during video embed loading'
            ]
        });
    }

    async runAllTests() {
        console.log('🚀 Starting Comprehensive News System Test...');
        console.log(`Testing against: ${API_BASE}`);
        
        try {
            // Test news display functionality
            const articles = await this.testNewsListLoading();
            
            // Test detail view with first article
            let firstArticle = null;
            if (articles.length > 0) {
                firstArticle = await this.testNewsDetailLoading(articles[0].id);
            }
            
            // Test categories
            await this.testNewsCategories();
            
            // Test comments system
            if (firstArticle) {
                await this.testCommentsAPI(firstArticle.id);
            }
            
            // Test mentions system
            await this.testMentionsSystem();
            
            // Test admin features
            await this.testAdminFeatures();
            
            // Generate recommendations
            this.generateRecommendations();
            
        } catch (error) {
            console.error('💥 Fatal error during testing:', error);
            this.log('system', 'Test Execution', 'failed', 
                `Fatal error: ${error.message}`, error.stack);
        }
    }

    generateReport() {
        const { summary, criticalIssues, recommendations } = this.results;
        
        console.log('\n📊 TEST EXECUTION SUMMARY');
        console.log('═══════════════════════════════════');
        console.log(`✅ Passed: ${summary.passed}`);
        console.log(`❌ Failed: ${summary.failed}`);
        console.log(`⚠️  Warnings: ${summary.warnings}`);
        console.log(`📊 Total: ${summary.total}`);
        console.log(`🎯 Success Rate: ${summary.total > 0 ? Math.round((summary.passed / summary.total) * 100) : 0}%`);
        
        if (criticalIssues.length > 0) {
            console.log('\n🚨 CRITICAL ISSUES');
            console.log('═══════════════════');
            criticalIssues.forEach((issue, index) => {
                console.log(`${index + 1}. [${issue.category}] ${issue.test}: ${issue.message}`);
            });
        }
        
        if (recommendations.length > 0) {
            console.log('\n💡 RECOMMENDATIONS');
            console.log('═══════════════════');
            recommendations.forEach(rec => {
                console.log(`\n${rec.priority.toUpperCase()} PRIORITY - ${rec.category}:`);
                rec.items.forEach(item => {
                    console.log(`  • ${item}`);
                });
            });
        }
        
        console.log('\n📋 MANUAL TESTING CHECKLIST');
        console.log('══════════════════════════════');
        console.log('□ Login and test comment posting');
        console.log('□ Test comment replies and editing');
        console.log('□ Test @username mention autocomplete');
        console.log('□ Test @team: and @player: mentions');
        console.log('□ Verify no [object Object] in comments');
        console.log('□ Test admin news creation with images');
        console.log('□ Test video embed playback');
        console.log('□ Test on mobile devices');
        console.log('□ Test tablet responsive layout');
        
        // Save results to file
        fs.writeFileSync(TEST_RESULTS_FILE, JSON.stringify(this.results, null, 2));
        console.log(`\n💾 Detailed results saved to: ${TEST_RESULTS_FILE}`);
        
        return this.results;
    }
}

// Run the tests
async function main() {
    const tester = new NewsSystemTester();
    await tester.runAllTests();
    const results = tester.generateReport();
    
    // Exit with error code if there are critical issues
    if (results.criticalIssues.length > 0) {
        console.log('\n❌ Tests completed with critical issues!');
        process.exit(1);
    } else {
        console.log('\n✅ Tests completed successfully!');
        process.exit(0);
    }
}

if (require.main === module) {
    main().catch(error => {
        console.error('💥 Fatal error:', error);
        process.exit(1);
    });
}

module.exports = NewsSystemTester;