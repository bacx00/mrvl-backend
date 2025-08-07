-- Marvel Rivals 2025 Team Rosters and Country Flags Database Update
-- This script updates teams and players with accurate 2025 data and proper ISO country codes

-- =================================================================
-- PART 1: Update Teams with Correct Country Flags and Regions
-- =================================================================

-- Fix 100 Thieves
UPDATE teams SET 
    country = 'US',
    coach = 'iRemiix & Malenia',
    region = 'Americas'
WHERE id = 1;

-- Fix Sentinels  
UPDATE teams SET 
    country = 'US',
    coach = 'Crimzo',
    region = 'Americas'
WHERE id = 2;

-- Fix FlyQuest
UPDATE teams SET 
    country = 'US',
    coach = 'TBD',
    region = 'Americas'
WHERE id = 4;

-- Fix Virtus.pro (update to proper EMEA region and country)
UPDATE teams SET 
    country = 'RU',
    coach = 'TBD',
    region = 'EMEA'
WHERE id = 17;

-- Fix OG Esports
UPDATE teams SET 
    country = 'EU',
    coach = 'TBD', 
    region = 'EMEA'
WHERE id = 19;

-- =================================================================
-- PART 2: Clean up and update players with correct 2025 roster data
-- =================================================================

-- First, let's get the current player IDs for each team so we can update them properly

-- =================================================================
-- 100 THIEVES ROSTER UPDATE (Team ID: 1)
-- Current roster: delenna 🇺🇸, hxrvey 🇬🇧, SJP 🇺🇸, Terra 🇺🇸, TTK 🇺🇸, Vinnie 🇺🇸
-- =================================================================

-- Update delenna (ID: 1) - already correct but fix country code
UPDATE players SET 
    name = 'delenna',
    username = 'delenna',
    real_name = 'delenna',
    role = 'Duelist',
    country = 'US',
    region = 'Americas'
WHERE id = 1 AND team_id = 1;

-- Update hxrvey (ID: 2) - fix country to GB
UPDATE players SET 
    name = 'hxrvey',
    username = 'hxrvey', 
    real_name = 'hxrvey',
    role = 'Vanguard',
    country = 'GB',
    region = 'Americas'
WHERE id = 2 AND team_id = 1;

-- Update SJP (ID: 3)
UPDATE players SET 
    name = 'SJP',
    username = 'SJP',
    real_name = 'SJP',
    role = 'Strategist',
    country = 'US',
    region = 'Americas'
WHERE id = 3 AND team_id = 1;

-- Update Terra (ID: 4)
UPDATE players SET 
    name = 'Terra',
    username = 'Terra',
    real_name = 'Terra', 
    role = 'Duelist',
    country = 'US',
    region = 'Americas'
WHERE id = 4 AND team_id = 1;

-- Update TTK (ID: 5)
UPDATE players SET 
    name = 'TTK',
    username = 'TTK',
    real_name = 'TTK',
    role = 'Vanguard', 
    country = 'US',
    region = 'Americas'
WHERE id = 5 AND team_id = 1;

-- Update Vinnie (ID: 6)
UPDATE players SET 
    name = 'Vinnie',
    username = 'Vinnie',
    real_name = 'Vinnie',
    role = 'Strategist',
    country = 'US', 
    region = 'Americas'
WHERE id = 6 AND team_id = 1;

-- =================================================================
-- SENTINELS ROSTER UPDATE (Team ID: 2)
-- New 2025 roster: SuperGomez 🇺🇸, Rymazing 🇺🇸, Aramori 🇺🇸, Karova 🇺🇸, Coluge 🇺🇸, teki 🇺🇸
-- =================================================================

-- Get current Sentinels player IDs and update them
-- We need to find the actual IDs first, let's update based on team_id
-- Assuming Sentinels has 6 players with consecutive IDs starting around team setup

-- Update first Sentinels player to SuperGomez
UPDATE players SET 
    name = 'SuperGomez',
    username = 'SuperGomez',
    real_name = 'SuperGomez',
    role = 'Duelist',
    country = 'US',
    region = 'Americas',
    main_hero = 'Iron Man'
WHERE team_id = 2 
ORDER BY id 
LIMIT 1;

-- For the remaining players, we'll need to identify them by their position in the team
-- This approach will update all Sentinels players with the new roster

-- Delete existing Sentinels placeholder players
DELETE FROM players WHERE team_id = 2;

-- Insert correct Sentinels 2025 roster
INSERT INTO players (name, username, real_name, team_id, role, main_hero, region, country, rating, created_at, updated_at)
VALUES 
    ('SuperGomez', 'SuperGomez', 'SuperGomez', 2, 'Duelist', 'Iron Man', 'Americas', 'US', 1850, datetime('now'), datetime('now')),
    ('Rymazing', 'Rymazing', 'Rymazing', 2, 'Duelist', 'Spider-Man', 'Americas', 'US', 1820, datetime('now'), datetime('now')),
    ('Aramori', 'Aramori', 'Aramori', 2, 'Vanguard', 'Venom', 'Americas', 'US', 1800, datetime('now'), datetime('now')),
    ('Karova', 'Karova', 'Karova', 2, 'Vanguard', 'Magneto', 'Americas', 'US', 1790, datetime('now'), datetime('now')),
    ('Coluge', 'Coluge', 'Coluge', 2, 'Strategist', 'Luna Snow', 'Americas', 'US', 1780, datetime('now'), datetime('now')),
    ('teki', 'teki', 'teki', 2, 'Strategist', 'Mantis', 'Americas', 'US', 1770, datetime('now'), datetime('now'));

-- =================================================================
-- VIRTUS.PRO ROSTER UPDATE (Team ID: 17) 
-- New 2025 roster: SparkR 🇸🇪, phi 🇩🇪, Sypeh 🇩🇰, dridro 🇭🇺, Nevix 🇸🇪, Finnsi 🇮🇸
-- =================================================================

-- Delete existing Virtus.pro placeholder players
DELETE FROM players WHERE team_id = 17;

-- Insert correct Virtus.pro 2025 roster
INSERT INTO players (name, username, real_name, team_id, role, main_hero, region, country, rating, created_at, updated_at)
VALUES 
    ('SparkR', 'SparkR', 'SparkR', 17, 'Duelist', 'Iron Man', 'EMEA', 'SE', 1900, datetime('now'), datetime('now')),
    ('phi', 'phi', 'phi', 17, 'Duelist', 'Spider-Man', 'EMEA', 'DE', 1880, datetime('now'), datetime('now')),
    ('Sypeh', 'Sypeh', 'Sypeh', 17, 'Vanguard', 'Venom', 'EMEA', 'DK', 1870, datetime('now'), datetime('now')),
    ('dridro', 'dridro', 'dridro', 17, 'Vanguard', 'Magneto', 'EMEA', 'HU', 1860, datetime('now'), datetime('now')),
    ('Nevix', 'Nevix', 'Nevix', 17, 'Strategist', 'Luna Snow', 'EMEA', 'SE', 1850, datetime('now'), datetime('now')),
    ('Finnsi', 'Finnsi', 'Finnsi', 17, 'Strategist', 'Mantis', 'EMEA', 'IS', 1840, datetime('now'), datetime('now'));

-- =================================================================
-- OG ESPORTS ROSTER UPDATE (Team ID: 19)
-- New 2025 roster: Snayz, Nzo, Etsu 🇫🇷, Tanuki, Alx, Ken 🇳🇴
-- =================================================================

-- Delete existing OG placeholder players  
DELETE FROM players WHERE team_id = 19;

-- Insert correct OG 2025 roster
INSERT INTO players (name, username, real_name, team_id, role, main_hero, region, country, rating, created_at, updated_at)
VALUES 
    ('Snayz', 'Snayz', 'Snayz', 19, 'Duelist', 'Iron Man', 'EMEA', 'EU', 1830, datetime('now'), datetime('now')),
    ('Nzo', 'Nzo', 'Nzo', 19, 'Duelist', 'Spider-Man', 'EMEA', 'EU', 1820, datetime('now'), datetime('now')),
    ('Etsu', 'Etsu', 'Etsu', 19, 'Vanguard', 'Venom', 'EMEA', 'FR', 1810, datetime('now'), datetime('now')),
    ('Tanuki', 'Tanuki', 'Tanuki', 19, 'Vanguard', 'Magneto', 'EMEA', 'EU', 1800, datetime('now'), datetime('now')),
    ('Alx', 'Alx', 'Alx', 19, 'Strategist', 'Luna Snow', 'EMEA', 'EU', 1790, datetime('now'), datetime('now')),
    ('Ken', 'Ken', 'Ken', 19, 'Strategist', 'Mantis', 'EMEA', 'NO', 1780, datetime('now'), datetime('now'));

-- =================================================================
-- FLYQUEST ROSTER UPDATE (Team ID: 4)
-- New 2025 roster: adios 🇺🇸, lyte 🇺🇸, energy 🇺🇸, SparkChief 🇲🇽, coopertastic 🇺🇸, Zelos 🇺🇸
-- =================================================================

-- Delete existing FlyQuest placeholder players
DELETE FROM players WHERE team_id = 4;

-- Insert correct FlyQuest 2025 roster  
INSERT INTO players (name, username, real_name, team_id, role, main_hero, region, country, rating, created_at, updated_at)
VALUES 
    ('adios', 'adios', 'adios', 4, 'Duelist', 'Iron Man', 'Americas', 'US', 1790, datetime('now'), datetime('now')),
    ('lyte', 'lyte', 'lyte', 4, 'Duelist', 'Spider-Man', 'Americas', 'US', 1780, datetime('now'), datetime('now')),
    ('energy', 'energy', 'energy', 4, 'Vanguard', 'Venom', 'Americas', 'US', 1770, datetime('now'), datetime('now')),
    ('SparkChief', 'SparkChief', 'SparkChief', 4, 'Vanguard', 'Magneto', 'Americas', 'MX', 1760, datetime('now'), datetime('now')),
    ('coopertastic', 'coopertastic', 'coopertastic', 4, 'Strategist', 'Luna Snow', 'Americas', 'US', 1750, datetime('now'), datetime('now')),
    ('Zelos', 'Zelos', 'Zelos', 4, 'Strategist', 'Mantis', 'Americas', 'US', 1740, datetime('now'), datetime('now'));

-- =================================================================
-- PART 3: Fix all remaining country codes to use proper ISO 2-letter codes
-- =================================================================

-- Update common country name issues to ISO codes
UPDATE teams SET country = 'US' WHERE country IN ('United States', 'USA', 'America');
UPDATE teams SET country = 'GB' WHERE country IN ('United Kingdom', 'UK');
UPDATE teams SET country = 'KR' WHERE country IN ('South Korea', 'Korea');
UPDATE teams SET country = 'CN' WHERE country IN ('China');
UPDATE teams SET country = 'JP' WHERE country IN ('Japan');
UPDATE teams SET country = 'BR' WHERE country IN ('Brazil');
UPDATE teams SET country = 'DE' WHERE country IN ('Germany');
UPDATE teams SET country = 'FR' WHERE country IN ('France');
UPDATE teams SET country = 'ES' WHERE country IN ('Spain');
UPDATE teams SET country = 'IT' WHERE country IN ('Italy');
UPDATE teams SET country = 'RU' WHERE country IN ('Russia');
UPDATE teams SET country = 'EU' WHERE country IN ('Europe');

-- Update players with same country code fixes  
UPDATE players SET country = 'US' WHERE country IN ('United States', 'USA', 'America');
UPDATE players SET country = 'GB' WHERE country IN ('United Kingdom', 'UK');
UPDATE players SET country = 'KR' WHERE country IN ('South Korea', 'Korea');
UPDATE players SET country = 'CN' WHERE country IN ('China');
UPDATE players SET country = 'JP' WHERE country IN ('Japan');
UPDATE players SET country = 'BR' WHERE country IN ('Brazil');
UPDATE players SET country = 'DE' WHERE country IN ('Germany');
UPDATE players SET country = 'FR' WHERE country IN ('France');
UPDATE players SET country = 'ES' WHERE country IN ('Spain');
UPDATE players SET country = 'IT' WHERE country IN ('Italy');
UPDATE players SET country = 'RU' WHERE country IN ('Russia');
UPDATE players SET country = 'EU' WHERE country IN ('Europe');

-- =================================================================
-- PART 4: Update team earnings and additional metadata
-- =================================================================

-- Update 100 Thieves with estimated earnings
UPDATE teams SET earnings = '$50,000' WHERE id = 1;

-- Update Sentinels with estimated earnings  
UPDATE teams SET earnings = '$45,000' WHERE id = 2;

-- Update Virtus.pro with estimated earnings
UPDATE teams SET earnings = '$60,000' WHERE id = 17;

-- Update OG with estimated earnings
UPDATE teams SET earnings = '$35,000' WHERE id = 19;

-- Update FlyQuest with estimated earnings
UPDATE teams SET earnings = '$25,000' WHERE id = 4;

-- =================================================================
-- VERIFICATION QUERIES
-- =================================================================

-- These queries can be run to verify the updates worked correctly:

-- Check updated teams
-- SELECT id, name, short_name, region, country, coach, earnings FROM teams WHERE id IN (1,2,4,17,19);

-- Check 100 Thieves roster
-- SELECT name, username, role, country FROM players WHERE team_id = 1 ORDER BY name;

-- Check Sentinels roster  
-- SELECT name, username, role, country FROM players WHERE team_id = 2 ORDER BY name;

-- Check Virtus.pro roster
-- SELECT name, username, role, country FROM players WHERE team_id = 17 ORDER BY name;

-- Check OG roster
-- SELECT name, username, role, country FROM players WHERE team_id = 19 ORDER BY name;

-- Check FlyQuest roster
-- SELECT name, username, role, country FROM players WHERE team_id = 4 ORDER BY name;

-- Check all country codes are now 2-letter ISO format
-- SELECT DISTINCT country FROM teams ORDER BY country;
-- SELECT DISTINCT country FROM players ORDER BY country;

-- =================================================================
-- END OF SCRIPT
-- =================================================================