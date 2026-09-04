<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM video_reports WHERE id = ?")->execute([(int)$_GET['delete']]);
    admin_flash('Report dismissed.');
    header('Location: reports.php');
    exit;
}

$reports = $pdo->query("SELECT r.*, vs.name AS server_name, vs.quality, vs.id AS source_id,
        e.title AS episode_title, s.title AS series_title, m.title AS movie_title
    FROM video_reports r
    JOIN video_sources vs ON r.video_source_id = vs.id
    LEFT JOIN episodes e ON vs.episode_id = e.id
    LEFT JOIN seasons se ON e.season_id = se.id
    LEFT JOIN series s ON se.series_id = s.id
    LEFT JOIN movies m ON vs.movie_id = m.id
    ORDER BY r.created_at DESC")->fetchAll();

// Group counts per source so admins can spot problem servers, per the spec's example
$bySource = [];
foreach ($reports as $r) {
    $key = $r['source_id'];
    $bySource[$key]['label'] = $r['series_title']
        ? ($r['series_title'] . ' — ' . $r['episode_title'])
        : ($r['movie_title'] ?? 'Unknown');
    $bySource[$key]['server'] = $r['server_name'] . ' (' . $r['quality'] . ')';
    $bySource[$key]['count'] = ($bySource[$key]['count'] ?? 0) + 1;
}

$admin_page_title = 'Video Reports';
$admin_active = 'reports';
include __DIR__ . '/includes/layout_top.php';
?>
  <div class="section-title">Reports by Server</div>
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Content</th><th>Server</th><th>Report Count</th></tr></thead>
        <tbody>
          <?php if (!$bySource): ?><tr><td colspan="3" class="empty">No reports yet.</td></tr><?php endif; ?>
          <?php foreach ($bySource as $row): ?>
            <tr>
              <td><?= h($row['label']) ?></td>
              <td><?= h($row['server']) ?></td>
              <td class="danger-text"><?= (int)$row['count'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section-title">All Reports</div>
  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Content</th><th>Server</th><th>Reason</th><th>When</th><th></th></tr></thead>
        <tbody>
          <?php if (!$reports): ?><tr><td colspan="5" class="empty">No reports yet.</td></tr><?php endif; ?>
          <?php foreach ($reports as $r): ?>
            <tr>
              <td><?= h($r['series_title'] ? ($r['series_title'] . ' — ' . $r['episode_title']) : ($r['movie_title'] ?? '—')) ?></td>
              <td><?= h($r['server_name']) ?> (<?= h($r['quality']) ?>)</td>
              <td><?= h($r['reason']) ?></td>
              <td class="muted"><?= h($r['created_at']) ?></td>
              <td><a href="reports.php?delete=<?= (int)$r['id'] ?>" class="btn">Dismiss</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
