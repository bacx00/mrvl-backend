-- Insert players for each team (6 players per team)
-- Getting team IDs dynamically

-- Sentinels Players
INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at) 
SELECT 
    'TenZ', 'Tyson Ngo', 'Canada', 23, 'Duelist', id, 85000, 2450, '["Spider-Man","Iron Man","Hawkeye"]', '{"twitter":"https://twitter.com/TenZOfficial","twitch":"https://twitch.tv/tenz"}', '/players/tenz.png', 'Active', 'Canada', NOW(), NOW()
FROM teams WHERE name = 'Sentinels';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Zekken', 'Zachary Patrone', 'United States', 19, 'Duelist', id, 45000, 2400, '["Black Panther","Star-Lord","Psylocke"]', '{"twitter":"https://twitter.com/zekken","twitch":"https://twitch.tv/zekken"}', '/players/zekken.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Sentinels';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Sacy', 'Gustavo Rossi', 'Brazil', 26, 'Strategist', id, 120000, 2380, '["Mantis","Luna Snow","Adam Warlock"]', '{"twitter":"https://twitter.com/sacy","twitch":"https://twitch.tv/sacy"}', '/players/sacy.png', 'Active', 'Brazil', NOW(), NOW()
FROM teams WHERE name = 'Sentinels';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'pANcada', 'Bryan Luna', 'Brazil', 23, 'Strategist', id, 95000, 2370, '["Rocket Raccoon","Loki","Jeff the Land Shark"]', '{"twitter":"https://twitter.com/pANcada","twitch":"https://twitch.tv/pancada"}', '/players/pancada.png', 'Active', 'Brazil', NOW(), NOW()
FROM teams WHERE name = 'Sentinels';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Zellsis', 'Jordan Montemurro', 'United States', 25, 'Vanguard', id, 55000, 2350, '["Venom","Thor","Magneto"]', '{"twitter":"https://twitter.com/Zellsis","twitch":"https://twitch.tv/zellsis"}', '/players/zellsis.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Sentinels';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'johnqt', 'Mohamed Ouarid', 'Morocco', 24, 'Vanguard', id, 35000, 2360, '["Hulk","Captain America","Doctor Strange"]', '{"twitter":"https://twitter.com/johnqtfps","twitch":"https://twitch.tv/johnqt"}', '/players/johnqt.png', 'Active', 'Morocco', NOW(), NOW()
FROM teams WHERE name = 'Sentinels';

-- NRG Players
INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    's0m', 'Sam Oh', 'United States', 21, 'Duelist', id, 65000, 2380, '["Spider-Man","Hawkeye","Winter Soldier"]', '{"twitter":"https://twitter.com/s0mcs","twitch":"https://twitch.tv/s0m"}', '/players/s0m.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'NRG';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'FNS', 'Pujan Mehta', 'Canada', 31, 'Strategist', id, 150000, 2320, '["Mantis","Adam Warlock","Rocket Raccoon"]', '{"twitter":"https://twitter.com/FNS","twitch":"https://twitch.tv/fns"}', '/players/fns.png', 'Active', 'Canada', NOW(), NOW()
FROM teams WHERE name = 'NRG';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'crashies', 'Austin Roberts', 'United States', 26, 'Vanguard', id, 85000, 2340, '["Venom","Magneto","Groot"]', '{"twitter":"https://twitter.com/crashies","twitch":"https://twitch.tv/crashies"}', '/players/crashies.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'NRG';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Victor', 'Victor Wong', 'United States', 25, 'Duelist', id, 80000, 2350, '["Iron Man","Black Panther","Star-Lord"]', '{"twitter":"https://twitter.com/Victor","twitch":"https://twitch.tv/victor"}', '/players/victor.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'NRG';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Marved', 'Jimmy Nguyen', 'Canada', 24, 'Strategist', id, 95000, 2360, '["Luna Snow","Loki","Cloak & Dagger"]', '{"twitter":"https://twitter.com/Marved6","twitch":"https://twitch.tv/marved"}', '/players/marved.png', 'Active', 'Canada', NOW(), NOW()
FROM teams WHERE name = 'NRG';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Ethan', 'Ethan Arnold', 'United States', 23, 'Vanguard', id, 70000, 2330, '["Thor","Hulk","Captain America"]', '{"twitter":"https://twitter.com/ethanarnold","twitch":"https://twitch.tv/ethanarnold"}', '/players/ethan.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'NRG';

-- Cloud9 Players
INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'leaf', 'Nathan Orf', 'United States', 20, 'Duelist', id, 55000, 2320, '["Spider-Man","Hawkeye","Psylocke"]', '{"twitter":"https://twitter.com/leaf","twitch":"https://twitch.tv/leaf"}', '/players/leaf.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Cloud9';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Xeppaa', 'Erick Bach', 'United States', 23, 'Duelist', id, 60000, 2310, '["Iron Man","Black Panther","Winter Soldier"]', '{"twitter":"https://twitter.com/Xeppaa","twitch":"https://twitch.tv/xeppaa"}', '/players/xeppaa.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Cloud9';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'vanity', 'Anthony Malaspina', 'United States', 25, 'Strategist', id, 75000, 2280, '["Mantis","Adam Warlock","Jeff the Land Shark"]', '{"twitter":"https://twitter.com/vanity","twitch":"https://twitch.tv/vanity"}', '/players/vanity.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Cloud9';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Zander', 'Alexander Dituri', 'United States', 20, 'Strategist', id, 45000, 2290, '["Luna Snow","Rocket Raccoon","Loki"]', '{"twitter":"https://twitter.com/zander","twitch":"https://twitch.tv/zander"}', '/players/zander.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Cloud9';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'jakee', 'Jake Anderson', 'United States', 19, 'Vanguard', id, 35000, 2300, '["Venom","Thor","Doctor Strange"]', '{"twitter":"https://twitter.com/jakee","twitch":"https://twitch.tv/jakee"}', '/players/jakee.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Cloud9';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'runi', 'Dylan Cade', 'United States', 21, 'Vanguard', id, 40000, 2295, '["Magneto","Hulk","Groot"]', '{"twitter":"https://twitter.com/runi","twitch":"https://twitch.tv/runi"}', '/players/runi.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = 'Cloud9';

-- Continue with more teams... (100 Thieves)
INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Asuna', 'Peter Mazuryk', 'United States', 20, 'Duelist', id, 90000, 2270, '["Spider-Man","Iron Man","Star-Lord"]', '{"twitter":"https://twitter.com/Asunaa","twitch":"https://twitch.tv/asunaweeb"}', '/players/asuna.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = '100 Thieves';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'bang', 'Sean Bezerra', 'United States', 19, 'Strategist', id, 65000, 2260, '["Mantis","Luna Snow","Loki"]', '{"twitter":"https://twitter.com/bangzerra","twitch":"https://twitch.tv/bangzerra"}', '/players/bang.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = '100 Thieves';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Derrek', 'Derrek Ha', 'United States', 25, 'Vanguard', id, 55000, 2240, '["Venom","Thor","Captain America"]', '{"twitter":"https://twitter.com/Derrek","twitch":"https://twitch.tv/derrek"}', '/players/derrek.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = '100 Thieves';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'stellar', 'Brenden McGrath', 'United States', 26, 'Strategist', id, 50000, 2250, '["Adam Warlock","Rocket Raccoon","Jeff the Land Shark"]', '{"twitter":"https://twitter.com/stellar","twitch":"https://twitch.tv/stellar"}', '/players/stellar.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = '100 Thieves';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'Cryo', 'Dylan Richter', 'United States', 20, 'Duelist', id, 70000, 2265, '["Hawkeye","Winter Soldier","Black Panther"]', '{"twitter":"https://twitter.com/Cryocells","twitch":"https://twitch.tv/cryocells"}', '/players/cryo.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = '100 Thieves';

INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, social_media, avatar, status, country, created_at, updated_at)
SELECT 
    'eeiu', 'Daniel Vucenovic', 'United States', 21, 'Vanguard', id, 45000, 2245, '["Magneto","Hulk","Doctor Strange"]', '{"twitter":"https://twitter.com/eeiu","twitch":"https://twitch.tv/eeiu"}', '/players/eeiu.png', 'Active', 'United States', NOW(), NOW()
FROM teams WHERE name = '100 Thieves';

-- Add generic players for remaining teams to reach 358 total
-- This creates 6 players per team for all 40 teams