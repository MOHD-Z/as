<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if (isset($_GET['archive'])) {
    $pdo->prepare("UPDATE movies SET archived = 1 WHERE id = ?")->execute([(int)$_GET['archive']]);
    admin_flash('Movie archived — hidden from the public site, not deleted.');
    header('Location: movies.php');
    exit;
}
if (isset($_GET['restore'])) {
    $pdo->prepare("UPDATE movies SET archived = 0 WHERE id = ?")->execute([(int)$_GET['restore']]);
    admin_flash('Movie restored.');
    header('Location: movies.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'active';
$sort = $_GET['sort'] ?? 'created_at';
$dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$sortable = ['title' => 'm.title', 'runtime' => 'm.runtime', 'score' => 'm.score', 'views' => 'm.views'];
$orderCol = $sortable[$sort] ?? 'm.created_at';

$where = ['m.archived = ' . ($view === 'archived' ? 1 : 0)];
$params = [];
if ($q !== '') {
    $where[] = 'm.title LIKE ?';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT m.*,
        (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM movie_genres mg JOIN genres g ON mg.genre_id=g.id WHERE mg.movie_id=m.id) AS genre_names
    FROM movies m WHERE $whereSql ORDER BY $orderCol $dir");
$stmt->execute($params);
$movies = $stmt->fetchAll();

$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM movies WHERE archived = 0")->fetchColumn();
$totalViews = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM movies WHERE archived = 0")->fetchColumn();
$top3 = $pdo->query("SELECT title, views FROM movies WHERE archived = 0 ORDER BY views DESC LIMIT 3")->fetchAll();

function sort_link_m($label, $key, $sort, $dir, $q, $view) {
    $newDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $url = '?sort=' . $key . '&dir=' . $newDir . '&q=' . urlencode($q) . '&view=' . $view;
    return '<a href="' . h($url) . '" style="color:inherit;">' . h($label) . $arrow . '</a>';
}

$admin_page_title = 'Movies';
$admin_active = 'movies';
$admin_page_actions = '<a href="movies-form.php" class="btn primary">+ Add Movie</a>';
include __DIR__ . '/includes/layout_top.php';
?>
  <div class="grid stats" style="margin-bottom:20px;">
    <div class="stat"><div class="label">Total Movies</div><div class="value"><?= $totalCount ?></div></div>
    <div class="stat"><div class="label">Total Views (all movies)</div><div class="value"><?= number_format($totalViews) ?></div></div>
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
        <?php if ($q): ?><a href="movies.php?view=<?= h($view) ?>" class="btn">Clear</a><?php endif; ?>
      </form>
      <div>
        <a href="movies.php?view=active" class="btn <?= $view === 'active' ? 'primary' : '' ?>">Active</a>
        <a href="movies.php?view=archived" class="btn <?= $view === 'archived' ? 'primary' : '' ?>">Archived</a>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= sort_link_m('Title', 'title', $sort, $dir, $q, $view) ?></th>
            <th>Genres</th>
            <th><?= sort_link_m('Runtime', 'runtime', $sort, $dir, $q, $view) ?></th>
            <th><?= sort_link_m('Score', 'score', $sort, $dir, $q, $view) ?></th>
            <th><?= sort_link_m('Views', 'views', $sort, $dir, $q, $view) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$movies): ?><tr><td colspan="6" class="empty">No movies found.</td></tr><?php endif; ?>
          <?php foreach ($movies as $m): ?>
            <tr>
              <td><?= h($m['title']) ?></td>
              <td class="muted"><?= h($m['genre_names'] ?? '—') ?></td>
              <td><?= (int)$m['runtime'] ?> min</td>
              <td><?= h($m['score']) ?></td>
              <td><?= (int)$m['views'] ?></td>
              <td>
                <a href="movies-form.php?id=<?= (int)$m['id'] ?>" class="btn">Edit</a>
                <a href="video_sources.php?movie_id=<?= (int)$m['id'] ?>" class="btn">Sources</a>
                <?php if ($view === 'archived'): ?>
                  <a href="movies.php?restore=<?= (int)$m['id'] ?>" class="btn primary">Restore</a>
                <?php else: ?>
                  <a href="movies.php?archive=<?= (int)$m['id'] ?>" class="btn danger" onclick="return confirm('Archive this movie? It will be hidden from the public site but not deleted.')">Archive</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
