#!/usr/bin/env python3

import requests
from bs4 import BeautifulSoup
import json
import time
import re
import sys

class TargetedTeamScraper:
    def __init__(self):
        self.base_url = "https://liquipedia.net/marvelrivals"
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        })
        
        # Known team URLs from the category page
        self.known_teams = [
            "100_Thieves", "3BL_Esports", "Al_Qadsiah", "All_Business", "Arrival_Seven",
            "Astronic_Esports", "Brr_Brr_Patapim", "Cafe_Noir", "Citadel_Gaming", "Crazy_Raccoon",
            "DetonationFocusMe", "Disguised_Toast", "EHOME", "Esports_Factory", "Ex_Oblivione",
            "FaZe_Clan", "Fnatic", "G2_Esports", "GenG", "GlobalZ", "Guild_Esports", "Heroic",
            "IGL", "ILLICIT", "JDG", "KRU_Esports", "LOUD", "Legacy_Esports", "M80", "NAVI",
            "NRG_Esports", "OUG", "Paper_Rex", "Rise_Gaming", "SLZZ", "Sector", "Sentinels",
            "T1", "TSM", "Team_Liquid", "Team_Secret", "Virtus.pro", "WBG", "ZETA_DIVISION",
            "beastcoast", "inZOI", "mCon_esports", "on_Sla2ers"
        ]
        
        self.teams_data = []
        self.players_data = []
    
    def delay_request(self):
        """Rate limiting"""
        time.sleep(2)
    
    def get_page(self, url):
        """Get page with error handling"""
        try:
            self.delay_request()
            response = self.session.get(url, timeout=30)
            response.raise_for_status()
            return BeautifulSoup(response.content, 'html.parser')
        except Exception as e:
            print(f"Error fetching {url}: {e}")
            return None
    
    def extract_country_code(self, flag_element):
        """Extract country code from flag image"""
        if not flag_element:
            return None
        src = flag_element.get('src', '')
        # Look for country code in URL path
        if '/commons/' in src:
            parts = src.split('/')
            for part in parts:
                if len(part) == 2 and part.isupper():
                    return part
        return None
    
    def clean_text(self, text):
        """Clean text"""
        if not text:
            return ""
        return re.sub(r'\s+', ' ', text.strip())
    
    def scrape_player_details(self, player_url, player_name):
        """Scrape individual player details"""
        player_page = self.get_page(player_url)
        if not player_page:
            return {
                'name': player_name,
                'username': player_name,
                'real_name': '',
                'country': '',
                'role': '',
                'age': None,
                'social_media': {},
                'earnings': 0.0,
                'rating': 1500.0
            }
        
        player_data = {
            'name': player_name,
            'username': player_name,
            'real_name': '',
            'country': '',
            'role': '',
            'age': None,
            'main_hero': '',
            'alt_heroes': [],
            'earnings': 0.0,
            'rating': 1500.0,
            'social_media': {},
            'biography': '',
            'past_teams': [],
            'achievements': []
        }
        
        # Find infobox
        infobox = player_page.find('div', class_='infobox') or player_page.find('table', class_='infobox')
        if infobox:
            rows = infobox.find_all('tr')
            for row in rows:
                th = row.find('th')
                td = row.find('td')
                if th and td:
                    header = self.clean_text(th.get_text()).lower()
                    
                    if 'name' in header and 'real' in header:
                        player_data['real_name'] = self.clean_text(td.get_text())
                    
                    elif any(word in header for word in ['nationality', 'country', 'location']):
                        flag_img = td.find('img')
                        if flag_img:
                            country = self.extract_country_code(flag_img)
                            if country:
                                player_data['country'] = country
                    
                    elif any(word in header for word in ['role', 'position']):
                        role_text = self.clean_text(td.get_text()).lower()
                        # Map roles
                        if any(word in role_text for word in ['duelist', 'dps', 'damage']):
                            player_data['role'] = 'duelist'
                        elif any(word in role_text for word in ['vanguard', 'tank', 'frontline']):
                            player_data['role'] = 'vanguard'
                        elif any(word in role_text for word in ['strategist', 'support', 'healer']):
                            player_data['role'] = 'strategist'
                    
                    elif 'age' in header:
                        age_text = self.clean_text(td.get_text())
                        age_match = re.search(r'(\d{1,2})', age_text)
                        if age_match:
                            player_data['age'] = int(age_match.group(1))
        
        # Look for social media links
        social_media = {}
        for link in player_page.find_all('a', href=True):
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
        
        return player_data
    
    def scrape_team_page(self, team_name):
        """Scrape a specific team page"""
        team_url = f"{self.base_url}/{team_name}"
        print(f"\nScraping team: {team_name}")
        
        team_page = self.get_page(team_url)
        if not team_page:
            return None, []
        
        # Initialize team data
        team_data = {
            'name': team_name.replace('_', ' '),
            'short_name': team_name.replace('_', '')[:4].upper(),
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
        logo_candidates = team_page.find_all('img')
        for img in logo_candidates:
            alt_text = img.get('alt', '').lower()
            src = img.get('src', '')
            if (team_name.lower().replace('_', ' ') in alt_text or 
                'logo' in alt_text or 
                'lightmode' in src.lower() or
                'allmode' in src.lower()):
                if 'commons' in src:
                    team_data['logo'] = f"https:{src}" if src.startswith('//') else src
                    break
        
        # Find infobox for team info
        infobox = team_page.find('div', class_='infobox') or team_page.find('table', class_='infobox')
        if infobox:
            rows = infobox.find_all('tr')
            for row in rows:
                th = row.find('th')
                td = row.find('td')
                if th and td:
                    header = self.clean_text(th.get_text()).lower()
                    
                    if any(word in header for word in ['region', 'location', 'country']):
                        region_text = self.clean_text(td.get_text())
                        team_data['region'] = region_text
                        
                        flag_img = td.find('img')
                        if flag_img:
                            country = self.extract_country_code(flag_img)
                            if country:
                                team_data['country'] = country
                                team_data['flag'] = f"https://flagcdn.com/16x12/{country.lower()}.png"
                    
                    elif any(word in header for word in ['founded', 'created', 'established']):
                        team_data['founded'] = self.clean_text(td.get_text())
                    
                    elif any(word in header for word in ['coach', 'manager']):
                        team_data['coach'] = self.clean_text(td.get_text())
        
        # Find current roster
        players = []
        
        # Look for roster section headers
        possible_headers = [
            'Current Roster', 'Active Roster', 'Roster', 'Players',
            'current_roster', 'active_roster', 'roster', 'players'
        ]
        
        roster_section = None
        for header_text in possible_headers:
            header = team_page.find(['h2', 'h3', 'span'], string=re.compile(header_text, re.IGNORECASE))
            if header:
                roster_section = header
                break
        
        if roster_section:
            # Look for player table or list after the header
            next_element = roster_section.find_next(['table', 'div'])
            if next_element:
                # Find player links
                player_links = next_element.find_all('a')
                
                for link in player_links:
                    href = link.get('href', '')
                    if href.startswith('/marvelrivals/') and not any(skip in href.lower() for skip in ['file:', 'category:', 'template:', 'special:', 'help:']):
                        player_name = self.clean_text(link.get_text())
                        
                        # Skip empty names or obvious non-players
                        if (not player_name or len(player_name) < 2 or 
                            any(word in player_name.lower() for word in ['team', 'esports', 'gaming', 'club', 'tournament'])):
                            continue
                        
                        # Skip if we already have this player
                        if any(p['name'] == player_name for p in players):
                            continue
                        
                        player_url = f"https://liquipedia.net{href}"
                        print(f"  Scraping player: {player_name}")
                        
                        # Scrape player details
                        player_data = self.scrape_player_details(player_url, player_name)
                        players.append(player_data)
                        
                        # Limit to 6 players
                        if len(players) >= 6:
                            break
        
        # If we didn't find enough players in the roster section, look elsewhere on the page
        if len(players) < 3:
            print("  Looking for additional players on page...")
            all_links = team_page.find_all('a', href=re.compile(r'^/marvelrivals/[^/]+$'))
            
            for link in all_links:
                if len(players) >= 6:
                    break
                
                player_name = self.clean_text(link.get_text())
                href = link.get('href')
                
                # Skip if not a valid player name
                if (not player_name or len(player_name) < 2 or 
                    any(word in player_name.lower() for word in ['team', 'esports', 'gaming', 'club', 'tournament', 'match', 'vs']) or
                    any(word in href.lower() for word in ['file:', 'category:', 'template:', 'special:', 'help:'])):
                    continue
                
                # Skip if we already have this player
                if any(p['name'] == player_name for p in players):
                    continue
                
                player_url = f"https://liquipedia.net{href}"
                print(f"  Scraping additional player: {player_name}")
                
                player_data = self.scrape_player_details(player_url, player_name)
                players.append(player_data)
        
        # Look for social media links
        social_media = {}
        for link in team_page.find_all('a', href=True):
            href = link['href']
            if 'twitter.com' in href or 'x.com' in href:
                social_media['twitter'] = href
            elif 'instagram.com' in href:
                social_media['instagram'] = href
            elif 'youtube.com' in href:
                social_media['youtube'] = href
            elif team_name.lower() in href.lower() and any(domain in href for domain in ['.com', '.gg', '.org']):
                social_media['website'] = href
        
        team_data['social_media'] = social_media
        
        print(f"  Found {len(players)} players for {team_data['name']}")
        return team_data, players
    
    def scrape_all_teams(self):
        """Scrape all known teams"""
        total_teams = 0
        total_players = 0
        
        for i, team_name in enumerate(self.known_teams):
            print(f"\n[{i+1}/{len(self.known_teams)}] Processing: {team_name}")
            
            try:
                result = self.scrape_team_page(team_name)
                if result:
                    team_data, players = result
                    
                    if players:  # Only add teams with players
                        self.teams_data.append(team_data)
                        self.players_data.extend(players)
                        total_teams += 1
                        total_players += len(players)
                        
                        print(f"  ✓ Added team: {team_data['name']} ({team_data['region']}) - {len(players)} players")
                    else:
                        print(f"  ✗ Skipped {team_data['name']} - no players found")
                else:
                    print(f"  ✗ Failed to scrape {team_name}")
                    
            except Exception as e:
                print(f"  Error: {e}")
                continue
        
        print(f"\n=== SCRAPING COMPLETED ===")
        print(f"Total teams: {total_teams}")
        print(f"Total players: {total_players}")
        
        return self.teams_data, self.players_data
    
    def save_to_json(self):
        """Save to JSON files"""
        with open('/var/www/mrvl-backend/scraped_teams.json', 'w') as f:
            json.dump(self.teams_data, f, indent=2)
        
        with open('/var/www/mrvl-backend/scraped_players.json', 'w') as f:
            json.dump(self.players_data, f, indent=2)
        
        print(f"\nSaved to JSON files:")
        print(f"  - scraped_teams.json ({len(self.teams_data)} teams)")
        print(f"  - scraped_players.json ({len(self.players_data)} players)")

def main():
    scraper = TargetedTeamScraper()
    
    print("=== TARGETED LIQUIPEDIA MARVEL RIVALS SCRAPER ===")
    teams_data, players_data = scraper.scrape_all_teams()
    scraper.save_to_json()

if __name__ == "__main__":
    main()