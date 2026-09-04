<?php

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function current_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
    ];
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

// Site settings: cached key/value lookups from the `site_settings` table
function get_setting($key, $default = '') {
    static $cache = null;
    global $pdo;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query("SELECT setting_key, setting_value FROM site_settings") as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function setting_on($key, $default = '1') {
    return get_setting($key, $default) === '1';
}

// Column class for the card grid — changes when the homepage sidebar is toggled
// (spec: 4 cards/row with sidebar, 6 cards/row without, Bootstrap classes untouched)
function card_col_class($isHomepage = false) {
    if ($isHomepage) {
        return setting_on('sidebar_enabled') ? 'col-lg-3 col-md-3 col-6' : 'col-lg-2 col-md-4 col-6';
    }
    return 'col-lg-3 col-md-4 col-6';
}

// Renders one "product__item" media card (used on index, series, movies, search, genre pages)
function render_card($item, $type, $isHomepage = false) {
    if ($type === 'episodes' || $type === 'episode') {
        $link = 'watching.php?slug=' . urlencode($item['slug']);
        $img = h($item['poster'] ?: 'img/trending/trend-1.jpg');
        $badge = 'S' . (int)($item['season_number'] ?? 1) . ' EP ' . (int)($item['episode_number'] ?? 1);
        $cardTitle = (!empty($item['series_title']) ? $item['series_title'] . ' - ' : '') . $item['title'];
        $typeLabel = __('episodes', 'Episode');
        $displayType = 'EPISODE';
    } elseif ($type === 'series') {
        $link = 'tv-details.php?slug=' . urlencode($item['slug']);
        $img = h($item['poster'] ?: 'img/trending/trend-1.jpg');
        $badge = h(($item['episode_count'] ?? $item['episodes_count'] ?? 0) . ' EP');
        $cardTitle = $item['title'];
        $typeLabel = __('series', 'Series');
        $displayType = 'SERIES';
    } else {
        $link = 'movie-details.php?slug=' . urlencode($item['slug']);
        $img = h($item['poster'] ?: 'img/trending/trend-1.jpg');
        $badge = h(($item['runtime'] ?? 0) . ' min');
        $cardTitle = $item['title'];
        $typeLabel = __('movies', 'Movie');
        $displayType = 'MOVIE';
    }

    // Differentiate Homepage settings vs Other Pages settings
    if ($isHomepage) {
        $showGenres = setting_on('home_card_show_genres', '1');
        $showType = setting_on('home_card_show_type', '1');
        $showViews = setting_on('home_card_show_views', '1');
        $showDuration = setting_on('home_card_show_duration', '1');
        $showScore = false;
    } else {
        $showGenres = setting_on('other_card_show_genres', '1');
        $showType = setting_on('other_card_show_type', '1');
        $showViews = setting_on('other_card_show_views', '1');
        $showDuration = setting_on('other_card_show_duration', '1');
        $showScore = setting_on('other_card_show_score', '1');
    }
    ?>
    <div class="<?= card_col_class($isHomepage) ?> inp3">
      <div class="product__item">
        <a href="<?= $link ?>">
          <div class="product__item__pic set-bg" style="background-image: url('<?= $img ?>');">
            <?php if ($showDuration): ?><div class="ep"><?= $badge ?></div><?php endif; ?>
            <?php if ($showType): ?><div class="type"><?= h($displayType) ?></div><?php endif; ?>
            <?php if ($showViews): ?><div class="view"><i class="fa fa-eye"></i> <?= (int)($item['views'] ?? 0) ?></div><?php endif; ?>
          </div>
        </a>
        <div class="product__item__text">
          <ul>
            <?php if ($showGenres):
                $genreNames = !empty($item['genre_names']) ? explode(',', $item['genre_names']) : (isset($item['genre_name']) ? [$item['genre_name']] : []);
                foreach (array_slice($genreNames, 0, 2) as $gn): ?>
              <li><a href="genrs.php?slug=<?= h($item['genre_slug'] ?? '') ?>"><?= h(trim($gn)) ?></a></li>
            <?php endforeach; endif; ?>
            <?php if ($showType): ?>
              <li><a href="#"><?= h($typeLabel) ?></a></li>
            <?php endif; ?>
            <?php if ($showScore && isset($item['score'])): ?><li><i class="fa fa-star"></i> <?= h($item['score']) ?></li><?php endif; ?>
          </ul>
          <h5><a href="<?= $link ?>"><?= h($cardTitle) ?></a></h5>
        </div>
      </div>
    </div>
    <?php
}

// ---- Multi-genre helpers ----------------------------------------------

// Genre rows currently assigned to a series or movie
function get_item_genres($pdo, $type, $id) {
    $table = $type === 'series' ? 'series_genres' : 'movie_genres';
    $col = $type === 'series' ? 'series_id' : 'movie_id';
    $stmt = $pdo->prepare("SELECT g.* FROM genres g JOIN $table j ON j.genre_id = g.id WHERE j.$col = ? ORDER BY g.name");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

// Replaces all genre assignments for a series/movie with the given genre id list
function set_item_genres($pdo, $type, $id, array $genreIds) {
    $table = $type === 'series' ? 'series_genres' : 'movie_genres';
    $col = $type === 'series' ? 'series_id' : 'movie_id';
    $pdo->prepare("DELETE FROM $table WHERE $col = ?")->execute([$id]);
    $ins = $pdo->prepare("INSERT IGNORE INTO $table ($col, genre_id) VALUES (?, ?)");
    foreach ($genreIds as $gid) {
        $gid = (int)$gid;
        if ($gid > 0) $ins->execute([$id, $gid]);
    }
}

// ---- Favorites / Watchlist / Continue Watching -----------------------

function is_in_list($pdo, $userId, $seriesId, $movieId, $listType) {
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND series_id = ? AND movie_id = ? AND list_type = ?");
    $stmt->execute([$userId, $seriesId ?: 0, $movieId ?: 0, $listType]);
    return (bool)$stmt->fetch();
}

function record_watch_progress($pdo, $userId, $seriesId, $movieId, $episodeId = null) {
    $stmt = $pdo->prepare("INSERT INTO watch_history (user_id, series_id, movie_id, episode_id) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE episode_id = VALUES(episode_id), updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$userId, $seriesId ?: 0, $movieId ?: 0, $episodeId]);
}

// ---- Editable header/footer nav links ---------------------------------

// Falls back to the original hardcoded links if the nav_links table doesn't
// exist yet (e.g. migration.sql hasn't been run) so this never fatals.
function get_nav_links($pdo, $location) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM nav_links WHERE location = ? AND visible = 1 ORDER BY sort_order");
        $stmt->execute([$location]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        if ($location === 'header') {
            return [
                ['label' => 'Homepage', 'url' => 'index.php', 'visibility' => 'always'],
                ['label' => 'Episodes', 'url' => 'episodes.php', 'visibility' => 'always'],
                ['label' => 'Genres', 'url' => 'genrs.php', 'visibility' => 'always'],
                ['label' => 'Blog', 'url' => 'blog.php', 'visibility' => 'always'],
                ['label' => 'My List', 'url' => 'my-list.php', 'visibility' => 'auth_only'],
                ['label' => 'Login', 'url' => 'login.php', 'visibility' => 'guest_only'],
                ['label' => 'Sign Up', 'url' => 'signup.php', 'visibility' => 'guest_only'],
            ];
        }
        return [
            ['label' => 'Homepage', 'url' => 'index.php', 'visibility' => 'always'],
            ['label' => 'Genres', 'url' => 'genrs.php', 'visibility' => 'always'],
            ['label' => 'Our Blog', 'url' => 'blog.php', 'visibility' => 'always'],
        ];
    }
}

// Should this link show for the current visitor, given its guest_only/auth_only/always rule?
function nav_link_visible_now($link) {
    $vis = $link['visibility'] ?? 'always';
    if ($vis === 'guest_only') return !is_logged_in();
    if ($vis === 'auth_only') return is_logged_in();
    return true;
}

// Maps a nav link's url to the $active key used to highlight the current page
function nav_link_active_key($url) {
    $map = [
        'index.php' => 'home',
        'episodes.php' => 'episodes',
        'genrs.php' => 'genres',
        'blog.php' => 'blog',
        'my-list.php' => 'my-list',
        'search.php' => 'search',
    ];
    return $map[$url] ?? null;
}

function time_ago_str($datetime) {
    if (!$datetime) return 'recently';
    $time = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    if (!$time) return 'recently';
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}
