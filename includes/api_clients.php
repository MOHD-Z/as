<?php
// API clients for TMDB and MyAnimeList (MAL) with extended metadata support.
// Both use cURL directly without external libraries.

function api_curl_get($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, 'AniStream-Admin/1.0 (Mozilla/5.0)');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'error' => $error];
    }
    $data = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'HTTP ' . $httpCode . ': ' . ($data['status_message'] ?? $data['message'] ?? 'Request failed')];
    }
    return ['ok' => true, 'data' => $data];
}

// ---- TMDB ---------------------------------------------------------
// $type is 'movie' or 'tv'
function tmdb_search($apiKey, $type, $query) {
    $url = 'https://api.themoviedb.org/3/search/' . $type . '?api_key=' . urlencode($apiKey) . '&query=' . urlencode($query);
    return api_curl_get($url);
}

function tmdb_details($apiKey, $type, $id) {
    $url = 'https://api.themoviedb.org/3/' . $type . '/' . urlencode($id) . '?api_key=' . urlencode($apiKey);
    return api_curl_get($url);
}

function tmdb_poster_url($posterPath) {
    return $posterPath ? 'https://image.tmdb.org/t/p/w500' . $posterPath : '';
}

// ---- MyAnimeList (official API v2 & Jikan public fallback) ----------
function mal_search($clientId, $query) {
    if (!empty($clientId)) {
        $url = 'https://api.myanimelist.net/v2/anime?q=' . urlencode($query)
            . '&limit=12&fields=id,title,main_picture,alternative_titles,synopsis,mean,num_episodes,status,genres,media_type';
        $res = api_curl_get($url, ['X-MAL-CLIENT-ID: ' . $clientId]);
        if ($res['ok']) return $res;
    }
    // Fallback to Jikan public free API (no key needed)
    $url = 'https://api.jikan.moe/v4/anime?q=' . urlencode($query) . '&limit=12';
    $res = api_curl_get($url);
    if ($res['ok'] && isset($res['data']['data'])) {
        $items = [];
        foreach ($res['data']['data'] as $item) {
            $items[] = [
                'node' => [
                    'id' => $item['mal_id'],
                    'title' => $item['title'],
                    'main_picture' => [
                        'large' => $item['images']['jpg']['large_image_url'] ?? '',
                        'medium' => $item['images']['jpg']['image_url'] ?? ''
                    ],
                    'synopsis' => $item['synopsis'] ?? '',
                    'mean' => $item['score'] ?? 0,
                    'num_episodes' => $item['episodes'] ?? 0,
                    'status' => strtolower(str_replace(' ', '_', $item['status'] ?? '')),
                    'media_type' => strtolower($item['type'] ?? 'tv'),
                    'genres' => $item['genres'] ?? []
                ]
            ];
        }
        return ['ok' => true, 'data' => ['data' => $items]];
    }
    return $res;
}

function mal_details($clientId, $id) {
    if (!empty($clientId)) {
        $url = 'https://api.myanimelist.net/v2/anime/' . urlencode($id)
            . '?fields=id,title,main_picture,alternative_titles,start_date,end_date,synopsis,mean,rank,popularity,num_list_users,num_scoring_users,nsfw,created_at,updated_at,media_type,status,genres,my_list_status,num_episodes,start_season,broadcast,source,average_episode_duration,rating,studios';
        $res = api_curl_get($url, ['X-MAL-CLIENT-ID: ' . $clientId]);
        if ($res['ok']) return $res;
    }
    // Fallback to Jikan public API v4
    $url = 'https://api.jikan.moe/v4/anime/' . urlencode($id) . '/full';
    $res = api_curl_get($url);
    if ($res['ok'] && isset($res['data']['data'])) {
        $d = $res['data']['data'];
        $genres = [];
        foreach (array_merge($d['genres'] ?? [], $d['themes'] ?? [], $d['demographics'] ?? []) as $g) {
            $genres[] = ['id' => $g['mal_id'] ?? 0, 'name' => $g['name'] ?? ''];
        }
        $normalized = [
            'id' => $d['mal_id'],
            'title' => $d['title'] ?? '',
            'alternative_titles' => [
                'ja' => $d['title_japanese'] ?? '',
                'en' => $d['title_english'] ?? '',
                'synonyms' => $d['title_synonyms'] ?? []
            ],
            'main_picture' => [
                'large' => $d['images']['jpg']['large_image_url'] ?? '',
                'medium' => $d['images']['jpg']['image_url'] ?? ''
            ],
            'synopsis' => $d['synopsis'] ?? '',
            'mean' => $d['score'] ?? 0,
            'rank' => $d['rank'] ?? null,
            'popularity' => $d['popularity'] ?? null,
            'num_list_users' => $d['members'] ?? 0,
            'num_scoring_users' => $d['scored_by'] ?? 0,
            'media_type' => strtolower($d['type'] ?? 'tv'),
            'status' => $d['status'] ?? 'Currently Airing',
            'genres' => $genres,
            'num_episodes' => $d['episodes'] ?? 0,
            'start_season' => [
                'year' => $d['year'] ?? null,
                'season' => $d['season'] ?? null
            ],
            'broadcast' => [
                'day_of_the_week' => $d['broadcast']['day'] ?? null,
                'start_time' => $d['broadcast']['time'] ?? null,
                'string' => $d['broadcast']['string'] ?? ''
            ],
            'source' => $d['source'] ?? '',
            'average_episode_duration' => $d['duration'] ?? '',
            'rating' => $d['rating'] ?? '',
            'studios' => $d['studios'] ?? [],
            'aired' => $d['aired']['string'] ?? ''
        ];
        return ['ok' => true, 'data' => $normalized];
    }
    return $res;
}

// Normalized parser for Series & Movies
function parse_metadata_item($source, $rawData) {
    $out = [
        'title' => '',
        'original_title' => '',
        'synopsis' => '',
        'poster' => '',
        'score' => 0.0,
        'vote_count' => 0,
        'status' => 'Ongoing',
        'episodes_count' => 0,
        'aired' => '',
        'premiered' => '',
        'broadcast' => '',
        'source' => '',
        'duration' => '',
        'rating' => '',
        'quality' => 'HD',
        'studio' => '',
        'country' => 'Japan',
        'runtime' => 90,
        'release_date' => '',
        'original_language' => '',
        'user_score_percent' => 0,
        'mal_id' => null,
        'tmdb_id' => null,
        'genres' => []
    ];

    if ($source === 'mal') {
        $out['mal_id'] = (int)($rawData['id'] ?? 0);
        $out['title'] = $rawData['title'] ?? '';
        $altJa = $rawData['alternative_titles']['ja'] ?? '';
        $altEn = $rawData['alternative_titles']['en'] ?? '';
        $out['original_title'] = $altJa ?: $altEn;
        $out['synopsis'] = $rawData['synopsis'] ?? '';
        $out['poster'] = $rawData['main_picture']['large'] ?? ($rawData['main_picture']['medium'] ?? '');
        $out['score'] = round((float)($rawData['mean'] ?? 0), 1);
        $out['vote_count'] = (int)($rawData['num_scoring_users'] ?? 0);
        $rawStatus = strtolower($rawData['status'] ?? '');
        if (strpos($rawStatus, 'finish') !== false || strpos($rawStatus, 'completed') !== false) {
            $out['status'] = 'Completed';
        } elseif (strpos($rawStatus, 'not_yet') !== false || strpos($rawStatus, 'upcoming') !== false) {
            $out['status'] = 'Upcoming';
        } else {
            $out['status'] = 'Ongoing';
        }
        $out['episodes_count'] = (int)($rawData['num_episodes'] ?? 0);
        $out['aired'] = $rawData['aired'] ?? (($rawData['start_date'] ?? '') . (!empty($rawData['end_date']) ? ' to ' . $rawData['end_date'] : ''));
        if (!empty($rawData['start_season']['season'])) {
            $out['premiered'] = ucfirst($rawData['start_season']['season']) . ' ' . ($rawData['start_season']['year'] ?? '');
        }
        $out['broadcast'] = $rawData['broadcast']['string'] ?? '';
        $out['source'] = $rawData['source'] ?? '';
        $out['duration'] = is_numeric($rawData['average_episode_duration'] ?? null) 
            ? round($rawData['average_episode_duration'] / 60) . ' min. per ep.'
            : ($rawData['average_episode_duration'] ?? '24 min. per ep.');
        $out['rating'] = $rawData['rating'] ?? '';
        if (!empty($rawData['studios'][0]['name'])) {
            $out['studio'] = $rawData['studios'][0]['name'];
        }
        if (!empty($rawData['genres'])) {
            foreach ($rawData['genres'] as $g) {
                if (!empty($g['name'])) $out['genres'][] = $g['name'];
            }
        }
    } elseif ($source === 'tmdb_tv' || $source === 'tmdb_movie') {
        $out['tmdb_id'] = (int)($rawData['id'] ?? 0);
        $out['title'] = $rawData['title'] ?? ($rawData['name'] ?? '');
        $out['original_title'] = $rawData['original_title'] ?? ($rawData['original_name'] ?? '');
        $out['synopsis'] = $rawData['overview'] ?? '';
        $out['poster'] = tmdb_poster_url($rawData['poster_path'] ?? null);
        $voteAvg = (float)($rawData['vote_average'] ?? 0);
        $out['score'] = round($voteAvg, 1);
        $out['vote_count'] = (int)($rawData['vote_count'] ?? 0);
        $out['user_score_percent'] = (int)round($voteAvg * 10);
        $out['release_date'] = $rawData['release_date'] ?? ($rawData['first_air_date'] ?? '');
        $out['original_language'] = $rawData['original_language'] ?? '';
        
        $status = $rawData['status'] ?? '';
        if ($status === 'Ended' || $status === 'Released') {
            $out['status'] = 'Completed';
        } else {
            $out['status'] = 'Ongoing';
        }

        $runtime = (int)($rawData['runtime'] ?? ($rawData['episode_run_time'][0] ?? 90));
        $out['runtime'] = $runtime > 0 ? $runtime : 90;
        $out['duration'] = $out['runtime'] . ' min.';
        $out['episodes_count'] = (int)($rawData['number_of_episodes'] ?? 0);

        if (!empty($rawData['genres'])) {
            foreach ($rawData['genres'] as $g) {
                if (!empty($g['name'])) $out['genres'][] = $g['name'];
            }
        }
    }

    return $out;
}

// Matches or automatically creates genres in the database, returns array of genre IDs
function match_or_create_genres($pdo, array $genreNames) {
    if (empty($genreNames)) return [];
    
    $existing = [];
    foreach ($pdo->query("SELECT id, LOWER(name) AS lname, name FROM genres") as $row) {
        $existing[$row['lname']] = (int)$row['id'];
    }

    $genreIds = [];
    $ins = $pdo->prepare("INSERT INTO genres (name, slug) VALUES (?, ?)");

    foreach ($genreNames as $rawName) {
        $name = trim($rawName);
        if ($name === '') continue;
        $lname = strtolower($name);

        if (isset($existing[$lname])) {
            $genreIds[] = $existing[$lname];
        } else {
            // Auto-create genre
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
            $slug = trim($slug, '-');
            try {
                $ins->execute([$name, $slug]);
                $newId = (int)$pdo->lastInsertId();
                $existing[$lname] = $newId;
                $genreIds[] = $newId;
            } catch (Exception $e) {
                // Ignore if duplicate slug
            }
        }
    }

    return array_unique($genreIds);
}
