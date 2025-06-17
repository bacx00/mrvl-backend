from fastapi import FastAPI, HTTPException, Depends, UploadFile, File, Form
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
from datetime import datetime, timedelta
import jwt
import os
import json
from uuid import uuid4

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
    }
}

players_db = {}
events_db = {}
matches_db = {}
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