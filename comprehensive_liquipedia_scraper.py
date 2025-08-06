#!/usr/bin/env python3

import requests
from bs4 import BeautifulSoup
import json
import time
import re
import sys
from urllib.parse import urljoin, urlparse
import sqlite3

class ComprehensiveLiquipediaScraper:
    def __init__(self):
        self.base_url = "https://liquipedia.net/marvelrivals"
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        })
        self.teams_data = []
        self.players_data = []
        
    def delay_request(self):
        """Rate limiting to be respectful"""
        time.sleep(1.5)
    
    def get_page(self, url):
        """Safely get a page with error handling"""
        try:
            self.delay_request()
            response = self.session.get(url)
            response.raise_for_status()
            return BeautifulSoup(response.content, 'html.parser')
        except Exception as e:
            print(f"Error fetching {url}: {e}")
            return None
    
    def extract_country_from_flag(self, flag_element):
        """Extract country code from flag image"""
        if not flag_element:
            return None
        src = flag_element.get('src', '')
        if '/commons/' in src and '/' in src:
            parts = src.split('/')
            for part in parts:
                if len(part) == 2 and part.isupper():
                    return part
        return None
    
    def clean_text(self, text):
        """Clean and normalize text"""
        if not text:
            return ""
        return re.sub(r'\s+', ' ', text.strip())
    
    def get_all_team_urls(self):
        """Get all team URLs from the teams category page"""
        teams_page = self.get_page(f"{self.base_url}/Category:Teams")
        if not teams_page:
            return []
        
        team_links = []
        # Look for team links in the category page
        for link in teams_page.find_all('a'):
            href = link.get('href', '')
            if href.startswith('/marvelrivals/') and not any(x in href.lower() for x in ['category:', 'template:', 'file:']):
                full_url = urljoin(self.base_url, href)
                if full_url not in team_links:
                    team_links.append(full_url)
        
        return team_links
    
    def scrape_player_page(self, player_url, player_name):
        """Scrape detailed player information"""
        player_page = self.get_page(player_url)
        if not player_page:
            return None
        
        player_data = {
            'name': player_name,
            'username': player_name,
            'real_name': '',
            'country': '',
            'age': None,
            'role': '',
            'main_hero': '',
            'alt_heroes': [],
            'earnings': 0,
            'rating': 1500.0,  # Default ELO
            'social_media': {},
            'biography': '',
            'past_teams': [],
            'achievements': []
        }
        
        # Try to extract player infobox
        infobox = player_page.find('div', class_='infobox')
        if infobox:
            # Extract real name
            name_row = infobox.find('th', string=re.compile(r'Name|Real Name'))
            if name_row and name_row.find_next_sibling('td'):
                player_data['real_name'] = self.clean_text(name_row.find_next_sibling('td').get_text())
            
            # Extract country
            country_row = infobox.find('th', string=re.compile(r'Nationality|Country'))
            if country_row and country_row.find_next_sibling('td'):
                flag_img = country_row.find_next_sibling('td').find('img')
                if flag_img:
                    country = self.extract_country_from_flag(flag_img)
                    if country:
                        player_data['country'] = country
            
            # Extract role
            role_row = infobox.find('th', string=re.compile(r'Role|Position'))
            if role_row and role_row.find_next_sibling('td'):
                role_text = self.clean_text(role_row.find_next_sibling('td').get_text())
                # Map Marvel Rivals roles
                role_mapping = {
                    'duelist': 'duelist',
                    'vanguard': 'vanguard', 
                    'strategist': 'strategist',
                    'dps': 'duelist',
                    'tank': 'vanguard',
                    'support': 'strategist'
                }
                for key, value in role_mapping.items():
                    if key in role_text.lower():
                        player_data['role'] = value
                        break
            
            # Extract age
            age_row = infobox.find('th', string=re.compile(r'Age|Born'))
            if age_row and age_row.find_next_sibling('td'):
                age_text = self.clean_text(age_row.find_next_sibling('td').get_text())
                age_match = re.search(r'(\d{1,2})', age_text)
                if age_match:
                    player_data['age'] = int(age_match.group(1))
        
        # Look for social media links
        social_links = player_page.find_all('a', href=True)
        social_media = {}
        for link in social_links:
            href = link['href']
            if 'twitter.com' in href or 'x.com' in href:
                social_media['twitter'] = href
            elif 'instagram.com' in href:
                social_media['instagram'] = href
            elif 'twitch.tv' in href:
                social_media['twitch'] = href
            elif 'youtube.com' in href:
                social_media['youtube'] = href
        
        player_data['social_media'] = social_media
        
        # Extract earnings if available
        earnings_section = player_page.find('span', string=re.compile(r'Earnings|Prize'))
        if earnings_section:
            earnings_text = earnings_section.get_text()
            earnings_match = re.search(r'\$?([\d,]+)', earnings_text)
            if earnings_match:
                try:
                    player_data['earnings'] = float(earnings_match.group(1).replace(',', ''))
                except ValueError:
                    pass
        
        return player_data
    
    def scrape_team_page(self, team_url):
        """Scrape detailed team information"""
        team_page = self.get_page(team_url)
        if not team_page:
            return None
        
        # Extract team name from URL
        team_name = team_url.split('/')[-1].replace('_', ' ')
        
        team_data = {
            'name': team_name,
            'short_name': team_name[:4].upper(),
            'logo': '',
            'region': '',
            'country': '',
            'flag': '',
            'platform': 'PC',
            'game': 'Marvel Rivals',
            'division': 'Professional',
            'rating': 1500,
            'rank': 0,
            'win_rate': 0.0,
            'points': 0,
            'record': '0-0',
            'peak': 1500,
            'streak': 0,
            'founded': '',
            'captain': '',
            'coach': '',
            'website': '',
            'earnings': 0.0,
            'social_media': {},
            'achievements': [],
            'recent_form': [],
            'player_count': 6
        }
        
        # Extract team logo
        logo_img = team_page.find('img', {'alt': re.compile(team_name, re.IGNORECASE)})
        if logo_img:
            team_data['logo'] = urljoin(self.base_url, logo_img.get('src', ''))
        
        # Try to find team infobox
        infobox = team_page.find('div', class_='infobox')
        if infobox:
            # Extract region/country
            region_row = infobox.find('th', string=re.compile(r'Region|Location|Country'))
            if region_row and region_row.find_next_sibling('td'):
                region_td = region_row.find_next_sibling('td')
                region_text = self.clean_text(region_td.get_text())
                team_data['region'] = region_text
                
                # Try to extract country flag
                flag_img = region_td.find('img')
                if flag_img:
                    country = self.extract_country_from_flag(flag_img)
                    if country:
                        team_data['country'] = country
                        team_data['flag'] = f"https://flagcdn.com/16x12/{country.lower()}.png"
            
            # Extract founded date
            founded_row = infobox.find('th', string=re.compile(r'Founded|Created'))
            if founded_row and founded_row.find_next_sibling('td'):
                team_data['founded'] = self.clean_text(founded_row.find_next_sibling('td').get_text())
            
            # Extract coach
            coach_row = infobox.find('th', string=re.compile(r'Coach|Manager'))
            if coach_row and coach_row.find_next_sibling('td'):
                team_data['coach'] = self.clean_text(coach_row.find_next_sibling('td').get_text())
        
        # Look for social media links
        social_links = team_page.find_all('a', href=True)
        social_media = {}
        for link in social_links:
            href = link['href']
            if 'twitter.com' in href or 'x.com' in href:
                social_media['twitter'] = href
            elif 'instagram.com' in href:
                social_media['instagram'] = href
            elif 'youtube.com' in href:
                social_media['youtube'] = href
            elif team_name.lower().replace(' ', '') in href.lower():
                social_media['website'] = href
        
        team_data['social_media'] = social_media
        
        # Find current roster
        roster_section = team_page.find(['h2', 'h3'], string=re.compile(r'Current.*Roster|Active.*Roster|Roster', re.IGNORECASE))
        if not roster_section:
            # Try different variations
            roster_section = team_page.find('span', {'id': re.compile(r'.*Roster.*', re.IGNORECASE)})
        
        players = []
        if roster_section:
            # Look for roster table or list after the roster header
            roster_container = roster_section.find_next(['table', 'div', 'ul'])
            if roster_container:
                # Try to find player links and information
                player_links = roster_container.find_all('a', href=re.compile(r'/marvelrivals/[^/]+$'))
                
                for player_link in player_links[:6]:  # Limit to 6 players
                    player_name = player_link.get_text().strip()
                    player_url = urljoin(self.base_url, player_link['href'])
                    
                    # Skip if it looks like a team page or other non-player page
                    if any(word in player_name.lower() for word in ['team', 'esports', 'gaming', 'club']):
                        continue
                    
                    print(f"    Scraping player: {player_name}")
                    player_data = self.scrape_player_page(player_url, player_name)
                    
                    if player_data:
                        # Try to determine role from context
                        player_row = player_link.find_parent('tr')
                        if player_row:
                            role_cell = player_row.find('td')
                            if role_cell:
                                role_text = self.clean_text(role_cell.get_text()).lower()
                                if 'duelist' in role_text or 'dps' in role_text:
                                    player_data['role'] = 'duelist'
                                elif 'vanguard' in role_text or 'tank' in role_text:
                                    player_data['role'] = 'vanguard'
                                elif 'strategist' in role_text or 'support' in role_text:
                                    player_data['role'] = 'strategist'
                        
                        players.append(player_data)
        
        # If we don't have enough players, try a different approach
        if len(players) < 3:
            # Look for any player links on the page
            all_player_links = team_page.find_all('a', href=re.compile(r'/marvelrivals/[A-Za-z0-9_]+$'))
            for link in all_player_links:
                if len(players) >= 6:
                    break
                
                player_name = link.get_text().strip()
                if not player_name or len(player_name) < 2:
                    continue
                
                # Skip if it's obviously not a player
                if any(word in player_name.lower() for word in ['team', 'esports', 'gaming', 'club', 'tournament', 'match', 'vs']):
                    continue
                
                # Skip if we already have this player
                if any(p['name'] == player_name for p in players):
                    continue
                
                player_url = urljoin(self.base_url, link['href'])
                print(f"    Scraping additional player: {player_name}")
                player_data = self.scrape_player_page(player_url, player_name)
                
                if player_data:
                    players.append(player_data)
        
        return team_data, players
    
    def scrape_all_teams(self):
        """Scrape all teams and their players"""
        print("Getting all team URLs...")
        team_urls = self.get_all_team_urls()
        print(f"Found {len(team_urls)} team URLs")
        
        # Also add some known major teams
        additional_teams = [
            f"{self.base_url}/100_Thieves",
            f"{self.base_url}/G2_Esports", 
            f"{self.base_url}/Team_Liquid",
            f"{self.base_url}/Cloud9",
            f"{self.base_url}/FaZe_Clan",
            f"{self.base_url}/Team_SoloMid",
            f"{self.base_url}/Evil_Geniuses",
            f"{self.base_url}/Sentinels",
            f"{self.base_url}/NRG_Esports",
            f"{self.base_url}/GenG",
            f"{self.base_url}/T1",
            f"{self.base_url}/DRX",
            f"{self.base_url}/Paper_Rex",
            f"{self.base_url}/LOUD",
            f"{self.base_url}/Fnatic",
            f"{self.base_url}/NAVI",
            f"{self.base_url}/Vitality"
        ]
        
        for url in additional_teams:
            if url not in team_urls:
                team_urls.append(url)
        
        total_teams = 0
        total_players = 0
        
        for i, team_url in enumerate(team_urls[:50]):  # Limit for now
            print(f"\n[{i+1}/{len(team_urls[:50])}] Scraping team: {team_url}")
            
            try:
                result = self.scrape_team_page(team_url)
                if result:
                    team_data, players = result
                    
                    print(f"  Team: {team_data['name']} ({team_data['region']}) - {len(players)} players")
                    
                    self.teams_data.append(team_data)
                    self.players_data.extend(players)
                    
                    total_teams += 1
                    total_players += len(players)
                else:
                    print("  Failed to scrape team")
                    
            except Exception as e:
                print(f"  Error scraping team: {e}")
                continue
        
        print(f"\n=== SCRAPING COMPLETE ===")
        print(f"Total teams scraped: {total_teams}")
        print(f"Total players scraped: {total_players}")
        
        return self.teams_data, self.players_data
    
    def save_to_json(self, teams_data, players_data):
        """Save scraped data to JSON files"""
        with open('/var/www/mrvl-backend/scraped_teams.json', 'w') as f:
            json.dump(teams_data, f, indent=2, default=str)
        
        with open('/var/www/mrvl-backend/scraped_players.json', 'w') as f:
            json.dump(players_data, f, indent=2, default=str)
        
        print(f"Saved {len(teams_data)} teams and {len(players_data)} players to JSON files")
    
    def import_to_database(self, teams_data, players_data):
        """Import scraped data to SQLite database"""
        db_path = '/var/www/mrvl-backend/database/database.sqlite'
        
        try:
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            
            print("Importing teams to database...")
            for team in teams_data:
                cursor.execute("""
                    INSERT INTO teams (name, short_name, logo, region, country, flag, platform, game, 
                                     division, rating, rank, win_rate, points, record, peak, streak, 
                                     founded, captain, coach, website, earnings, social_media, achievements, 
                                     recent_form, player_count, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                """, (
                    team['name'], team['short_name'], team['logo'], team['region'], team['country'],
                    team['flag'], team['platform'], team['game'], team['division'], team['rating'],
                    team['rank'], team['win_rate'], team['points'], team['record'], team['peak'],
                    team['streak'], team['founded'], team['captain'], team['coach'], team['website'],
                    team['earnings'], json.dumps(team['social_media']), json.dumps(team['achievements']),
                    json.dumps(team['recent_form']), team['player_count']
                ))
            
            conn.commit()
            print(f"Imported {len(teams_data)} teams")
            
            print("Importing players to database...")
            for player in players_data:
                # Find team_id for this player's team
                team_id = None
                
                cursor.execute("""
                    INSERT INTO players (name, username, real_name, team_id, role, main_hero, alt_heroes,
                                       region, country, rank, rating, age, earnings, social_media, biography,
                                       past_teams, hero_pool, career_stats, achievements, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                """, (
                    player['name'], player['username'], player['real_name'], team_id, player['role'],
                    player['main_hero'], json.dumps(player['alt_heroes']), player.get('region', ''),
                    player['country'], 0, player['rating'], player['age'], player['earnings'],
                    json.dumps(player['social_media']), player['biography'], json.dumps(player['past_teams']),
                    json.dumps(player.get('alt_heroes', [])), json.dumps({}), json.dumps(player['achievements'])
                ))
            
            conn.commit()
            print(f"Imported {len(players_data)} players")
            
            conn.close()
            print("Database import completed successfully!")
            
        except Exception as e:
            print(f"Database import error: {e}")

def main():
    scraper = ComprehensiveLiquipediaScraper()
    
    print("=== COMPREHENSIVE LIQUIPEDIA MARVEL RIVALS SCRAPER ===")
    print("Starting comprehensive scrape of all teams and players...")
    
    teams_data, players_data = scraper.scrape_all_teams()
    
    # Save to JSON files
    scraper.save_to_json(teams_data, players_data)
    
    # Import to database
    scraper.import_to_database(teams_data, players_data)
    
    print("\n=== SCRAPING AND IMPORT COMPLETED ===")

if __name__ == "__main__":
    main()