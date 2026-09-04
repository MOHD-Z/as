USE anistream;

-- 1. Translations table
CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lang_code VARCHAR(10) NOT NULL,
    trans_key VARCHAR(100) NOT NULL,
    trans_val TEXT NOT NULL,
    UNIQUE KEY uq_lang_trans (lang_code, trans_key)
);

-- 2. New columns for series (MAL / TMDB metadata)
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

-- 3. New columns for movies (MAL / TMDB metadata)
ALTER TABLE movies ADD COLUMN IF NOT EXISTS original_title VARCHAR(255) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS release_date VARCHAR(50) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS quality VARCHAR(20) DEFAULT 'HD';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS vote_count INT DEFAULT 0;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS user_score_percent INT DEFAULT 0;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS original_language VARCHAR(50) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS rating VARCHAR(50) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS mal_id INT NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS tmdb_id INT NULL;

-- 4. Site settings values to TEXT
ALTER TABLE site_settings MODIFY COLUMN setting_value TEXT NOT NULL;

-- 5. Seed default settings for General, Layout, Media Cards
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

