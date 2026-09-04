<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$episode_id = $_GET['episode_id'] ?? null;
$movie_id = $_GET['movie_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $stmt = $pdo->prepare("INSERT INTO video_sources (episode_id, movie_id, name, quality, url, priority, enabled) VALUES (?,?,?,?,?,?,1)");
    $stmt->execute([
        $_POST['episode_id'] ?: null,
        $_POST['movie_id'] ?: null,
        trim($_POST['name']),
        trim($_POST['quality']),
        trim($_POST['url']),
        (int)($_POST['priority'] ?? 1),
    ]);
    admin_flash('Video source added.');
    header('Location: video_sources.php' . ($episode_id ? '?episode_id=' . $episode_id : ($movie_id ? '?movie_id=' . $movie_id : '')));
    exit;
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE video_sources SET enabled = NOT enabled WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header('Location: video_sources.php' . ($episode_id ? '?episode_id=' . $episode_id : ($movie_id ? '?movie_id=' . $movie_id : '')));
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM video_sources WHERE id = ?")->execute([(int)$_GET['delete']]);
    admin_flash('Video source deleted.');
    header('Location: video_sources.php' . ($episode_id ? '?episode_id=' . $episode_id : ($movie_id ? '?movie_id=' . $movie_id : '')));
    exit;
}

$where = [];
$params = [];
if ($episode_id) { $where[] = 'vs.episode_id = ?'; $params[] = $episode_id; }
if ($movie_id) { $where[] = 'vs.movie_id = ?'; $params[] = $movie_id; }

$sql = "SELECT vs.*, e.title AS episode_title, m.title AS movie_title,
        (SELECT COUNT(*) FROM video_reports WHERE video_source_id = vs.id) AS report_count
    FROM video_sources vs LEFT JOIN episodes e ON vs.episode_id = e.id LEFT JOIN movies m ON vs.movie_id = m.id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY vs.priority LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sources = $stmt->fetchAll();

$contextLabel = $episode_id
    ? ('Episode: ' . ($sources[0]['episode_title'] ?? '#' . $episode_id))
    : ($movie_id ? ('Movie: ' . ($sources[0]['movie_title'] ?? '#' . $movie_id)) : 'All sources (latest 200)');

$admin_page_title = 'Video Sources';
$admin_active = 'sources';
include __DIR__ . '/includes/layout_top.php';
?>
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2>Add Video Source — <?= h($contextLabel) ?></h2></div>
    <div class="panel-body">
      <?php if (!$episode_id && !$movie_id): ?>
        <p class="muted">Open this page from an episode's or movie's "Sources" link to add a source for it.</p>
      <?php else: ?>
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="episode_id" value="<?= h($episode_id ?? '') ?>">
        <input type="hidden" name="movie_id" value="<?= h($movie_id ?? '') ?>">
        <div class="field"><label>Name</label><input type="text" name="name" placeholder="Server 1" required></div>
        <div class="field">
          <label>Quality</label>
          <select name="quality">
            <?php foreach (['SD','HD','FHD','4K'] as $q): ?><option><?= $q ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field full"><label>Video URL / path (e.g. videos/1.mp4)</label><input type="text" name="url" required></div>
        <div class="field"><label>Priority (lower = shown first)</label><input type="number" name="priority" value="1"></div>
        <div class="field full"><button type="submit" class="btn primary">Add Source</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Content</th><th>Name</th><th>Quality</th><th>URL</th><th>Priority</th><th>Status</th><th>Reports</th><th></th></tr></thead>
        <tbody>
          <?php if (!$sources): ?><tr><td colspan="8" class="empty">No video sources.</td></tr><?php endif; ?>
          <?php foreach ($sources as $vs): ?>
            <tr>
              <td><?= h($vs['episode_title'] ?? $vs['movie_title'] ?? '—') ?></td>
              <td><?= h($vs['name']) ?></td>
              <td><?= h($vs['quality']) ?></td>
              <td class="muted"><?= h($vs['url']) ?></td>
              <td><?= (int)$vs['priority'] ?></td>
              <td><span class="status <?= $vs['enabled'] ? 'published' : 'archived' ?>"><?= $vs['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
              <td><?= $vs['report_count'] > 0 ? '<span class="danger-text">' . (int)$vs['report_count'] . '</span>' : '0' ?></td>
              <td>
                <a href="video_sources.php?toggle=<?= (int)$vs['id'] ?><?= $episode_id ? '&episode_id=' . $episode_id : ($movie_id ? '&movie_id=' . $movie_id : '') ?>" class="btn">Toggle</a>
                <a href="video_sources.php?delete=<?= (int)$vs['id'] ?><?= $episode_id ? '&episode_id=' . $episode_id : ($movie_id ? '&movie_id=' . $movie_id : '') ?>" class="btn danger" onclick="return confirm('Delete this source?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
