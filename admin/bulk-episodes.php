<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

function slugify_ep($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text ?: 'ep');
}

function get_or_create_season_default($pdo, $series_id) {
    // Look for existing season 1, or the latest season
    $stmt = $pdo->prepare("SELECT id FROM seasons WHERE series_id = ? ORDER BY season_number ASC LIMIT 1");
    $stmt->execute([$series_id]);
    $existing = $stmt->fetchColumn();
    if ($existing) return (int)$existing;

    $stmt = $pdo->prepare("INSERT INTO seasons (series_id, season_number, title) VALUES (?, 1, 'Season 1')");
    $stmt->execute([$series_id]);
    return (int)$pdo->lastInsertId();
}

$allSeries = $pdo->query("SELECT id, title FROM series WHERE archived = 0 ORDER BY title")->fetchAll();

// Handle bulk creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_create') {
    $series_id = (int)$_POST['bulk_series_id'];
    $count = max(1, min(500, (int)$_POST['bulk_count']));
    $startFrom = max(1, (int)($_POST['bulk_start'] ?? 1));
    $titleLines = array_values(array_filter(array_map('trim', explode("\n", $_POST['bulk_titles'] ?? '')), fn($l) => $l !== ''));
    $urlLines = array_values(array_filter(array_map('trim', explode("\n", $_POST['bulk_urls'] ?? '')), fn($l) => $l !== ''));
    $quality = trim($_POST['bulk_quality'] ?? 'HD');
    $serverName = trim($_POST['bulk_server_name'] ?? 'Server 1');

    $season_id = get_or_create_season_default($pdo, $series_id);
    $insEp = $pdo->prepare("INSERT INTO episodes (season_id, episode_number, title, slug) VALUES (?,?,?,?)");
    $insSrc = $pdo->prepare("INSERT INTO video_sources (episode_id, name, quality, url, priority, enabled) VALUES (?,?,?,?,1,1)");

    $created = 0;
    for ($i = 0; $i < $count; $i++) {
        $epNum = $startFrom + $i;
        $title = $titleLines[$i] ?? ('Episode ' . $epNum);
        $slug = slugify_ep($title) . '-' . $series_id . '-e' . $epNum . '-' . substr(md5(uniqid()), 0, 5);
        $insEp->execute([$season_id, $epNum, $title, $slug]);
        $newId = $pdo->lastInsertId();
        if (!empty($urlLines[$i])) {
            $insSrc->execute([$newId, $serverName ?: 'Server 1', $quality, $urlLines[$i]]);
        }
        $created++;
    }

    admin_flash("Successfully created $created episodes for the series.");
    header('Location: episodes.php?series_id=' . $series_id);
    exit;
}

$admin_page_title = __('nav_bulk_episodes', 'Bulk Add Episodes');
$admin_active = 'bulk_episodes';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="panel" style="margin-bottom:24px;">
  <div class="panel-head">
    <h2>⚡ <?= __('nav_bulk_episodes', 'Bulk Add Episodes') ?></h2>
  </div>
  <div class="panel-body">
    <p class="muted" style="margin-bottom:18px;">
      Quickly create multiple episodes for an anime/series at once. Season assignment is handled automatically.
      You can paste video URLs or episode titles line-by-line; if left blank, episodes will automatically be titled "Episode 1", "Episode 2", etc.
    </p>

    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="bulk_create">

      <div class="field">
        <label>Select Series</label>
        <select name="bulk_series_id" required style="font-size:15px;">
          <?php foreach ($allSeries as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= h($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>Start Episode #</label>
        <input type="number" name="bulk_start" min="1" value="1" required>
      </div>

      <div class="field">
        <label>How Many Episodes to Create</label>
        <input type="number" name="bulk_count" min="1" max="500" value="12" required>
      </div>

      <div class="field">
        <label>Video Quality</label>
        <select name="bulk_quality">
          <?php foreach (['4K', 'Blu-ray', 'FHD', 'HD', 'MD', 'SD', 'CAM'] as $q): ?>
            <option value="<?= $q ?>" <?= $q === 'HD' ? 'selected' : '' ?>><?= $q ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>Server Name (for added URLs)</label>
        <input type="text" name="bulk_server_name" value="Server 1" placeholder="Server 1">
      </div>

      <div class="field full">
        <label>Episode Titles (Optional - one per line; first line matches Start Episode #)</label>
        <textarea name="bulk_titles" placeholder="Episode 1: The Awakening&#10;Episode 2: Into the Unknown&#10;..." style="min-height:110px;"></textarea>
      </div>

      <div class="field full">
        <label>Video Links (Optional - one per line; matching order of episodes)</label>
        <textarea name="bulk_urls" placeholder="https://server.com/videos/ep1.mp4&#10;https://server.com/videos/ep2.mp4&#10;..." style="min-height:110px;"></textarea>
      </div>

      <div class="field full" style="margin-top:10px;">
        <button type="submit" class="btn primary" onclick="return confirm('Create these episodes now?')">
          Create Episodes
        </button>
        <a href="episodes.php" class="btn" style="margin-left:8px;">View All Episodes</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
