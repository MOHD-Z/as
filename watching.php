<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT e.*, se.series_id, se.season_number, s.title AS series_title, s.slug AS series_slug
    FROM episodes e JOIN seasons se ON e.season_id = se.id JOIN series s ON se.series_id = s.id
    WHERE e.slug = ? AND e.archived = 0 AND s.archived = 0");
$stmt->execute([$slug]);
$episode = $stmt->fetch();

if (!$episode) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pdo->prepare("UPDATE episodes SET views = views + 1 WHERE id = ?")->execute([$episode['id']]);

if (is_logged_in()) {
    record_watch_progress($pdo, current_user()['id'], $episode['series_id'], 0, $episode['id']);
}

$sources = $pdo->prepare("SELECT * FROM video_sources WHERE episode_id = ? AND enabled = 1 ORDER BY priority");
$sources->execute([$episode['id']]);
$sources = $sources->fetchAll();

// all episodes of this series, for the sidebar + prev/next
$allEps = $pdo->prepare("SELECT e.*, se.season_number FROM episodes e JOIN seasons se ON e.season_id = se.id
    WHERE se.series_id = ? ORDER BY se.season_number, e.episode_number");
$allEps->execute([$episode['series_id']]);
$allEps = $allEps->fetchAll();

$currentIndex = null;
foreach ($allEps as $i => $e) {
    if ($e['id'] == $episode['id']) { $currentIndex = $i; break; }
}
$prevEp = $currentIndex !== null && $currentIndex > 0 ? $allEps[$currentIndex - 1] : null;
$nextEp = $currentIndex !== null && $currentIndex < count($allEps) - 1 ? $allEps[$currentIndex + 1] : null;

$page_title = 'AniStream | ' . $episode['series_title'] . ' - ' . $episode['title'];
include __DIR__ . '/includes/header.php';
?>
    <section class="media-details spad">
      <div class="container-fluid px-4">
        <div class="row">
          <div class="col-lg-9">
            <div class="media__video__player">
              <video id="player" playsinline controls data-poster="videos/anime-watch.jpg">
                <?php foreach ($sources as $i => $src): ?>
                  <source src="<?= h($src['url']) ?>" type="video/mp4" data-source-id="<?= (int)$src['id']; ?>" <?= $i === 0 ? '' : 'style="display:none"' ?> />
                <?php endforeach; ?>
                <?php if (!$sources): ?>
                  <source src="videos/1.mp4" type="video/mp4" />
                <?php endif; ?>
              </video>
            </div>

            <div class="server-group">
              <h4>SERVERS</h4>
              <div class="server-buttons">
                <?php foreach ($sources as $i => $src): ?>
                  <button type="button" class="server-btn<?= $i === 0 ? ' active' : '' ?>"
                          data-url="<?= h($src['url']) ?>" data-id="<?= (int)$src['id'] ?>">
                    <?= h($src['name']) ?> (<?= h($src['quality']) ?>)
                  </button>
                <?php endforeach; ?>
                <?php if (!$sources): ?><p>No video sources configured for this episode yet.</p><?php endif; ?>
              </div>
            </div>

            <div class="episode-nav">
              <?php if ($prevEp): ?>
                <a href="watching.php?slug=<?= h($prevEp['slug']) ?>" class="btn-nav"><i class="fa fa-arrow-left"></i> Prev</a>
              <?php endif; ?>
              <span class="current-ep">S<?= (int)$episode['season_number'] ?>E<?= (int)$episode['episode_number'] ?> — <?= h($episode['title']) ?></span>
              <?php if ($nextEp): ?>
                <a href="watching.php?slug=<?= h($nextEp['slug']) ?>" class="btn-nav">Next <i class="fa fa-arrow-right"></i></a>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-lg-3">
            <div class="episodes-sidebar">
              <div class="sidebar-header">
                <h3><?= h($episode['series_title']) ?></h3>
                <span><?= count($allEps) ?> Episodes</span>
              </div>
              <div class="episodes-list">
                <?php foreach ($allEps as $e): ?>
                  <a href="watching.php?slug=<?= h($e['slug']) ?>" class="episode-item<?= $e['id'] == $episode['id'] ? ' active' : '' ?>">
                    <span class="ep-number"><?= (int)$e['episode_number'] ?></span>
                    <span class="ep-title"><?= h($e['title']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <script>
      document.querySelectorAll('.server-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          document.querySelectorAll('.server-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          var player = document.getElementById('player');
          var wasPlaying = !player.paused;
          player.querySelector('source').src = btn.getAttribute('data-url');
          player.load();
          if (wasPlaying) player.play();
        });
      });
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>
