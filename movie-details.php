<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT m.* FROM movies m WHERE m.slug = ? AND m.archived = 0");
$stmt->execute([$slug]);
$movie = $stmt->fetch();

if (!$movie) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pdo->prepare("UPDATE movies SET views = views + 1 WHERE id = ?")->execute([$movie['id']]);

$isFav = is_logged_in() && is_in_list($pdo, current_user()['id'], 0, $movie['id'], 'favorite');
$isWatchlist = is_logged_in() && is_in_list($pdo, current_user()['id'], 0, $movie['id'], 'watchlist');
$movieGenres = get_item_genres($pdo, 'movie', $movie['id']);

$siteName = get_setting('site_name', 'AniStream');
$page_title = $siteName . ' | ' . $movie['title'];
$page_description = mb_strimwidth(strip_tags($movie['synopsis'] ?? ''), 0, 160, '...');
$page_image = $movie['poster'];
include __DIR__ . '/includes/header.php';
?>
    <section class="media-details spad">
      <div class="container">
        <div class="media__details__content">
          <div class="row">
            <div class="col-lg-3 col-md-4">
              <div class="media__details__pic set-bg" style="background-image:url('<?= h($movie['poster']) ?>');border-radius:8px;position:relative;">
                <div class="view"><i class="fa fa-eye"></i> <?= (int)$movie['views'] ?></div>
                <?php if (!empty($movie['quality'])): ?>
                  <div class="ep" style="position:absolute;bottom:10px;left:10px;"><?= h($movie['quality']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-lg-9 col-md-8">
              <div class="media__details__text">
                <div class="media__details__title">
                  <h3 style="margin-bottom:4px;"><?= h($movie['title']) ?></h3>
                  <?php if (!empty($movie['original_title'])): ?>
                    <span style="color:#aaa;font-size:14px;display:block;margin-bottom:12px;"><?= h($movie['original_title']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="media__details__rating" style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
                  <span style="background:#e53637;color:#fff;padding:2px 8px;border-radius:4px;font-size:14px;font-weight:bold;">
                    ★ <?= h($movie['score']) ?> / 10
                  </span>
                  <?php if (!empty($movie['user_score_percent'])): ?>
                    <span class="badge" style="background:#2a4365;color:#90cdf4;font-size:13px;">
                      <?= (int)$movie['user_score_percent'] ?>% User Score
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($movie['vote_count'])): ?>
                    <small class="muted">(<?= number_format($movie['vote_count']) ?> votes)</small>
                  <?php endif; ?>
                </div>
                <p><?= nl2br(h($movie['synopsis'])) ?></p>
                <div class="media__details__widget">
                  <div class="row">
                    <div class="col-lg-6 col-md-6">
                      <ul>
                        <li><span>Type:</span> <?= __('movies', 'Movie') ?></li>
                        <li><span>Runtime:</span> <?= (int)$movie['runtime'] ?> min</li>
                        <li><span>Genre:</span> <?= h($movieGenres ? implode(', ', array_column($movieGenres, 'name')) : 'General') ?></li>
                        <?php if (!empty($movie['quality'])): ?>
                          <li><span>Quality:</span> <?= h($movie['quality']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($movie['rating'])): ?>
                          <li><span>Rating:</span> <?= h($movie['rating']) ?></li>
                        <?php endif; ?>
                      </ul>
                    </div>
                    <div class="col-lg-6 col-md-6">
                      <ul>
                        <?php if (!empty($movie['release_date'])): ?>
                          <li><span>Release Date:</span> <?= h($movie['release_date']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($movie['original_language'])): ?>
                          <li><span>Language:</span> <?= strtoupper(h($movie['original_language'])) ?></li>
                        <?php endif; ?>
                        <li><span>Score:</span> <?= h($movie['score']) ?></li>
                        <li><span>Views:</span> <?= (int)$movie['views'] ?></li>
                      </ul>
                    </div>
                  </div>
                </div>
                <div class="media__details__btn" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                  <a href="movie-watching.php?slug=<?= h($movie['slug']) ?>" class="watch-btn">
                    <span><?= __('watch_now', 'Watch Now') ?></span> <i class="fa fa-angle-right"></i>
                  </a>
                  <?php if (is_logged_in()): ?>
                    <?php $backUrl = 'movie-details.php?slug=' . urlencode($movie['slug']); ?>
                    <a href="list-action.php?do=<?= $isFav ? 'remove' : 'add' ?>&movie_id=<?= (int)$movie['id'] ?>&list_type=favorite&back=<?= urlencode($backUrl) ?>" class="primary-btn">
                      <?= $isFav ? '♥ Remove Favorite' : '♡ ' . __('favorites', 'Add to Favorites') ?>
                    </a>
                    <a href="list-action.php?do=<?= $isWatchlist ? 'remove' : 'add' ?>&movie_id=<?= (int)$movie['id'] ?>&list_type=watchlist&back=<?= urlencode($backUrl) ?>" class="primary-btn">
                      <?= $isWatchlist ? '✓ In Watchlist' : '+ ' . __('watchlist', 'Add to Watchlist') ?>
                    </a>
                  <?php else: ?>
                    <a href="login.php" class="primary-btn"><?= __('login', 'Login') ?> to save</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
