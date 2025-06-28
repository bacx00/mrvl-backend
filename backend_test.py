#!/usr/bin/env python3
import requests
import json
import sys
from datetime import datetime, timedelta

# Base URL for API requests
BASE_URL = "https://4f39eda8-cbb7-481b-9353-f2b323e7f99d.preview.emergentagent.com/api"

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

# Test data for BO1 competitive match creation
BO1_MATCH_DATA = {
    "team1_id": 87,  # Sentinels
    "team2_id": 88,  # Another team
    "match_format": "BO1",
    "map_pool": [
        {
            "map_name": "Yggsgard: Royal Palace",
            "game_mode": "Domination"
        }
    ],
    "scheduled_at": (datetime.now() + timedelta(hours=1)).isoformat()
}

# Test data for BO3 competitive match creation
COMPETITIVE_MATCH_DATA = {
    "team1_id": 87,  # Sentinels
    "team2_id": 86,  # T1
    "match_format": "BO3",
    "map_pool": [
        {
            "map_name": "Yggsgard: Royal Palace",
            "game_mode": "Domination"
        },
        {
            "map_name": "Tokyo 2099: Spider-Islands",
            "game_mode": "Convoy"
        },
        {
            "map_name": "Wakanda: Birnin T'Challa",
            "game_mode": "Domination"
        }
    ],
    "scheduled_at": (datetime.now() + timedelta(hours=1)).isoformat()
}

# Test data for BO5 match creation
BO5_MATCH_DATA = {
    "team1_id": 87,  # Sentinels
    "team2_id": 86,  # T1
    "match_format": "BO5",
    "map_pool": [
        {
            "map_name": "Yggsgard: Royal Palace",
            "game_mode": "Domination"
        },
        {
            "map_name": "Tokyo 2099: Spider-Islands",
            "game_mode": "Convoy"
        },
        {
            "map_name": "Wakanda: Birnin T'Challa",
            "game_mode": "Domination"
        },
        {
            "map_name": "Latveria: Castle Doom",
            "game_mode": "Assault"
        },
        {
            "map_name": "Sakaar: Grand Arena",
            "game_mode": "Control"
        }
    ],
    "scheduled_at": (datetime.now() + timedelta(hours=2)).isoformat()
}

# Team composition data
TEAM_COMPOSITION_DATA = {
    "round_number": 1,
    "team1_composition": [
        {
            "player_id": 183,
            "hero": "Iron Man",
            "role": "Duelist"
        },
        {
            "player_id": 184,
            "hero": "Black Panther",
            "role": "Duelist"
        },
        {
            "player_id": 185,
            "hero": "Thor",
            "role": "Vanguard"
        },
        {
            "player_id": 186,
            "hero": "Doctor Strange",
            "role": "Vanguard"
        },
        {
            "player_id": 187,
            "hero": "Luna Snow",
            "role": "Strategist"
        },
        {
            "player_id": 188,
            "hero": "Rocket Raccoon",
            "role": "Strategist"
        }
    ],
    "team2_composition": [
        {
            "player_id": 189,
            "hero": "Iron Man",
            "role": "Duelist"
        },
        {
            "player_id": 190,
            "hero": "Doctor Strange",
            "role": "Vanguard"
        },
        {
            "player_id": 191,
            "hero": "Thor",
            "role": "Vanguard"
        },
        {
            "player_id": 192,
            "hero": "Black Panther",
            "role": "Duelist"
        },
        {
            "player_id": 193,
            "hero": "Luna Snow",
            "role": "Strategist"
        },
        {
            "player_id": 194,
            "hero": "Hulk",
            "role": "Vanguard"
        }
    ]
}

# Player stats update data
PLAYER_STATS_DATA = {
    "eliminations": 12,
    "deaths": 4,
    "assists": 8,
    "damage": 8500,
    "healing": 0,
    "final_blows": 8,
    "environmental_kills": 1,
    "accuracy_percentage": 45.5,
    "critical_hits": 15,
    "hero_played": "Iron Man",
    "role_played": "Duelist"
}

# Bulk player stats update data
BULK_PLAYER_STATS_DATA = {
    "player_stats": [
        {
            "player_id": 183,
            "eliminations": 15,
            "deaths": 5,
            "assists": 10,
            "damage": 9500,
            "hero_played": "Iron Man",
            "role_played": "Duelist"
        },
        {
            "player_id": 184,
            "eliminations": 18,
            "deaths": 6,
            "assists": 8,
            "damage": 10200,
            "hero_played": "Black Panther",
            "role_played": "Duelist"
        },
        {
            "player_id": 185,
            "eliminations": 10,
            "deaths": 7,
            "assists": 12,
            "damage": 7500,
            "damage_blocked": 5000,
            "hero_played": "Thor",
            "role_played": "Vanguard"
        }
    ]
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

def test_create_competitive_match():
    """Test POST /api/admin/matches/create-competitive endpoint for BO3"""
    token = get_admin_token()
    if not token:
        log_test("Create Competitive Match", False, "Failed to get admin token")
        return None
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/admin/matches/create-competitive", json=COMPETITIVE_MATCH_DATA, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                match_id = data.get("data", {}).get("match", {}).get("id")
                rounds = data.get("data", {}).get("rounds", [])
                
                # Verify that 3 rounds were created for BO3
                if len(rounds) == 3:
                    log_test("Create Competitive Match", True, f"Successfully created BO3 competitive match with ID {match_id} with 3 rounds")
                else:
                    log_test("Create Competitive Match", False, f"Expected 3 rounds for BO3 match, got {len(rounds)}")
                
                return match_id
            else:
                log_test("Create Competitive Match", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return None
        else:
            log_test("Create Competitive Match", False, f"Request failed with status code {response.status_code}: {response.text}")
            return None
    except Exception as e:
        log_test("Create Competitive Match", False, f"Exception: {str(e)}")
        return None

def test_create_bo5_match():
    """Test creating a BO5 competitive match"""
    token = get_admin_token()
    if not token:
        log_test("Create BO5 Match", False, "Failed to get admin token")
        return None
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.post(f"{BASE_URL}/admin/matches/create-competitive", json=BO5_MATCH_DATA, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                match_id = data.get("data", {}).get("match", {}).get("id")
                rounds = data.get("data", {}).get("rounds", [])
                
                # Verify that 5 rounds were created for BO5
                if len(rounds) == 5:
                    log_test("Create BO5 Match", True, f"Successfully created BO5 match with ID {match_id} with 5 rounds")
                else:
                    log_test("Create BO5 Match", False, f"Expected 5 rounds for BO5 match, got {len(rounds)}")
                
                return match_id
            else:
                log_test("Create BO5 Match", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return None
        else:
            log_test("Create BO5 Match", False, f"Request failed with status code {response.status_code}: {response.text}")
            return None
    except Exception as e:
        log_test("Create BO5 Match", False, f"Exception: {str(e)}")
        return None

def test_timer_management(match_id):
    """Test timer management endpoints"""
    token = get_admin_token()
    if not token:
        log_test("Timer Management", False, "Failed to get admin token")
        return False
    
    if not match_id:
        log_test("Timer Management", False, "No match ID provided")
        return False
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        
        # Test start preparation timer
        prep_data = {"duration_seconds": 45, "phase": "hero_selection"}
        prep_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/timer/start-preparation", json=prep_data, headers=headers)
        
        if prep_response.status_code == 200 and prep_response.json().get("success"):
            log_test("Start Preparation Timer", True, "Successfully started preparation timer")
        else:
            log_test("Start Preparation Timer", False, f"Failed to start preparation timer: {prep_response.status_code}")
            return False
        
        # Test start match timer
        match_data = {"duration_seconds": 600}
        match_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/timer/start-match", json=match_data, headers=headers)
        
        if match_response.status_code == 200 and match_response.json().get("success"):
            log_test("Start Match Timer", True, "Successfully started match timer")
        else:
            log_test("Start Match Timer", False, f"Failed to start match timer: {match_response.status_code}")
            return False
        
        # Test pause timer
        pause_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/timer/pause", headers=headers)
        
        if pause_response.status_code == 200 and pause_response.json().get("success"):
            log_test("Pause Timer", True, "Successfully paused timer")
        else:
            log_test("Pause Timer", False, f"Failed to pause timer: {pause_response.status_code}")
            return False
        
        # Test resume timer
        resume_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/timer/resume", headers=headers)
        
        if resume_response.status_code == 200 and resume_response.json().get("success"):
            log_test("Resume Timer", True, "Successfully resumed timer")
        else:
            log_test("Resume Timer", False, f"Failed to resume timer: {resume_response.status_code}")
            return False
        
        # Test overtime timer
        overtime_data = {"grace_period_ms": 500, "extended_duration": 180}
        overtime_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/timer/overtime", json=overtime_data, headers=headers)
        
        if overtime_response.status_code == 200 and overtime_response.json().get("success"):
            log_test("Overtime Timer", True, "Successfully started overtime timer")
        else:
            log_test("Overtime Timer", False, f"Failed to start overtime timer: {overtime_response.status_code}")
            return False
        
        return True
    except Exception as e:
        log_test("Timer Management", False, f"Exception: {str(e)}")
        return False

def test_team_composition(match_id):
    """Test team composition endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Team Composition", False, "Failed to get admin token")
        return False
    
    if not match_id:
        log_test("Team Composition", False, "No match ID provided")
        return False
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/team-composition", json=TEAM_COMPOSITION_DATA, headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                log_test("Team Composition", True, "Successfully updated 6v6 team compositions")
                return True
            else:
                log_test("Team Composition", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return False
        else:
            log_test("Team Composition", False, f"Request failed with status code {response.status_code}: {response.text}")
            return False
    except Exception as e:
        log_test("Team Composition", False, f"Exception: {str(e)}")
        return False

def test_round_transition(match_id):
    """Test round transition endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Round Transition", False, "Failed to get admin token")
        return False
    
    if not match_id:
        log_test("Round Transition", False, "No match ID provided")
        return False
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        
        # Complete current round
        complete_data = {
            "action": "complete_round",
            "winner_team_id": 87,  # Sentinels
            "round_scores": {
                "team1": 3,
                "team2": 2
            }
        }
        
        complete_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/round-transition", json=complete_data, headers=headers)
        
        if complete_response.status_code == 200 and complete_response.json().get("success"):
            log_test("Complete Round", True, "Successfully completed round")
        else:
            log_test("Complete Round", False, f"Failed to complete round: {complete_response.status_code}")
            return False
        
        # Start next round
        next_data = {
            "action": "start_next_round"
        }
        
        next_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/round-transition", json=next_data, headers=headers)
        
        if next_response.status_code == 200 and next_response.json().get("success"):
            log_test("Start Next Round", True, "Successfully started next round")
        else:
            log_test("Start Next Round", False, f"Failed to start next round: {next_response.status_code}")
            return False
        
        # Complete match
        complete_match_data = {
            "action": "complete_match"
        }
        
        complete_match_response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/round-transition", json=complete_match_data, headers=headers)
        
        if complete_match_response.status_code == 200 and complete_match_response.json().get("success"):
            log_test("Complete Match", True, "Successfully completed match")
        else:
            log_test("Complete Match", False, f"Failed to complete match: {complete_match_response.status_code}")
            return False
        
        return True
    except Exception as e:
        log_test("Round Transition", False, f"Exception: {str(e)}")
        return False

def test_player_stats_update(match_id):
    """Test player statistics update endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Player Stats Update", False, "Failed to get admin token")
        return False
    
    if not match_id:
        log_test("Player Stats Update", False, "No match ID provided")
        return False
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        player_id = 183  # SicK from Sentinels
        
        response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/player/{player_id}/stats", json=PLAYER_STATS_DATA, headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                log_test("Player Stats Update", True, f"Successfully updated stats for player {player_id}")
                return True
            else:
                log_test("Player Stats Update", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return False
        else:
            log_test("Player Stats Update", False, f"Request failed with status code {response.status_code}: {response.text}")
            return False
    except Exception as e:
        log_test("Player Stats Update", False, f"Exception: {str(e)}")
        return False

def test_bulk_player_stats_update(match_id):
    """Test bulk player statistics update endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Bulk Player Stats Update", False, "Failed to get admin token")
        return False
    
    if not match_id:
        log_test("Bulk Player Stats Update", False, "No match ID provided")
        return False
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/bulk-player-stats", json=BULK_PLAYER_STATS_DATA, headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                log_test("Bulk Player Stats Update", True, f"Successfully updated stats for {data.get('data', {}).get('players_updated', 0)} players")
                return True
            else:
                log_test("Bulk Player Stats Update", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return False
        else:
            log_test("Bulk Player Stats Update", False, f"Request failed with status code {response.status_code}: {response.text}")
            return False
    except Exception as e:
        log_test("Bulk Player Stats Update", False, f"Exception: {str(e)}")
        return False

def test_live_scoreboard(match_id):
    """Test live scoreboard endpoint"""
    if not match_id:
        log_test("Live Scoreboard", False, "No match ID provided")
        return False
    
    try:
        response = requests.get(f"{BASE_URL}/matches/{match_id}/live-scoreboard")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                # Check cache control headers
                cache_control = response.headers.get("Cache-Control")
                if cache_control and "max-age=5" in cache_control:
                    log_test("Live Scoreboard", True, f"Successfully retrieved live scoreboard with proper cache control")
                else:
                    log_test("Live Scoreboard", True, f"Successfully retrieved live scoreboard but missing proper cache control")
                return True
            else:
                log_test("Live Scoreboard", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return False
        else:
            log_test("Live Scoreboard", False, f"Request failed with status code {response.status_code}: {response.text}")
            return False
    except Exception as e:
        log_test("Live Scoreboard", False, f"Exception: {str(e)}")
        return False

def test_admin_live_control(match_id):
    """Test admin live control dashboard endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Admin Live Control", False, "Failed to get admin token")
        return False
    
    if not match_id:
        log_test("Admin Live Control", False, "No match ID provided")
        return False
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.get(f"{BASE_URL}/admin/matches/{match_id}/live-control", headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success"):
                control_capabilities = data.get("data", {}).get("control_capabilities", {})
                if control_capabilities:
                    log_test("Admin Live Control", True, "Successfully retrieved admin live control dashboard")
                else:
                    log_test("Admin Live Control", False, "Missing control capabilities in response")
                return True
            else:
                log_test("Admin Live Control", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return False
        else:
            log_test("Admin Live Control", False, f"Request failed with status code {response.status_code}: {response.text}")
            return False
    except Exception as e:
        log_test("Admin Live Control", False, f"Exception: {str(e)}")
        return False

def test_match_history(match_id):
    """Test match history endpoints"""
    try:
        # Test team match history
        team_id = 87  # Sentinels
        team_response = requests.get(f"{BASE_URL}/teams/{team_id}/match-history")
        
        if team_response.status_code == 200 and team_response.json().get("success"):
            log_test("Team Match History", True, "Successfully retrieved team match history")
        else:
            log_test("Team Match History", False, f"Failed to retrieve team match history: {team_response.status_code}")
        
        # Test player match history
        player_id = 183  # SicK
        player_response = requests.get(f"{BASE_URL}/players/{player_id}/match-history")
        
        if player_response.status_code == 200 and player_response.json().get("success"):
            log_test("Player Match History", True, "Successfully retrieved player match history")
        else:
            log_test("Player Match History", False, f"Failed to retrieve player match history: {player_response.status_code}")
        
        return True
    except Exception as e:
        log_test("Match History", False, f"Exception: {str(e)}")
        return False

def test_match_status_update(match_id):
    """Test match status update endpoint"""
    token = get_admin_token()
    if not token:
        log_test("Match Status Update", False, "Failed to get admin token")
        return False
    
    if not match_id:
        log_test("Match Status Update", False, "No match ID provided")
        return False
    
    try:
        headers = {"Authorization": f"Bearer {token}"}
        
        # Test different status updates
        statuses = ["live", "paused", "live", "completed"]
        
        for status in statuses:
            status_data = {
                "status": status,
                "reason": f"Testing {status} status"
            }
            
            response = requests.put(f"{BASE_URL}/admin/matches/{match_id}/status", json=status_data, headers=headers)
            
            if response.status_code == 200 and response.json().get("success"):
                log_test(f"Match Status Update - {status}", True, f"Successfully updated match status to {status}")
            else:
                log_test(f"Match Status Update - {status}", False, f"Failed to update match status to {status}: {response.status_code}")
                return False
        
        return True
    except Exception as e:
        log_test("Match Status Update", False, f"Exception: {str(e)}")
        return False

def test_viewer_count_update(match_id):
    """Test viewer count update endpoint"""
    if not match_id:
        log_test("Viewer Count Update", False, "No match ID provided")
        return False
    
    try:
        # Test increment action
        increment_data = {
            "action": "increment"
        }
        
        increment_response = requests.post(f"{BASE_URL}/matches/{match_id}/viewers/update", json=increment_data)
        
        if increment_response.status_code == 200 and increment_response.json().get("success"):
            log_test("Viewer Count Increment", True, "Successfully incremented viewer count")
        else:
            log_test("Viewer Count Increment", False, f"Failed to increment viewer count: {increment_response.status_code}")
            return False
        
        # Test set action
        set_data = {
            "action": "set",
            "count": 1500
        }
        
        set_response = requests.post(f"{BASE_URL}/matches/{match_id}/viewers/update", json=set_data)
        
        if set_response.status_code == 200 and set_response.json().get("success"):
            log_test("Viewer Count Set", True, "Successfully set viewer count")
        else:
            log_test("Viewer Count Set", False, f"Failed to set viewer count: {set_response.status_code}")
            return False
        
        # Test decrement action
        decrement_data = {
            "action": "decrement"
        }
        
        decrement_response = requests.post(f"{BASE_URL}/matches/{match_id}/viewers/update", json=decrement_data)
        
        if decrement_response.status_code == 200 and decrement_response.json().get("success"):
            log_test("Viewer Count Decrement", True, "Successfully decremented viewer count")
        else:
            log_test("Viewer Count Decrement", False, f"Failed to decrement viewer count: {decrement_response.status_code}")
            return False
        
        return True
    except Exception as e:
        log_test("Viewer Count Update", False, f"Exception: {str(e)}")
        return False

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

def test_match_creation_with_maps_data():
    """Test match creation with maps_data and verify scoreboard retrieval"""
    token = get_admin_token()
    if not token:
        log_test("Match Creation With Maps Data", False, "Failed to get admin token")
        return None
    
    # Test data for match creation with maps_data as specified in the review request
    match_data = {
        "team1_id": 83,
        "team2_id": 84,
        "event_id": 22,
        "status": "live",
        "format": "BO1",
        "scheduled_at": (datetime.now() + timedelta(days=1)).isoformat(),  # Set to tomorrow
        "maps_data": [
            {
                "map_number": 1,
                "map_name": "Yggsgard: Yggdrasil",
                "mode": "Conquest",
                "team1_composition": [
                    {
                        "player_id": 195,
                        "player_name": "p6",
                        "hero": "Thor",
                        "role": "Tank"
                    }
                ],
                "team2_composition": []
            }
        ]
    }
    
    try:
        headers = {"Authorization": f"Bearer 415|ySK4yrjyULCTlprffD0KeT5zxd6J2mMMHOHkX6pv1d5fc012"}
        response = requests.post("https://staging.mrvl.net/api/admin/matches", json=match_data, headers=headers)
        
        if response.status_code in [200, 201]:
            data = response.json()
            if data.get("success"):
                match_id = data.get("data", {}).get("id")
                log_test("Match Creation With Maps Data", True, f"Successfully created match with ID {match_id} including maps_data")
                
                # Return the match ID for the scoreboard test
                return match_id
            else:
                log_test("Match Creation With Maps Data", False, f"API returned error: {data.get('message', 'Unknown error')}")
                return None
        else:
            log_test("Match Creation With Maps Data", False, f"Request failed with status code {response.status_code}: {response.text}")
            return None
    except Exception as e:
        log_test("Match Creation With Maps Data", False, f"Exception: {str(e)}")
        return None

def test_scoreboard_with_maps_data(match_id):
    """Test scoreboard retrieval for a match with maps_data"""
    if not match_id:
        log_test("Scoreboard With Maps Data", False, "No match ID provided")
        return
    
    try:
        response = requests.get(f"https://staging.mrvl.net/api/matches/{match_id}/scoreboard")
        
        if response.status_code == 200:
            data = response.json()
            if data.get("success") and "data" in data:
                match_data = data["data"]
                
                # Check if current_map is set correctly
                current_map = match_data.get("current_map")
                if current_map == "Yggsgard: Yggdrasil":
                    log_test("Scoreboard With Maps Data - Current Map", True, f"Current map is correctly set to '{current_map}'")
                else:
                    log_test("Scoreboard With Maps Data - Current Map", False, f"Expected current_map to be 'Yggsgard: Yggdrasil', got '{current_map}'")
                
                # Check if maps array contains the map data
                maps = match_data.get("maps_played", [])
                if maps:
                    map_data = maps[0]
                    if map_data.get("map") == "Yggsgard: Yggdrasil" and map_data.get("mode") == "Conquest":
                        log_test("Scoreboard With Maps Data - Maps Array", True, "Maps array contains the correct map data")
                    else:
                        log_test("Scoreboard With Maps Data - Maps Array", False, f"Maps array does not contain the expected data: {map_data}")
                else:
                    log_test("Scoreboard With Maps Data - Maps Array", False, "Maps array is empty")
                
                # Print the full response for debugging
                print(f"\nScoreboard Response for Match {match_id}:")
                print(json.dumps(match_data, indent=2))
            else:
                log_test("Scoreboard With Maps Data", False, f"API returned error: {data.get('message', 'Unknown error')}")
        else:
            log_test("Scoreboard With Maps Data", False, f"Request failed with status code {response.status_code}: {response.text}")
    except Exception as e:
        log_test("Scoreboard With Maps Data", False, f"Exception: {str(e)}")

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
    
    # Analytics Endpoints
    test_player_stats(183, "SicK")
    test_player_stats(189, "Faker")
    test_hero_usage_stats()
    
    # Leaderboards
    test_player_leaderboards()
    test_player_leaderboards_sorted()
    test_team_leaderboards()
    
    # Problematic POST endpoints
    test_update_match_viewers()
    test_aggregate_match_stats()
    test_complete_match()
    
    # Test match creation with maps_data and scoreboard retrieval
    print("\n=== Testing Match Creation with Maps Data vs Scoreboard Data Mismatch Fix ===")
    match_id = test_match_creation_with_maps_data()
    if match_id:
        test_scoreboard_with_maps_data(match_id)
    
    # Professional Live Scoring System Tests
    print("\n=== Testing Professional Live Scoring System ===")
    
    # Create a competitive match
    bo3_match_id = test_create_competitive_match()
    
    if bo3_match_id:
        # Test timer management
        test_timer_management(bo3_match_id)
        
        # Test team composition
        test_team_composition(bo3_match_id)
        
        # Test player stats update
        test_player_stats_update(bo3_match_id)
        
        # Test bulk player stats update
        test_bulk_player_stats_update(bo3_match_id)
        
        # Test live scoreboard
        test_live_scoreboard(bo3_match_id)
        
        # Test admin live control
        test_admin_live_control(bo3_match_id)
        
        # Test match status update
        test_match_status_update(bo3_match_id)
        
        # Test viewer count update
        test_viewer_count_update(bo3_match_id)
        
        # Test round transition
        test_round_transition(bo3_match_id)
    
    # Create a BO5 match
    bo5_match_id = test_create_bo5_match()
    
    # Test match history
    test_match_history(bo3_match_id if bo3_match_id else None)
    
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