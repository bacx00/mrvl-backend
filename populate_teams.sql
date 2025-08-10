-- Clear existing data
DELETE FROM players;
DELETE FROM teams;

-- Insert top Marvel Rivals teams
INSERT INTO teams (name, short_name, logo, region, country, founded, earnings, rating, platform, game, division, status, created_at, updated_at) VALUES
-- North America
('Sentinels', 'SEN', '/teams/sentinels-logo.png', 'NA', 'United States', '2024-01-01', 125000, 2400, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('NRG', 'NRG', '/teams/nrg-logo.png', 'NA', 'United States', '2024-01-01', 95000, 2350, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Cloud9', 'C9', '/teams/cloud9-logo.png', 'NA', 'United States', '2024-01-01', 85000, 2300, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('100 Thieves', '100T', '/teams/100thieves-logo.png', 'NA', 'United States', '2024-01-01', 75000, 2250, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('TSM', 'TSM', '/teams/tsm-logo.png', 'NA', 'United States', '2024-01-01', 65000, 2200, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Evil Geniuses', 'EG', '/teams/eg-logo.png', 'NA', 'United States', '2024-01-01', 55000, 2150, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('OpTic Gaming', 'OG', '/teams/optic-logo.png', 'NA', 'United States', '2024-01-01', 50000, 2100, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('FaZe Clan', 'FaZe', '/teams/faze-logo.png', 'NA', 'United States', '2024-01-01', 45000, 2050, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Luminosity', 'LG', '/teams/luminosity-logo.png', 'NA', 'United States', '2024-01-01', 40000, 2000, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('DarkZero', 'DZ', '/teams/darkzero-logo.png', 'NA', 'United States', '2024-01-01', 35000, 1950, 'PC', 'Marvel Rivals', 'Diamond', 'Active', NOW(), NOW()),

-- Europe
('G2 Esports', 'G2', '/teams/g2-logo.png', 'EU', 'Germany', '2024-01-01', 150000, 2450, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Team Liquid', 'TL', '/teams/liquid-logo.png', 'EU', 'Netherlands', '2024-01-01', 120000, 2400, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Fnatic', 'FNC', '/teams/fnatic-logo.png', 'EU', 'United Kingdom', '2024-01-01', 100000, 2350, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Team Vitality', 'VIT', '/teams/vitality-logo.png', 'EU', 'France', '2024-01-01', 90000, 2300, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('NAVI', 'NAVI', '/teams/navi-logo.png', 'EU', 'Ukraine', '2024-01-01', 80000, 2250, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),

-- Asia Pacific
('Paper Rex', 'PRX', '/teams/paperrex-logo.png', 'APAC', 'Singapore', '2024-01-01', 180000, 2500, 'PC', 'Marvel Rivals', 'Grandmaster', 'Active', NOW(), NOW()),
('DRX', 'DRX', '/teams/drx-logo.png', 'APAC', 'South Korea', '2024-01-01', 160000, 2450, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('T1', 'T1', '/teams/t1-logo.png', 'APAC', 'South Korea', '2024-01-01', 140000, 2400, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Gen.G', 'GEN', '/teams/geng-logo.png', 'APAC', 'South Korea', '2024-01-01', 120000, 2350, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('ZETA DIVISION', 'ZETA', '/teams/zeta-logo.png', 'APAC', 'Japan', '2024-01-01', 100000, 2300, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),

-- China
('Edward Gaming', 'EDG', '/teams/edg-logo.png', 'CN', 'China', '2024-01-01', 130000, 2400, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('FunPlus Phoenix', 'FPX', '/teams/fpx-logo.png', 'CN', 'China', '2024-01-01', 110000, 2350, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Bilibili Gaming', 'BLG', '/teams/blg-logo.png', 'CN', 'China', '2024-01-01', 95000, 2300, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('JD Gaming', 'JDG', '/teams/jdg-logo.png', 'CN', 'China', '2024-01-01', 85000, 2250, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Wolves Esports', 'WOL', '/teams/wolves-logo.png', 'CN', 'China', '2024-01-01', 75000, 2200, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),

-- Latin America
('LOUD', 'LOUD', '/teams/loud-logo.png', 'SA', 'Brazil', '2024-01-01', 105000, 2350, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('FURIA', 'FUR', '/teams/furia-logo.png', 'SA', 'Brazil', '2024-01-01', 90000, 2300, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('MIBR', 'MIBR', '/teams/mibr-logo.png', 'SA', 'Brazil', '2024-01-01', 80000, 2250, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('paiN Gaming', 'PNG', '/teams/pain-logo.png', 'SA', 'Brazil', '2024-01-01', 70000, 2200, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('KRU Esports', 'KRU', '/teams/kru-logo.png', 'SA', 'Chile', '2024-01-01', 60000, 2150, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),

-- Additional teams
('Version1', 'V1', '/teams/version1-logo.png', 'NA', 'United States', '2024-01-01', 30000, 1900, 'PC', 'Marvel Rivals', 'Diamond', 'Active', NOW(), NOW()),
('XSET', 'XSET', '/teams/xset-logo.png', 'NA', 'United States', '2024-01-01', 25000, 1850, 'PC', 'Marvel Rivals', 'Diamond', 'Active', NOW(), NOW()),
('The Guard', 'GRD', '/teams/guard-logo.png', 'NA', 'United States', '2024-01-01', 20000, 1800, 'PC', 'Marvel Rivals', 'Diamond', 'Active', NOW(), NOW()),
('Team Heretics', 'TH', '/teams/heretics-logo.png', 'EU', 'Spain', '2024-01-01', 70000, 2200, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('KOI', 'KOI', '/teams/koi-logo.png', 'EU', 'Spain', '2024-01-01', 60000, 2150, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Karmine Corp', 'KC', '/teams/karmine-logo.png', 'EU', 'France', '2024-01-01', 55000, 2100, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Team Secret', 'TS', '/teams/secret-logo.png', 'APAC', 'Philippines', '2024-01-01', 75000, 2200, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('BOOM Esports', 'BOOM', '/teams/boom-logo.png', 'APAC', 'Indonesia', '2024-01-01', 65000, 2150, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Leviatan', 'LEV', '/teams/leviatan-logo.png', 'SA', 'Argentina', '2024-01-01', 55000, 2100, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW()),
('Team Falcons', 'FAL', '/teams/falcons-logo.png', 'MENA', 'Saudi Arabia', '2024-01-01', 70000, 2200, 'PC', 'Marvel Rivals', 'Master', 'Active', NOW(), NOW());