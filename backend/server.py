from fastapi import FastAPI, HTTPException, Depends, UploadFile, File, Form, Query
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
from datetime import datetime, timedelta
import jwt
import os
import json
from uuid import uuid4
import random

# Initialize FastAPI app
app = FastAPI(title="Marvel Rivals Esports Platform API")

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# JWT Configuration
SECRET_KEY = "marvel_rivals_secret_key"
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 60

# OAuth2 scheme for token authentication
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="api/auth/login")

# Mock database
users_db = {
    "admin@marvelrivals.com": {
        "id": 1,
        "email": "admin@marvelrivals.com",
        "name": "Admin User",
        "password": "password123",
        "roles": ["admin"]
    }
}

teams_db = {
    1: {
        "id": 1,
        "name": "Team Alpha",
        "short_name": "ALP",
        "region": "NA",
        "country": "USA",
        "logo": None,
        "flag": None
    },
    2: {
        "id": 2,
        "name": "Team Beta",
        "short_name": "BET",
        "region": "EU",
        "country": "France",
        "logo": None,
        "flag": None
    },
    87: {
        "id": 87,
        "name": "Sentinels",
        "short_name": "SEN",
        "region": "NA",
        "country": "USA",
        "logo": "/storage/teams/logos/sentinels.png",
        "flag": "/storage/teams/flags/usa.png",
        "rank": 1,
        "wins": 42,
        "losses": 8,
        "total_score": 4250,
        "total_damage": 1250000,
        "total_healing": 750000
    },
    86: {
        "id": 86,
        "name": "T1",
        "short_name": "T1",
        "region": "KR",
        "country": "South Korea",
        "logo": "/storage/teams/logos/t1.png",
        "flag": "/storage/teams/flags/korea.png",
        "rank": 2,
        "wins": 40,
        "losses": 10,
        "total_score": 4100,
        "total_damage": 1200000,
        "total_healing": 780000
    }
}

# Basic 5 heroes
basic_heroes = [
    {"id": 1, "name": "Iron Man", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/iron_man.png"},
    {"id": 2, "name": "Doctor Strange", "role": "Support", "difficulty": 3, "image": "/assets/heroes/doctor_strange.png"},
    {"id": 3, "name": "Thor", "role": "Tank", "difficulty": 1, "image": "/assets/heroes/thor.png"},
    {"id": 4, "name": "Luna Snow", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/luna_snow.png"},
    {"id": 5, "name": "Black Panther", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/black_panther.png"}
]

# Complete 29 hero roster
all_heroes = basic_heroes + [
    {"id": 6, "name": "Spider-Man", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/spider_man.png"},
    {"id": 7, "name": "Captain America", "role": "Tank", "difficulty": 1, "image": "/assets/heroes/captain_america.png"},
    {"id": 8, "name": "Hulk", "role": "Tank", "difficulty": 1, "image": "/assets/heroes/hulk.png"},
    {"id": 9, "name": "Black Widow", "role": "Damage", "difficulty": 3, "image": "/assets/heroes/black_widow.png"},
    {"id": 10, "name": "Scarlet Witch", "role": "Support", "difficulty": 3, "image": "/assets/heroes/scarlet_witch.png"},
    {"id": 11, "name": "Loki", "role": "Damage", "difficulty": 3, "image": "/assets/heroes/loki.png"},
    {"id": 12, "name": "Storm", "role": "Support", "difficulty": 2, "image": "/assets/heroes/storm.png"},
    {"id": 13, "name": "Magneto", "role": "Damage", "difficulty": 3, "image": "/assets/heroes/magneto.png"},
    {"id": 14, "name": "Rocket Raccoon", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/rocket_raccoon.png"},
    {"id": 15, "name": "Groot", "role": "Tank", "difficulty": 1, "image": "/assets/heroes/groot.png"},
    {"id": 16, "name": "Star-Lord", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/star_lord.png"},
    {"id": 17, "name": "Gamora", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/gamora.png"},
    {"id": 18, "name": "Drax", "role": "Tank", "difficulty": 1, "image": "/assets/heroes/drax.png"},
    {"id": 19, "name": "Mantis", "role": "Support", "difficulty": 2, "image": "/assets/heroes/mantis.png"},
    {"id": 20, "name": "Hawkeye", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/hawkeye.png"},
    {"id": 21, "name": "Vision", "role": "Support", "difficulty": 3, "image": "/assets/heroes/vision.png"},
    {"id": 22, "name": "War Machine", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/war_machine.png"},
    {"id": 23, "name": "Falcon", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/falcon.png"},
    {"id": 24, "name": "Winter Soldier", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/winter_soldier.png"},
    {"id": 25, "name": "Ant-Man", "role": "Damage", "difficulty": 3, "image": "/assets/heroes/ant_man.png"},
    {"id": 26, "name": "Wasp", "role": "Support", "difficulty": 3, "image": "/assets/heroes/wasp.png"},
    {"id": 27, "name": "Deadpool", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/deadpool.png"},
    {"id": 28, "name": "Wolverine", "role": "Tank", "difficulty": 1, "image": "/assets/heroes/wolverine.png"},
    {"id": 29, "name": "Cyclops", "role": "Damage", "difficulty": 2, "image": "/assets/heroes/cyclops.png"}
]

# Game maps
maps = [
    {"id": 1, "name": "Asgard", "type": "Control", "image": "/assets/maps/asgard.png"},
    {"id": 2, "name": "Wakanda", "type": "Escort", "image": "/assets/maps/wakanda.png"},
    {"id": 3, "name": "New York", "type": "Hybrid", "image": "/assets/maps/new_york.png"},
    {"id": 4, "name": "Tokyo", "type": "Control", "image": "/assets/maps/tokyo.png"},
    {"id": 5, "name": "Latveria", "type": "Assault", "image": "/assets/maps/latveria.png"},
    {"id": 6, "name": "Sakaar", "type": "Escort", "image": "/assets/maps/sakaar.png"},
    {"id": 7, "name": "Knowhere", "type": "Control", "image": "/assets/maps/knowhere.png"},
    {"id": 8, "name": "Madripoor", "type": "Hybrid", "image": "/assets/maps/madripoor.png"},
    {"id": 9, "name": "Attilan", "type": "Assault", "image": "/assets/maps/attilan.png"},
    {"id": 10, "name": "Savage Land", "type": "Control", "image": "/assets/maps/savage_land.png"}
]

# Game modes
game_modes = [
    {"id": 1, "name": "Control", "description": "Capture and hold objectives to win"},
    {"id": 2, "name": "Escort", "description": "Escort the payload to the destination"},
    {"id": 3, "name": "Assault", "description": "Attack or defend objectives"},
    {"id": 4, "name": "Hybrid", "description": "Combination of Escort and Assault"}
]

# Sentinels players (IDs 183-188)
sentinels_players = {
    183: {
        "id": 183,
        "username": "SicK",
        "name": "Hunter Mims",
        "team_id": 87,
        "role": "Duelist",
        "main_hero": "Iron Man",
        "alt_heroes": ["Spider-Man", "Star-Lord"],
        "country": "USA",
        "avatar": "/assets/players/sick.png",
        "stats": {
            "matches_played": 50,
            "wins": 42,
            "losses": 8,
            "win_rate": 84.0,
            "avg_damage": 15000,
            "avg_healing": 0,
            "avg_score": 85,
            "avg_kills": 18,
            "avg_deaths": 5,
            "avg_assists": 10,
            "hero_usage": {
                "Iron Man": 35,
                "Spider-Man": 10,
                "Star-Lord": 5
            }
        }
    },
    184: {
        "id": 184,
        "username": "TenZ",
        "name": "Tyson Ngo",
        "team_id": 87,
        "role": "Duelist",
        "main_hero": "Black Panther",
        "alt_heroes": ["Deadpool", "Wolverine"],
        "country": "Canada",
        "avatar": "/assets/players/tenz.png",
        "stats": {
            "matches_played": 50,
            "wins": 42,
            "losses": 8,
            "win_rate": 84.0,
            "avg_damage": 16000,
            "avg_healing": 0,
            "avg_score": 90,
            "avg_kills": 20,
            "avg_deaths": 4,
            "avg_assists": 8,
            "hero_usage": {
                "Black Panther": 30,
                "Deadpool": 15,
                "Wolverine": 5
            }
        }
    },
    185: {
        "id": 185,
        "username": "dapr",
        "name": "Michael Gulino",
        "team_id": 87,
        "role": "Tank",
        "main_hero": "Thor",
        "alt_heroes": ["Hulk", "Captain America"],
        "country": "USA",
        "avatar": "/assets/players/dapr.png",
        "stats": {
            "matches_played": 50,
            "wins": 42,
            "losses": 8,
            "win_rate": 84.0,
            "avg_damage": 10000,
            "avg_healing": 0,
            "avg_score": 75,
            "avg_kills": 12,
            "avg_deaths": 6,
            "avg_assists": 15,
            "hero_usage": {
                "Thor": 35,
                "Hulk": 10,
                "Captain America": 5
            }
        }
    },
    186: {
        "id": 186,
        "username": "zombs",
        "name": "Jared Gitlin",
        "team_id": 87,
        "role": "Support",
        "main_hero": "Doctor Strange",
        "alt_heroes": ["Scarlet Witch", "Mantis"],
        "country": "USA",
        "avatar": "/assets/players/zombs.png",
        "stats": {
            "matches_played": 50,
            "wins": 42,
            "losses": 8,
            "win_rate": 84.0,
            "avg_damage": 5000,
            "avg_healing": 15000,
            "avg_score": 80,
            "avg_kills": 5,
            "avg_deaths": 4,
            "avg_assists": 25,
            "hero_usage": {
                "Doctor Strange": 40,
                "Scarlet Witch": 5,
                "Mantis": 5
            }
        }
    },
    187: {
        "id": 187,
        "username": "ShahZaM",
        "name": "Shahzeb Khan",
        "team_id": 87,
        "role": "Flex",
        "main_hero": "Luna Snow",
        "alt_heroes": ["Storm", "Black Widow"],
        "country": "USA",
        "avatar": "/assets/players/shahzam.png",
        "stats": {
            "matches_played": 50,
            "wins": 42,
            "losses": 8,
            "win_rate": 84.0,
            "avg_damage": 12000,
            "avg_healing": 5000,
            "avg_score": 85,
            "avg_kills": 15,
            "avg_deaths": 5,
            "avg_assists": 15,
            "hero_usage": {
                "Luna Snow": 30,
                "Storm": 15,
                "Black Widow": 5
            }
        }
    },
    188: {
        "id": 188,
        "username": "sinatraa",
        "name": "Jay Won",
        "team_id": 87,
        "role": "Sub",
        "main_hero": "Rocket Raccoon",
        "alt_heroes": ["Hawkeye", "Ant-Man"],
        "country": "USA",
        "avatar": "/assets/players/sinatraa.png",
        "stats": {
            "matches_played": 20,
            "wins": 16,
            "losses": 4,
            "win_rate": 80.0,
            "avg_damage": 14000,
            "avg_healing": 0,
            "avg_score": 82,
            "avg_kills": 16,
            "avg_deaths": 6,
            "avg_assists": 10,
            "hero_usage": {
                "Rocket Raccoon": 10,
                "Hawkeye": 5,
                "Ant-Man": 5
            }
        }
    }
}

# T1 players (IDs 189-194)
t1_players = {
    189: {
        "id": 189,
        "username": "Faker",
        "name": "Lee Sang-hyeok",
        "team_id": 86,
        "role": "Duelist",
        "main_hero": "Iron Man",
        "alt_heroes": ["Loki", "Vision"],
        "country": "South Korea",
        "avatar": "/assets/players/faker.png",
        "stats": {
            "matches_played": 50,
            "wins": 40,
            "losses": 10,
            "win_rate": 80.0,
            "avg_damage": 16500,
            "avg_healing": 0,
            "avg_score": 92,
            "avg_kills": 21,
            "avg_deaths": 3,
            "avg_assists": 9,
            "hero_usage": {
                "Iron Man": 30,
                "Loki": 15,
                "Vision": 5
            }
        }
    },
    190: {
        "id": 190,
        "username": "Keria",
        "name": "Ryu Min-seok",
        "team_id": 86,
        "role": "Support",
        "main_hero": "Doctor Strange",
        "alt_heroes": ["Mantis", "Wasp"],
        "country": "South Korea",
        "avatar": "/assets/players/keria.png",
        "stats": {
            "matches_played": 50,
            "wins": 40,
            "losses": 10,
            "win_rate": 80.0,
            "avg_damage": 4500,
            "avg_healing": 16000,
            "avg_score": 85,
            "avg_kills": 4,
            "avg_deaths": 3,
            "avg_assists": 28,
            "hero_usage": {
                "Doctor Strange": 35,
                "Mantis": 10,
                "Wasp": 5
            }
        }
    },
    191: {
        "id": 191,
        "username": "Zeus",
        "name": "Choi Woo-je",
        "team_id": 86,
        "role": "Tank",
        "main_hero": "Thor",
        "alt_heroes": ["Captain America", "Drax"],
        "country": "South Korea",
        "avatar": "/assets/players/zeus.png",
        "stats": {
            "matches_played": 50,
            "wins": 40,
            "losses": 10,
            "win_rate": 80.0,
            "avg_damage": 11000,
            "avg_healing": 0,
            "avg_score": 78,
            "avg_kills": 13,
            "avg_deaths": 5,
            "avg_assists": 16,
            "hero_usage": {
                "Thor": 30,
                "Captain America": 15,
                "Drax": 5
            }
        }
    },
    192: {
        "id": 192,
        "username": "Gumayusi",
        "name": "Lee Min-hyeong",
        "team_id": 86,
        "role": "Duelist",
        "main_hero": "Black Panther",
        "alt_heroes": ["Gamora", "Winter Soldier"],
        "country": "South Korea",
        "avatar": "/assets/players/gumayusi.png",
        "stats": {
            "matches_played": 50,
            "wins": 40,
            "losses": 10,
            "win_rate": 80.0,
            "avg_damage": 15500,
            "avg_healing": 0,
            "avg_score": 88,
            "avg_kills": 19,
            "avg_deaths": 4,
            "avg_assists": 10,
            "hero_usage": {
                "Black Panther": 35,
                "Gamora": 10,
                "Winter Soldier": 5
            }
        }
    },
    193: {
        "id": 193,
        "username": "Oner",
        "name": "Moon Hyeon-joon",
        "team_id": 86,
        "role": "Flex",
        "main_hero": "Luna Snow",
        "alt_heroes": ["Storm", "Cyclops"],
        "country": "South Korea",
        "avatar": "/assets/players/oner.png",
        "stats": {
            "matches_played": 50,
            "wins": 40,
            "losses": 10,
            "win_rate": 80.0,
            "avg_damage": 13000,
            "avg_healing": 4000,
            "avg_score": 82,
            "avg_kills": 16,
            "avg_deaths": 5,
            "avg_assists": 14,
            "hero_usage": {
                "Luna Snow": 25,
                "Storm": 15,
                "Cyclops": 10
            }
        }
    },
    194: {
        "id": 194,
        "username": "Canna",
        "name": "Kim Chang-dong",
        "team_id": 86,
        "role": "Sub",
        "main_hero": "Hulk",
        "alt_heroes": ["Groot", "Wolverine"],
        "country": "South Korea",
        "avatar": "/assets/players/canna.png",
        "stats": {
            "matches_played": 15,
            "wins": 12,
            "losses": 3,
            "win_rate": 80.0,
            "avg_damage": 9500,
            "avg_healing": 0,
            "avg_score": 75,
            "avg_kills": 10,
            "avg_deaths": 6,
            "avg_assists": 15,
            "hero_usage": {
                "Hulk": 8,
                "Groot": 4,
                "Wolverine": 3
            }
        }
    }
}

# Combine all players
players_db = {**sentinels_players, **t1_players}

# Match 99: Sentinels vs T1 (live match)
matches_db = {
    99: {
        "id": 99,
        "team1_id": 87,  # Sentinels
        "team2_id": 86,  # T1
        "event_id": None,
        "scheduled_at": datetime.now().isoformat(),
        "format": "BO5",
        "status": "live",
        "stream_url": "https://twitch.tv/marvel_rivals_official",
        "team1_score": 2,
        "team2_score": 1,
        "current_map": "Asgard",
        "current_mode": "Control",
        "maps_played": [
            {
                "map": "Wakanda",
                "mode": "Escort",
                "winner": "Sentinels",
                "team1_score": 3,
                "team2_score": 2
            },
            {
                "map": "New York",
                "mode": "Hybrid",
                "winner": "T1",
                "team1_score": 2,
                "team2_score": 3
            },
            {
                "map": "Asgard",
                "mode": "Control",
                "winner": "Sentinels",
                "team1_score": 2,
                "team2_score": 0,
                "in_progress": True
            }
        ],
        "scoreboard": {
            "team1": {  # Sentinels
                "players": [
                    {
                        "player_id": 183,
                        "username": "SicK",
                        "hero": "Iron Man",
                        "kills": 24,
                        "deaths": 6,
                        "assists": 12,
                        "damage": 18500,
                        "healing": 0,
                        "score": 95
                    },
                    {
                        "player_id": 184,
                        "username": "TenZ",
                        "hero": "Black Panther",
                        "kills": 28,
                        "deaths": 5,
                        "assists": 10,
                        "damage": 19200,
                        "healing": 0,
                        "score": 98
                    },
                    {
                        "player_id": 185,
                        "username": "dapr",
                        "hero": "Thor",
                        "kills": 15,
                        "deaths": 8,
                        "assists": 18,
                        "damage": 12500,
                        "healing": 0,
                        "score": 85
                    },
                    {
                        "player_id": 186,
                        "username": "zombs",
                        "hero": "Doctor Strange",
                        "kills": 6,
                        "deaths": 4,
                        "assists": 32,
                        "damage": 5800,
                        "healing": 18500,
                        "score": 90
                    },
                    {
                        "player_id": 187,
                        "username": "ShahZaM",
                        "hero": "Luna Snow",
                        "kills": 18,
                        "deaths": 7,
                        "assists": 16,
                        "damage": 14200,
                        "healing": 6500,
                        "score": 88
                    }
                ],
                "team_stats": {
                    "total_kills": 91,
                    "total_deaths": 30,
                    "total_assists": 88,
                    "total_damage": 70200,
                    "total_healing": 25000
                }
            },
            "team2": {  # T1
                "players": [
                    {
                        "player_id": 189,
                        "username": "Faker",
                        "hero": "Iron Man",
                        "kills": 25,
                        "deaths": 8,
                        "assists": 11,
                        "damage": 19800,
                        "healing": 0,
                        "score": 96
                    },
                    {
                        "player_id": 190,
                        "username": "Keria",
                        "hero": "Doctor Strange",
                        "kills": 5,
                        "deaths": 5,
                        "assists": 35,
                        "damage": 4900,
                        "healing": 19200,
                        "score": 92
                    },
                    {
                        "player_id": 191,
                        "username": "Zeus",
                        "hero": "Thor",
                        "kills": 14,
                        "deaths": 10,
                        "assists": 19,
                        "damage": 13200,
                        "healing": 0,
                        "score": 82
                    },
                    {
                        "player_id": 192,
                        "username": "Gumayusi",
                        "hero": "Black Panther",
                        "kills": 22,
                        "deaths": 12,
                        "assists": 12,
                        "damage": 17500,
                        "healing": 0,
                        "score": 90
                    },
                    {
                        "player_id": 193,
                        "username": "Oner",
                        "hero": "Luna Snow",
                        "kills": 19,
                        "deaths": 10,
                        "assists": 15,
                        "damage": 15600,
                        "healing": 5200,
                        "score": 85
                    }
                ],
                "team_stats": {
                    "total_kills": 85,
                    "total_deaths": 45,
                    "total_assists": 92,
                    "total_damage": 71000,
                    "total_healing": 24400
                }
            }
        }
    }
}

events_db = {}
forum_threads_db = {
    1: {
        "id": 1,
        "title": "Welcome to Marvel Rivals Forums",
        "content": "This is the first thread in our forums.",
        "category": "general",
        "views": 100,
        "replies": 5,
        "pinned": True,
        "locked": False,
        "created_at": datetime.now().isoformat(),
        "updated_at": datetime.now().isoformat(),
        "user_id": 1,
        "user_name": "Admin User",
        "user_avatar": None
    }
}

forum_categories = [
    {"id": "general", "name": "General Discussion", "description": "General Marvel Rivals discussion"},
    {"id": "strategies", "name": "Strategies & Tips", "description": "Team compositions and tactics"},
    {"id": "team-recruitment", "name": "Team Recruitment", "description": "Looking for team/players"},
    {"id": "announcements", "name": "Announcements", "description": "Official tournament news"},
    {"id": "bugs", "name": "Bug Reports", "description": "Game issues and feedback"},
    {"id": "feedback", "name": "Feedback", "description": "Community feedback"},
    {"id": "discussion", "name": "Discussion", "description": "General discussions"},
    {"id": "guides", "name": "Guides", "description": "How-to guides and tutorials"}
]

# Hero usage statistics
hero_usage_stats = {
    "Iron Man": {"pick_rate": 65.2, "win_rate": 52.8, "avg_damage": 15800, "avg_score": 88},
    "Doctor Strange": {"pick_rate": 58.7, "win_rate": 54.3, "avg_damage": 5200, "avg_healing": 16500, "avg_score": 85},
    "Thor": {"pick_rate": 52.1, "win_rate": 51.2, "avg_damage": 11500, "avg_score": 80},
    "Luna Snow": {"pick_rate": 48.6, "win_rate": 53.7, "avg_damage": 13800, "avg_healing": 5800, "avg_score": 84},
    "Black Panther": {"pick_rate": 45.3, "win_rate": 55.1, "avg_damage": 16200, "avg_score": 89},
    "Spider-Man": {"pick_rate": 42.8, "win_rate": 50.9, "avg_damage": 14500, "avg_score": 82},
    "Captain America": {"pick_rate": 38.5, "win_rate": 52.4, "avg_damage": 10800, "avg_score": 79},
    "Hulk": {"pick_rate": 35.2, "win_rate": 49.8, "avg_damage": 12200, "avg_score": 77},
    "Black Widow": {"pick_rate": 32.7, "win_rate": 51.5, "avg_damage": 14200, "avg_score": 81},
    "Scarlet Witch": {"pick_rate": 30.1, "win_rate": 53.2, "avg_damage": 8500, "avg_healing": 12800, "avg_score": 83}
}

# Models
class Token(BaseModel):
    access_token: str
    token_type: str

class TokenData(BaseModel):
    email: Optional[str] = None

class User(BaseModel):
    id: int
    email: str
    name: str
    roles: List[str]

class UserInDB(User):
    password: str

class LoginRequest(BaseModel):
    email: str
    password: str

class EventCreate(BaseModel):
    name: str
    type: str
    status: str
    start_date: str
    end_date: str
    prize_pool: Optional[str] = None
    location: Optional[str] = None
    organizer: Optional[str] = None
    format: Optional[str] = None
    team_count: Optional[int] = None
    registration_open: Optional[bool] = None

class MatchCreate(BaseModel):
    team1_id: int
    team2_id: int
    event_id: Optional[int] = None
    scheduled_at: str
    format: str
    status: str
    stream_url: Optional[str] = None

class PlayerCreate(BaseModel):
    username: str
    role: str
    name: str
    real_name: Optional[str] = None
    team_id: Optional[int] = None
    main_hero: Optional[str] = None
    alt_heroes: Optional[List[str]] = None
    region: Optional[str] = None
    country: Optional[str] = None
    rating: Optional[int] = None
    age: Optional[int] = None
    social_media: Optional[Dict[str, str]] = None
    biography: Optional[str] = None
    avatar: Optional[str] = None

# Helper functions
def create_access_token(data: dict, expires_delta: Optional[timedelta] = None):
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

def get_user(email: str):
    if email in users_db:
        user_dict = users_db[email]
        return UserInDB(**user_dict)
    return None

def authenticate_user(email: str, password: str):
    user = get_user(email)
    if not user:
        return False
    if password != user.password:  # In a real app, use password hashing
        return False
    return user

async def get_current_user(token: str = Depends(oauth2_scheme)):
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        email: str = payload.get("sub")
        if email is None:
            raise HTTPException(status_code=401, detail="Invalid authentication credentials")
        token_data = TokenData(email=email)
    except jwt.PyJWTError:
        raise HTTPException(status_code=401, detail="Invalid authentication credentials")
    user = get_user(email=token_data.email)
    if user is None:
        raise HTTPException(status_code=401, detail="User not found")
    return user

async def get_admin_user(current_user: User = Depends(get_current_user)):
    if "admin" not in current_user.roles:
        raise HTTPException(status_code=403, detail="Not authorized")
    return current_user

# Routes
@app.post("/api/auth/login", response_model=Dict[str, Any])
async def login_for_access_token(form_data: LoginRequest):
    user = authenticate_user(form_data.email, form_data.password)
    if not user:
        raise HTTPException(
            status_code=401,
            detail="Incorrect email or password",
            headers={"WWW-Authenticate": "Bearer"},
        )
    access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = create_access_token(
        data={"sub": user.email}, expires_delta=access_token_expires
    )
    return {
        "success": True,
        "token": access_token,
        "token_type": "bearer",
        "user": {
            "id": user.id,
            "name": user.name,
            "email": user.email,
            "roles": user.roles
        }
    }

@app.post("/api/admin/events", response_model=Dict[str, Any])
async def create_event(event: EventCreate, current_user: User = Depends(get_admin_user)):
    event_id = len(events_db) + 1
    event_dict = event.dict()
    event_dict["id"] = event_id
    events_db[event_id] = event_dict
    return {
        "success": True,
        "message": "Event created successfully",
        "data": event_dict
    }

@app.post("/api/admin/matches", response_model=Dict[str, Any])
async def create_match(match: MatchCreate, current_user: User = Depends(get_admin_user)):
    match_id = len(matches_db) + 1
    match_dict = match.dict()
    match_dict["id"] = match_id
    match_dict["team1_score"] = 0
    match_dict["team2_score"] = 0
    matches_db[match_id] = match_dict
    
    # Add team and event data for response
    response_data = match_dict.copy()
    response_data["team1"] = teams_db.get(match.team1_id)
    response_data["team2"] = teams_db.get(match.team2_id)
    response_data["event"] = events_db.get(match.event_id) if match.event_id else None
    
    return {
        "success": True,
        "message": "Match created successfully",
        "data": response_data
    }

@app.post("/api/admin/players", response_model=Dict[str, Any])
async def create_player(player: PlayerCreate, current_user: User = Depends(get_admin_user)):
    # Validate player role
    valid_roles = ["Duelist", "Tank", "Support", "Flex", "Sub"]
    if player.role not in valid_roles:
        raise HTTPException(status_code=422, detail=f"Invalid role. Must be one of: {', '.join(valid_roles)}")
    
    player_id = len(players_db) + 1
    player_dict = player.dict()
    player_dict["id"] = player_id
    players_db[player_id] = player_dict
    
    # Add team data for response
    response_data = player_dict.copy()
    response_data["team"] = teams_db.get(player.team_id) if player.team_id else None
    
    return {
        "success": True,
        "message": "Player created successfully",
        "data": response_data
    }

@app.post("/api/upload/team/{team_id}/flag", response_model=Dict[str, Any])
async def upload_team_flag(team_id: int, flag: UploadFile = File(...), current_user: User = Depends(get_admin_user)):
    if team_id not in teams_db:
        raise HTTPException(status_code=404, detail="Team not found")
    
    # In a real app, save the file to storage
    flag_url = f"/storage/teams/flags/{uuid4()}.{flag.filename.split('.')[-1]}"
    teams_db[team_id]["flag"] = flag_url
    
    return {
        "success": True,
        "message": "Team flag uploaded successfully",
        "data": {
            "flag_url": flag_url,
            "team": teams_db[team_id]
        }
    }

@app.get("/api/forums/threads/{thread_id}", response_model=Dict[str, Any])
async def get_forum_thread(thread_id: int):
    if thread_id not in forum_threads_db:
        raise HTTPException(status_code=404, detail="Thread not found")
    
    return {
        "success": True,
        "data": forum_threads_db[thread_id]
    }

@app.get("/api/forums/categories", response_model=Dict[str, Any])
async def get_forum_categories():
    return {
        "success": True,
        "data": forum_categories,
        "total": len(forum_categories)
    }

# Game Data Endpoints
@app.get("/api/game-data/heroes", response_model=Dict[str, Any])
async def get_basic_heroes():
    """Get basic 5 heroes"""
    return {
        "success": True,
        "data": basic_heroes,
        "total": len(basic_heroes)
    }

@app.get("/api/game-data/all-heroes", response_model=Dict[str, Any])
async def get_all_heroes():
    """Get complete 29 hero roster"""
    return {
        "success": True,
        "data": all_heroes,
        "total": len(all_heroes)
    }

@app.get("/api/game-data/maps", response_model=Dict[str, Any])
async def get_maps():
    """Get 10 official maps"""
    return {
        "success": True,
        "data": maps,
        "total": len(maps)
    }

@app.get("/api/game-data/modes", response_model=Dict[str, Any])
async def get_game_modes():
    """Get 4 game modes"""
    return {
        "success": True,
        "data": game_modes,
        "total": len(game_modes)
    }

# Live Scoring System
@app.get("/api/matches/{match_id}/scoreboard", response_model=Dict[str, Any])
async def get_match_scoreboard(match_id: int):
    """Get live scoreboard for a match"""
    if match_id not in matches_db:
        raise HTTPException(status_code=404, detail="Match not found")
    
    match = matches_db[match_id]
    
    # Add team data
    response_data = {
        "match_id": match_id,
        "team1": teams_db.get(match["team1_id"]),
        "team2": teams_db.get(match["team2_id"]),
        "team1_score": match["team1_score"],
        "team2_score": match["team2_score"],
        "format": match["format"],
        "status": match["status"],
        "current_map": match.get("current_map"),
        "current_mode": match.get("current_mode"),
        "maps_played": match.get("maps_played", []),
        "scoreboard": match.get("scoreboard", {})
    }
    
    return {
        "success": True,
        "data": response_data
    }

# Analytics Endpoints
@app.get("/api/analytics/players/{player_id}/stats", response_model=Dict[str, Any])
async def get_player_stats(player_id: int):
    """Get player performance analytics"""
    if player_id not in players_db:
        raise HTTPException(status_code=404, detail="Player not found")
    
    player = players_db[player_id]
    
    return {
        "success": True,
        "data": {
            "player": {
                "id": player["id"],
                "username": player["username"],
                "name": player["name"],
                "team_id": player["team_id"],
                "team": teams_db.get(player["team_id"]) if player.get("team_id") else None,
                "role": player["role"],
                "main_hero": player.get("main_hero"),
                "alt_heroes": player.get("alt_heroes", []),
                "country": player.get("country"),
                "avatar": player.get("avatar")
            },
            "stats": player.get("stats", {})
        }
    }

@app.get("/api/analytics/heroes/usage", response_model=Dict[str, Any])
async def get_hero_usage_stats():
    """Get hero usage statistics"""
    return {
        "success": True,
        "data": hero_usage_stats
    }

# Leaderboards
@app.get("/api/leaderboards/players", response_model=Dict[str, Any])
async def get_player_leaderboards(sort_by: str = Query("score", description="Sort by: score, damage, healing, kills, deaths, assists")):
    """Get player leaderboards"""
    valid_sort_fields = ["score", "damage", "healing", "kills", "deaths", "assists"]
    if sort_by not in valid_sort_fields:
        sort_by = "score"
    
    # Extract player stats
    leaderboard_data = []
    for player_id, player in players_db.items():
        if "stats" in player:
            stats = player["stats"]
            leaderboard_data.append({
                "id": player["id"],
                "username": player["username"],
                "name": player["name"],
                "team_id": player["team_id"],
                "team_name": teams_db.get(player["team_id"], {}).get("name") if player.get("team_id") else None,
                "role": player["role"],
                "main_hero": player.get("main_hero"),
                "country": player.get("country"),
                "avatar": player.get("avatar"),
                "matches_played": stats.get("matches_played", 0),
                "wins": stats.get("wins", 0),
                "losses": stats.get("losses", 0),
                "win_rate": stats.get("win_rate", 0),
                "avg_score": stats.get("avg_score", 0),
                "avg_damage": stats.get("avg_damage", 0),
                "avg_healing": stats.get("avg_healing", 0),
                "avg_kills": stats.get("avg_kills", 0),
                "avg_deaths": stats.get("avg_deaths", 0),
                "avg_assists": stats.get("avg_assists", 0)
            })
    
    # Sort by the requested field
    if sort_by == "score":
        leaderboard_data.sort(key=lambda x: x["avg_score"], reverse=True)
    elif sort_by == "damage":
        leaderboard_data.sort(key=lambda x: x["avg_damage"], reverse=True)
    elif sort_by == "healing":
        leaderboard_data.sort(key=lambda x: x["avg_healing"], reverse=True)
    elif sort_by == "kills":
        leaderboard_data.sort(key=lambda x: x["avg_kills"], reverse=True)
    elif sort_by == "deaths":
        leaderboard_data.sort(key=lambda x: x["avg_deaths"], reverse=True)
    elif sort_by == "assists":
        leaderboard_data.sort(key=lambda x: x["avg_assists"], reverse=True)
    
    return {
        "success": True,
        "data": leaderboard_data,
        "total": len(leaderboard_data),
        "sort_by": sort_by
    }

@app.get("/api/leaderboards/teams", response_model=Dict[str, Any])
async def get_team_leaderboards():
    """Get team leaderboards"""
    # Extract team stats
    leaderboard_data = []
    for team_id, team in teams_db.items():
        if "rank" in team:  # Only include teams with stats
            leaderboard_data.append({
                "id": team["id"],
                "name": team["name"],
                "short_name": team["short_name"],
                "region": team["region"],
                "country": team["country"],
                "logo": team["logo"],
                "flag": team["flag"],
                "rank": team.get("rank", 0),
                "wins": team.get("wins", 0),
                "losses": team.get("losses", 0),
                "total_score": team.get("total_score", 0),
                "total_damage": team.get("total_damage", 0),
                "total_healing": team.get("total_healing", 0)
            })
    
    # Sort by rank
    leaderboard_data.sort(key=lambda x: x["rank"])
    
    return {
        "success": True,
        "data": leaderboard_data,
        "total": len(leaderboard_data)
    }

# Root endpoint
@app.get("/")
async def root():
    return {"message": "Marvel Rivals Esports Platform API"}

# Health check endpoint
@app.get("/api/health")
async def health_check():
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "version": "1.0.0"
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)