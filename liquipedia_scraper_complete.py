#!/usr/bin/env python3
"""
Comprehensive Liquipedia Marvel Rivals Scraper
Scrapes all players and teams with complete information
"""

import requests
import json
import time
import re
from datetime import datetime
from urllib.parse import urljoin, quote
import sys
import traceback

class LiquipediaMarvelRivalsScraper:
    def __init__(self):
        self.base_url = "https://liquipedia.net/marvelrivals/"
        self.api_url = "https://liquipedia.net/marvelrivals/api.php"
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
        self.session = requests.Session()
        self.session.headers.update(self.headers)
        self.players = []
        self.teams = []
        self.processed_players = set()
        self.processed_teams = set()

    def get_page_content(self, page_title):
        """Get wiki page content via API"""
        try:
            params = {
                'action': 'query',
                'format': 'json',
                'prop': 'revisions',
                'titles': page_title,
                'rvprop': 'content',
                'rvslots': 'main'
            }
            response = self.session.get(self.api_url, params=params)
            data = response.json()
            
            pages = data.get('query', {}).get('pages', {})
            for page_id, page_data in pages.items():
                if 'revisions' in page_data:
                    return page_data['revisions'][0]['slots']['main']['*']
            return None
        except Exception as e:
            print(f"Error getting page {page_title}: {e}")
            return None

    def parse_player_page(self, player_name):
        """Parse individual player page"""
        if player_name in self.processed_players:
            return None
            
        print(f"Parsing player: {player_name}")
        content = self.get_page_content(player_name.replace(' ', '_'))
        if not content:
            return None
            
        self.processed_players.add(player_name)
        
        player_data = {
            'name': player_name,
            'nationality': None,
            'country': None,
            'born': None,
            'age': None,
            'region': None,
            'status': 'Active',
            'role': None,
            'team': None,
            'alternate_ids': [],
            'earnings': 0,
            'signature_heroes': [],
            'social_links': {},
            'achievements': [],
            'history': [],
            'real_name': None
        }
        
        # Parse infobox
        infobox_match = re.search(r'\{\{Infobox player(.*?)\}\}', content, re.DOTALL)
        if infobox_match:
            infobox = infobox_match.group(1)
            
            # Extract fields
            if '|name=' in infobox:
                real_name = re.search(r'\|name=([^\n|]+)', infobox)
                if real_name:
                    player_data['real_name'] = real_name.group(1).strip()
            
            if '|nationality=' in infobox:
                nat = re.search(r'\|nationality=([^\n|]+)', infobox)
                if nat:
                    player_data['nationality'] = nat.group(1).strip()
                    player_data['country'] = nat.group(1).strip()
            
            if '|nationality2=' in infobox:
                nat2 = re.search(r'\|nationality2=([^\n|]+)', infobox)
                if nat2:
                    player_data['nationality'] += f", {nat2.group(1).strip()}"
            
            if '|birth=' in infobox:
                birth = re.search(r'\|birth=([^\n|]+)', infobox)
                if birth:
                    player_data['born'] = birth.group(1).strip()
            
            if '|region=' in infobox:
                region = re.search(r'\|region=([^\n|]+)', infobox)
                if region:
                    player_data['region'] = region.group(1).strip()
            
            if '|team=' in infobox:
                team = re.search(r'\|team=([^\n|]+)', infobox)
                if team:
                    player_data['team'] = team.group(1).strip()
            
            if '|role=' in infobox:
                role = re.search(r'\|role=([^\n|]+)', infobox)
                if role:
                    player_data['role'] = role.group(1).strip()
            
            if '|earnings=' in infobox:
                earnings = re.search(r'\|earnings=([^\n|]+)', infobox)
                if earnings:
                    earnings_str = earnings.group(1).strip()
                    earnings_num = re.sub(r'[^\d]', '', earnings_str)
                    if earnings_num:
                        player_data['earnings'] = int(earnings_num)
            
            # Social links
            if '|twitter=' in infobox:
                twitter = re.search(r'\|twitter=([^\n|]+)', infobox)
                if twitter:
                    player_data['social_links']['twitter'] = f"https://twitter.com/{twitter.group(1).strip()}"
            
            if '|twitch=' in infobox:
                twitch = re.search(r'\|twitch=([^\n|]+)', infobox)
                if twitch:
                    player_data['social_links']['twitch'] = f"https://twitch.tv/{twitch.group(1).strip()}"
            
            if '|youtube=' in infobox:
                youtube = re.search(r'\|youtube=([^\n|]+)', infobox)
                if youtube:
                    player_data['social_links']['youtube'] = youtube.group(1).strip()
        
        # Parse signature heroes
        heroes_match = re.search(r'==\s*Signature Heroes\s*==(.*?)(?===|$)', content, re.DOTALL)
        if heroes_match:
            heroes_text = heroes_match.group(1)
            heroes = re.findall(r'\[\[([^\]]+)\]\]', heroes_text)
            player_data['signature_heroes'] = [h.split('|')[-1] for h in heroes]
        
        # Parse history
        history_match = re.search(r'==\s*History\s*==(.*?)(?===|$)', content, re.DOTALL)
        if history_match:
            history_text = history_match.group(1)
            history_items = re.findall(r'\*\s*([^\n]+)', history_text)
            for item in history_items:
                # Parse history entries
                date_team = re.match(r'(\d{4}-\d{2}-\d{2})\s*(?:—|–|-)\s*(?:(\d{4}-\d{2}-\d{2})|Present)?\s*\[\[([^\]]+)\]\]', item)
                if date_team:
                    history_entry = {
                        'start_date': date_team.group(1),
                        'end_date': date_team.group(2) if date_team.group(2) else 'Present',
                        'team': date_team.group(3).split('|')[-1]
                    }
                    player_data['history'].append(history_entry)
        
        # Parse achievements
        achievements_match = re.search(r'==\s*Achievements\s*==(.*?)(?===|$)', content, re.DOTALL)
        if achievements_match:
            achievements_text = achievements_match.group(1)
            achievement_items = re.findall(r'\{\{TournamentResultSlot(.*?)\}\}', achievements_text, re.DOTALL)
            for item in achievement_items:
                place = re.search(r'\|place=([^\n|]+)', item)
                event = re.search(r'\|event=([^\n|]+)', item)
                if place and event:
                    player_data['achievements'].append({
                        'place': place.group(1).strip(),
                        'event': event.group(1).strip()
                    })
        
        return player_data

    def parse_team_page(self, team_name):
        """Parse individual team page"""
        if team_name in self.processed_teams:
            return None
            
        print(f"Parsing team: {team_name}")
        content = self.get_page_content(team_name.replace(' ', '_'))
        if not content:
            return None
            
        self.processed_teams.add(team_name)
        
        team_data = {
            'name': team_name,
            'short_name': None,
            'region': None,
            'country': None,
            'founded': None,
            'disbanded': None,
            'earnings': 0,
            'coach': None,
            'manager': None,
            'captain': None,
            'social_links': {},
            'roster': [],
            'achievements': [],
            'sponsors': [],
            'website': None
        }
        
        # Parse infobox
        infobox_match = re.search(r'\{\{Infobox team(.*?)\}\}', content, re.DOTALL)
        if infobox_match:
            infobox = infobox_match.group(1)
            
            # Extract fields
            if '|shortname=' in infobox:
                shortname = re.search(r'\|shortname=([^\n|]+)', infobox)
                if shortname:
                    team_data['short_name'] = shortname.group(1).strip()
            
            if '|location=' in infobox:
                location = re.search(r'\|location=([^\n|]+)', infobox)
                if location:
                    team_data['country'] = location.group(1).strip()
            
            if '|region=' in infobox:
                region = re.search(r'\|region=([^\n|]+)', infobox)
                if region:
                    team_data['region'] = region.group(1).strip()
            
            if '|founded=' in infobox:
                founded = re.search(r'\|founded=([^\n|]+)', infobox)
                if founded:
                    team_data['founded'] = founded.group(1).strip()
            
            if '|disbanded=' in infobox:
                disbanded = re.search(r'\|disbanded=([^\n|]+)', infobox)
                if disbanded:
                    team_data['disbanded'] = disbanded.group(1).strip()
            
            if '|earnings=' in infobox:
                earnings = re.search(r'\|earnings=([^\n|]+)', infobox)
                if earnings:
                    earnings_str = earnings.group(1).strip()
                    earnings_num = re.sub(r'[^\d]', '', earnings_str)
                    if earnings_num:
                        team_data['earnings'] = int(earnings_num)
            
            if '|coach=' in infobox:
                coach = re.search(r'\|coach=([^\n|]+)', infobox)
                if coach:
                    team_data['coach'] = coach.group(1).strip()
            
            if '|manager=' in infobox:
                manager = re.search(r'\|manager=([^\n|]+)', infobox)
                if manager:
                    team_data['manager'] = manager.group(1).strip()
            
            if '|captain=' in infobox:
                captain = re.search(r'\|captain=([^\n|]+)', infobox)
                if captain:
                    team_data['captain'] = captain.group(1).strip()
            
            # Social links
            if '|website=' in infobox:
                website = re.search(r'\|website=([^\n|]+)', infobox)
                if website:
                    team_data['website'] = website.group(1).strip()
            
            if '|twitter=' in infobox:
                twitter = re.search(r'\|twitter=([^\n|]+)', infobox)
                if twitter:
                    team_data['social_links']['twitter'] = f"https://twitter.com/{twitter.group(1).strip()}"
            
            if '|youtube=' in infobox:
                youtube = re.search(r'\|youtube=([^\n|]+)', infobox)
                if youtube:
                    team_data['social_links']['youtube'] = youtube.group(1).strip()
        
        # Parse roster
        roster_match = re.search(r'==\s*Active Squad\s*==(.*?)(?===|$)', content, re.DOTALL)
        if not roster_match:
            roster_match = re.search(r'==\s*Roster\s*==(.*?)(?===|$)', content, re.DOTALL)
        
        if roster_match:
            roster_text = roster_match.group(1)
            # Find squad rows
            squad_rows = re.findall(r'\{\{SquadRow(.*?)\}\}', roster_text, re.DOTALL)
            for row in squad_rows:
                player = re.search(r'\|player=([^\n|]+)', row)
                role = re.search(r'\|role=([^\n|]+)', row)
                if player:
                    player_name = player.group(1).strip()
                    player_role = role.group(1).strip() if role else 'Flex'
                    team_data['roster'].append({
                        'name': player_name,
                        'role': player_role
                    })
                    # Also parse this player's page
                    player_info = self.parse_player_page(player_name)
                    if player_info:
                        self.players.append(player_info)
        
        return team_data

    def get_all_players(self):
        """Get all players from the players category"""
        print("Getting all players...")
        params = {
            'action': 'query',
            'format': 'json',
            'list': 'categorymembers',
            'cmtitle': 'Category:Players',
            'cmlimit': 500
        }
        
        try:
            response = self.session.get(self.api_url, params=params)
            data = response.json()
            
            for member in data.get('query', {}).get('categorymembers', []):
                player_name = member['title']
                if ':' not in player_name:  # Skip category pages
                    player_data = self.parse_player_page(player_name)
                    if player_data:
                        self.players.append(player_data)
                        time.sleep(0.5)  # Rate limiting
        except Exception as e:
            print(f"Error getting players: {e}")

    def get_all_teams(self):
        """Get all teams from the teams category"""
        print("Getting all teams...")
        params = {
            'action': 'query',
            'format': 'json',
            'list': 'categorymembers',
            'cmtitle': 'Category:Teams',
            'cmlimit': 500
        }
        
        try:
            response = self.session.get(self.api_url, params=params)
            data = response.json()
            
            for member in data.get('query', {}).get('categorymembers', []):
                team_name = member['title']
                if ':' not in team_name:  # Skip category pages
                    team_data = self.parse_team_page(team_name)
                    if team_data:
                        self.teams.append(team_data)
                        time.sleep(0.5)  # Rate limiting
        except Exception as e:
            print(f"Error getting teams: {e}")

    def save_data(self):
        """Save scraped data to JSON files"""
        print(f"\nSaving {len(self.players)} players and {len(self.teams)} teams...")
        
        with open('/var/www/mrvl-backend/liquipedia_players.json', 'w') as f:
            json.dump(self.players, f, indent=2)
        
        with open('/var/www/mrvl-backend/liquipedia_teams.json', 'w') as f:
            json.dump(self.teams, f, indent=2)
        
        print("Data saved successfully!")
        print(f"Total players: {len(self.players)}")
        print(f"Total teams: {len(self.teams)}")

    def run(self):
        """Main scraping function"""
        print("Starting Liquipedia Marvel Rivals scraper...")
        
        # Get all teams first (this will also get players from rosters)
        self.get_all_teams()
        
        # Then get any remaining players
        self.get_all_players()
        
        # Save the data
        self.save_data()
        
        return {
            'players': self.players,
            'teams': self.teams
        }

if __name__ == "__main__":
    scraper = LiquipediaMarvelRivalsScraper()
    data = scraper.run()