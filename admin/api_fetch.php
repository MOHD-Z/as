<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/api_clients.php';

header('Content-Type: application/json');

$source = $_GET['source'] ?? ($_POST['source'] ?? '');
$id = trim($_GET['id'] ?? ($_POST['id'] ?? ''));

if (!$source || !$id) {
    echo json_encode(['ok' => false, 'error' => 'Missing source or ID']);
    exit;
}

$tmdbKey = get_setting('tmdb_api_key');
$malClient = get_setting('mal_client_id');

$meta = null;
if ($source === 'mal') {
    $res = mal_details($malClient, $id);
    if (!$res['ok']) {
        // Try searching if id was not numeric
        if (!is_numeric($id)) {
            $search = mal_search($malClient, $id);
            if ($search['ok'] && !empty($search['data']['data'][0]['node']['id'])) {
                $res = mal_details($malClient, $search['data']['data'][0]['node']['id']);
            }
        }
    }
    if (!$res['ok']) {
        echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'MAL details request failed']);
        exit;
    }
    $meta = parse_metadata_item('mal', $res['data']);
} elseif ($source === 'tmdb_tv' || $source === 'tmdb_movie') {
    if (!$tmdbKey) {
        echo json_encode(['ok' => false, 'error' => 'TMDB API key is not configured in Settings.']);
        exit;
    }
    $tmdbType = ($source === 'tmdb_tv') ? 'tv' : 'movie';
    $res = tmdb_details($tmdbKey, $tmdbType, $id);
    if (!$res['ok'] && !is_numeric($id)) {
        $search = tmdb_search($tmdbKey, $tmdbType, $id);
        if ($search['ok'] && !empty($search['data']['results'][0]['id'])) {
            $res = tmdb_details($tmdbKey, $tmdbType, $search['data']['results'][0]['id']);
        }
    }
    if (!$res['ok']) {
        echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'TMDB details request failed']);
        exit;
    }
    $meta = parse_metadata_item($source, $res['data']);
} else {
    echo json_encode(['ok' => false, 'error' => 'Unknown source: ' . $source]);
    exit;
}

// Resolve genre IDs in the database
$genreIds = match_or_create_genres($pdo, $meta['genres']);
$meta['genre_ids'] = $genreIds;

echo json_encode(['ok' => true, 'data' => $meta]);
exit;
