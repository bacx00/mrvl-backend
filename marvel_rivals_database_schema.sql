-- MARVEL RIVALS DATABASE SCHEMA
-- Complete database structure for the Marvel Rivals platform

-- ==========================================
-- USERS & AUTHENTICATION
-- ==========================================

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'moderator', 'user') DEFAULT 'user',
    avatar VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- TEAMS & PLAYERS
-- ==========================================

CREATE TABLE IF NOT EXISTS teams (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    country VARCHAR(5) DEFAULT 'US',
    region VARCHAR(50) DEFAULT 'NA',
    logo VARCHAR(500) NULL,
    description TEXT NULL,
    website VARCHAR(255) NULL,
    twitter VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    youtube VARCHAR(255) NULL,
    founded_at DATE NULL,
    status ENUM('active', 'inactive', 'disbanded') DEFAULT 'active',
    total_winnings DECIMAL(12,2) DEFAULT 0.00,
    total_matches INT DEFAULT 0,
    total_wins INT DEFAULT 0,
    total_losses INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_country (country),
    INDEX idx_region (region),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS players (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    real_name VARCHAR(255) NULL,
    team_id BIGINT UNSIGNED NULL,
    role ENUM('Vanguard', 'Duelist', 'Strategist', 'Flex', 'Sub') NOT NULL,
    country VARCHAR(5) DEFAULT 'US',
    nationality VARCHAR(5) DEFAULT 'US',
    team_country VARCHAR(5) DEFAULT 'US',
    age INT NULL,
    avatar VARCHAR(500) NULL,
    bio TEXT NULL,
    twitter VARCHAR(255) NULL,
    twitch VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'retired', 'free_agent') DEFAULT 'active',
    joined_team_at DATE NULL,
    total_winnings DECIMAL(10,2) DEFAULT 0.00,
    total_matches INT DEFAULT 0,
    total_wins INT DEFAULT 0,
    total_losses INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    INDEX idx_team (team_id),
    INDEX idx_role (role),
    INDEX idx_country (country),
    INDEX idx_status (status)
);

-- ==========================================
-- EVENTS & TOURNAMENTS
-- ==========================================

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    type ENUM('tournament', 'championship', 'qualifier', 'showmatch', 'league') DEFAULT 'tournament',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    location VARCHAR(255) NULL,
    venue VARCHAR(255) NULL,
    prize_pool DECIMAL(12,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    description TEXT NULL,
    logo VARCHAR(500) NULL,
    banner VARCHAR(500) NULL,
    website VARCHAR(255) NULL,
    twitch_channel VARCHAR(255) NULL,
    youtube_channel VARCHAR(255) NULL,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    tier ENUM('S', 'A', 'B', 'C') DEFAULT 'B',
    format VARCHAR(100) NULL,
    team_count INT DEFAULT 0,
    max_teams INT NULL,
    organizer VARCHAR(255) NULL,
    region VARCHAR(50) DEFAULT 'Global',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_tier (tier)
);

-- ==========================================
-- MATCHES
-- ==========================================

CREATE TABLE IF NOT EXISTS matches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    team1_id BIGINT UNSIGNED NOT NULL,
    team2_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NULL,
    scheduled_at TIMESTAMP NOT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status ENUM('scheduled', 'live', 'paused', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    winner_id BIGINT UNSIGNED NULL,
    format VARCHAR(20) DEFAULT 'BO3',
    current_map INT DEFAULT 1,
    viewers INT DEFAULT 0,
    peak_viewers INT DEFAULT 0,
    maps JSON NULL,
    maps_data JSON NULL,
    broadcast JSON NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team1_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (team2_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES teams(id) ON DELETE SET NULL,
    INDEX idx_teams (team1_id, team2_id),
    INDEX idx_event (event_id),
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_winner (winner_id)
);

-- ==========================================
-- HEROES & GAME DATA
-- ==========================================

CREATE TABLE IF NOT EXISTS heroes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    slug VARCHAR(255) UNIQUE,
    role ENUM('Vanguard', 'Duelist', 'Strategist') NOT NULL,
    type ENUM('Tank', 'DPS', 'Support') NOT NULL,
    description TEXT NULL,
    abilities JSON NULL,
    image VARCHAR(500) NULL,
    portrait VARCHAR(500) NULL,
    icon VARCHAR(500) NULL,
    difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    release_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    pick_rate DECIMAL(5,2) DEFAULT 0.00,
    win_rate DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_type (type),
    INDEX idx_active (is_active)
);

-- Insert Marvel Rivals Heroes
INSERT INTO heroes (name, slug, role, type, description, image, difficulty) VALUES
-- Vanguard (Tanks)
('Doctor Strange', 'doctor-strange', 'Vanguard', 'Tank', 'Master of the Mystic Arts', '/heroes/doctor-strange.webp', 'Hard'),
('Groot', 'groot', 'Vanguard', 'Tank', 'I am Groot!', '/heroes/groot.webp', 'Easy'),
('Hulk', 'hulk', 'Vanguard', 'Tank', 'The Incredible Hulk', '/heroes/hulk.webp', 'Medium'),
('Magneto', 'magneto', 'Vanguard', 'Tank', 'Master of Magnetism', '/heroes/magneto.webp', 'Hard'),
('Peni Parker', 'peni-parker', 'Vanguard', 'Tank', 'SP//dr Pilot', '/heroes/peni-parker.webp', 'Medium'),
('Thor', 'thor', 'Vanguard', 'Tank', 'God of Thunder', '/heroes/thor.webp', 'Medium'),
('Venom', 'venom', 'Vanguard', 'Tank', 'Symbiote', '/heroes/venom.webp', 'Medium'),
('Captain America', 'captain-america', 'Vanguard', 'Tank', 'First Avenger', '/heroes/captain-america.webp', 'Easy'),

-- Duelist (DPS)
('Black Panther', 'black-panther', 'Duelist', 'DPS', 'King of Wakanda', '/heroes/black-panther.webp', 'Medium'),
('Hawkeye', 'hawkeye', 'Duelist', 'DPS', 'Master Archer', '/heroes/hawkeye.webp', 'Hard'),
('Hela', 'hela', 'Duelist', 'DPS', 'Goddess of Death', '/heroes/hela.webp', 'Medium'),
('Iron Man', 'iron-man', 'Duelist', 'DPS', 'Armored Avenger', '/heroes/iron-man.webp', 'Medium'),
('Magik', 'magik', 'Duelist', 'DPS', 'Sorceress Supreme', '/heroes/magik.webp', 'Hard'),
('Namor', 'namor', 'Duelist', 'DPS', 'King of Atlantis', '/heroes/namor.webp', 'Medium'),
('Psylocke', 'psylocke', 'Duelist', 'DPS', 'Psychic Ninja', '/heroes/psylocke.webp', 'Hard'),
('Punisher', 'punisher', 'Duelist', 'DPS', 'Vigilante', '/heroes/punisher.webp', 'Easy'),
('Scarlet Witch', 'scarlet-witch', 'Duelist', 'DPS', 'Chaos Magic', '/heroes/scarlet-witch.webp', 'Hard'),
('Spider-Man', 'spider-man', 'Duelist', 'DPS', 'Web Slinger', '/heroes/spider-man.webp', 'Medium'),
('Star-Lord', 'star-lord', 'Duelist', 'DPS', 'Guardian of the Galaxy', '/heroes/star-lord.webp', 'Easy'),
('Storm', 'storm', 'Duelist', 'DPS', 'Weather Goddess', '/heroes/storm.webp', 'Medium'),
('Winter Soldier', 'winter-soldier', 'Duelist', 'DPS', 'Assassin', '/heroes/winter-soldier.webp', 'Medium'),
('Wolverine', 'wolverine', 'Duelist', 'DPS', 'Adamantium Claws', '/heroes/wolverine.webp', 'Easy'),

-- Strategist (Support)
('Adam Warlock', 'adam-warlock', 'Strategist', 'Support', 'Cosmic Being', '/heroes/adam-warlock.webp', 'Hard'),
('Cloak & Dagger', 'cloak-dagger', 'Strategist', 'Support', 'Light and Dark', '/heroes/cloak-dagger.webp', 'Hard'),
('Jeff the Land Shark', 'jeff', 'Strategist', 'Support', 'Adorable Predator', '/heroes/jeff.webp', 'Easy'),
('Loki', 'loki', 'Strategist', 'Support', 'God of Mischief', '/heroes/loki.webp', 'Hard'),
('Luna Snow', 'luna-snow', 'Strategist', 'Support', 'K-Pop Star', '/heroes/luna-snow.webp', 'Medium'),
('Mantis', 'mantis', 'Strategist', 'Support', 'Empath', '/heroes/mantis.webp', 'Easy'),
('Rocket Raccoon', 'rocket-raccoon', 'Strategist', 'Support', 'Guardian Gunner', '/heroes/rocket-raccoon.webp', 'Medium');

-- ==========================================
-- MAPS & GAME MODES
-- ==========================================

CREATE TABLE IF NOT EXISTS maps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    slug VARCHAR(255) UNIQUE,
    description TEXT NULL,
    image VARCHAR(500) NULL,
    thumbnail VARCHAR(500) NULL,
    modes JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
);

-- Insert Marvel Rivals Maps
INSERT INTO maps (name, slug, description, image, modes) VALUES
('Asgard: Royal Palace', 'asgard-royal-palace', 'The royal palace of Asgard', '/maps/asgard-royal-palace.jpg', '["Domination", "Convergence"]'),
('Birnin Zana: Golden City', 'birnin-zana', 'The golden city of Wakanda', '/maps/birnin-zana.jpg', '["Escort", "Convergence"]'),
('Klyntar: Symbiote Research Station', 'klyntar', 'Symbiote research facility', '/maps/klyntar.jpg', '["Convoy", "Domination"]'),
('Midtown: Times Square', 'midtown', 'The heart of New York City', '/maps/midtown.jpg', '["Domination", "Escort"]'),
('Moon Base: Artiluna-1', 'moon-base', 'Lunar research station', '/maps/moon-base.jpg', '["Convoy", "Convergence"]'),
('Sanctum Sanctorum', 'sanctum', 'Doctor Strange\'s mystical sanctuary', '/maps/sanctum.jpg', '["Domination", "Escort"]'),
('Throne Room of Asgard', 'throne-room', 'Odin\'s throne room', '/maps/throne-room.jpg', '["Convergence", "Convoy"]'),
('Tokyo 2099: Spider Islands', 'tokyo-2099', 'Futuristic Tokyo', '/maps/tokyo-2099.jpg', '["Escort", "Domination"]'),
('Wakanda', 'wakanda', 'The technological paradise', '/maps/wakanda.jpg', '["Convoy", "Convergence"]'),
('Yggsgard: Seed of the World Tree', 'yggsgard', 'The World Tree seed', '/maps/yggsgard.jpg', '["Domination", "Escort"]');

-- ==========================================
-- NEWS & CONTENT
-- ==========================================

CREATE TABLE IF NOT EXISTS news (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(500) NULL,
    author_id BIGINT UNSIGNED NULL,
    category ENUM('general', 'tournament', 'roster', 'patch', 'interview', 'analysis') DEFAULT 'general',
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    views INT DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_published (published_at),
    INDEX idx_featured (is_featured)
);

-- ==========================================
-- FORUM SYSTEM
-- ==========================================

CREATE TABLE IF NOT EXISTS forum_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT NULL,
    icon VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
);

-- Insert default forum categories
INSERT INTO forum_categories (name, slug, description, icon, sort_order) VALUES
('General Discussion', 'general', 'General Marvel Rivals discussion', 'chat', 1),
('Team Discussion', 'teams', 'Discuss your favorite teams', 'users', 2),
('Tournament Talk', 'tournaments', 'Tournament discussions and predictions', 'trophy', 3),
('Hero Discussion', 'heroes', 'Hero strategies and discussions', 'star', 4),
('Looking for Team', 'lft', 'Find teammates and scrimmage partners', 'search', 5),
('Technical Support', 'support', 'Get help with technical issues', 'help', 6);

CREATE TABLE IF NOT EXISTS forum_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    author_id BIGINT UNSIGNED NOT NULL,
    content LONGTEXT NOT NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_locked BOOLEAN DEFAULT FALSE,
    views INT DEFAULT 0,
    replies_count INT DEFAULT 0,
    last_reply_at TIMESTAMP NULL,
    last_reply_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES forum_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_reply_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_author (author_id),
    INDEX idx_pinned (is_pinned),
    INDEX idx_last_reply (last_reply_at)
);

CREATE TABLE IF NOT EXISTS forum_replies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    thread_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    content LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_thread (thread_id),
    INDEX idx_author (author_id),
    INDEX idx_created (created_at)
);

-- ==========================================
-- STATISTICS & ANALYTICS
-- ==========================================

CREATE TABLE IF NOT EXISTS player_stats (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    match_id BIGINT UNSIGNED NOT NULL,
    hero_id BIGINT UNSIGNED NULL,
    map_name VARCHAR(255) NULL,
    eliminations INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assists INT DEFAULT 0,
    damage BIGINT DEFAULT 0,
    healing BIGINT DEFAULT 0,
    damage_blocked BIGINT DEFAULT 0,
    play_time INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (hero_id) REFERENCES heroes(id) ON DELETE SET NULL,
    INDEX idx_player (player_id),
    INDEX idx_match (match_id),
    INDEX idx_hero (hero_id)
);

-- ==========================================
-- INDEXES FOR PERFORMANCE
-- ==========================================

-- Additional indexes for better query performance
CREATE INDEX idx_matches_recent ON matches(scheduled_at DESC, status);
CREATE INDEX idx_matches_live ON matches(status, scheduled_at) WHERE status = 'live';
CREATE INDEX idx_players_team_role ON players(team_id, role);
CREATE INDEX idx_events_dates ON events(start_date, end_date, status);
CREATE INDEX idx_news_published ON news(published_at DESC, status) WHERE status = 'published';

-- ==========================================
-- VIEWS FOR COMMON QUERIES
-- ==========================================

-- Leaderboard view
CREATE VIEW player_leaderboard AS
SELECT 
    p.id,
    p.name,
    p.real_name,
    p.role,
    p.country,
    t.name as team_name,
    COUNT(ps.match_id) as matches_played,
    SUM(ps.eliminations) as total_eliminations,
    SUM(ps.deaths) as total_deaths,
    SUM(ps.assists) as total_assists,
    SUM(ps.damage) as total_damage,
    SUM(ps.healing) as total_healing,
    SUM(ps.damage_blocked) as total_damage_blocked,
    CASE 
        WHEN SUM(ps.deaths) > 0 THEN ROUND(SUM(ps.eliminations) / SUM(ps.deaths), 2)
        ELSE SUM(ps.eliminations)
    END as kd_ratio,
    CASE 
        WHEN SUM(ps.deaths) > 0 THEN ROUND((SUM(ps.eliminations) + SUM(ps.assists)) / SUM(ps.deaths), 2)
        ELSE (SUM(ps.eliminations) + SUM(ps.assists))
    END as kad_ratio
FROM players p
LEFT JOIN teams t ON p.team_id = t.id
LEFT JOIN player_stats ps ON p.id = ps.player_id
WHERE p.status = 'active'
GROUP BY p.id, p.name, p.real_name, p.role, p.country, t.name
HAVING matches_played > 0
ORDER BY kd_ratio DESC, total_eliminations DESC;