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

// Handle video report
$reportSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_video') {
    $sourceId = !empty($_POST['report_source_id']) ? (int)$_POST['report_source_id'] : null;
    $reason = trim($_POST['reason'] ?? 'Other');
    if ($sourceId) {
        $ins = $pdo->prepare("INSERT INTO video_reports (video_source_id, reason) VALUES (?, ?)");
        $ins->execute([$sourceId, $reason]);
        $reportSent = true;
    }
}

// Handle comment submission
$commentSent = false;
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_comment') {
    $commentText = trim($_POST['comment'] ?? '');
    if (mb_strlen($commentText) < 2) {
        $commentError = 'Please enter a valid comment (at least 2 characters).';
    } else {
        $userId = is_logged_in() ? (int)current_user()['id'] : null;
        $authorName = is_logged_in() ? current_user()['name'] : trim($_POST['author_name'] ?? '');
        if (!$authorName) {
            $authorName = 'Anime Fan';
        }
        $authorName = mb_substr($authorName, 0, 100);
        $ins = $pdo->prepare("INSERT INTO comments (user_id, episode_id, movie_id, author_name, comment) VALUES (?, ?, NULL, ?, ?)");
        $ins->execute([$userId, $episode['id'], $authorName, $commentText]);
        $commentSent = true;
    }
}

// Fetch comments for this episode
$commentStmt = $pdo->prepare("SELECT * FROM comments WHERE episode_id = ? ORDER BY created_at DESC");
$commentStmt->execute([$episode['id']]);
$comments = $commentStmt->fetchAll();

$page_title = 'AniStream | ' . $episode['series_title'] . ' - ' . $episode['title'];
include __DIR__ . '/includes/header.php';
?>
    <section class="media-details spad">
      <div class="container-fluid px-4">
        <div class="row">
          <div class="col-lg-9">
            <?php if ($reportSent): ?>
              <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="background:#1d1e39;color:#52c41a;border:1px solid #274916;">
                <i class="fa fa-check-circle"></i> <strong>Report received:</strong> Thank you! Our team has been notified of the issue.
                <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="color:#fff;"><span aria-hidden="true">&times;</span></button>
              </div>
            <?php endif; ?>

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
              <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:10px;">
                <h4 class="mb-0">SERVERS</h4>
                <?php if ($sources): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#reportModal" style="border-radius:20px;padding:4px 14px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa fa-flag"></i> Report
                  </button>
                <?php endif; ?>
              </div>
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

            <!-- Comments & Reviews Section -->
            <div class="media__details__review mt-5">
              <div class="section-title">
                <h5>Reviews &amp; Comments (<?= count($comments) ?>)</h5>
              </div>
              <?php if (!$comments): ?>
                <p class="text-white-50" style="font-size:14px;">No comments yet. Be the first to share your thoughts!</p>
              <?php endif; ?>
              <?php foreach ($comments as $c): ?>
                <div class="media__review__item">
                  <div class="media__review__item__pic">
                    <img src="img/anime/review-1.jpg" alt="<?= h($c['author_name']) ?>" />
                  </div>
                  <div class="media__review__item__text">
                    <h6><?= h($c['author_name']) ?> - <span><?= time_ago_str($c['created_at']) ?></span></h6>
                    <p><?= nl2br(h($c['comment'])) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="media__details__form">
              <div class="section-title">
                <h5>Your Comment</h5>
              </div>
              <?php if ($commentSent): ?>
                <div class="alert alert-success" style="background:#1d1e39;color:#52c41a;border:1px solid #274916;">
                  <i class="fa fa-check-circle"></i> Your comment has been posted!
                </div>
              <?php endif; ?>
              <?php if (!empty($commentError)): ?>
                <div class="alert alert-danger" style="background:#1d1e39;color:#ff4d4f;border:1px solid #5c1819;">
                  <?= h($commentError) ?>
                </div>
              <?php endif; ?>
              <form method="post" action="">
                <input type="hidden" name="action" value="post_comment">
                <?php if (!is_logged_in()): ?>
                  <div class="form-group mb-3">
                    <input type="text" name="author_name" class="form-control" placeholder="Your Name or Nickname" required style="max-width:320px;background:#1d1e39;border:1px solid #2e3048;color:#fff;" />
                  </div>
                <?php else: ?>
                  <p class="text-white-50 mb-2"><i class="fa fa-user"></i> Commenting as <strong class="text-white"><?= h(current_user()['name']) ?></strong></p>
                <?php endif; ?>
                <textarea name="comment" placeholder="Write your review or comment..." required></textarea>
                <button type="submit"><i class="fa fa-location-arrow"></i> Post Comment</button>
              </form>
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

    <!-- Report Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" role="dialog" aria-labelledby="reportModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="background:#151622;color:#fff;border:1px solid #2e3048;border-radius:10px;">
          <div class="modal-header" style="border-bottom:1px solid #2e3048;">
            <h5 class="modal-title" id="reportModalLabel" style="color:#fff;"><i class="fa fa-flag text-danger"></i> Report Video Problem</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form method="post" action="">
            <input type="hidden" name="action" value="report_video">
            <div class="modal-body">
              <div class="form-group mb-3">
                <label class="text-white-50">Select Server / Source</label>
                <select name="report_source_id" class="form-control" style="background:#1d1e39;color:#fff;border:1px solid #2e3048;">
                  <?php foreach ($sources as $src): ?>
                    <option value="<?= (int)$src['id'] ?>"><?= h($src['name']) ?> (<?= h($src['quality']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group mb-3">
                <label class="text-white-50">Problem Type</label>
                <select name="reason" class="form-control" style="background:#1d1e39;color:#fff;border:1px solid #2e3048;">
                  <option>Video doesn't play</option>
                  <option>Broken link / 404</option>
                  <option>Buffering / Very slow</option>
                  <option>Audio out of sync or missing</option>
                  <option>Subtitle problem</option>
                  <option>Wrong video or episode</option>
                  <option>Other issue</option>
                </select>
              </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #2e3048;">
              <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-paper-plane"></i> Submit Report</button>
            </div>
          </form>
        </div>
      </div>
    </div>

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
