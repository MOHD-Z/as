<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';

// Lightweight visitor tracking (public pages only, not the admin panel):
// one row per session per calendar day, so counts approximate visitors
// rather than raw page hits. Silently does nothing if the `visits` table
// isn't there yet (e.g. migration.sql hasn't been run) or on admin pages.
$__scriptDir = isset($_SERVER['SCRIPT_FILENAME']) ? realpath(dirname($_SERVER['SCRIPT_FILENAME'])) : '';
if ($__scriptDir && basename($__scriptDir) !== 'admin') {
    $today = date('Y-m-d');
    if (($_SESSION['tracked_visit_date'] ?? '') !== $today) {
        try {
            $pdo->prepare("INSERT INTO visits (visited_on) VALUES (?)")->execute([$today]);
        } catch (PDOException $e) {
            // visits table not migrated yet — ignore, dashboard will just show no data
        }
        $_SESSION['tracked_visit_date'] = $today;
    }
}
