<?php
// Generates a portable .sql dump (CREATE TABLE + INSERT statements) for a
// given list of tables, without relying on the `mysqldump` binary — some
// hosts don't allow shell_exec, so this stays pure-PHP/PDO.

function generate_sql_dump($pdo, array $tables) {
    $out = "-- AniStream backup — generated " . date('Y-m-d H:i:s') . "\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $out .= "-- ---------------------------------------------------------------\n";
        $out .= "-- Table: $table\n";
        $out .= "DROP TABLE IF EXISTS `$table`;\n";

        $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $out .= $createRow['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            $out .= "INSERT INTO `$table` ($columnList) VALUES\n";

            $valueLines = [];
            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, $row);
                $valueLines[] = '(' . implode(', ', $values) . ')';
            }
            $out .= implode(",\n", $valueLines) . ";\n\n";
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

// Tables that hold actual site content (safe to restore into another
// install without touching its settings/admin accounts)
function content_backup_tables() {
    return [
        'genres', 'series', 'seasons', 'episodes', 'movies',
        'series_genres', 'movie_genres', 'video_sources', 'video_reports',
        'blog_posts',
    ];
}

// Every table — content, settings, users, everything
function all_backup_tables($pdo) {
    $tables = [];
    foreach ($pdo->query("SHOW TABLES") as $row) {
        $tables[] = array_values($row)[0];
    }
    return $tables;
}
