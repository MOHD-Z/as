<?php
// Automatic Schema Self-Healing & Catch-up Migrator
// Ensures newly added tables and columns exist on any local/production MySQL instance without manual SQL execution.

function ensure_database_schema(PDO $pdo) {
    static $alreadyRun = false;
    if ($alreadyRun) return;
    $alreadyRun = true;

    try {
        // 1. Ensure translations table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS translations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lang_code VARCHAR(10) NOT NULL,
            trans_key VARCHAR(100) NOT NULL,
            trans_val TEXT NOT NULL,
            UNIQUE KEY uq_lang_trans (lang_code, trans_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Ensure comments table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            episode_id INT NULL,
            movie_id INT NULL,
            author_name VARCHAR(150) NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ep (episode_id),
            INDEX idx_movie (movie_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Ensure series table columns
        $seriesCols = $pdo->query("SHOW COLUMNS FROM series")->fetchAll(PDO::FETCH_COLUMN);
        $seriesColsMap = array_flip($seriesCols);

        $seriesAdditions = [
            'original_title' => "VARCHAR(255) NULL",
            'episodes_count' => "INT DEFAULT 0",
            'aired' => "VARCHAR(100) NULL",
            'premiered' => "VARCHAR(100) NULL",
            'broadcast' => "VARCHAR(100) NULL",
            'source' => "VARCHAR(100) NULL",
            'duration' => "VARCHAR(50) NULL",
            'rating' => "VARCHAR(50) NULL",
            'quality' => "VARCHAR(20) DEFAULT 'HD'",
            'studio' => "VARCHAR(150) NULL",
            'country' => "VARCHAR(50) NULL",
            'vote_count' => "INT DEFAULT 0",
            'mal_id' => "INT NULL",
            'tmdb_id' => "INT NULL",
            'archived' => "TINYINT(1) NOT NULL DEFAULT 0"
        ];

        foreach ($seriesAdditions as $col => $def) {
            if (!isset($seriesColsMap[$col])) {
                try {
                    $pdo->exec("ALTER TABLE series ADD COLUMN $col $def");
                } catch (Exception $e) {}
            }
        }

        // 4. Ensure movies table columns
        $moviesCols = $pdo->query("SHOW COLUMNS FROM movies")->fetchAll(PDO::FETCH_COLUMN);
        $moviesColsMap = array_flip($moviesCols);

        $moviesAdditions = [
            'original_title' => "VARCHAR(255) NULL",
            'release_date' => "VARCHAR(50) NULL",
            'quality' => "VARCHAR(20) DEFAULT 'HD'",
            'vote_count' => "INT DEFAULT 0",
            'user_score_percent' => "INT DEFAULT 0",
            'original_language' => "VARCHAR(50) NULL",
            'rating' => "VARCHAR(50) NULL",
            'mal_id' => "INT NULL",
            'tmdb_id' => "INT NULL",
            'archived' => "TINYINT(1) NOT NULL DEFAULT 0"
        ];

        foreach ($moviesAdditions as $col => $def) {
            if (!isset($moviesColsMap[$col])) {
                try {
                    $pdo->exec("ALTER TABLE movies ADD COLUMN $col $def");
                } catch (Exception $e) {}
            }
        }

        // 5. Ensure homepage_sections content_type column accepts any type including 'episodes'
        try {
            $pdo->exec("ALTER TABLE homepage_sections MODIFY COLUMN content_type VARCHAR(50) NOT NULL");
        } catch (Exception $e) {}

        // 6. Ensure site_settings setting_value is TEXT (so long JSON or templates don't truncate)
        try {
            $pdo->exec("ALTER TABLE site_settings MODIFY COLUMN setting_value TEXT NOT NULL");
        } catch (Exception $e) {}

    } catch (Exception $e) {
        // Silently tolerate if DB permissions are constrained
    }
}
