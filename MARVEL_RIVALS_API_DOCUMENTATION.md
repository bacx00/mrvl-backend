# Marvel Rivals Esports Platform - Complete API Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base URLs & Headers](#base-urls--headers)
4. [Core Data Structure](#core-data-structure)
5. [Live Scoreboards API](#live-scoreboards-api)
6. [Player Statistics API](#player-statistics-api)
7. [Viewer Management API](#viewer-management-api)
8. [Analytics & Leaderboards API](#analytics--leaderboards-api)
9. [Game Data API](#game-data-api)
10. [Match Lifecycle Management](#match-lifecycle-management)
11. [Team Management API](#team-management-api)
12. [Tournament Brackets API](#tournament-brackets-api)
13. [Predictions & Betting API](#predictions--betting-api)
14. [Real-Time Integration Examples](#real-time-integration-examples)
15. [Error Handling](#error-handling)
16. [Frontend Integration Guide](#frontend-integration-guide)

---

## 🎯 Overview

The Marvel Rivals Esports Platform provides a complete HLTV.org equivalent backend for Marvel Rivals competitive gaming. This API supports:

- **6v6 Match Scoreboards** with real-time player statistics
- **Tournament Management** with complete BO1/BO3/BO5 bracket support
- **Team Roster Management** with player transfers and salary tracking
- **Player Performance Analytics** with K/D ratios, damage tracking
- **Team Leaderboards** and rankings
- **Live Viewer Count Management** from streaming platforms
- **Professional Esports Broadcasting** capabilities
- **Match Predictions** with community betting and odds

### Current Working Features (✅ DEPLOYED)
- **6v6 Team Format**: 12 players per match
- **Marvel Rivals Heroes**: 29 heroes with Vanguard/Duelist/Strategist roles
- **Tournament Brackets**: Single/double elimination with BO1/BO3/BO5 support
- **Team Management**: Roster changes, player transfers
- **Live Scoreboards**: Real-time player statistics
- **Match Predictions**: Community odds and betting
- **Player Analytics**: Performance tracking and leaderboards

### Current Match Data
- **Primary Match**: ID `97` (test1 vs test2) - **6v6 FORMAT**
- **Teams**: test1 (ID: 83) vs test2 (ID: 84) 
- **Players**: 12 total players (6v6) - p6 & p66 added for 6v6 support
- **Event**: Tournament ID `20`
- **Roles**: Vanguard, Duelist, Strategist (Marvel Rivals official)
- **Format**: BO5 series support with map rotation
- **Stats**: K/D, damage, healing, damage_blocked tracking

---

## 🔐 Authentication

### Bearer Token Authentication
Protected endpoints require Bearer token authentication.

**Working Token**: `415|ySK4yrjyULCTlprffD0KeT5zxd6J2mMMHOHkX6pv1d5fc012`

```javascript
// Example Authorization Header
headers: {
  'Authorization': 'Bearer 415|ySK4yrjyULCTlprffD0KeT5zxd6J2mMMHOHkX6pv1d5fc012',
  'Content-Type': 'application/json'
}
```

### Endpoint Access Levels
- 🔓 **Public**: Scoreboards, analytics, game data
- 🔒 **Admin/Moderator**: Live scoring, viewer updates, match completion

---

## 🌐 Base URLs & Headers

### Base URL
```
https://staging.mrvl.net/api
```

### Standard Headers
```javascript
{
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'Authorization': 'Bearer TOKEN' // For protected endpoints
}
```

---

## 📊 Core Data Structure

### Match Entity Structure
```javascript
{
  "match_id": 97,
  "status": "upcoming|live|paused|completed",
  "teams": {
    "team1": {
      "id": 83,
      "name": "test1",
      "region": "EU",
      "players": [...]
    },
    "team2": {
      "id": 84, 
      "name": "test2",
      "region": "APAC",
      "players": [...]
    }
  },
  "viewers": 75000,
  "format": "BO1|BO3|BO5"
}
```

### Player Statistics Structure - MARVEL RIVALS ENHANCED
```javascript
{
  "player_id": 169,
  "match_id": 97,
  "kills": 15,                 // E column (Eliminations)
  "deaths": 3,                 // D column  
  "assists": 8,                // A column
  "damage": 12500,             // DMG column
  "healing": 2300,             // HEAL column (nullable for non-Strategists)
  "damage_blocked": 8900,      // BLK column (critical for Vanguards)
  "ultimate_usage": 3,         // Ultimate abilities used
  "objective_time": 120,       // Time on objective (seconds)
  "hero_played": "Luna Snow",
  "kd_ratio": 5.0              // Calculated: kills/deaths
}
```