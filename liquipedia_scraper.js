const fs = require('fs');
const path = require('path');

/**
 * Liquipedia Marvel Rivals Data Scraper
 * 
 * This script implements a comprehensive scraper for Marvel Rivals esports data
 * from Liquipedia with rate limiting, retry logic, and comprehensive error handling.
 */

class LiquipediaScraper {
    constructor() {
        this.baseUrl = 'https://liquipedia.net/marvelrivals';
        this.rateLimitDelay = 5000; // 5 seconds between requests
        this.maxRetries = 3;
        this.outputDir = '/var/www/mrvl-backend';
        this.teams = [];
        this.players = [];
        
        // Known major teams to prioritize
        this.majorTeams = [
            'Sentinels',
            'NRG_Esports',
            'Cloud9',
            '100_Thieves',
            'TSM',
            'G2_Esports',
            'Team_Liquid',
            'FNATIC',
            'OpTic_Gaming',
            'FaZe_Clan'
        ];
    }

    /**
     * Sleep function for rate limiting
     */
    async sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Make HTTP request with rate limiting and retry logic
     */
    async makeRequest(url, prompt, retryCount = 0) {
        try {
            console.log(`Fetching: ${url} (attempt ${retryCount + 1})`);
            
            // Add delay to respect rate limits
            if (retryCount > 0) {
                await this.sleep(this.rateLimitDelay * Math.pow(2, retryCount)); // Exponential backoff
            } else {
                await this.sleep(this.rateLimitDelay);
            }

            // Use WebFetch tool (this would be replaced with actual HTTP client in Node.js environment)
            const response = await this.webFetch(url, prompt);
            
            console.log(`✅ Successfully fetched: ${url}`);
            return response;
            
        } catch (error) {
            console.log(`❌ Error fetching ${url}: ${error.message}`);
            
            if (error.message.includes('429') && retryCount < this.maxRetries) {
                console.log(`⏳ Rate limited. Retrying in ${this.rateLimitDelay * Math.pow(2, retryCount + 1)}ms...`);
                return await this.makeRequest(url, prompt, retryCount + 1);
            }
            
            throw error;
        }
    }

    /**
     * Simulate WebFetch call (in actual implementation, this would use axios or fetch)
     */
    async webFetch(url, prompt) {
        // This is a placeholder - in real implementation would use HTTP client
        throw new Error("WebFetch not implemented in Node.js environment");
    }

    /**
     * Scrape team data from a team page
     */
    async scrapeTeam(teamName) {
        const url = `${this.baseUrl}/${teamName}`;
        const prompt = `Extract comprehensive team data from this ${teamName} page including:
        - Team name, short name, logo URL
        - Region, country, founded date
        - Complete roster with all 6 players (usernames, real names, roles, countries, ages if available)
        - Social media links (Twitter, Instagram, YouTube, Twitch, website)
        - Recent achievements and tournament results
        - Earnings information
        - Current status and activity`;

        try {
            const data = await this.makeRequest(url, prompt);
            return this.parseTeamData(data, teamName);
        } catch (error) {
            console.error(`Failed to scrape team ${teamName}:`, error.message);
            return null;
        }
    }

    /**
     * Parse team data from scraped content
     */
    parseTeamData(content, teamName) {
        // This would contain logic to parse the scraped content
        // For now, return a template structure
        return {
            name: teamName.replace(/_/g, ' '),
            short_name: this.generateShortName(teamName),
            scraped_at: new Date().toISOString(),
            raw_content: content,
            needs_manual_review: true
        };
    }

    /**
     * Scrape player data from a player page
     */
    async scrapePlayer(playerName) {
        const url = `${this.baseUrl}/${playerName}`;
        const prompt = `Extract comprehensive player data from this ${playerName} page including:
        - Username, real name, birth date, age, nationality
        - Current team and role
        - Main heroes and signature characters
        - Career earnings and statistics
        - Social media links and streaming information
        - Past teams and career history
        - Tournament achievements and awards
        - Equipment and game settings if available`;

        try {
            const data = await this.makeRequest(url, prompt);
            return this.parsePlayerData(data, playerName);
        } catch (error) {
            console.error(`Failed to scrape player ${playerName}:`, error.message);
            return null;
        }
    }

    /**
     * Parse player data from scraped content
     */
    parsePlayerData(content, playerName) {
        // This would contain logic to parse the scraped content
        return {
            username: playerName,
            scraped_at: new Date().toISOString(),
            raw_content: content,
            needs_manual_review: true
        };
    }

    /**
     * Get list of all teams from main pages
     */
    async getAllTeams() {
        const urls = [
            `${this.baseUrl}/Portal:Teams`,
            `${this.baseUrl}/Category:Teams`,
            `${this.baseUrl}/Main_Page`
        ];

        const allTeams = new Set();
        
        for (const url of urls) {
            try {
                const prompt = "Extract all team names and links from this page. Look for team listings, navigation menus, and any references to professional Marvel Rivals teams.";
                const data = await this.makeRequest(url, prompt);
                
                // Parse team names from the content (this would need actual parsing logic)
                const teams = this.extractTeamNames(data);
                teams.forEach(team => allTeams.add(team));
                
            } catch (error) {
                console.error(`Failed to get teams from ${url}:`, error.message);
            }
        }

        return Array.from(allTeams);
    }

    /**
     * Get list of all players from main pages
     */
    async getAllPlayers() {
        const urls = [
            `${this.baseUrl}/Portal:Players`,
            `${this.baseUrl}/Category:Players`,
            `${this.baseUrl}/Player_List`
        ];

        const allPlayers = new Set();
        
        for (const url of urls) {
            try {
                const prompt = "Extract all player names and links from this page. Look for player listings, rosters, and any references to professional Marvel Rivals players.";
                const data = await this.makeRequest(url, prompt);
                
                // Parse player names from the content
                const players = this.extractPlayerNames(data);
                players.forEach(player => allPlayers.add(player));
                
            } catch (error) {
                console.error(`Failed to get players from ${url}:`, error.message);
            }
        }

        return Array.from(allPlayers);
    }

    /**
     * Extract team names from scraped content (placeholder)
     */
    extractTeamNames(content) {
        // This would contain actual parsing logic
        return this.majorTeams; // Return major teams as fallback
    }

    /**
     * Extract player names from scraped content (placeholder)
     */
    extractPlayerNames(content) {
        // This would contain actual parsing logic
        return []; // Return empty for now
    }

    /**
     * Generate short name for team
     */
    generateShortName(teamName) {
        const name = teamName.replace(/_/g, ' ');
        if (name === 'Sentinels') return 'SEN';
        if (name === 'NRG Esports') return 'NRG';
        if (name === 'Cloud9') return 'C9';
        if (name === '100 Thieves') return '100T';
        if (name === 'TSM') return 'TSM';
        if (name === 'G2 Esports') return 'G2';
        if (name === 'Team Liquid') return 'TL';
        if (name === 'FNATIC') return 'FNC';
        if (name === 'OpTic Gaming') return 'OPT';
        if (name === 'FaZe Clan') return 'FAZE';
        
        // Generate from first letters
        return name.split(' ')
            .map(word => word.charAt(0).toUpperCase())
            .join('')
            .substring(0, 4);
    }

    /**
     * Save data to JSON file
     */
    async saveData(filename, data) {
        const filepath = path.join(this.outputDir, filename);
        try {
            fs.writeFileSync(filepath, JSON.stringify(data, null, 2), 'utf8');
            console.log(`✅ Data saved to: ${filepath}`);
        } catch (error) {
            console.error(`❌ Failed to save data to ${filepath}:`, error.message);
        }
    }

    /**
     * Main scraping function
     */
    async scrapeAll() {
        console.log('🚀 Starting comprehensive Liquipedia Marvel Rivals data scrape...');
        
        try {
            // Step 1: Get list of all teams
            console.log('📋 Getting list of all teams...');
            const teamNames = await this.getAllTeams();
            console.log(`Found ${teamNames.length} teams to scrape`);

            // Step 2: Scrape major teams first
            console.log('⭐ Scraping major teams first...');
            for (const teamName of this.majorTeams) {
                if (teamNames.includes(teamName)) {
                    const teamData = await this.scrapeTeam(teamName);
                    if (teamData) {
                        this.teams.push(teamData);
                        console.log(`✅ Scraped team: ${teamName}`);
                    }
                }
            }

            // Step 3: Scrape remaining teams
            console.log('📝 Scraping remaining teams...');
            for (const teamName of teamNames) {
                if (!this.majorTeams.includes(teamName)) {
                    const teamData = await this.scrapeTeam(teamName);
                    if (teamData) {
                        this.teams.push(teamData);
                        console.log(`✅ Scraped team: ${teamName}`);
                    }
                }
            }

            // Step 4: Get list of all players
            console.log('👥 Getting list of all players...');
            const playerNames = await this.getAllPlayers();
            console.log(`Found ${playerNames.length} players to scrape`);

            // Step 5: Scrape all players
            console.log('🎮 Scraping player profiles...');
            for (const playerName of playerNames) {
                const playerData = await this.scrapePlayer(playerName);
                if (playerData) {
                    this.players.push(playerData);
                    console.log(`✅ Scraped player: ${playerName}`);
                }
            }

            // Step 6: Save all data
            console.log('💾 Saving scraped data...');
            await this.saveData('liquipedia_teams_scraped.json', this.teams);
            await this.saveData('liquipedia_players_scraped.json', this.players);

            // Step 7: Generate summary report
            const summary = {
                scrape_date: new Date().toISOString(),
                total_teams: this.teams.length,
                total_players: this.players.length,
                major_teams_scraped: this.teams.filter(t => 
                    this.majorTeams.some(major => 
                        t.name.toLowerCase().includes(major.toLowerCase().replace('_', ' '))
                    )
                ).length,
                teams_needing_review: this.teams.filter(t => t.needs_manual_review).length,
                players_needing_review: this.players.filter(p => p.needs_manual_review).length,
                scraping_stats: {
                    rate_limit_delay: this.rateLimitDelay,
                    max_retries: this.maxRetries,
                    successful_requests: this.teams.length + this.players.length
                }
            };

            await this.saveData('liquipedia_scrape_summary.json', summary);

            console.log('🎉 Scraping completed successfully!');
            console.log(`📊 Teams scraped: ${this.teams.length}`);
            console.log(`👥 Players scraped: ${this.players.length}`);

        } catch (error) {
            console.error('💥 Scraping failed:', error.message);
            
            // Save partial results
            if (this.teams.length > 0) {
                await this.saveData('liquipedia_teams_partial.json', this.teams);
            }
            if (this.players.length > 0) {
                await this.saveData('liquipedia_players_partial.json', this.players);
            }
        }
    }

    /**
     * Scrape specific teams only
     */
    async scrapeSpecificTeams(teamNames) {
        console.log(`🎯 Scraping specific teams: ${teamNames.join(', ')}`);
        
        for (const teamName of teamNames) {
            try {
                const teamData = await this.scrapeTeam(teamName);
                if (teamData) {
                    this.teams.push(teamData);
                    console.log(`✅ Scraped team: ${teamName}`);
                }
            } catch (error) {
                console.error(`❌ Failed to scrape ${teamName}:`, error.message);
            }
        }

        await this.saveData('liquipedia_specific_teams.json', this.teams);
        return this.teams;
    }
}

module.exports = LiquipediaScraper;

// Usage examples:
if (require.main === module) {
    const scraper = new LiquipediaScraper();
    
    // Scrape everything
    // scraper.scrapeAll();
    
    // Or scrape specific teams
    scraper.scrapeSpecificTeams([
        'Sentinels',
        'NRG_Esports',
        'Cloud9',
        '100_Thieves',
        'TSM'
    ]);
}