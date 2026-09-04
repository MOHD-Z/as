<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

function slugify_ep($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function get_or_create_season($pdo, $series_id, $season_number) {
    $stmt = $pdo->prepare("SELECT id FROM seasons WHERE series_id = ? AND season_number = ?");
    $stmt->execute([$series_id, $season_number]);
    $season = $stmt->fetch();
    if ($season) return (int)$season['id'];
    $pdo->prepare("INSERT INTO seasons (series_id, season_number, title) VALUES (?, ?, ?)")
        ->execute([$series_id, $season_number, 'Season ' . $season_number]);
    return (int)$pdo->lastInsertId();
}

// Quick delete video source from episode edit screen
if (isset($_GET['delete_source'])) {
    $pdo->prepare("DELETE FROM video_sources WHERE id = ?")->execute([(int)$_GET['delete_source']]);
    admin_flash('Video source deleted.');
    $redirectEdit = isset($_GET['edit']) ? '&edit=' . (int)$_GET['edit'] : '';
    $redirectSeries = isset($_GET['series_id']) ? '&series_id=' . (int)$_GET['series_id'] : '';
    header('Location: episodes.php?' . ltrim($redirectEdit . $redirectSeries, '&'));
    exit;
}

// Single create episode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $series_id = (int)$_POST['series_id'];
    $season_number = (int)$_POST['season_number'];
    $episode_number = (int)$_POST['episode_number'];
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        $title = 'Episode ' . $episode_number;
    }

    $season_id = get_or_create_season($pdo, $series_id, $season_number);
    $slug = slugify_ep($title) . '-' . $series_id . '-s' . $season_number . 'e' . $episode_number . '-' . substr(md5(uniqid()), 0, 5);
    $pdo->prepare("INSERT INTO episodes (season_id, episode_number, title, slug) VALUES (?,?,?,?)")
        ->execute([$season_id, $episode_number, $title, $slug]);
    $epId = (int)$pdo->lastInsertId();

    // Attach video source directly if provided
    $sourceUrl = trim($_POST['source_url'] ?? '');
    if ($sourceUrl !== '') {
        $serverName = trim($_POST['source_server'] ?? 'Server 1') ?: 'Server 1';
        $quality = trim($_POST['source_quality'] ?? 'HD') ?: 'HD';
        $pdo->prepare("INSERT INTO video_sources (episode_id, name, quality, url, priority, enabled) VALUES (?,?,?,?,1,1)")
            ->execute([$epId, $serverName, $quality, $sourceUrl]);
    }

    admin_flash('Episode added successfully.');
    header('Location: episodes.php' . ($series_id ? '?series_id=' . $series_id : ''));
    exit;
}

// Update episode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int)$_POST['id'];
    $series_id = (int)$_POST['series_id'];
    $season_number = (int)$_POST['season_number'];
    $episode_number = (int)$_POST['episode_number'];
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        $title = 'Episode ' . $episode_number;
    }

    $season_id = get_or_create_season($pdo, $series_id, $season_number);
    $pdo->prepare("UPDATE episodes SET season_id=?, episode_number=?, title=? WHERE id=?")
        ->execute([$season_id, $episode_number, $title, $id]);

    // Attach new video source directly if provided
    $sourceUrl = trim($_POST['source_url'] ?? '');
    if ($sourceUrl !== '') {
        $serverName = trim($_POST['source_server'] ?? 'Server 1') ?: 'Server 1';
        $quality = trim($_POST['source_quality'] ?? 'HD') ?: 'HD';
        $pdo->prepare("INSERT INTO video_sources (episode_id, name, quality, url, priority, enabled) VALUES (?,?,?,?,1,1)")
            ->execute([$id, $serverName, $quality, $sourceUrl]);
    }

    admin_flash('Episode updated successfully.');
    header('Location: episodes.php?series_id=' . $series_id . '&edit=' . $id);
    exit;
}

if (isset($_GET['archive'])) {
    $pdo->prepare("UPDATE episodes SET archived = 1 WHERE id = ?")->execute([(int)$_GET['archive']]);
    admin_flash('Episode archived.');
    header('Location: episodes.php' . (isset($_GET['series_id']) ? '?series_id=' . (int)$_GET['series_id'] : ''));
    exit;
}
if (isset($_GET['restore'])) {
    $pdo->prepare("UPDATE episodes SET archived = 0 WHERE id = ?")->execute([(int)$_GET['restore']]);
    admin_flash('Episode restored.');
    header('Location: episodes.php' . (isset($_GET['series_id']) ? '?series_id=' . (int)$_GET['series_id'] : ''));
    exit;
}

$series_id = $_GET['series_id'] ?? null;
$allSeries = $pdo->query("SELECT id, title FROM series WHERE archived = 0 ORDER BY title")->fetchAll();
$q = trim($_GET['q'] ?? '');

// Safe per_page check avoiding undefined array key warning
$perPage = (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [25, 50, 75, 100], true)) ? (int)$_GET['per_page'] : 25;
$view = $_GET['view'] ?? 'active';

$editing = null;
$existingSources = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT e.*, se.season_number, se.series_id FROM episodes e JOIN seasons se ON e.season_id = se.id WHERE e.id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();

    if ($editing) {
        $srcStmt = $pdo->prepare("SELECT * FROM video_sources WHERE episode_id = ? ORDER BY priority ASC, id ASC");
        $srcStmt->execute([(int)$editing['id']]);
        $existingSources = $srcStmt->fetchAll();
    }
}

$where = ['e.archived = ' . ($view === 'archived' ? 1 : 0)];
$params = [];
if ($series_id) { $where[] = 's.id = ?'; $params[] = $series_id; }
if ($q !== '') { $where[] = '(e.title LIKE ? OR s.title LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$whereSql = implode(' AND ', $where);

$sql = "SELECT e.*, se.season_number, s.title AS series_title, s.id AS series_id,
        (SELECT COUNT(*) FROM video_sources vs WHERE vs.episode_id = e.id) AS source_count
    FROM episodes e 
    JOIN seasons se ON e.season_id = se.id 
    JOIN series s ON se.series_id = s.id
    WHERE $whereSql ORDER BY e.created_at DESC LIMIT $perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$episodes = $stmt->fetchAll();

$admin_page_title = __('nav_episodes', 'Episodes');
$admin_active = 'episodes';
$admin_page_actions = '<a href="bulk-episodes.php" class="btn primary">⚡ Bulk Add Episodes</a>';
include __DIR__ . '/includes/layout_top.php';
?>

  <!-- ADD / EDIT EPISODE PANEL -->
  <div class="panel" style="margin-bottom:24px;">
    <div class="panel-head">
      <h2><?= $editing ? 'Edit Episode: ' . h($editing['title']) : 'Add Episode' ?></h2>
    </div>
    <div class="panel-body">
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

        <div class="field">
          <label>Series</label>
          <select name="series_id" required>
            <?php foreach ($allSeries as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= ($editing['series_id'] ?? $series_id) == $s['id'] ? 'selected' : '' ?>><?= h($s['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Season Number</label>
          <input type="number" name="season_number" min="1" value="<?= h($editing['season_number'] ?? '1') ?>" required>
        </div>

        <div class="field">
          <label>Episode Number</label>
          <input type="number" name="episode_number" min="1" value="<?= h($editing['episode_number'] ?? '') ?>" required>
        </div>

        <div class="field">
          <label>Episode Title <span class="muted" style="font-weight:normal;">(Optional — defaults to "Episode #")</span></label>
          <input type="text" name="title" placeholder="e.g. The Beginning (leave empty for Episode #)" value="<?= h($editing['title'] ?? '') ?>">
        </div>

        <!-- VIDEO SOURCE SECTION DIRECTLY IN EPISODES -->
        <div class="field full" style="background:#13151f;padding:16px;border-radius:8px;border:1px solid #28283c;margin-top:10px;">
          <div style="font-weight:600;margin-bottom:8px;color:#fff;display:flex;align-items:center;gap:8px;">
            <span>🎬</span> Video Source (Add Direct Stream)
          </div>
          <p class="muted" style="margin-bottom:12px;font-size:13px;">
            Provide a direct video link (MP4, HLS/m3u8, or iframe embed) to attach a playable stream to this episode.
          </p>

          <div style="display:grid;grid-template-columns:1fr 1fr 2fr;gap:12px;">
            <div>
              <label style="font-size:12px;">Server Name</label>
              <input type="text" name="source_server" value="Server 1" placeholder="e.g. Server 1, FastCloud">
            </div>
            <div>
              <label style="font-size:12px;">Quality</label>
              <select name="source_quality">
                <?php foreach (['4K','Blu-ray','FHD','HD','MD','SD'] as $qopt): ?>
                  <option value="<?= $qopt ?>" <?= $qopt === 'HD' ? 'selected' : '' ?>><?= $qopt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size:12px;">Video URL</label>
              <input type="text" name="source_url" placeholder="https://domain.com/video.mp4 or embed url">
            </div>
          </div>

          <?php if ($editing && !empty($existingSources)): ?>
            <div style="margin-top:16px;border-top:1px solid #28283c;padding-top:12px;">
              <label style="font-size:13px;font-weight:600;color:#ccc;">Current Attached Video Sources (<?= count($existingSources) ?>):</label>
              <table class="table" style="margin-top:8px;">
                <thead><tr><th>Server</th><th>Quality</th><th>URL</th><th></th></tr></thead>
                <tbody>
                  <?php foreach ($existingSources as $src): ?>
                    <tr>
                      <td><?= h($src['name']) ?></td>
                      <td><span class="badge"><?= h($src['quality']) ?></span></td>
                      <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;"><?= h($src['url']) ?></td>
                      <td style="text-align:right;">
                        <a href="episodes.php?delete_source=<?= (int)$src['id'] ?>&edit=<?= (int)$editing['id'] ?>&series_id=<?= (int)$editing['series_id'] ?>"
                           class="btn danger" style="padding:4px 8px;font-size:12px;" onclick="return confirm('Delete this video source?')">Delete</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <div class="field full" style="margin-top:14px;">
          <button type="submit" class="btn primary"><?= $editing ? 'Save Changes' : 'Add Episode' ?></button>
          <?php if ($editing): ?>
            <a href="episodes.php?series_id=<?= (int)$editing['series_id'] ?>" class="btn">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- SEARCH & FILTER BAR -->
  <div class="panel" style="margin-bottom:16px;">
    <div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <form method="get" style="display:flex;gap:10px;flex:1;min-width:240px;">
        <?php if ($series_id): ?><input type="hidden" name="series_id" value="<?= (int)$series_id ?>"><?php endif; ?>
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search episode or series title..."
               style="flex:1;background:#0f111b;border:1px solid var(--border);color:#e9ebf4;border-radius:8px;padding:9px 10px;">
        <button type="submit" class="btn primary">Search</button>
      </form>

      <form method="get">
        <?php if ($series_id): ?><input type="hidden" name="series_id" value="<?= (int)$series_id ?>"><?php endif; ?>
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <label class="muted">Show:</label>
        <select name="per_page" onchange="this.form.submit()">
          <?php foreach ([25, 50, 75, 100] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?> per page</option>
          <?php endforeach; ?>
        </select>
      </form>

      <div>
        <a href="episodes.php?view=active<?= $series_id ? '&series_id=' . (int)$series_id : '' ?>" class="btn <?= $view === 'active' ? 'primary' : '' ?>">Active</a>
        <a href="episodes.php?view=archived<?= $series_id ? '&series_id=' . (int)$series_id : '' ?>" class="btn <?= $view === 'archived' ? 'primary' : '' ?>">Archived</a>
      </div>
    </div>
  </div>

  <!-- EPISODES LIST TABLE -->
  <div class="panel">
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
      <h2><?= $series_id ? h($allSeries[array_search($series_id, array_column($allSeries, 'id'))]['title'] ?? 'Episodes') : "Episodes (showing up to $perPage)" ?></h2>
      <?php if ($series_id): ?><a href="episodes.php" class="btn">Show All Series</a><?php endif; ?>
    </div>
    <div class="panel-body table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Series</th>
            <th>Season</th>
            <th>Ep</th>
            <th>Title</th>
            <th>Sources</th>
            <th>Views</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$episodes): ?>
            <tr><td colspan="7" class="empty">No episodes found.</td></tr>
          <?php endif; ?>
          <?php foreach ($episodes as $e): ?>
            <tr>
              <td><strong><?= h($e['series_title']) ?></strong></td>
              <td><?= (int)$e['season_number'] ?></td>
              <td><span class="badge">#<?= (int)$e['episode_number'] ?></span></td>
              <td><?= h($e['title']) ?></td>
              <td>
                <span class="badge" style="background:<?= (int)$e['source_count'] > 0 ? '#1e3a2b' : '#3d2424' ?>;color:<?= (int)$e['source_count'] > 0 ? '#52c41a' : '#ff7875' ?>;">
                  <?= (int)$e['source_count'] ?> source<?= (int)$e['source_count'] === 1 ? '' : 's' ?>
                </span>
              </td>
              <td><?= (int)$e['views'] ?></td>
              <td>
                <a href="episodes.php?edit=<?= (int)$e['id'] ?>&series_id=<?= (int)$e['series_id'] ?>" class="btn">Edit / +Source</a>
                <a href="video_sources.php?episode_id=<?= (int)$e['id'] ?>" class="btn">Sources</a>
                <?php if ($view === 'archived'): ?>
                  <a href="episodes.php?restore=<?= (int)$e['id'] ?>&series_id=<?= (int)$e['series_id'] ?>" class="btn primary">Restore</a>
                <?php else: ?>
                  <a href="episodes.php?archive=<?= (int)$e['id'] ?>&series_id=<?= (int)$e['series_id'] ?>" class="btn danger" onclick="return confirm('Archive this episode?')">Archive</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
