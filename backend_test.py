#!/usr/bin/env python3
import requests
import json
import os
import sys
from datetime import datetime, timedelta

# Base URL for API requests
BASE_URL = "http://localhost:8000/api"

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

def run_all_tests():
    """Run all tests"""
    print("Starting Marvel Rivals Esports Platform API Tests...")
    
    # Run tests
    test_event_creation()
    test_match_creation_without_event()
    test_team_flag_upload()
    test_player_role_validation()
    test_forum_endpoints()
    
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