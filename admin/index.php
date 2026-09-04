<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$stats = [
    'Series' => $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn(),
    'Movies' => $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn(),
    'Episodes' => $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn(),
    'Genres' => $pdo->query("SELECT COUNT(*) FROM genres")->fetchColumn(),
    'Users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'Open Reports' => $pdo->query("SELECT COUNT(*) FROM video_reports")->fetchColumn(),
];

// Build a 30-day date range and fill in real counts where they exist (0 elsewhere)
function last_30_days_series($pdo, $table, $dateCol) {
    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    try {
        $rows = $pdo->query("SELECT DATE($dateCol) AS d, COUNT(*) AS c FROM $table
            WHERE $dateCol >= (CURDATE() - INTERVAL 29 DAY) GROUP BY DATE($dateCol)")->fetchAll();
        foreach ($rows as $r) {
            if (isset($days[$r['d']])) $days[$r['d']] = (int)$r['c'];
        }
    } catch (PDOException $e) {
        // table not migrated yet — return all zeros
    }
    return $days;
}

$visitsByDay = last_30_days_series($pdo, 'visits', 'visited_on');
$usersByDay = last_30_days_series($pdo, 'users', 'created_at');

$visitsThisWeek = array_sum(array_slice($visitsByDay, -7, 7, true));
$visitsThisMonth = array_sum($visitsByDay);
$usersThisWeek = array_sum(array_slice($usersByDay, -7, 7, true));
$usersThisMonth = array_sum($usersByDay);

$recentReports = $pdo->query("SELECT r.*, vs.name AS server_name,
        COALESCE(e.title, m.title) AS content_title
    FROM video_reports r
    JOIN video_sources vs ON r.video_source_id = vs.id
    LEFT JOIN episodes e ON vs.episode_id = e.id
    LEFT JOIN movies m ON vs.movie_id = m.id
    ORDER BY r.created_at DESC LIMIT 5")->fetchAll();

$admin_page_title = 'Dashboard';
$admin_active = 'dashboard';
include __DIR__ . '/includes/layout_top.php';
?>
  <div class="grid stats">
    <?php foreach ($stats as $label => $value): ?>
      <div class="stat">
        <div class="label"><?= h($label) ?></div>
        <div class="value"><?= (int)$value ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid stats" style="margin-top:12px;">
    <div class="stat"><div class="label">Visitors Today</div><div class="value"><?= (int)end($visitsByDay) ?></div></div>
    <div class="stat"><div class="label">Visitors This Week</div><div class="value"><?= (int)$visitsThisWeek ?></div></div>
    <div class="stat"><div class="label">Visitors This Month</div><div class="value"><?= (int)$visitsThisMonth ?></div></div>
    <div class="stat"><div class="label">New Users This Week</div><div class="value"><?= (int)$usersThisWeek ?></div></div>
    <div class="stat"><div class="label">New Users This Month</div><div class="value"><?= (int)$usersThisMonth ?></div></div>
  </div>

  <div class="section-title">Visitors — Last 30 Days</div>
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-body"><canvas id="visitsChart" height="80"></canvas></div>
  </div>

  <div class="section-title">New User Registrations — Last 30 Days</div>
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-body"><canvas id="usersChart" height="80"></canvas></div>
  </div>

  <div class="section-title">Recent Video Reports</div>
  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Content</th><th>Server</th><th>Reason</th><th>When</th></tr></thead>
        <tbody>
          <?php if (!$recentReports): ?>
            <tr><td colspan="4" class="empty">No reports yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($recentReports as $r): ?>
            <tr>
              <td><?= h($r['content_title']) ?></td>
              <td><?= h($r['server_name']) ?></td>
              <td><?= h($r['reason']) ?></td>
              <td class="muted"><?= h($r['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <script>
    const chartOpts = {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#9aa0b4' }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y: { beginAtZero: true, ticks: { color: '#9aa0b4', precision: 0 }, grid: { color: 'rgba(255,255,255,0.05)' } }
      }
    };
    new Chart(document.getElementById('visitsChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_keys($visitsByDay)) ?>,
        datasets: [{
          data: <?= json_encode(array_values($visitsByDay)) ?>,
          borderColor: '#5b8def', backgroundColor: 'rgba(91,141,239,0.15)',
          tension: 0.3, fill: true, pointRadius: 2
        }]
      },
      options: chartOpts
    });
    new Chart(document.getElementById('usersChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_keys($usersByDay)) ?>,
        datasets: [{
          data: <?= json_encode(array_values($usersByDay)) ?>,
          backgroundColor: '#7c5cff'
        }]
      },
      options: chartOpts
    });
  </script>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
