<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM movies WHERE slug = ?");
$stmt->execute([$slug]);
$movie = $stmt->fetch();

if (!$movie) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pdo->prepare("UPDATE movies SET views = views + 1 WHERE id = ?")->execute([$movie['id']]);

if (is_logged_in()) {
    record_watch_progress($pdo, current_user()['id'], 0, $movie['id'], null);
}

$sources = $pdo->prepare("SELECT * FROM video_sources WHERE movie_id = ? AND enabled = 1 ORDER BY priority");
$sources->execute([$movie['id']]);
$sources = $sources->fetchAll();

$reportSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_source_id'])) {
    $ins = $pdo->prepare("INSERT INTO video_reports (video_source_id, reason) VALUES (?, ?)");
    $ins->execute([(int)$_POST['report_source_id'], $_POST['reason'] ?? 'Other']);
    $reportSent = true;
}

$page_title = 'AniStream | ' . $movie['title'];
include __DIR__ . '/includes/header.php';
?>
    <section class="media-details spad">
      <div class="container-fluid px-4">
        <div class="row">
          <div class="col-lg-12">
            <div class="media__video__player">
              <video id="player" playsinline controls data-poster="videos/anime-watch.jpg">
                <?php foreach ($sources as $i => $src): ?>
                  <source src="<?= h($src['url']) ?>" type="video/mp4" <?= $i === 0 ? '' : 'style="display:none"' ?> />
                <?php endforeach; ?>
                <?php if (!$sources): ?><source src="videos/1.mp4" type="video/mp4" /><?php endif; ?>
              </video>
            </div>

            <div class="server-group">
              <h4>SERVERS</h4>
              <div class="server-buttons">
                <?php foreach ($sources as $i => $src): ?>
                  <button type="button" class="server-btn<?= $i === 0 ? ' active' : '' ?>" data-url="<?= h($src['url']) ?>">
                    <?= h($src['name']) ?> (<?= h($src['quality']) ?>)
                  </button>
                <?php endforeach; ?>
                <?php if (!$sources): ?><p>No video sources configured for this movie yet.</p><?php endif; ?>
              </div>
            </div>

            <h3 class="mt-3"><?= h($movie['title']) ?></h3>

            <div class="media__details__form mt-3">
              <div class="section-title"><h5>Report a Problem</h5></div>
              <?php if ($reportSent): ?><p>Thanks — your report was submitted.</p><?php endif; ?>
              <?php if ($sources): ?>
              <form method="post">
                <select name="report_source_id" class="form-control mb-2" style="max-width:300px;">
                  <?php foreach ($sources as $src): ?>
                    <option value="<?= (int)$src['id'] ?>"><?= h($src['name']) ?> (<?= h($src['quality']) ?>)</option>
                  <?php endforeach; ?>
                </select>
                <select name="reason" class="form-control mb-2" style="max-width:300px;">
                  <option>Video doesn't play</option>
                  <option>Buffering</option>
                  <option>Wrong video</option>
                  <option>Audio problem</option>
                  <option>Subtitle problem</option>
                  <option>Other</option>
                </select>
                <button type="submit" class="primary-btn">Submit Report</button>
              </form>
              <?php endif; ?>
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
          player.querySelector('source').src = btn.getAttribute('data-url');
          player.load();
          player.play();
        });
      });
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>
