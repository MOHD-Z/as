<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'AniStream | Episodes';
$active = 'episodes';

$sort = $_GET['sort'] ?? 'newest';
$order = match ($sort) {
    'oldest' => 'e.created_at ASC',
    'most_viewed' => 'e.views DESC',
    default => 'e.created_at DESC',
};

$episodes = $pdo->query("SELECT e.*, se.season_number, s.title AS series_title, s.slug AS series_slug, s.poster,
        (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM series_genres sg2 JOIN genres g ON sg2.genre_id=g.id WHERE sg2.series_id=s.id) AS genre_names
    FROM episodes e
    JOIN seasons se ON e.season_id = se.id
    JOIN series s ON se.series_id = s.id
    WHERE e.archived = 0 AND s.archived = 0
    ORDER BY $order")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
    <section class="breadcrumb-option">
      <div class="container"><h2>All Episodes</h2></div>
    </section>
    <section class="product spad">
      <div class="container-fluid">
        <div class="row mb-4">
          <div class="col-12">
            <form method="get" class="d-flex" style="gap:10px;align-items:center;">
              <label for="sort" style="margin:0;">Sort:</label>
              <select name="sort" id="sort" onchange="this.form.submit()" class="form-control" style="max-width:220px;">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="most_viewed" <?= $sort === 'most_viewed' ? 'selected' : '' ?>>Most Viewed</option>
              </select>
            </form>
          </div>
        </div>
        <div class="row">
          <?php foreach ($episodes as $ep): ?>
            <div class="col-lg-2 col-md-4 col-6 inp3">
              <div class="product__item">
                <a href="watching.php?slug=<?= h($ep['slug']) ?>">
                  <div class="product__item__pic set-bg" style="background-image: url('<?= h($ep['poster']) ?>');">
                    <div class="ep">EP <?= (int)$ep['episode_number'] ?></div>
                    <div class="type">S<?= (int)$ep['season_number'] ?></div>
                    <div class="view"><i class="fa fa-eye"></i> <?= (int)$ep['views'] ?></div>
                  </div>
                </a>
                <div class="product__item__text">
                  <ul><li><?= h($ep['genre_names'] ?? 'General') ?></li></ul>
                  <h5><a href="watching.php?slug=<?= h($ep['slug']) ?>"><?= h($ep['series_title']) ?> — <?= h($ep['title']) ?></a></h5>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
