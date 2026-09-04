<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$userId = current_user()['id'];
$seriesId = (int)($_GET['series_id'] ?? 0);
$movieId = (int)($_GET['movie_id'] ?? 0);
$listType = in_array($_GET['list_type'] ?? '', ['favorite', 'watchlist'], true) ? $_GET['list_type'] : 'favorite';
$back = $_GET['back'] ?? 'index.php';

if (($_GET['do'] ?? '') === 'add') {
    if (!is_in_list($pdo, $userId, $seriesId, $movieId, $listType)) {
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, series_id, movie_id, list_type) VALUES (?,?,?,?)");
        $stmt->execute([$userId, $seriesId, $movieId, $listType]);
    }
} elseif (($_GET['do'] ?? '') === 'remove') {
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND series_id = ? AND movie_id = ? AND list_type = ?");
    $stmt->execute([$userId, $seriesId, $movieId, $listType]);
}

redirect($back);
