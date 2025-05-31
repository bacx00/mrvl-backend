# 🏆 Complete Admin Dashboard CRUD API

## 🎯 **OVERVIEW: Full CRUD Operations Available**

Your admin dashboard now has **complete Create, Read, Update, Delete** functionality for:
- ✅ **Teams Management**
- ✅ **Players Management** 
- ✅ **Users Management**

---

## 👑 **1. TEAM MANAGEMENT (Full CRUD)**

### **CREATE Team**
```bash
POST /api/admin/teams
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**Request Body:**
```json
{
  "name": "Team Name",
  "region": "NA|EU|APAC|Global",
  "description": "Team description",
  "logo": "logo_url.png"
}
```
**Response:**
```json
{
  "data": {
    "id": 25,
    "name": "Team Name",
    "region": "NA",
    "description": "Team description",
    "logo": "logo_url.png",
    "created_at": "2025-05-31T07:30:00.000000Z"
  },
  "success": true,
  "message": "Team created successfully"
}
```

### **READ Team** (Already working)
```bash
GET /api/teams/{id}
```

### **UPDATE Team**
```bash
PUT /api/admin/teams/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**Request Body:**
```json
{
  "name": "Updated Team Name",
  "region": "EU",
  "description": "Updated description",
  "logo": "new_logo_url.png"
}
```

### **DELETE Team**
```bash
DELETE /api/admin/teams/{id}
Authorization: Bearer {admin_token}
```
**Response:**
```json
{
  "success": true,
  "message": "Team 'Team Name' deleted successfully"
}
```

---

## 👤 **2. PLAYER MANAGEMENT (Full CRUD)**

### **CREATE Player**
```bash
POST /api/admin/players
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**Request Body:**
```json
{
  "name": "Player Name",
  "username": "playertag",
  "role": "Tank|DPS|Support",
  "team_id": 10
}
```
**Response:**
```json
{
  "data": {
    "id": 20,
    "name": "Player Name",
    "username": "playertag",
    "role": "Tank",
    "team_id": 10,
    "team": {
      "id": 10,
      "name": "Team Name"
    }
  },
  "success": true,
  "message": "Player created successfully"
}
```

### **READ Player** (Already working)
```bash
GET /api/players/{id}
```

### **UPDATE Player**
```bash
PUT /api/admin/players/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**Request Body:**
```json
{
  "name": "Updated Player Name",
  "username": "newusername",
  "role": "DPS",
  "team_id": 15
}
```
**Features:**
- Validates unique username (excluding current player)
- Updates team relationship
- Returns updated player with team data

### **DELETE Player**
```bash
DELETE /api/admin/players/{id}
Authorization: Bearer {admin_token}
```
**Response:**
```json
{
  "success": true,
  "message": "Player 'Player Name' deleted successfully"
}
```

---

## 👥 **3. USER MANAGEMENT (Full CRUD)**

### **LIST Users** 
```bash
GET /api/admin/users
Authorization: Bearer {admin_token}
```
**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2025-05-31T07:00:00.000000Z",
      "roles": [
        {
          "name": "admin",
          "guard_name": "sanctum"
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 2
  },
  "success": true
}
```

### **CREATE User**
```bash
POST /api/admin/users
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**Request Body:**
```json
{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "securepassword",
  "role": "admin|user"
}
```
**Response:**
```json
{
  "data": {
    "id": 3,
    "name": "New User",
    "email": "newuser@example.com",
    "roles": [
      {
        "name": "admin",
        "guard_name": "sanctum"
      }
    ]
  },
  "success": true,
  "message": "User created successfully"
}
```

### **UPDATE User**
```bash
PUT /api/admin/users/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**Request Body:**
```json
{
  "name": "Updated Name",
  "email": "updated@example.com",
  "password": "newpassword",  // Optional
  "role": "user"
}
```
**Features:**
- Password is optional (only updates if provided)
- Validates unique email (excluding current user)
- Updates role assignment
- Returns updated user with roles

### **DELETE User**
```bash
DELETE /api/admin/users/{id}
Authorization: Bearer {admin_token}
```
**Safety Features:**
- Prevents admin from deleting their own account
- Confirms deletion with user name

**Response:**
```json
{
  "success": true,
  "message": "User 'User Name' deleted successfully"
}
```
**Safety Response (if trying to delete self):**
```json
{
  "success": false,
  "message": "You cannot delete your own account"
}
```

---

## 🔒 **4. AUTHENTICATION & SECURITY**

### **Admin Role Required**
All CRUD operations require:
- Valid Sanctum authentication token
- Admin role assignment
- Proper request headers

### **Example Admin Authentication Flow:**
```bash
# 1. Login to get admin token
POST /api/auth/login
{
  "email": "admin@example.com",
  "password": "password"
}

# 2. Use token for all admin operations
Authorization: Bearer {received_token}
```

---

## 📋 **5. VALIDATION RULES**

### **Team Validation:**
- `name`: Required, string, max 255 characters
- `region`: Required string
- `description`: Optional string
- `logo`: Optional string (URL/path)

### **Player Validation:**
- `name`: Required, string, max 255 characters
- `username`: Required, string, unique across all players
- `role`: Required string (Tank/DPS/Support)
- `team_id`: Optional, must exist in teams table

### **User Validation:**
- `name`: Required, string, max 255 characters
- `email`: Required, valid email, unique across all users
- `password`: Required on create, min 8 characters (optional on update)
- `role`: Required, must be 'admin' or 'user'

---

## 🎯 **6. FRONTEND IMPLEMENTATION EXAMPLES**

### **React Admin Dashboard Usage:**

#### **Team Management Component:**
```javascript
// Create Team
const createTeam = async (teamData) => {
  const response = await api.post('/admin/teams', teamData, {
    headers: { Authorization: `Bearer ${adminToken}` }
  });
  return response.data;
};

// Update Team
const updateTeam = async (teamId, teamData) => {
  const response = await api.put(`/admin/teams/${teamId}`, teamData, {
    headers: { Authorization: `Bearer ${adminToken}` }
  });
  return response.data;
};

// Delete Team
const deleteTeam = async (teamId) => {
  const response = await api.delete(`/admin/teams/${teamId}`, {
    headers: { Authorization: `Bearer ${adminToken}` }
  });
  return response.data;
};
```

#### **Player Management Component:**
```javascript
// Create Player
const createPlayer = async (playerData) => {
  const response = await api.post('/admin/players', playerData, {
    headers: { Authorization: `Bearer ${adminToken}` }
  });
  return response.data;
};

// Update Player
const updatePlayer = async (playerId, playerData) => {
  const response = await api.put(`/admin/players/${playerId}`, playerData, {
    headers: { Authorization: `Bearer ${adminToken}` }
  });
  return response.data;
};
```

#### **User Management Component:**
```javascript
// List Users
const getUsers = async () => {
  const response = await api.get('/admin/users', {
    headers: { Authorization: `Bearer ${adminToken}` }
  });
  return response.data;
};

// Create User
const createUser = async (userData) => {
  const response = await api.post('/admin/users', userData, {
    headers: { Authorization: `Bearer ${adminToken}` }
  });
  return response.data;
};
```

---

## 🚀 **7. ADMIN DASHBOARD CAPABILITIES**

With these APIs, your admin dashboard can now:

### **Team Management:**
- ✅ View all teams in a data table
- ✅ Add new teams with form validation
- ✅ Edit existing team details
- ✅ Delete teams with confirmation
- ✅ Assign/reassign players to teams

### **Player Management:**
- ✅ View all players with team affiliations
- ✅ Create new player profiles
- ✅ Edit player information and roles
- ✅ Transfer players between teams
- ✅ Remove players from the system

### **User Management:**
- ✅ View all registered users
- ✅ Create admin and regular user accounts
- ✅ Edit user details and permissions
- ✅ Manage role assignments
- ✅ Delete user accounts (with safety checks)

### **Advanced Features:**
- ✅ Real-time validation feedback
- ✅ Role-based access control
- ✅ Pagination for large datasets
- ✅ Error handling and success messages
- ✅ Data relationships (players ↔ teams)

---

## 🏆 **SUMMARY: COMPLETE ADMIN FUNCTIONALITY**

**Your admin dashboard now has 100% CRUD capability for:**
- **Teams**: Create ✅ Read ✅ Update ✅ Delete ✅
- **Players**: Create ✅ Read ✅ Update ✅ Delete ✅  
- **Users**: Create ✅ Read ✅ Update ✅ Delete ✅

**All endpoints are:**
- ✅ Properly authenticated with admin role checks
- ✅ Fully validated with comprehensive error handling
- ✅ Returning consistent JSON responses
- ✅ Ready for immediate frontend integration

**🚀 Your admin can now fully manage the entire MRVL platform!**
