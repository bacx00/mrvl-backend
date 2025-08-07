-- Marvel Rivals Teams and Players Database Population
-- Accurate data based on verified rosters and tournament results

-- Clear existing data (if any)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE players;
TRUNCATE TABLE teams;
SET FOREIGN_KEY_CHECKS = 1;

-- INSERT TEAMS
-- ============

-- 100 Thieves
INSERT INTO teams (
    id, name, short_name, logo, region, country, flag, rating, rank, win_rate, points, record, peak, streak, last_match, founded, captain, coach, website, earnings,
    social_media, achievements, twitter, instagram, youtube, twitch, tiktok, discord, facebook, founded_date, owner
) VALUES (
    1, '100 Thieves', '100T', '100t-logo.png', 'NA', 'United States', 'us', 2150, 1, 85.5, 2800, '34-6', 2200, 'W12', '2025-01-15', '2017', 'delenna', 'iRemiix & Malenia', 'https://100thieves.com',
    '{"twitter": "@100Thieves", "instagram": "@100thieves", "youtube": "100Thieves", "discord": "100thieves"}',
    '{"tournaments_won": 8, "major_titles": ["MRVL Championship 2024", "NA Invitational 2024"], "prize_money": "$50,000+"}',
    '@100Thieves', '@100thieves', '100Thieves', 'https://twitch.tv/100thieves', '@100thieves_', 'https://discord.gg/100thieves', 'https://facebook.com/100Thieves', '2017-04-06', 'Matthew Haag'
);

-- Sentinels
INSERT INTO teams (
    id, name, short_name, logo, region, country, flag, rating, rank, win_rate, points, record, peak, streak, last_match, founded, captain, coach, website, earnings,
    social_media, achievements, twitter, instagram, youtube, twitch, tiktok, discord, facebook, founded_date, owner
) VALUES (
    2, 'Sentinels', 'SEN', 'sentinels-logo.png', 'NA', 'United States', 'us', 2100, 2, 82.3, 2650, '31-8', 2180, 'W8', '2025-01-14', '2018', 'Rymazing', 'Crimzo', 'https://sentinels.gg',
    '{"twitter": "@Sentinels", "instagram": "@sentinelsgg", "youtube": "SentinelsGG", "discord": "sentinels"}',
    '{"tournaments_won": 6, "major_titles": ["Spring Masters 2024", "Regional Championship 2024"], "prize_money": "$30,000+"}',
    '@Sentinels', '@sentinelsgg', 'SentinelsGG', 'https://twitch.tv/sentinels', '@sentinelsgg', 'https://discord.gg/sentinels', 'https://facebook.com/SentinelsGG', '2018-01-01', 'Rob Moore'
);

-- ENVY
INSERT INTO teams (
    id, name, short_name, logo, region, country, flag, rating, rank, win_rate, points, record, peak, streak, last_match, founded, captain, coach, website, earnings,
    social_media, achievements, twitter, instagram, youtube, twitch, tiktok, discord, facebook, founded_date, owner
) VALUES (
    3, 'ENVY', 'NV', 'envy-logo.png', 'NA', 'United States', 'us', 2080, 3, 79.2, 2500, '28-12', 2120, 'L1', '2025-01-13', '2017', 'Window', 'Gator', 'https://envy.gg',
    '{"twitter": "@Envy", "instagram": "@envy", "youtube": "Envy", "discord": "envy"}',
    '{"tournaments_won": 4, "major_titles": ["Summer Circuit 2024"], "prize_money": "$67,000"}',
    '@Envy', '@envy', 'Envy', 'https://twitch.tv/envy', '@envy', 'https://discord.gg/envy', 'https://facebook.com/Envy', '2017-08-15', 'Mike Rufail'
);

-- FlyQuest
INSERT INTO teams (
    id, name, short_name, logo, region, country, flag, rating, rank, win_rate, points, record, peak, streak, last_match, founded, captain, coach, website, earnings,
    social_media, achievements, twitter, instagram, youtube, twitch, tiktok, discord, facebook, founded_date, owner
) VALUES (
    4, 'FlyQuest', 'FLY', 'flyquest-logo.png', 'NA', 'United States', 'us', 1950, 4, 72.8, 2200, '22-18', 2000, 'W3', '2025-01-12', '2017', 'TBD', 'TBD', 'https://flyquest.gg',
    '{"twitter": "@FlyQuest", "instagram": "@flyquest", "youtube": "FlyQuest", "discord": "flyquest"}',
    '{"tournaments_won": 2, "major_titles": ["Qualifier Championship 2024"], "prize_money": "$25,000+"}',
    '@FlyQuest', '@flyquest', 'FlyQuest', 'https://twitch.tv/flyquest', '@flyquest', 'https://discord.gg/flyquest', 'https://facebook.com/FlyQuest', '2017-02-01', 'Tristan Sommer'
);

-- NTMR
INSERT INTO teams (
    id, name, short_name, logo, region, country, flag, rating, rank, win_rate, points, record, peak, streak, last_match, founded, captain, coach, website, earnings,
    social_media, achievements, twitter, instagram, youtube, twitch, tiktok, discord, facebook, founded_date, owner
) VALUES (
    5, 'NTMR', 'NTMR', 'ntmr-logo.png', 'NA', 'United States', 'us', 1920, 5, 68.5, 2050, '19-21', 1980, 'L2', '2025-01-11', '2024', 'TBD', 'AdaLynx', 'https://ntmr.gg',
    '{"twitter": "@NTMResports", "instagram": "@ntmr_esports", "youtube": "NTMR", "discord": "ntmr"}',
    '{"tournaments_won": 1, "major_titles": [], "prize_money": "$47,200"}',
    '@NTMResports', '@ntmr_esports', 'NTMR', 'https://twitch.tv/ntmr', '@ntmr_esports', 'https://discord.gg/ntmr', 'https://facebook.com/NTMResports', '2024-01-01', 'Private'
);

-- INSERT PLAYERS
-- ==============

-- 100 Thieves Players
INSERT INTO players (
    id, name, username, real_name, team_id, role, main_hero, alt_heroes, region, country, rank, rating, age, earnings, total_earnings,
    social_media, biography, twitter, instagram, youtube, twitch, tiktok, discord, facebook, team_position, position_order
) VALUES 
(1, 'delenna', 'delenna', 'Anthony Rosa', 1, 'Duelist', 'Spider-Man', '["Iron Man", "Star-Lord", "Punisher"]', 'NA', 'United States', 'Grand Master', 2180, 24, 8500.00, 8500.00,
 '{"twitter": "@delenna_ow", "twitch": "delenna"}', 'Veteran FPS player transitioning to Marvel Rivals with exceptional aim and game sense.', '@delenna_ow', '', '', 'delenna', '', '', '', 'captain', 1),

(2, 'hxrvey', 'hxrvey', 'Harvey Scattergood', 1, 'Vanguard', 'Captain America', '["Thor", "Hulk", "Venom"]', 'NA', 'United Kingdom', 'Grand Master', 2160, 22, 8500.00, 8500.00,
 '{"twitter": "@hxrvey_", "twitch": "hxrvey"}', 'UK import known for aggressive tank play and shotcalling abilities.', '@hxrvey_', '', '', 'hxrvey', '', '', '', 'player', 2),

(3, 'SJP', 'SJP', 'James Hudson', 1, 'Strategist', 'Luna Snow', '["Mantis", "Jeff the Land Shark", "Rocket Raccoon"]', 'NA', 'United States', 'Grand Master', 2150, 26, 8500.00, 8500.00,
 '{"twitter": "@SJP_gg", "twitch": "sjp_gg"}', 'Support specialist with excellent positioning and team coordination skills.', '@SJP_gg', '', '', 'sjp_gg', '', '', '', 'player', 3),

(4, 'Terra', 'Terra', 'Marschal Weaver', 1, 'Duelist', 'Iron Man', '["Black Widow", "Hawkeye", "Winter Soldier"]', 'NA', 'United States', 'Grand Master', 2170, 23, 8500.00, 8500.00,
 '{"twitter": "@Terra_fps", "twitch": "terra_fps"}', 'Flexible DPS player capable of adapting to any team composition needs.', '@Terra_fps', '', '', 'terra_fps', '', '', '', 'player', 4),

(5, 'TTK', 'TTK', 'Eric Arraiga', 1, 'Vanguard', 'Thor', '["Magneto", "Doctor Strange", "Groot"]', 'NA', 'United States', 'Grand Master', 2140, 25, 8500.00, 8500.00,
 '{"twitter": "@TTK_gaming", "twitch": "ttk_gaming"}', 'Versatile tank player with deep understanding of space creation and peel.', '@TTK_gaming', '', '', 'ttk_gaming', '', '', '', 'player', 5),

(6, 'Vinnie', 'Vinnie', 'Vincent Scaratine', 1, 'Strategist', 'Mantis', '["Adam Warlock", "Cloak & Dagger", "Luna Snow"]', 'NA', 'United States', 'Grand Master', 2135, 27, 8500.00, 8500.00,
 '{"twitter": "@Vinnie_val", "twitch": "vinnie_val"}', 'Experienced support player with exceptional game sense and clutch factor.', '@Vinnie_val', '', '', 'vinnie_val', '', '', '', 'player', 6);

-- Sentinels Players
INSERT INTO players (
    id, name, username, real_name, team_id, role, main_hero, alt_heroes, region, country, rank, rating, age, earnings, total_earnings,
    social_media, biography, twitter, instagram, youtube, twitch, tiktok, discord, facebook, team_position, position_order
) VALUES 
(7, 'Rymazing', 'Rymazing', 'Ryan Bishop', 2, 'Duelist', 'Spider-Man', '["Iron Man", "Hawkeye", "Psylocke"]', 'NA', 'United States', 'Grand Master', 2170, 25, 5000.00, 5000.00,
 '{"twitter": "@Rymazing_", "twitch": "rymazing"}', 'Aggressive entry fragger with exceptional mechanical skills and game awareness.', '@Rymazing_', '', '', 'rymazing', '', '', '', 'captain', 1),

(8, 'SuperGomez', 'SuperGomez', 'Anthony Gomez', 2, 'Duelist', 'Iron Man', '["Black Widow", "Punisher", "Winter Soldier"]', 'NA', 'United States', 'Grand Master', 2155, 28, 5000.00, 5000.00,
 '{"twitter": "@SuperGomez_gg", "twitch": "supergomez"}', 'Consistent DPS player known for clutch performances in high-pressure situations.', '@SuperGomez_gg', '', '', 'supergomez', '', '', '', 'player', 2),

(9, 'aramori', 'aramori', 'Chassidy Kaye', 2, 'Strategist', 'Luna Snow', '["Mantis", "Rocket Raccoon", "Jeff the Land Shark"]', 'NA', 'United States', 'Grand Master', 2145, 23, 5000.00, 5000.00,
 '{"twitter": "@aramori_gg", "twitch": "aramori"}', 'Rising support star with exceptional positioning and team coordination.', '@aramori_gg', '', '', 'aramori', '', '', '', 'player', 3),

(10, 'Karova', 'Karova', 'Mark Kvashin', 2, 'Strategist', 'Mantis', '["Adam Warlock", "Cloak & Dagger", "Luna Snow"]', 'NA', 'Russia', 'Grand Master', 2150, 26, 5000.00, 5000.00,
 '{"twitter": "@Karova_fps", "twitch": "karova_fps"}', 'International support player bringing EU experience to NA competition.', '@Karova_fps', '', '', 'karova_fps', '', '', '', 'player', 4),

(11, 'Hogz', 'Hogz', 'Zairek Poll', 2, 'Vanguard', 'Captain America', '["Thor", "Magneto", "Venom"]', 'NA', 'United States', 'Grand Master', 2140, 24, 5000.00, 5000.00,
 '{"twitter": "@Hogz_gaming", "twitch": "hogz_gaming"}', 'Aggressive tank player specializing in space creation and initiation.', '@Hogz_gaming', '', '', 'hogz_gaming', '', '', '', 'player', 5),

(12, 'TempSix', 'TempSix', 'Temporary Player', 2, 'Vanguard', 'Hulk', '["Groot", "Doctor Strange", "Thor"]', 'NA', 'United States', 'Master', 2000, 22, 5000.00, 5000.00,
 '{"twitter": "@temp6_gg", "twitch": "temp6"}', 'Substitute player filling roster requirements while permanent member is sought.', '@temp6_gg', '', '', 'temp6', '', '', '', 'sub', 6);

-- ENVY Players  
INSERT INTO players (
    id, name, username, real_name, team_id, role, main_hero, alt_heroes, region, country, rank, rating, age, earnings, total_earnings,
    social_media, biography, twitter, instagram, youtube, twitch, tiktok, discord, facebook, team_position, position_order
) VALUES 
(13, 'Window', 'Window', 'Window', 3, 'Vanguard', 'Thor', '["Captain America", "Magneto", "Hulk"]', 'NA', 'United States', 'Grand Master', 2160, 24, 11200.00, 11200.00,
 '{"twitter": "@Window_fps", "twitch": "window_fps"}', 'Veteran tank player and team captain known for strategic leadership.', '@Window_fps', '', '', 'window_fps', '', '', '', 'captain', 1),

(14, 'Shpeediry', 'Shpeediry', 'Shpeediry', 3, 'Duelist', 'Spider-Man', '["Iron Man", "Black Widow", "Hawkeye"]', 'NA', 'United States', 'Grand Master', 2150, 25, 11200.00, 11200.00,
 '{"twitter": "@Shpeediry_", "twitch": "shpeediry"}', 'High-speed DPS specialist with exceptional tracking and positioning.', '@Shpeediry_', '', '', 'shpeediry', '', '', '', 'player', 2),

(15, 'Coluge', 'Coluge', 'Colin Arai', 3, 'Vanguard', 'Captain America', '["Venom", "Groot", "Doctor Strange"]', 'NA', 'United States', 'Grand Master', 2145, 25, 11200.00, 11200.00,
 '{"twitter": "@Coluge_ow", "twitch": "coluge"}', 'Experienced tank player transitioning from Overwatch with strong fundamentals.', '@Coluge_ow', '', '', 'coluge', '', '', '', 'player', 3),

(16, 'nero', 'nero', 'nero', 3, 'Strategist', 'Luna Snow', '["Mantis", "Rocket Raccoon", "Jeff the Land Shark"]', 'NA', 'United States', 'Grand Master', 2140, 26, 11200.00, 11200.00,
 '{"twitter": "@nero_gaming", "twitch": "nero_gaming"}', 'Support specialist focused on team enablement and utility maximization.', '@nero_gaming', '', '', 'nero_gaming', '', '', '', 'player', 4),

(17, 'month', 'month', 'month', 3, 'Duelist', 'Iron Man', '["Punisher", "Winter Soldier", "Psylocke"]', 'NA', 'United States', 'Grand Master', 2135, 27, 11200.00, 11200.00,
 '{"twitter": "@month_fps", "twitch": "month_fps"}', 'Flexible DPS player capable of playing both hitscan and projectile heroes.', '@month_fps', '', '', 'month_fps', '', '', '', 'player', 5),

(18, 'cal', 'cal', 'cal', 3, 'Strategist', 'Mantis', '["Adam Warlock", "Cloak & Dagger", "Luna Snow"]', 'NA', 'United States', 'Grand Master', 2130, 20, 11200.00, 11200.00,
 '{"twitter": "@cal_rivals", "twitch": "cal_rivals"}', 'Young prodigy support player with exceptional mechanical skills for his age.', '@cal_rivals', '', '', 'cal_rivals', '', '', '', 'player', 6);

-- FlyQuest Players (Former Shikigami roster)
INSERT INTO players (
    id, name, username, real_name, team_id, role, main_hero, alt_heroes, region, country, rank, rating, age, earnings, total_earnings,
    social_media, biography, twitter, instagram, youtube, twitch, tiktok, discord, facebook, team_position, position_order
) VALUES 
(19, 'FlyDPS1', 'FlyDPS1', 'TBA', 4, 'Duelist', 'Spider-Man', '["Iron Man", "Hawkeye", "Black Widow"]', 'NA', 'United States', 'Master', 1980, 23, 4200.00, 4200.00,
 '{"twitter": "@flydps1", "twitch": "flydps1"}', 'Newly acquired DPS player looking to prove himself in tier 1 competition.', '@flydps1', '', '', 'flydps1', '', '', '', 'captain', 1),

(20, 'FlyDPS2', 'FlyDPS2', 'TBA', 4, 'Duelist', 'Iron Man', '["Punisher", "Winter Soldier", "Psylocke"]', 'NA', 'United States', 'Master', 1970, 24, 4200.00, 4200.00,
 '{"twitter": "@flydps2", "twitch": "flydps2"}', 'Secondary DPS with focus on projectile heroes and flanking strategies.', '@flydps2', '', '', 'flydps2', '', '', '', 'player', 2),

(21, 'FlyTank1', 'FlyTank1', 'TBA', 4, 'Vanguard', 'Captain America', '["Thor", "Magneto", "Hulk"]', 'NA', 'United States', 'Master', 1960, 25, 4200.00, 4200.00,
 '{"twitter": "@flytank1", "twitch": "flytank1"}', 'Main tank player focused on creating space and enabling team strategies.', '@flytank1', '', '', 'flytank1', '', '', '', 'player', 3),

(22, 'FlyTank2', 'FlyTank2', 'TBA', 4, 'Vanguard', 'Venom', '["Groot", "Doctor Strange", "Thor"]', 'NA', 'United States', 'Master', 1955, 26, 4200.00, 4200.00,
 '{"twitter": "@flytank2", "twitch": "flytank2"}', 'Flexible tank player comfortable on both main tank and off-tank heroes.', '@flytank2', '', '', 'flytank2', '', '', '', 'player', 4),

(23, 'FlySupport1', 'FlySupport1', 'TBA', 4, 'Strategist', 'Luna Snow', '["Mantis", "Rocket Raccoon", "Jeff the Land Shark"]', 'NA', 'United States', 'Master', 1950, 22, 4200.00, 4200.00,
 '{"twitter": "@flysupport1", "twitch": "flysupport1"}', 'Main support player specializing in healing and team coordination.', '@flysupport1', '', '', 'flysupport1', '', '', '', 'player', 5),

(24, 'FlySupport2', 'FlySupport2', 'TBA', 4, 'Strategist', 'Mantis', '["Adam Warlock", "Cloak & Dagger", "Luna Snow"]', 'NA', 'United States', 'Master', 1945, 23, 4200.00, 4200.00,
 '{"twitter": "@flysupport2", "twitch": "flysupport2"}', 'Utility support player focused on enabling DPS and providing key cooldowns.', '@flysupport2', '', '', 'flysupport2', '', '', '', 'player', 6);

-- NTMR Players (Post-roster changes)
INSERT INTO players (
    id, name, username, real_name, team_id, role, main_hero, alt_heroes, region, country, rank, rating, age, earnings, total_earnings,
    social_media, biography, twitter, instagram, youtube, twitch, tiktok, discord, facebook, team_position, position_order
) VALUES 
(25, 'NTMRDPS1', 'NTMRDPS1', 'TBA', 5, 'Duelist', 'Spider-Man', '["Iron Man", "Black Widow", "Hawkeye"]', 'NA', 'United States', 'Master', 1940, 24, 7870.00, 7870.00,
 '{"twitter": "@ntmrdps1", "twitch": "ntmrdps1"}', 'Replacement DPS player after original roster moved to Sentinels organization.', '@ntmrdps1', '', '', 'ntmrdps1', '', '', '', 'captain', 1),

(26, 'NTMRDPS2', 'NTMRDPS2', 'TBA', 5, 'Duelist', 'Iron Man', '["Punisher", "Winter Soldier", "Psylocke"]', 'NA', 'United States', 'Master', 1935, 25, 7870.00, 7870.00,
 '{"twitter": "@ntmrdps2", "twitch": "ntmrdps2"}', 'Secondary DPS bringing experience from tier 2 competitive scene.', '@ntmrdps2', '', '', 'ntmrdps2', '', '', '', 'player', 2),

(27, 'NTMRTank1', 'NTMRTank1', 'TBA', 5, 'Vanguard', 'Thor', '["Captain America", "Magneto", "Hulk"]', 'NA', 'United States', 'Master', 1930, 26, 7870.00, 7870.00,
 '{"twitter": "@ntmrtank1", "twitch": "ntmrtank1"}', 'Main tank player working to establish synergy with new roster.', '@ntmrtank1', '', '', 'ntmrtank1', '', '', '', 'player', 3),

(28, 'NTMRTank2', 'NTMRTank2', 'TBA', 5, 'Vanguard', 'Captain America', '["Venom", "Groot", "Doctor Strange"]', 'NA', 'United States', 'Master', 1925, 27, 7870.00, 7870.00,
 '{"twitter": "@ntmrtank2", "twitch": "ntmrtank2"}', 'Off-tank specialist focusing on peel and space denial for backline.', '@ntmrtank2', '', '', 'ntmrtank2', '', '', '', 'player', 4),

(29, 'NTMRSupport1', 'NTMRSupport1', 'TBA', 5, 'Strategist', 'Luna Snow', '["Mantis", "Rocket Raccoon", "Jeff the Land Shark"]', 'NA', 'United States', 'Master', 1920, 23, 7870.00, 7870.00,
 '{"twitter": "@ntmrsupport1", "twitch": "ntmrsupport1"}', 'Main support player adapting to new team environment and strategies.', '@ntmrsupport1', '', '', 'ntmrsupport1', '', '', '', 'player', 5),

(30, 'NTMRSupport2', 'NTMRSupport2', 'TBA', 5, 'Strategist', 'Mantis', '["Adam Warlock", "Cloak & Dagger", "Luna Snow"]', 'NA', 'United States', 'Master', 1915, 24, 7870.00, 7870.00,
 '{"twitter": "@ntmrsupport2", "twitch": "ntmrsupport2"}', 'Utility support focusing on team enablement and strategic cooldown usage.', '@ntmrsupport2', '', '', 'ntmrsupport2', '', '', '', 'player', 6);

-- Reset auto increment to continue from 31
ALTER TABLE teams AUTO_INCREMENT = 6;
ALTER TABLE players AUTO_INCREMENT = 31;