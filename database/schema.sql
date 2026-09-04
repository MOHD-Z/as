-- AniStream local database
-- Import this whole file in phpMyAdmin (or `mysql -u root anistream < schema.sql`)

CREATE DATABASE IF NOT EXISTS anistream CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE anistream;

-- ---------------------------------------------------------------
CREATE TABLE genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    synopsis TEXT,
    poster VARCHAR(255),
    genre_id INT,
    status VARCHAR(50) DEFAULT 'Ongoing',
    score DECIMAL(3,1) DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL
);

CREATE TABLE seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    series_id INT NOT NULL,
    season_number INT NOT NULL,
    title VARCHAR(255),
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
);

CREATE TABLE episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    episode_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

CREATE TABLE movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    synopsis TEXT,
    poster VARCHAR(255),
    genre_id INT,
    runtime INT DEFAULT 90,
    score DECIMAL(3,1) DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL
);

-- Video sources can belong to either an episode or a movie (exactly one of the two is set)
CREATE TABLE video_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NULL,
    movie_id INT NULL,
    name VARCHAR(100) NOT NULL,
    quality VARCHAR(20) DEFAULT 'HD',
    url VARCHAR(500) NOT NULL,
    priority INT DEFAULT 1,
    enabled TINYINT(1) DEFAULT 1,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
);

CREATE TABLE video_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_source_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_source_id) REFERENCES video_sources(id) ON DELETE CASCADE
);

CREATE TABLE blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt VARCHAR(500),
    body TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    series_id INT NOT NULL DEFAULT 0,
    movie_id INT NOT NULL DEFAULT 0,
    list_type ENUM('favorite','watchlist') NOT NULL DEFAULT 'favorite',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_entry (user_id, series_id, movie_id, list_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Continue Watching / Watch History (series_id/movie_id use 0, not NULL, so the
-- unique key + ON DUPLICATE KEY UPDATE below work correctly in MySQL)
CREATE TABLE watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    series_id INT NOT NULL DEFAULT 0,
    movie_id INT NOT NULL DEFAULT 0,
    episode_id INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_progress (user_id, series_id, movie_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------
-- Seed data (reuses the template's existing image assets)

INSERT INTO genres (name, slug) VALUES
('Action', 'action'), ('Adventure', 'adventure'), ('Comedy', 'comedy'),
('Drama', 'drama'), ('Fantasy', 'fantasy'), ('Romance', 'romance'), ('Sci-Fi', 'sci-fi');

INSERT INTO series (title, slug, synopsis, poster, genre_id, status, score, views) VALUES
('The Seven Deadly Sins: Wrath of the Gods', 'seven-deadly-sins-wrath-of-the-gods', 'A band of fallen knights fights to save the kingdom of Britannia once more.', 'img/trending/trend-1.jpg', 1, 'Ongoing', 8.4, 9141),
('Shingeki no Kyojin Season 3', 'shingeki-no-kyojin-season-3', 'Humanity continues its desperate struggle against the Titans.', 'img/trending/trend-3.jpg', 4, 'Completed', 9.0, 15320),
('Dogulwang: Tomb Raider King', 'dogulwang-tomb-raider-king', 'A treasure hunter uncovers secrets buried for centuries.', 'img/recent/recent-1.jpg', 2, 'Ongoing', 7.8, 5210),
('Blue Lock', 'blue-lock', '300 strikers enter a brutal program to find Japan''s next number 9.', 'img/recent/recent-3.jpg', 1, 'Ongoing', 8.7, 12040),
('One Piece', 'one-piece', 'Monkey D. Luffy sails the Grand Line in search of the ultimate treasure.', 'img/popular/popular-1.jpg', 2, 'Ongoing', 9.2, 30122),
('Game of Thrones', 'game-of-thrones', 'Noble families vie for control of the Iron Throne.', 'img/popular/popular-3.jpg', 4, 'Completed', 9.3, 41022);

INSERT INTO seasons (series_id, season_number, title) VALUES
(1,1,'Season 1'), (2,1,'Season 1'), (2,2,'Season 2'),
(3,1,'Season 1'), (4,1,'Season 1'), (5,1,'Season 1'), (6,1,'Season 1'), (6,2,'Season 2');

INSERT INTO episodes (season_id, episode_number, title, slug, views) VALUES
(1,1,'Episode 1','seven-deadly-sins-s1e1',3200),
(1,2,'Episode 2','seven-deadly-sins-s1e2',2950),
(2,1,'Episode 1','snk-s1e1',9800),
(3,1,'Episode 1','snk-s2e1',9100),
(3,2,'Episode 2','snk-s2e2',8700),
(4,1,'Episode 1','dogulwang-e1',1500),
(5,1,'Episode 1','blue-lock-e1',4200),
(6,1,'Episode 1','one-piece-e1',7300),
(7,1,'Episode 1','got-s1e1',12000),
(8,1,'Episode 8','got-s2e8',15800);

INSERT INTO movies (title, slug, synopsis, poster, genre_id, runtime, score, views) VALUES
('Gintama Movie 2: Kanketsu-hen', 'gintama-movie-2-kanketsu-hen', 'The Yorozuya face their final battle in Edo.', 'img/trending/trend-2.jpg', 3, 110, 8.5, 9141),
('Blue Lock: Episode Nagi', 'blue-lock-episode-nagi', 'A side story following Nagi''s rise as a striker.', 'img/recent/recent-4.jpg', 1, 95, 8.2, 6600),
('Your Name', 'your-name', 'Two strangers find themselves linked in a bizarre way.', 'img/popular/popular-5.jpg', 6, 106, 9.1, 22000);

INSERT INTO video_sources (episode_id, movie_id, name, quality, url, priority, enabled) VALUES
(1, NULL, 'Server 1', 'HD', 'videos/1.mp4', 1, 1),
(1, NULL, 'Backup', 'SD', 'videos/1.mp4', 2, 1),
(10, NULL, 'Server 1', 'HD', 'videos/1.mp4', 1, 1),
(10, NULL, 'YouTube 2', 'FHD', 'videos/1.mp4', 2, 1),
(NULL, 1, 'Server 1', 'HD', 'videos/1.mp4', 1, 1),
(NULL, 3, 'Server 1', 'FHD', 'videos/1.mp4', 1, 1);

-- Default admin login: admin@anistream.test / admin123 (change this after first login!)
INSERT INTO admin_users (name, email, password) VALUES
('Admin', 'admin@anistream.test', '$2b$12$hQZfE2KtBx8CLE/QN46G7uYPLf3o7vrcLmPHCRGsoO8oWQZ7w/hd.');

INSERT INTO blog_posts (title, slug, excerpt, body, image) VALUES
('Top 10 Anime of the Season', 'top-10-anime-of-the-season', 'Our picks for the must-watch series this season.', 'Full article body goes here. Replace with real content from the admin panel.', 'img/blog/blog-1.jpg'),
('Why Game of Thrones Still Holds Up', 'why-game-of-thrones-still-holds-up', 'A look back at the series years later.', 'Full article body goes here. Replace with real content from the admin panel.', 'img/blog/blog-2.jpg');

-- ---------------------------------------------------------------
-- Configurable homepage sections (spec: admin can add/remove/reorder
-- homepage blocks without touching code)
CREATE TABLE homepage_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    content_type ENUM('series','movies','trending','popular','blog') NOT NULL,
    posts_count INT DEFAULT 8,
    sort_by ENUM('newest','oldest','score','views') DEFAULT 'newest',
    visible TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);

-- Site-wide key/value settings (sidebar toggle, media-card field toggles, site name)
CREATE TABLE site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);

-- Languages
CREATE TABLE languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    rtl TINYINT(1) DEFAULT 0,
    enabled TINYINT(1) DEFAULT 1
);

INSERT INTO homepage_sections (title, content_type, posts_count, sort_by, visible, sort_order) VALUES
('Trending Now', 'series', 8, 'views', 1, 1),
('Latest Movies', 'movies', 8, 'newest', 1, 2),
('Popular Series', 'series', 8, 'score', 1, 3);

INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'AniStream'),
('sidebar_enabled', '1'),
('show_episode_badge', '1'),
('show_genre_tag', '1'),
('show_type_badge', '1'),
('show_views', '1'),
('show_score', '1');

INSERT INTO languages (code, name, is_default, rtl, enabled) VALUES
('en', 'English', 1, 0, 1),
('ar', 'Arabic', 0, 1, 1);

-- ---------------------------------------------------------------
-- v3 additions: multi-genre, archive/status, editable nav links

-- Many-to-many genres (a title can have more than one genre)
CREATE TABLE series_genres (
    series_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (series_id, genre_id),
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

CREATE TABLE movie_genres (
    movie_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (movie_id, genre_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

-- Backfill from the existing single genre_id column
INSERT IGNORE INTO series_genres (series_id, genre_id) SELECT id, genre_id FROM series WHERE genre_id IS NOT NULL;
INSERT IGNORE INTO movie_genres (movie_id, genre_id) SELECT id, genre_id FROM movies WHERE genre_id IS NOT NULL;

-- Archive status (soft-hide instead of delete)
ALTER TABLE series ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE movies ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE episodes ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0;

-- Editable header/footer navigation links
CREATE TABLE nav_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    location ENUM('header','footer') NOT NULL DEFAULT 'header',
    visibility ENUM('always','guest_only','auth_only') NOT NULL DEFAULT 'always',
    sort_order INT DEFAULT 0,
    visible TINYINT(1) DEFAULT 1
);

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible) VALUES
('Homepage', 'index.php', 'header', 'always', 1, 1),
('Episodes', 'episodes.php', 'header', 'always', 2, 1),
('Blog', 'blog.php', 'header', 'always', 4, 1),
('My List', 'my-list.php', 'header', 'auth_only', 5, 1),
('Login', 'login.php', 'header', 'guest_only', 6, 1),
('Sign Up', 'signup.php', 'header', 'guest_only', 7, 1),
('Homepage', 'index.php', 'footer', 'always', 1, 1),
('Genres', 'genrs.php', 'footer', 'always', 2, 1),
('Our Blog', 'blog.php', 'footer', 'always', 3, 1);

-- Optional API keys for the metadata importer (TMDB requires a free key;
-- MAL import uses the public Jikan API which needs no key)
INSERT INTO site_settings (setting_key, setting_value) VALUES ('tmdb_api_key', '');

-- ---------------------------------------------------------------
-- v4 additions: blog publish toggle, genre secondary-language name

ALTER TABLE blog_posts ADD COLUMN published TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE genres ADD COLUMN name_secondary VARCHAR(100) NULL;

-- Lightweight visitor tracking: one row per session per day (not every
-- single page hit), so counts approximate daily/weekly/monthly *visitors*
-- rather than raw page views.
CREATE TABLE visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visited_on DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- Latest Updates: Translations, Metadata fields, Settings
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lang_code VARCHAR(10) NOT NULL,
    trans_key VARCHAR(100) NOT NULL,
    trans_val TEXT NOT NULL,
    UNIQUE KEY uq_lang_trans (lang_code, trans_key)
);

ALTER TABLE series ADD COLUMN IF NOT EXISTS original_title VARCHAR(255) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS episodes_count INT DEFAULT 0;
ALTER TABLE series ADD COLUMN IF NOT EXISTS aired VARCHAR(100) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS premiered VARCHAR(100) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS broadcast VARCHAR(100) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS source VARCHAR(100) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS duration VARCHAR(50) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS rating VARCHAR(50) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS quality VARCHAR(20) DEFAULT 'HD';
ALTER TABLE series ADD COLUMN IF NOT EXISTS studio VARCHAR(150) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS country VARCHAR(50) NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS vote_count INT DEFAULT 0;
ALTER TABLE series ADD COLUMN IF NOT EXISTS mal_id INT NULL;
ALTER TABLE series ADD COLUMN IF NOT EXISTS tmdb_id INT NULL;

ALTER TABLE movies ADD COLUMN IF NOT EXISTS original_title VARCHAR(255) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS release_date VARCHAR(50) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS quality VARCHAR(20) DEFAULT 'HD';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS vote_count INT DEFAULT 0;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS user_score_percent INT DEFAULT 0;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS original_language VARCHAR(50) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS rating VARCHAR(50) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS mal_id INT NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS tmdb_id INT NULL;

ALTER TABLE site_settings MODIFY COLUMN setting_value TEXT NOT NULL;

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('site_icon', ''),
('site_logo', ''),
('seo_meta_title', 'AniStream - Watch Anime & Movies Online'),
('seo_meta_description', 'Watch anime series and movies online in HD quality for free.'),
('seo_meta_keywords', 'anime, watch anime, movies, series, streaming, episodes'),
('sidebar_enabled', '1'),
('sidebar_content', 'views_and_blog'),
('slider_enabled', '1'),
('slider_show_genres', '1'),
('slider_show_synopsis_btn', '1'),
('home_card_show_genres', '1'),
('home_card_show_type', '1'),
('home_card_show_views', '1'),
('home_card_show_duration', '1'),
('other_card_show_genres', '1'),
('other_card_show_type', '1'),
('other_card_show_views', '1'),
('other_card_show_duration', '1'),
('other_card_show_score', '1');
