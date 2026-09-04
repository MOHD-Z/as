<?php
require_once __DIR__ . '/includes/bootstrap.php';
$active = 'genres';

$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();

$selected = null;
$results = [];
if (!empty($_GET['slug'])) {
    $stmt = $pdo->prepare("SELECT * FROM genres WHERE slug = ?");
    $stmt->execute([$_GET['slug']]);
    $selected = $stmt->fetch();

    if ($selected) {
        $s = $pdo->prepare("SELECT s.*,
                (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM series_genres sg2 JOIN genres g ON sg2.genre_id=g.id WHERE sg2.series_id=s.id) AS genre_names,
                (SELECT COUNT(*) FROM episodes e JOIN seasons se ON e.season_id=se.id WHERE se.series_id=s.id) AS episode_count
            FROM series s JOIN series_genres sg ON sg.series_id = s.id WHERE sg.genre_id = ? AND s.archived = 0");
        $s->execute([$selected['id']]);
        $results = array_map(fn($r) => $r + ['_type' => 'series'], $s->fetchAll());

        $m = $pdo->prepare("SELECT m.*,
                (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM movie_genres mg2 JOIN genres g ON mg2.genre_id=g.id WHERE mg2.movie_id=m.id) AS genre_names
            FROM movies m JOIN movie_genres mg ON mg.movie_id = m.id WHERE mg.genre_id = ? AND m.archived = 0");
        $m->execute([$selected['id']]);
        $results = array_merge($results, array_map(fn($r) => $r + ['_type' => 'movie'], $m->fetchAll()));
    }
}

$siteName = get_setting('site_name', 'AniStream');
$page_title = $selected ? ($siteName . ' | ' . $selected['name']) : ($siteName . ' | ' . __('genres', 'Genres'));
include __DIR__ . '/includes/header.php';
?>
    <section class="breadcrumb-option">
      <div class="container"><h2><?= $selected ? h($selected['name']) : __('genres', 'All Genres') ?></h2></div>
    </section>

    <?php if (!$selected): ?>
    <section class="product spad">
      <div class="container">
        <div class="row">
          <?php foreach ($genres as $g): ?>
            <div class="col-lg-2 col-md-4 col-6 mb-4">
              <a href="genrs.php?slug=<?= h($g['slug']) ?>" class="primary-btn" style="display:block;text-align:center;">
                <?= h($g['name']) ?>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php else: ?>
    <section class="product spad">
      <div class="container-fluid">
        <div class="row">
          <?php if (!$results): ?>
            <div class="col-12"><p class="muted"><?= __('no_results_found', 'Nothing tagged with this genre yet.') ?></p></div>
          <?php endif; ?>
          <?php foreach ($results as $item): render_card($item, $item['_type'], false); endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
