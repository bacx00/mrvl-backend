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
11. [Real-Time Integration Examples](#real-time-integration-examples)
12. [Error Handling](#error-handling)
13. [Frontend Integration Guide](#frontend-integration-guide)

---

## 🎯 Overview

The Marvel Rivals Esports Platform provides a complete HLTV.org equivalent backend for Marvel Rivals competitive gaming. This API supports:

- **Live Match Scoreboards** with real-time player statistics
- **Tournament Management** with complete match lifecycle
- **Player Performance Analytics** with K/D ratios, damage tracking
- **Team Leaderboards** and rankings
- **Live Viewer Count Management** from streaming platforms
- **Professional Esports Broadcasting** capabilities

### Current Match Data
- **Primary Match**: ID `97` (test1 vs test2)
- **Teams**: test1 (ID: 83) vs test2 (ID: 84)
- **Players**: 10 total players (IDs: 169-173, 176-180)
- **Event**: Tournament ID `20`

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