<?php
require_once __DIR__ . '/includes/bootstrap.php';
$siteName = get_setting('site_name', 'AniStream');
$page_title = $siteName . ' | ' . __('movies', 'Movies');
$active = 'movies';

$sortBy = $_GET['sort'] ?? 'newest';
$orderMap = [
    'newest' => 'm.created_at DESC',
    'oldest' => 'm.created_at ASC',
    'views'  => 'm.views DESC',
    'score'  => 'm.score DESC',
];
$orderBy = $orderMap[$sortBy] ?? 'm.created_at DESC';

$movies = $pdo->query("SELECT m.*,
        (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM movie_genres mg JOIN genres g ON mg.genre_id=g.id WHERE mg.movie_id=m.id) AS genre_names
    FROM movies m WHERE m.archived = 0 ORDER BY $orderBy")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
    <section class="breadcrumb-option">
      <div class="container d-flex justify-content-between align-items-center flex-wrap" style="gap:14px;">
        <h2><?= __('movies', 'All Movies') ?></h2>
        
        <!-- Sorting dropdown -->
        <div style="display:flex;align-items:center;gap:10px;">
          <label style="color:#fff;margin:0;"><?= __('sort', 'Sort') ?>:</label>
          <select onchange="window.location.href='movies.php?sort='+this.value" style="background:#13151f;color:#fff;border:1px solid #333;padding:6px 12px;border-radius:6px;">
            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>><?= __('newest', 'Newest') ?></option>
            <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>><?= __('oldest', 'Oldest') ?></option>
            <option value="views" <?= $sortBy === 'views' ? 'selected' : '' ?>><?= __('most_viewed', 'Most Viewed') ?></option>
          </select>
        </div>
      </div>
    </section>

    <section class="product spad">
      <div class="container-fluid">
        <div class="row">
          <?php if (!$movies): ?>
            <div class="col-12"><p class="muted"><?= __('no_results_found', 'No movies in the database yet.') ?></p></div>
          <?php endif; ?>
          <?php foreach ($movies as $item) render_card($item, 'movie', false); ?>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
