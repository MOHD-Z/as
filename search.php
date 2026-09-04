<?php
require_once __DIR__ . '/includes/bootstrap.php';
$siteName = get_setting('site_name', 'AniStream');
$page_title = $siteName . ' | Search';
$active = 'search';

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $s = $pdo->prepare("SELECT s.*,
            (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM series_genres sg JOIN genres g ON sg.genre_id=g.id WHERE sg.series_id=s.id) AS genre_names,
            (SELECT COUNT(*) FROM episodes e JOIN seasons se ON e.season_id=se.id WHERE se.series_id=s.id) AS episode_count
        FROM series s WHERE s.title LIKE ? AND s.archived = 0");
    $s->execute([$like]);
    $results = array_map(fn($r) => $r + ['_type' => 'series'], $s->fetchAll());

    $m = $pdo->prepare("SELECT m.*,
            (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM movie_genres mg JOIN genres g ON mg.genre_id=g.id WHERE mg.movie_id=m.id) AS genre_names
        FROM movies m WHERE m.title LIKE ? AND m.archived = 0");
    $m->execute([$like]);
    $results = array_merge($results, array_map(fn($r) => $r + ['_type' => 'movie'], $m->fetchAll()));

    $epStmt = $pdo->prepare("SELECT e.*, se.season_number, s.title AS series_title, s.poster
        FROM episodes e JOIN seasons se ON e.season_id = se.id JOIN series s ON se.series_id = s.id
        WHERE (e.title LIKE ? OR s.title LIKE ?) AND e.archived = 0 AND s.archived = 0");
    $epStmt->execute([$like, $like]);
    $episodeResults = $epStmt->fetchAll();
} else {
    $episodeResults = [];
}

include __DIR__ . '/includes/header.php';
?>
    <section class="breadcrumb-option">
      <div class="container"><h2><?= $q !== '' ? __('no_results_found', 'Search results for') . ' "' . h($q) . '"' : 'Search' ?></h2></div>
    </section>
    <section class="product spad">
      <div class="container-fluid">
        <div class="row mb-4">
          <div class="col-12">
            <form method="get" class="d-flex" style="gap:10px;">
              <input type="text" name="q" value="<?= h($q) ?>" placeholder="<?= h(__('search_placeholder', 'Search anime or movie...')) ?>" class="form-control" style="max-width:400px;">
              <button type="submit" class="primary-btn">Search</button>
            </form>
          </div>
        </div>
        <div class="row">
          <?php if ($q === ''): ?>
            <div class="col-12"><p class="muted">Type something above to search series, movies and episodes.</p></div>
          <?php elseif (!$results && !$episodeResults): ?>
            <div class="col-12"><p class="muted"><?= __('no_results_found', 'No results found for') ?> "<?= h($q) ?>".</p></div>
          <?php endif; ?>
          <?php foreach ($results as $item): render_card($item, $item['_type'], false); endforeach; ?>
        </div>

        <?php if ($episodeResults): ?>
          <div class="section-title mt-5"><h4><?= __('episodes', 'Episodes') ?></h4></div>
          <div class="row">
            <?php foreach ($episodeResults as $ep): ?>
              <div class="col-lg-2 col-md-4 col-6 inp3">
                <div class="product__item">
                  <a href="watching.php?slug=<?= h($ep['slug']) ?>">
                    <div class="product__item__pic set-bg" style="background-image: url('<?= h($ep['poster']) ?>');">
                      <div class="ep">S<?= (int)$ep['season_number'] ?>E<?= (int)$ep['episode_number'] ?></div>
                    </div>
                  </a>
                  <div class="product__item__text">
                    <h5><a href="watching.php?slug=<?= h($ep['slug']) ?>"><?= h($ep['series_title']) ?> — <?= h($ep['title']) ?></a></h5>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
