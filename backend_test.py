#!/usr/bin/env python3
import requests
import json
import sys
from datetime import datetime, timedelta

# Base URL for API requests
BASE_URL = "http://localhost:8001/api"

# Admin credentials for authentication
ADMIN_CREDENTIALS = {
    "email": "admin@marvelrivals.com",
    "password": "password123"
}

# Test results
test_results = {
    "success": [],
    "failure": []
}

def log_test(name, success, message=""):
    """Log test results"""
    if success:
        test_results["success"].append({"name": name, "message": message})
        print(f"✅ {name}: {message}")
    else:
        test_results["failure"].append({"name": name, "message": message})
        print(f"❌ {name}: {message}")

def get_admin_token():
    """Get admin authentication token"""
    try:
        response = requests.post(f"{BASE_URL}/auth/login", json=ADMIN_CREDENTIALS)
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and data.get("token"):
                return data["token"]
            else:
                print(f"Login failed: {data.get('message', 'Unknown error')}")
                return None
        else:
            print(f"Login request failed with status code {response.status_code}")
            return None
    except Exception as e:
        print(f"Error during login: {str(e)}")
        return None

def test_game_data_heroes():
    """Test GET /api/game-data/heroes endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/game-data/heroes")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                heroes = data["data"]
                if len(heroes) == 5:
                    log_test("Game Data - Basic Heroes", True, f"Successfully retrieved {len(heroes)} basic heroes")
                else:
                    log_test("Game Data - Basic Heroes", False, f"Expected 5 heroes, got {len(heroes)}")
            else:
                log_test("Game Data - Basic Heroes", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Game Data - Basic Heroes", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Game Data - Basic Heroes", False, f"Exception: {str(e)}")

def test_game_data_all_heroes():
    """Test GET /api/game-data/all-heroes endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/game-data/all-heroes")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                heroes = data["data"]
                if len(heroes) == 29:
                    log_test("Game Data - All Heroes", True, f"Successfully retrieved {len(heroes)} heroes")
                else:
                    log_test("Game Data - All Heroes", False, f"Expected 29 heroes, got {len(heroes)}")
            else:
                log_test("Game Data - All Heroes", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Game Data - All Heroes", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Game Data - All Heroes", False, f"Exception: {str(e)}")

def test_game_data_maps():
    """Test GET /api/game-data/maps endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/game-data/maps")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                maps = data["data"]
                if len(maps) == 10:
                    log_test("Game Data - Maps", True, f"Successfully retrieved {len(maps)} maps")
                else:
                    log_test("Game Data - Maps", False, f"Expected 10 maps, got {len(maps)}")
            else:
                log_test("Game Data - Maps", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Game Data - Maps", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Game Data - Maps", False, f"Exception: {str(e)}")

def test_game_data_modes():
    """Test GET /api/game-data/modes endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/game-data/modes")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                modes = data["data"]
                if len(modes) == 4:
                    log_test("Game Data - Modes", True, f"Successfully retrieved {len(modes)} game modes")
                else:
                    log_test("Game Data - Modes", False, f"Expected 4 game modes, got {len(modes)}")
            else:
                log_test("Game Data - Modes", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Game Data - Modes", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Game Data - Modes", False, f"Exception: {str(e)}")

def test_match_scoreboard():
    """Test GET /api/matches/99/scoreboard endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/matches/99/scoreboard")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                match_data = data["data"]
                
                # Check if it's the correct match (Sentinels vs T1)
                team1_id = match_data.get("team1", {}).get("id")
                team2_id = match_data.get("team2", {}).get("id")
                
                if team1_id == 87 and team2_id == 86:
                    # Check if scoreboard data exists
                    scoreboard = match_data.get("scoreboard", {})
                    if scoreboard and "team1" in scoreboard and "team2" in scoreboard:
                        log_test("Match Scoreboard", True, "Successfully retrieved live scoreboard for Sentinels vs T1")
                    else:
                        log_test("Match Scoreboard", False, "Scoreboard data is missing or incomplete")
                else:
                    log_test("Match Scoreboard", False, f"Expected teams 87 vs 86, got {team1_id} vs {team2_id}")
            else:
                log_test("Match Scoreboard", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Match Scoreboard", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Match Scoreboard", False, f"Exception: {str(e)}")

def test_player_stats(player_id, player_name):
    """Test GET /api/analytics/players/{player_id}/stats endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/analytics/players/{player_id}/stats")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                player_data = data["data"]
                
                # Check if it's the correct player
                if player_data.get("player", {}).get("id") == player_id:
                    # Check if stats data exists
                    stats = player_data.get("stats", {})
                    if stats and "matches_played" in stats and "avg_damage" in stats:
                        log_test(f"Player Stats - {player_name}", True, f"Successfully retrieved performance analytics for {player_name}")
                    else:
                        log_test(f"Player Stats - {player_name}", False, "Stats data is missing or incomplete")
                else:
                    log_test(f"Player Stats - {player_name}", False, f"Expected player ID {player_id}, got {player_data.get('player', {}).get('id')}")
            else:
                log_test(f"Player Stats - {player_name}", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test(f"Player Stats - {player_name}", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test(f"Player Stats - {player_name}", False, f"Exception: {str(e)}")

def test_hero_usage_stats():
    """Test GET /api/analytics/heroes/usage endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/analytics/heroes/usage")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                hero_stats = data["data"]
                
                # Check if hero usage data exists for at least a few heroes
                if len(hero_stats) >= 5:
                    # Check if the data structure is correct
                    sample_hero = next(iter(hero_stats.values()))
                    if "pick_rate" in sample_hero and "win_rate" in sample_hero:
                        log_test("Hero Usage Stats", True, f"Successfully retrieved usage statistics for {len(hero_stats)} heroes")
                    else:
                        log_test("Hero Usage Stats", False, "Hero usage data is missing required fields")
                else:
                    log_test("Hero Usage Stats", False, f"Expected at least 5 heroes, got {len(hero_stats)}")
            else:
                log_test("Hero Usage Stats", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Hero Usage Stats", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Hero Usage Stats", False, f"Exception: {str(e)}")

def test_player_leaderboards():
    """Test GET /api/leaderboards/players endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/leaderboards/players")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                players = data["data"]
                
                # Check if player leaderboard data exists
                if len(players) > 0:
                    # Check if the data structure is correct
                    if "avg_score" in players[0] and "avg_damage" in players[0]:
                        log_test("Player Leaderboards", True, f"Successfully retrieved leaderboard data for {len(players)} players")
                    else:
                        log_test("Player Leaderboards", False, "Player leaderboard data is missing required fields")
                else:
                    log_test("Player Leaderboards", False, "No players found in leaderboard")
            else:
                log_test("Player Leaderboards", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Player Leaderboards", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Player Leaderboards", False, f"Exception: {str(e)}")

def test_player_leaderboards_sorted():
    """Test GET /api/leaderboards/players?sort_by=damage endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/leaderboards/players?sort_by=damage")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                players = data["data"]
                
                # Check if player leaderboard data exists
                if len(players) > 0:
                    # Check if the data is sorted by damage
                    if data.get("sort_by") == "damage":
                        # Check if the first player has higher damage than the second
                        if len(players) >= 2:
                            if players[0]["avg_damage"] >= players[1]["avg_damage"]:
                                log_test("Player Leaderboards - Sorted by Damage", True, "Successfully retrieved player leaderboard sorted by damage")
                            else:
                                log_test("Player Leaderboards - Sorted by Damage", False, "Players are not correctly sorted by damage")
                        else:
                            log_test("Player Leaderboards - Sorted by Damage", True, "Successfully retrieved player leaderboard with only one player")
                    else:
                        log_test("Player Leaderboards - Sorted by Damage", False, f"Expected sort_by=damage, got sort_by={data.get('sort_by')}")
                else:
                    log_test("Player Leaderboards - Sorted by Damage", False, "No players found in leaderboard")
            else:
                log_test("Player Leaderboards - Sorted by Damage", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Player Leaderboards - Sorted by Damage", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Player Leaderboards - Sorted by Damage", False, f"Exception: {str(e)}")

def test_team_leaderboards():
    """Test GET /api/leaderboards/teams endpoint"""
    try:
        response = requests.get(f"{BASE_URL}/leaderboards/teams")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                teams = data["data"]
                
                # Check if team leaderboard data exists
                if len(teams) > 0:
                    # Check if the data structure is correct
                    if "rank" in teams[0] and "wins" in teams[0] and "losses" in teams[0]:
                        log_test("Team Leaderboards", True, f"Successfully retrieved leaderboard data for {len(teams)} teams")
                    else:
                        log_test("Team Leaderboards", False, "Team leaderboard data is missing required fields")
                else:
                    log_test("Team Leaderboards", False, "No teams found in leaderboard")
            else:
                log_test("Team Leaderboards", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Team Leaderboards", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Team Leaderboards", False, f"Exception: {str(e)}")

def test_event_creation():
    """Test event creation with new types"""
    token = get_admin_token()
    if not token:
        log_test("Event Creation", False, "Failed to get admin token")
        return

    # Test data for event creation
    event_data = {
        "name": "Test Championship",
        "type": "championship",
        "status": "upcoming",
        "start_date": (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%d"),
        "end_date": (datetime.now() + timedelta(days=2)).strftime("%Y-%m-%d")
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/admin/events", json=event_data, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                log_test("Event Creation", True, f"Successfully created event with type '{event_data['type']}'")
            else:
                log_test("Event Creation", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Event Creation", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Event Creation", False, f"Exception: {str(e)}")

def test_match_creation_without_event():
    """Test match creation without event_id"""
    token = get_admin_token()
    if not token:
        log_test("Match Creation Without Event", False, "Failed to get admin token")
        return

    # Test data for match creation without event_id
    match_data = {
        "team1_id": 1,
        "team2_id": 2,
        "scheduled_at": (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%dT10:00:00Z"),
        "format": "BO3",
        "status": "upcoming"
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/admin/matches", json=match_data, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                log_test("Match Creation Without Event", True, "Successfully created match without event_id")
            else:
                log_test("Match Creation Without Event", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Match Creation Without Event", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Match Creation Without Event", False, f"Exception: {str(e)}")

def test_team_flag_upload():
    """Test team flag upload"""
    token = get_admin_token()
    if not token:
        log_test("Team Flag Upload", False, "Failed to get admin token")
        return

    # Create a test image file
    try:
        from PIL import Image
        import io
        
        # Create a small test image
        img = Image.new('RGB', (64, 42), color='red')
        img_byte_arr = io.BytesIO()
        img.save(img_byte_arr, format='PNG')
        img_byte_arr.seek(0)
        
        # Upload the image
        headers = {"Authorization": f"Bearer {token}"}
        files = {"flag": ("test_flag.png", img_byte_arr, "image/png")}
        
        response = requests.post(f"{BASE_URL}/upload/team/1/flag", files=files, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                log_test("Team Flag Upload", True, "Successfully uploaded team flag")
            else:
                log_test("Team Flag Upload", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Team Flag Upload", False, f"Request failed with status code {response.status_code}: {response.text}")
    except ImportError:
        log_test("Team Flag Upload", False, "PIL library not available, skipping test")
    except Exception as e:
        log_test("Team Flag Upload", False, f"Exception: {str(e)}")

def test_player_role_validation():
    """Test player role validation"""
    token = get_admin_token()
    if not token:
        log_test("Player Role Validation", False, "Failed to get admin token")
        return

    # Test data for player creation with valid role
    player_data = {
        "username": f"testplayer_{datetime.now().timestamp()}",
        "role": "Duelist",
        "name": "Test Player"
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/admin/players", json=player_data, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                log_test("Player Role Validation", True, f"Successfully created player with role '{player_data['role']}'")
            else:
                log_test("Player Role Validation", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Player Role Validation", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Player Role Validation", False, f"Exception: {str(e)}")

def test_forum_endpoints():
    """Test forum endpoints"""
    try:
        # Test individual thread endpoint
        thread_response = requests.get(f"{BASE_URL}/forums/threads/1")
        thread_success = thread_response.status_code == 200 and thread_response.json().get("success", False)
        
        # Test categories endpoint
        categories_response = requests.get(f"{BASE_URL}/forums/categories")
        categories_success = categories_response.status_code == 200 and categories_response.json().get("success", False)
        
        if thread_success:
            log_test("Forum Thread Endpoint", True, "Successfully retrieved forum thread")
        else:
            log_test("Forum Thread Endpoint", False, f"Failed to retrieve forum thread: {thread_response.status_code}")
        
        if categories_success:
            log_test("Forum Categories Endpoint", True, "Successfully retrieved forum categories")
        else:
            log_test("Forum Categories Endpoint", False, f"Failed to retrieve forum categories: {categories_response.status_code}")
    except Exception as e:
        log_test("Forum Endpoints", False, f"Exception: {str(e)}")

def test_update_match_viewers():
    """Test POST /api/matches/99/viewers endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Update Match Viewers", False, "Failed to get admin token")
        return

    # Test data for updating match viewers
    viewers_data = {
        "viewers": 1500,
        "platform": "Twitch",
        "stream_url": "https://twitch.tv/marvelrivals"
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/matches/99/viewers", json=viewers_data, headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                log_test("Update Match Viewers", True, f"Successfully updated match viewers to {viewers_data['viewers']}")
            else:
                log_test("Update Match Viewers", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Update Match Viewers", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Update Match Viewers", False, f"Exception: {str(e)}")

def test_aggregate_match_stats():
    """Test POST /api/matches/99/aggregate-stats endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Aggregate Match Stats", False, "Failed to get admin token")
        return

    # Empty payload as specified
    stats_data = {}

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/matches/99/aggregate-stats", json=stats_data, headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                log_test("Aggregate Match Stats", True, "Successfully aggregated match statistics")
            else:
                log_test("Aggregate Match Stats", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Aggregate Match Stats", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Aggregate Match Stats", False, f"Exception: {str(e)}")

def test_complete_match():
    """Test POST /api/matches/99/complete endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Complete Match", False, "Failed to get admin token")
        return

    # Test data for completing a match
    complete_data = {
        "winner_team_id": 87,
        "final_score": {"team1": 2, "team2": 1},
        "match_duration": "45:30",
        "mvp_player_id": 183
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/matches/99/complete", json=complete_data, headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                log_test("Complete Match", True, f"Successfully completed match with winner team ID {complete_data['winner_team_id']}")
            else:
                log_test("Complete Match", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Complete Match", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Complete Match", False, f"Exception: {str(e)}")

def test_live_scoreboard_data_consistency():
    """Test GET /api/matches/99/live-scoreboard endpoint for data consistency"""
    try:
        # First, get the current scoreboard data
        response = requests.get(f"{BASE_URL}/matches/99/scoreboard")
        
        if response.status_code != 200:
            log_test("Live Scoreboard Data Consistency", False, f"Initial scoreboard request failed with status code {response.status_code}")
            return
            
        initial_data = response.json()
        if not initial_data.get("success"):
            log_test("Live Scoreboard Data Consistency", False, f"Initial API returned error: {initial_data.get('message', 'Unknown error')}")
            return
            
        initial_map = initial_data.get("data", {}).get("current_map")
        initial_mode = initial_data.get("data", {}).get("current_mode")
        
        # Now update the current map and mode
        token = get_admin_token()
        if not token:
            log_test("Live Scoreboard Data Consistency", False, "Failed to get admin token")
            return
            
        # Update to a different map
        new_map = "Tokyo" if initial_map != "Tokyo" else "Wakanda"
        update_data = {"current_map": new_map}
        
        headers = {"Authorization": f"Bearer {token}"}
        update_response = requests.put(f"{BASE_URL}/admin/matches/99/current-map", json=update_data, headers=headers)
        
        if update_response.status_code != 200:
            log_test("Live Scoreboard Data Consistency", False, f"Map update request failed with status code {update_response.status_code}: {update_response.text}")
            return
            
        # Now get the scoreboard again to verify the update
        updated_response = requests.get(f"{BASE_URL}/matches/99/scoreboard")
        
        if updated_response.status_code != 200:
            log_test("Live Scoreboard Data Consistency", False, f"Updated scoreboard request failed with status code {updated_response.status_code}")
            return
            
        updated_data = updated_response.json()
        if not updated_data.get("success"):
            log_test("Live Scoreboard Data Consistency", False, f"Updated API returned error: {updated_data.get('message', 'Unknown error')}")
            return
            
        updated_map = updated_data.get("data", {}).get("current_map")
        updated_mode = updated_data.get("data", {}).get("current_mode")
        
        # Check if the map was updated correctly
        if updated_map == new_map:
            # Check if the mode was updated to match the map
            map_mode_mapping = {
                "Asgard": "Control",
                "Wakanda": "Escort",
                "New York": "Hybrid",
                "Tokyo": "Control",
                "Latveria": "Assault",
                "Sakaar": "Escort",
                "Knowhere": "Control",
                "Madripoor": "Hybrid",
                "Attilan": "Assault",
                "Savage Land": "Control"
            }
            
            expected_mode = map_mode_mapping.get(new_map)
            
            if updated_mode == expected_mode:
                log_test("Live Scoreboard Data Consistency", True, 
                         f"Successfully verified map/mode consistency. Map updated from '{initial_map}' to '{updated_map}' and mode updated from '{initial_mode}' to '{updated_mode}'")
            else:
                log_test("Live Scoreboard Data Consistency", False, 
                         f"Map updated correctly to '{updated_map}', but mode '{updated_mode}' doesn't match expected '{expected_mode}'")
        else:
            log_test("Live Scoreboard Data Consistency", False, 
                     f"Map not updated correctly. Expected '{new_map}', got '{updated_map}'")
    except Exception as e:
        log_test("Live Scoreboard Data Consistency", False, f"Exception: {str(e)}")

def test_match_creation_with_valid_event():
    """Test POST /api/admin/matches with valid event ID"""
    token = get_admin_token()
    if not token:
        log_test("Match Creation With Valid Event", False, "Failed to get admin token")
        return

    # Test data for match creation with valid event_id
    match_data = {
        "team1_id": 87,  # Sentinels
        "team2_id": 86,  # T1
        "event_id": 22,  # Valid event ID
        "scheduled_at": (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%dT10:00:00Z"),
        "format": "BO5",
        "status": "upcoming",
        "stream_url": "https://twitch.tv/marvel_rivals_official"
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/admin/matches", json=match_data, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                log_test("Match Creation With Valid Event", True, f"Successfully created match with event_id: {match_data['event_id']}")
            else:
                log_test("Match Creation With Valid Event", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Match Creation With Valid Event", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Match Creation With Valid Event", False, f"Exception: {str(e)}")

def test_match_creation_with_invalid_event():
    """Test POST /api/admin/matches with invalid event ID to verify error message"""
    token = get_admin_token()
    if not token:
        log_test("Match Creation With Invalid Event", False, "Failed to get admin token")
        return

    # Test data for match creation with invalid event_id
    match_data = {
        "team1_id": 87,  # Sentinels
        "team2_id": 86,  # T1
        "event_id": 20,  # Invalid event ID
        "scheduled_at": (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%dT10:00:00Z"),
        "format": "BO5",
        "status": "upcoming",
        "stream_url": "https://twitch.tv/marvel_rivals_official"
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/admin/matches", json=match_data, headers=headers)
        
        # We expect this to fail with a 400 or 404 status code
        if response.status_code in [400, 404, 422]:
            data = response.json()
            error_message = data.get("detail", "") if isinstance(data, dict) else str(data)
            
            # Check if the error message mentions available events
            if "22" in error_message and "event" in error_message.lower():
                log_test("Match Creation With Invalid Event", True, 
                         f"Received appropriate error message for invalid event ID: {error_message}")
            else:
                log_test("Match Creation With Invalid Event", False, 
                         f"Error message doesn't mention available events: {error_message}")
        else:
            # If it succeeded, that's unexpected
            if response.status_code in [200, 201]:
                log_test("Match Creation With Invalid Event", False, 
                         "Request unexpectedly succeeded with invalid event ID")
            else:
                log_test("Match Creation With Invalid Event", False, 
                         f"Request failed with unexpected status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Match Creation With Invalid Event", False, f"Exception: {str(e)}")

def test_player_statistics_update():
    """Test POST /api/matches/99/players/{player_id}/stats endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Player Statistics Update", False, "Failed to get admin token")
        return

    # Test data for updating player statistics
    player_id = 183  # SicK from Sentinels
    stats_data = {
        "kills": 30,
        "deaths": 8,
        "assists": 15,
        "damage": 20000,
        "healing": 0,
        "score": 98,
        "hero": "Iron Man"
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/matches/99/players/{player_id}/stats", json=stats_data, headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                log_test("Player Statistics Update", True, f"Successfully updated statistics for player ID {player_id}")
            else:
                log_test("Player Statistics Update", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Player Statistics Update", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Player Statistics Update", False, f"Exception: {str(e)}")

def test_player_statistics_update_invalid_player():
    """Test POST /api/matches/99/players/{player_id}/stats with invalid player ID"""
    token = get_admin_token()
    if not token:
        log_test("Player Statistics Update - Invalid Player", False, "Failed to get admin token")
        return

    # Test data for updating player statistics with invalid player ID
    invalid_player_id = 999  # Non-existent player ID
    stats_data = {
        "kills": 30,
        "deaths": 8,
        "assists": 15,
        "damage": 20000,
        "healing": 0,
        "score": 98,
        "hero": "Iron Man"
    }

    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/matches/99/players/{invalid_player_id}/stats", json=stats_data, headers=headers)
        
        # We expect this to fail with a 400 or 404 status code
        if response.status_code in [400, 404, 422]:
            data = response.json()
            error_message = data.get("detail", "") if isinstance(data, dict) else str(data)
            
            # Check if the error message mentions valid player ID ranges
            if ("183-188" in error_message or "189-194" in error_message) and "player" in error_message.lower():
                log_test("Player Statistics Update - Invalid Player", True, 
                         f"Received appropriate error message for invalid player ID: {error_message}")
            else:
                log_test("Player Statistics Update - Invalid Player", False, 
                         f"Error message doesn't mention valid player ID ranges: {error_message}")
        else:
            # If it succeeded, that's unexpected
            if response.status_code in [200, 201]:
                log_test("Player Statistics Update - Invalid Player", False, 
                         "Request unexpectedly succeeded with invalid player ID")
            else:
                log_test("Player Statistics Update - Invalid Player", False, 
                         f"Request failed with unexpected status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Player Statistics Update - Invalid Player", False, f"Exception: {str(e)}")

def run_all_tests():
    """Run all tests"""
    print("Starting Marvel Rivals Esports Platform API Tests...")
    
    # Game Data Endpoints
    test_game_data_heroes()
    test_game_data_all_heroes()
    test_game_data_maps()
    test_game_data_modes()
    
    # Live Scoring System
    test_match_scoreboard()
    test_live_scoreboard_data_consistency()
    
    # Analytics Endpoints
    test_player_stats(183, "SicK")
    test_player_stats(189, "Faker")
    test_hero_usage_stats()
    
    # Leaderboards
    test_player_leaderboards()
    test_player_leaderboards_sorted()
    test_team_leaderboards()
    
    # Match Creation with Valid/Invalid Event
    test_match_creation_with_valid_event()
    test_match_creation_with_invalid_event()
    
    # Player Statistics Update
    test_player_statistics_update()
    test_player_statistics_update_invalid_player()
    
    # Problematic POST endpoints
    test_update_match_viewers()
    test_aggregate_match_stats()
    test_complete_match()
    
    # Print summary
    print("\n=== Test Summary ===")
    print(f"Passed: {len(test_results['success'])}")
    print(f"Failed: {len(test_results['failure'])}")
    
    if test_results["failure"]:
        print("\nFailed Tests:")
        for test in test_results["failure"]:
            print(f"- {test['name']}: {test['message']}")
    
    return len(test_results["failure"]) == 0

if __name__ == "__main__":
    success = run_all_tests()
    sys.exit(0 if success else 1)