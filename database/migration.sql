-- AniStream — safe catch-up migration
-- Safe to run on an existing database: every statement only ADDS what's
-- missing (tables via IF NOT EXISTS, columns via ADD COLUMN IF NOT EXISTS).
-- It will never drop or overwrite your existing data.
--
-- How to run: phpMyAdmin -> click your `anistream` database -> "SQL" tab
-- -> paste this whole file -> Go.

USE anistream;

-- ---------------------------------------------------------------
-- Core tables (created only if they don't already exist)

CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    synopsis TEXT,
    poster VARCHAR(255),
    genre_id INT,
    status VARCHAR(50) DEFAULT 'Ongoing',
    score DECIMAL(3,1) DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    series_id INT NOT NULL,
    season_number INT NOT NULL,
    title VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    episode_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    synopsis TEXT,
    poster VARCHAR(255),
    genre_id INT,
    runtime INT DEFAULT 90,
    score DECIMAL(3,1) DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS video_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NULL,
    movie_id INT NULL,
    name VARCHAR(100) NOT NULL,
    quality VARCHAR(20) DEFAULT 'HD',
    url VARCHAR(500) NOT NULL,
    priority INT DEFAULT 1,
    enabled TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS video_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_source_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt VARCHAR(500),
    body TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    series_id INT NOT NULL DEFAULT 0,
    movie_id INT NOT NULL DEFAULT 0,
    list_type ENUM('favorite','watchlist') NOT NULL DEFAULT 'favorite',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    series_id INT NOT NULL DEFAULT 0,
    movie_id INT NOT NULL DEFAULT 0,
    episode_id INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS homepage_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    content_type ENUM('series','movies','trending','popular','blog') NOT NULL,
    posts_count INT DEFAULT 8,
    sort_by ENUM('newest','oldest','score','views') DEFAULT 'newest',
    visible TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    rtl TINYINT(1) DEFAULT 0,
    enabled TINYINT(1) DEFAULT 1
);

-- ---------------------------------------------------------------
-- Catch up any table that already existed but is missing newer columns
-- (safe no-ops if the column is already there — covers every table,
-- since partial/older setups may be missing columns anywhere)

ALTER TABLE genres ADD COLUMN IF NOT EXISTS name VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE genres ADD COLUMN IF NOT EXISTS slug VARCHAR(100) NOT NULL DEFAULT '';

ALTER TABLE series ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE series ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE series ADD COLUMN IF NOT EXISTS synopsis TEXT;
ALTER TABLE series ADD COLUMN IF NOT EXISTS poster VARCHAR(255);
ALTER TABLE series ADD COLUMN IF NOT EXISTS genre_id INT;
ALTER TABLE series ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Ongoing';
ALTER TABLE series ADD COLUMN IF NOT EXISTS score DECIMAL(3,1) DEFAULT 0;
ALTER TABLE series ADD COLUMN IF NOT EXISTS views INT DEFAULT 0;
ALTER TABLE series ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE seasons ADD COLUMN IF NOT EXISTS series_id INT NOT NULL DEFAULT 0;
ALTER TABLE seasons ADD COLUMN IF NOT EXISTS season_number INT NOT NULL DEFAULT 1;
ALTER TABLE seasons ADD COLUMN IF NOT EXISTS title VARCHAR(255);

ALTER TABLE episodes ADD COLUMN IF NOT EXISTS season_id INT NOT NULL DEFAULT 0;
ALTER TABLE episodes ADD COLUMN IF NOT EXISTS episode_number INT NOT NULL DEFAULT 1;
ALTER TABLE episodes ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE episodes ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE episodes ADD COLUMN IF NOT EXISTS views INT DEFAULT 0;
ALTER TABLE episodes ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE movies ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS synopsis TEXT;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS poster VARCHAR(255);
ALTER TABLE movies ADD COLUMN IF NOT EXISTS genre_id INT;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS runtime INT DEFAULT 90;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS score DECIMAL(3,1) DEFAULT 0;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS views INT DEFAULT 0;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE video_sources ADD COLUMN IF NOT EXISTS episode_id INT NULL;
ALTER TABLE video_sources ADD COLUMN IF NOT EXISTS movie_id INT NULL;
ALTER TABLE video_sources ADD COLUMN IF NOT EXISTS name VARCHAR(100) NOT NULL DEFAULT 'Server 1';
ALTER TABLE video_sources ADD COLUMN IF NOT EXISTS quality VARCHAR(20) DEFAULT 'HD';
ALTER TABLE video_sources ADD COLUMN IF NOT EXISTS url VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE video_sources ADD COLUMN IF NOT EXISTS priority INT DEFAULT 1;
ALTER TABLE video_sources ADD COLUMN IF NOT EXISTS enabled TINYINT(1) DEFAULT 1;

ALTER TABLE video_reports ADD COLUMN IF NOT EXISTS video_source_id INT NOT NULL DEFAULT 0;
ALTER TABLE video_reports ADD COLUMN IF NOT EXISTS reason VARCHAR(100) NOT NULL DEFAULT 'Other';
ALTER TABLE video_reports ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS excerpt VARCHAR(500);
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS body TEXT;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS image VARCHAR(255);
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE users ADD COLUMN IF NOT EXISTS name VARCHAR(150) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(190) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS password VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS name VARCHAR(150) NOT NULL DEFAULT '';
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS email VARCHAR(190) NOT NULL DEFAULT '';
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS password VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE favorites ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 0;
ALTER TABLE favorites ADD COLUMN IF NOT EXISTS series_id INT NOT NULL DEFAULT 0;
ALTER TABLE favorites ADD COLUMN IF NOT EXISTS movie_id INT NOT NULL DEFAULT 0;
ALTER TABLE favorites ADD COLUMN IF NOT EXISTS list_type ENUM('favorite','watchlist') NOT NULL DEFAULT 'favorite';
ALTER TABLE favorites ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE watch_history ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 0;
ALTER TABLE watch_history ADD COLUMN IF NOT EXISTS series_id INT NOT NULL DEFAULT 0;
ALTER TABLE watch_history ADD COLUMN IF NOT EXISTS movie_id INT NOT NULL DEFAULT 0;
ALTER TABLE watch_history ADD COLUMN IF NOT EXISTS episode_id INT NULL;
ALTER TABLE watch_history ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE homepage_sections ADD COLUMN IF NOT EXISTS title VARCHAR(150) NOT NULL DEFAULT '';
ALTER TABLE homepage_sections ADD COLUMN IF NOT EXISTS content_type ENUM('series','movies','trending','popular','blog') NOT NULL DEFAULT 'series';
ALTER TABLE homepage_sections ADD COLUMN IF NOT EXISTS posts_count INT DEFAULT 8;
ALTER TABLE homepage_sections ADD COLUMN IF NOT EXISTS sort_by ENUM('newest','oldest','score','views') DEFAULT 'newest';
ALTER TABLE homepage_sections ADD COLUMN IF NOT EXISTS visible TINYINT(1) DEFAULT 1;
ALTER TABLE homepage_sections ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;

ALTER TABLE site_settings ADD COLUMN IF NOT EXISTS setting_value VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE languages ADD COLUMN IF NOT EXISTS code VARCHAR(10) NOT NULL DEFAULT '';
ALTER TABLE languages ADD COLUMN IF NOT EXISTS name VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE languages ADD COLUMN IF NOT EXISTS is_default TINYINT(1) DEFAULT 0;
ALTER TABLE languages ADD COLUMN IF NOT EXISTS rtl TINYINT(1) DEFAULT 0;
ALTER TABLE languages ADD COLUMN IF NOT EXISTS enabled TINYINT(1) DEFAULT 1;

-- ---------------------------------------------------------------
-- Seed data — only inserted if missing (won't duplicate or overwrite)

INSERT IGNORE INTO admin_users (id, name, email, password) VALUES
(1, 'Admin', 'admin@anistream.test', '$2b$12$hQZfE2KtBx8CLE/QN46G7uYPLf3o7vrcLmPHCRGsoO8oWQZ7w/hd.');

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'AniStream'),
('sidebar_enabled', '1'),
('show_episode_badge', '1'),
('show_genre_tag', '1'),
('show_type_badge', '1'),
('show_views', '1'),
('show_score', '1'),
('tmdb_api_key', ''),
('mal_client_id', '');

INSERT IGNORE INTO languages (code, name, is_default, rtl, enabled) VALUES
('en', 'English', 1, 0, 1),
('ar', 'Arabic', 0, 1, 1);

-- Only seed homepage sections if you have none configured yet
INSERT INTO homepage_sections (title, content_type, posts_count, sort_by, visible, sort_order)
SELECT * FROM (SELECT 'Trending Now' AS title, 'series' AS content_type, 8 AS posts_count, 'views' AS sort_by, 1 AS visible, 1 AS sort_order) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM homepage_sections);

INSERT INTO homepage_sections (title, content_type, posts_count, sort_by, visible, sort_order)
SELECT * FROM (SELECT 'Latest Movies', 'movies', 8, 'newest', 1, 2) AS tmp
WHERE (SELECT COUNT(*) FROM homepage_sections) <= 1;

INSERT INTO homepage_sections (title, content_type, posts_count, sort_by, visible, sort_order)
SELECT * FROM (SELECT 'Popular Series', 'series', 8, 'score', 1, 3) AS tmp
WHERE (SELECT COUNT(*) FROM homepage_sections) <= 2;

-- ---------------------------------------------------------------
-- v4 catch-up: multi-genre, archive status, editable nav links,
-- blog publish toggle, genre secondary-language name
-- (safe to run again even if you already ran an earlier version of
-- this file — every statement only adds what's missing)

CREATE TABLE IF NOT EXISTS series_genres (
    series_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (series_id, genre_id)
);

CREATE TABLE IF NOT EXISTS movie_genres (
    movie_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (movie_id, genre_id)
);

-- Backfill multi-genre tables from whatever each title's old single
-- genre_id column currently holds (only if not already backfilled)
INSERT IGNORE INTO series_genres (series_id, genre_id) SELECT id, genre_id FROM series WHERE genre_id IS NOT NULL;
INSERT IGNORE INTO movie_genres (movie_id, genre_id) SELECT id, genre_id FROM movies WHERE genre_id IS NOT NULL;

ALTER TABLE series ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE episodes ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS published TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE genres ADD COLUMN IF NOT EXISTS name_secondary VARCHAR(100) NULL;

CREATE TABLE IF NOT EXISTS nav_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    location ENUM('header','footer') NOT NULL DEFAULT 'header',
    visibility ENUM('always','guest_only','auth_only') NOT NULL DEFAULT 'always',
    sort_order INT DEFAULT 0,
    visible TINYINT(1) DEFAULT 1
);

-- Only seed the default nav links once (skip if you've already customized them)
INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Homepage' AS label, 'index.php' AS url, 'header' AS location, 'always' AS visibility, 1 AS sort_order, 1 AS visible) t
WHERE NOT EXISTS (SELECT 1 FROM nav_links);

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Episodes', 'episodes.php', 'header', 'always', 2, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 1;

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Blog', 'blog.php', 'header', 'always', 4, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 2;

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'My List', 'my-list.php', 'header', 'auth_only', 5, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 3;

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Login', 'login.php', 'header', 'guest_only', 6, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 4;

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Sign Up', 'signup.php', 'header', 'guest_only', 7, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 5;

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Homepage', 'index.php', 'footer', 'always', 1, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 6;

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Genres', 'genrs.php', 'footer', 'always', 2, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 7;

INSERT INTO nav_links (label, url, location, visibility, sort_order, visible)
SELECT * FROM (SELECT 'Our Blog', 'blog.php', 'footer', 'always', 3, 1) t
WHERE (SELECT COUNT(*) FROM nav_links) <= 8;

-- v5 catch-up: visitor tracking
CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visited_on DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
