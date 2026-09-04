<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if (isset($_GET['archive'])) {
    $pdo->prepare("UPDATE series SET archived = 1 WHERE id = ?")->execute([(int)$_GET['archive']]);
    admin_flash('Series archived — hidden from the public site, not deleted.');
    header('Location: series.php');
    exit;
}
if (isset($_GET['restore'])) {
    $pdo->prepare("UPDATE series SET archived = 0 WHERE id = ?")->execute([(int)$_GET['restore']]);
    admin_flash('Series restored.');
    header('Location: series.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'active'; // active | archived
$sort = $_GET['sort'] ?? 'created_at';
$dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$sortable = ['title' => 's.title', 'status' => 's.status', 'episodes' => 'episode_count', 'seasons' => 'season_count', 'score' => 's.score', 'views' => 's.views'];
$orderCol = $sortable[$sort] ?? 's.created_at';

$where = ['s.archived = ' . ($view === 'archived' ? 1 : 0)];
$params = [];
if ($q !== '') {
    $where[] = 's.title LIKE ?';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT s.*,
        (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM series_genres sg JOIN genres g ON sg.genre_id=g.id WHERE sg.series_id=s.id) AS genre_names,
        (SELECT COUNT(*) FROM episodes e JOIN seasons se ON e.season_id=se.id WHERE se.series_id=s.id) AS episode_count,
        (SELECT COUNT(DISTINCT season_number) FROM seasons WHERE series_id = s.id) AS season_count
    FROM series s WHERE $whereSql ORDER BY $orderCol $dir");
$stmt->execute($params);
$series = $stmt->fetchAll();

$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM series WHERE archived = 0")->fetchColumn();
$totalViews = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM series WHERE archived = 0")->fetchColumn();
$top3 = $pdo->query("SELECT title, views FROM series WHERE archived = 0 ORDER BY views DESC LIMIT 3")->fetchAll();

function sort_link($label, $key, $sort, $dir, $q, $view) {
    $newDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $url = '?sort=' . $key . '&dir=' . $newDir . '&q=' . urlencode($q) . '&view=' . $view;
    return '<a href="' . h($url) . '" style="color:inherit;">' . h($label) . $arrow . '</a>';
}

$admin_page_title = 'Series';
$admin_active = 'series';
$admin_page_actions = '<a href="series-form.php" class="btn primary">+ Add Series</a>';
include __DIR__ . '/includes/layout_top.php';
?>
  <div class="grid stats" style="margin-bottom:20px;">
    <div class="stat"><div class="label">Total Series</div><div class="value"><?= $totalCount ?></div></div>
    <div class="stat"><div class="label">Total Views (all series)</div><div class="value"><?= number_format($totalViews) ?></div></div>
    <div class="stat">
      <div class="label">Top 3 by Views</div>
      <div style="font-size:13px;margin-top:6px;">
        <?php foreach ($top3 as $i => $t): ?>
          <div><?= $i + 1 ?>. <?= h($t['title']) ?> — <?= number_format($t['views']) ?></div>
        <?php endforeach; ?>
        <?php if (!$top3): ?><span class="muted">No data yet</span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;">
    <div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <form method="get" style="display:flex;gap:10px;flex:1;min-width:220px;">
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by title..."
               style="flex:1;background:#0f111b;border:1px solid var(--border);color:#e9ebf4;border-radius:8px;padding:9px 10px;">
        <button type="submit" class="btn primary">Search</button>
        <?php if ($q): ?><a href="series.php?view=<?= h($view) ?>" class="btn">Clear</a><?php endif; ?>
      </form>
      <div>
        <a href="series.php?view=active" class="btn <?= $view === 'active' ? 'primary' : '' ?>">Active</a>
        <a href="series.php?view=archived" class="btn <?= $view === 'archived' ? 'primary' : '' ?>">Archived</a>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= sort_link('Title', 'title', $sort, $dir, $q, $view) ?></th>
            <th>Genres</th>
            <th><?= sort_link('Status', 'status', $sort, $dir, $q, $view) ?></th>
            <th><?= sort_link('Episodes', 'episodes', $sort, $dir, $q, $view) ?></th>
            <th><?= sort_link('Seasons', 'seasons', $sort, $dir, $q, $view) ?></th>
            <th><?= sort_link('Score', 'score', $sort, $dir, $q, $view) ?></th>
            <th><?= sort_link('Views', 'views', $sort, $dir, $q, $view) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$series): ?><tr><td colspan="8" class="empty">No series found.</td></tr><?php endif; ?>
          <?php foreach ($series as $s): ?>
            <tr>
              <td><?= h($s['title']) ?></td>
              <td class="muted"><?= h($s['genre_names'] ?? '—') ?></td>
              <td><span class="status published"><?= h($s['status']) ?></span></td>
              <td><?= (int)$s['episode_count'] ?></td>
              <td><?= (int)$s['season_count'] ?></td>
              <td><?= h($s['score']) ?></td>
              <td><?= (int)$s['views'] ?></td>
              <td>
                <a href="series-form.php?id=<?= (int)$s['id'] ?>" class="btn">Edit</a>
                <a href="episodes.php?series_id=<?= (int)$s['id'] ?>" class="btn">Episodes</a>
                <?php if ($view === 'archived'): ?>
                  <a href="series.php?restore=<?= (int)$s['id'] ?>" class="btn primary">Restore</a>
                <?php else: ?>
                  <a href="series.php?archive=<?= (int)$s['id'] ?>" class="btn danger" onclick="return confirm('Archive this series? It will be hidden from the public site but not deleted.')">Archive</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
