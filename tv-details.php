<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT s.* FROM series s WHERE s.slug = ? AND s.archived = 0");
$stmt->execute([$slug]);
$series = $stmt->fetch();

if (!$series) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pdo->prepare("UPDATE series SET views = views + 1 WHERE id = ?")->execute([$series['id']]);

$isFav = is_logged_in() && is_in_list($pdo, current_user()['id'], $series['id'], 0, 'favorite');
$isWatchlist = is_logged_in() && is_in_list($pdo, current_user()['id'], $series['id'], 0, 'watchlist');
$seriesGenres = get_item_genres($pdo, 'series', $series['id']);

$seasons = $pdo->prepare("SELECT * FROM seasons WHERE series_id = ? ORDER BY season_number");
$seasons->execute([$series['id']]);
$seasons = $seasons->fetchAll();

foreach ($seasons as &$season) {
    $eps = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
    $eps->execute([$season['id']]);
    $season['episodes'] = $eps->fetchAll();
}
unset($season);

$siteName = get_setting('site_name', 'AniStream');
$page_title = $siteName . ' | ' . $series['title'];
$page_description = mb_strimwidth(strip_tags($series['synopsis'] ?? ''), 0, 160, '...');
$page_image = $series['poster'];
include __DIR__ . '/includes/header.php';
?>
    <section class="media-details spad">
      <div class="container">
        <div class="media__details__content">
          <div class="row">
            <div class="col-lg-3 col-md-4">
              <div class="media__details__pic set-bg" style="background-image:url('<?= h($series['poster']) ?>');border-radius:8px;position:relative;">
                <div class="view"><i class="fa fa-eye"></i> <?= (int)$series['views'] ?></div>
                <?php if (!empty($series['quality'])): ?>
                  <div class="ep" style="position:absolute;bottom:10px;left:10px;"><?= h($series['quality']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-lg-9 col-md-8">
              <div class="media__details__text">
                <div class="media__details__title">
                  <h3 style="margin-bottom:4px;"><?= h($series['title']) ?></h3>
                  <?php if (!empty($series['original_title'])): ?>
                    <span style="color:#aaa;font-size:14px;display:block;margin-bottom:12px;"><?= h($series['original_title']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="media__details__rating" style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
                  <span style="background:#e53637;color:#fff;padding:2px 8px;border-radius:4px;font-size:14px;font-weight:bold;">
                    ★ <?= h($series['score']) ?> / 10
                  </span>
                  <?php if (!empty($series['vote_count'])): ?>
                    <small class="muted">(Scored by <?= number_format($series['vote_count']) ?> users)</small>
                  <?php endif; ?>
                </div>
                <p><?= nl2br(h($series['synopsis'])) ?></p>
                <div class="media__details__widget">
                  <div class="row">
                    <div class="col-lg-6 col-md-6">
                      <ul>
                        <li><span>Type:</span> TV <?= __('series', 'Series') ?></li>
                        <li><span>Status:</span> <?= h($series['status']) ?></li>
                        <li><span>Genre:</span> <?= h($seriesGenres ? implode(', ', array_column($seriesGenres, 'name')) : 'General') ?></li>
                        <li><span>Seasons:</span> <?= count($seasons) ?></li>
                        <?php if (!empty($series['episodes_count'])): ?>
                          <li><span>Episodes:</span> <?= (int)$series['episodes_count'] ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['duration'])): ?>
                          <li><span>Duration:</span> <?= h($series['duration']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['quality'])): ?>
                          <li><span>Quality:</span> <?= h($series['quality']) ?></li>
                        <?php endif; ?>
                      </ul>
                    </div>
                    <div class="col-lg-6 col-md-6">
                      <ul>
                        <?php if (!empty($series['aired'])): ?>
                          <li><span>Aired:</span> <?= h($series['aired']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['premiered'])): ?>
                          <li><span>Premiered:</span> <?= h($series['premiered']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['broadcast'])): ?>
                          <li><span>Broadcast:</span> <?= h($series['broadcast']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['source'])): ?>
                          <li><span>Source:</span> <?= h($series['source']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['studio'])): ?>
                          <li><span>Studio:</span> <?= h($series['studio']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['country'])): ?>
                          <li><span>Country:</span> <?= h($series['country']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($series['rating'])): ?>
                          <li><span>Rating:</span> <?= h($series['rating']) ?></li>
                        <?php endif; ?>
                        <li><span>Views:</span> <?= (int)$series['views'] ?></li>
                      </ul>
                    </div>
                  </div>
                </div>
                <div class="media__details__btn" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                  <?php if ($seasons && !empty($seasons[0]['episodes'])): ?>
                  <a href="watching.php?slug=<?= h($seasons[0]['episodes'][0]['slug']) ?>" class="watch-btn">
                    <span><?= __('watch_now', 'Watch Now') ?></span> <i class="fa fa-angle-right"></i>
                  </a>
                  <?php endif; ?>
                  <?php if (is_logged_in()): ?>
                    <?php $backUrl = 'tv-details.php?slug=' . urlencode($series['slug']); ?>
                    <a href="list-action.php?do=<?= $isFav ? 'remove' : 'add' ?>&series_id=<?= (int)$series['id'] ?>&list_type=favorite&back=<?= urlencode($backUrl) ?>" class="primary-btn">
                      <?= $isFav ? '♥ Remove Favorite' : '♡ ' . __('favorites', 'Add to Favorites') ?>
                    </a>
                    <a href="list-action.php?do=<?= $isWatchlist ? 'remove' : 'add' ?>&series_id=<?= (int)$series['id'] ?>&list_type=watchlist&back=<?= urlencode($backUrl) ?>" class="primary-btn">
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

        <div class="row mt-5">
          <div class="col-12">
            <div class="section-title"><h4><?= __('seasons_and_episodes', 'Seasons & Episodes') ?></h4></div>
            <?php foreach ($seasons as $season): ?>
              <h5 class="mt-3" style="color:#fff;">Season <?= (int)$season['season_number'] ?></h5>
              <div class="row">
                <?php foreach ($season['episodes'] as $ep): ?>
                  <div class="col-lg-2 col-md-3 col-4 mb-3">
                    <a href="watching.php?slug=<?= h($ep['slug']) ?>" class="primary-btn" style="display:block;text-align:center;">
                      <?= __('episodes', 'EP') ?> <?= (int)$ep['episode_number'] ?>
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <?php if (!$seasons): ?><p class="muted"><?= __('no_episodes_yet', 'No episodes added yet.') ?></p><?php endif; ?>
          </div>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
