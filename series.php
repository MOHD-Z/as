<?php
require_once __DIR__ . '/includes/bootstrap.php';
$siteName = get_setting('site_name', 'AniStream');
$page_title = $siteName . ' | ' . __('series', 'Series');
$active = 'series';

$sortBy = $_GET['sort'] ?? 'newest';
$orderMap = [
    'newest' => 's.created_at DESC',
    'oldest' => 's.created_at ASC',
    'views'  => 's.views DESC',
    'score'  => 's.score DESC',
];
$orderBy = $orderMap[$sortBy] ?? 's.created_at DESC';

$series = $pdo->query("SELECT s.*,
        (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM series_genres sg JOIN genres g ON sg.genre_id=g.id WHERE sg.series_id=s.id) AS genre_names,
        (SELECT COUNT(*) FROM episodes e JOIN seasons se ON e.season_id=se.id WHERE se.series_id=s.id) AS episode_count,
        (SELECT COUNT(DISTINCT season_number) FROM seasons WHERE series_id = s.id) AS season_count
    FROM series s WHERE s.archived = 0 ORDER BY $orderBy")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
    <section class="breadcrumb-option">
      <div class="container d-flex justify-content-between align-items-center flex-wrap" style="gap:14px;">
        <h2><?= __('series', 'All Series') ?></h2>

        <!-- Sorting dropdown -->
        <div style="display:flex;align-items:center;gap:10px;">
          <label style="color:#fff;margin:0;"><?= __('sort', 'Sort') ?>:</label>
          <select onchange="window.location.href='series.php?sort='+this.value" style="background:#13151f;color:#fff;border:1px solid #333;padding:6px 12px;border-radius:6px;">
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
          <?php if (!$series): ?>
            <div class="col-12"><p class="muted"><?= __('no_results_found', 'No series in the database yet.') ?></p></div>
          <?php endif; ?>
          <?php foreach ($series as $item) render_card($item, 'series', false); ?>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
