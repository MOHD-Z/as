<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/api_clients.php';

function slugify_import($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

// MAL 'media_type' -> our import bucket. Movies go to movies; everything
// episodic (tv, ova, ona, special) goes to series.
function mal_type_to_bucket($mediaType) {
    return $mediaType === 'movie' ? 'movie' : 'series';
}

function do_import($pdo, $type, $title, $synopsis, $poster, $score, $extra = []) {
    $slug = slugify_import($title);
    if ($type === 'movie') {
        $stmt = $pdo->prepare("INSERT INTO movies (title, slug, synopsis, poster, runtime, score) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$title, $slug, $synopsis, $poster, $extra['runtime'] ?? 90, $score]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO series (title, slug, synopsis, poster, status, score) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$title, $slug, $synopsis, $poster, $extra['status'] ?? 'Ongoing', $score]);
    }
    return ['type' => $type, 'id' => $pdo->lastInsertId(), 'title' => $title];
}

$tmdbKey = get_setting('tmdb_api_key', '');
$malId = get_setting('mal_client_id', '');

$source = $_GET['source'] ?? $_POST['source'] ?? 'tmdb_movie';
$query = trim($_GET['q'] ?? '');
$results = [];
$searchError = '';

// ---- Search by title -------------------------------------------------
if ($query !== '') {
    if (in_array($source, ['tmdb_movie', 'tmdb_tv'], true)) {
        if (!$tmdbKey) {
            $searchError = 'No TMDB API key set. Add one in Site Settings first.';
        } else {
            $type = $source === 'tmdb_movie' ? 'movie' : 'tv';
            $res = tmdb_search($tmdbKey, $type, $query);
            if (!$res['ok']) {
                $searchError = 'TMDB error: ' . $res['error'];
            } else {
                foreach (($res['data']['results'] ?? []) as $r) {
                    $results[] = [
                        'source' => $source,
                        'id' => $r['id'],
                        'title' => $r['title'] ?? $r['name'] ?? '(untitled)',
                        'year' => substr($r['release_date'] ?? $r['first_air_date'] ?? '', 0, 4),
                        'poster' => tmdb_poster_url($r['poster_path'] ?? null),
                        'score' => $r['vote_average'] ?? null,
                        'overview' => $r['overview'] ?? '',
                        'bucket' => $source === 'tmdb_movie' ? 'movie' : 'series',
                        'bucket_label' => $source === 'tmdb_movie' ? 'Movie' : 'Series',
                    ];
                }
            }
        }
    } elseif ($source === 'mal') {
        if (!$malId) {
            $searchError = 'No MAL Client ID set. Add one in Site Settings first.';
        } else {
            $res = mal_search($malId, $query);
            if (!$res['ok']) {
                $searchError = 'MAL error: ' . $res['error'];
            } else {
                foreach (($res['data']['data'] ?? []) as $row) {
                    $node = $row['node'] ?? [];
                    $bucket = mal_type_to_bucket($node['media_type'] ?? 'tv');
                    $results[] = [
                        'source' => 'mal',
                        'id' => $node['id'] ?? null,
                        'title' => $node['title'] ?? '(untitled)',
                        'year' => '',
                        'poster' => $node['main_picture']['large'] ?? $node['main_picture']['medium'] ?? '',
                        'score' => $node['mean'] ?? null,
                        'overview' => $node['synopsis'] ?? '',
                        'bucket' => $bucket,
                        'bucket_label' => $bucket === 'movie' ? 'Movie' : 'Series (' . h($node['media_type'] ?? 'tv') . ')',
                    ];
                }
            }
        }
    }
}

// ---- Look up directly by ID ------------------------------------------
$idLookup = null;
$idLookupError = '';
if (($_GET['action'] ?? '') === 'lookup' && !empty($_GET['lookup_id'])) {
    $lookupId = trim($_GET['lookup_id']);
    if (in_array($source, ['tmdb_movie', 'tmdb_tv'], true)) {
        if (!$tmdbKey) {
            $idLookupError = 'No TMDB API key set.';
        } else {
            $type = $source === 'tmdb_movie' ? 'movie' : 'tv';
            $res = tmdb_details($tmdbKey, $type, $lookupId);
            if (!$res['ok']) {
                $idLookupError = 'TMDB error: ' . $res['error'];
            } else {
                $d = $res['data'];
                $idLookup = [
                    'source' => $source,
                    'id' => $lookupId,
                    'title' => $d['title'] ?? $d['name'] ?? '(untitled)',
                    'poster' => tmdb_poster_url($d['poster_path'] ?? null),
                    'score' => $d['vote_average'] ?? null,
                    'overview' => $d['overview'] ?? '',
                    'bucket' => $source === 'tmdb_movie' ? 'movie' : 'series',
                    'bucket_label' => $source === 'tmdb_movie' ? 'Movie' : 'Series',
                ];
            }
        }
    } elseif ($source === 'mal') {
        if (!$malId) {
            $idLookupError = 'No MAL Client ID set.';
        } else {
            $res = mal_details($malId, $lookupId);
            if (!$res['ok']) {
                $idLookupError = 'MAL error: ' . $res['error'];
            } else {
                $d = $res['data'];
                $bucket = mal_type_to_bucket($d['media_type'] ?? 'tv');
                $idLookup = [
                    'source' => 'mal',
                    'id' => $lookupId,
                    'title' => $d['title'] ?? '(untitled)',
                    'poster' => $d['main_picture']['large'] ?? $d['main_picture']['medium'] ?? '',
                    'score' => $d['mean'] ?? null,
                    'overview' => $d['synopsis'] ?? '',
                    'bucket' => $bucket,
                    'bucket_label' => $bucket === 'movie' ? 'Movie' : 'Series (' . h($d['media_type'] ?? 'tv') . ')',
                ];
            }
        }
    }
}

// ---- Import (fetch full details, then insert) ------------------------
$imported = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    $impSource = $_POST['source'];
    $impId = $_POST['id'];
    $impBucket = $_POST['bucket']; // auto-detected, not admin-chosen

    if ($impSource === 'tmdb_movie' || $impSource === 'tmdb_tv') {
        $type = $impSource === 'tmdb_movie' ? 'movie' : 'tv';
        $res = tmdb_details($tmdbKey, $type, $impId);
        if ($res['ok']) {
            $d = $res['data'];
            $title = $d['title'] ?? $d['name'] ?? 'Untitled';
            $runtime = $d['runtime'] ?? ($d['episode_run_time'][0] ?? 90);
            $status = ($d['status'] ?? '') === 'Ended' ? 'Completed' : 'Ongoing';
            $imported = do_import($pdo, $impBucket, $title, $d['overview'] ?? '', tmdb_poster_url($d['poster_path'] ?? null),
                round($d['vote_average'] ?? 0, 1), ['runtime' => $runtime, 'status' => $status]);
        } else {
            $searchError = 'TMDB error: ' . $res['error'];
        }
    } elseif ($impSource === 'mal') {
        $res = mal_details($malId, $impId);
        if ($res['ok']) {
            $d = $res['data'];
            $title = $d['title'] ?? 'Untitled';
            $status = ($d['status'] ?? '') === 'finished_airing' ? 'Completed' : 'Ongoing';
            $imported = do_import($pdo, $impBucket, $title, $d['synopsis'] ?? '',
                $d['main_picture']['large'] ?? $d['main_picture']['medium'] ?? '',
                round($d['mean'] ?? 0, 1), ['status' => $status]);
        } else {
            $searchError = 'MAL error: ' . $res['error'];
        }
    }
}

$admin_page_title = 'Import from TMDB/MAL';
$admin_active = 'import';
include __DIR__ . '/includes/layout_top.php';

function import_button($r) { ?>
  <form method="post" style="display:inline;">
    <input type="hidden" name="action" value="import">
    <input type="hidden" name="source" value="<?= h($r['source']) ?>">
    <input type="hidden" name="id" value="<?= h($r['id']) ?>">
    <input type="hidden" name="bucket" value="<?= h($r['bucket']) ?>">
    <button type="submit" class="btn primary" onclick="return confirm('Import \'<?= h(addslashes($r['title'])) ?>\' as a <?= h($r['bucket'] === 'movie' ? 'Movie' : 'Series') ?>?')">
      Import as <?= h($r['bucket'] === 'movie' ? 'Movie' : 'Series') ?>
    </button>
  </form>
<?php }
?>
  <p class="muted" style="margin-bottom:16px;">
    Search by title, or paste a known TMDB/MAL ID directly. The item type (Movie vs
    Series) is detected automatically from the source — MAL entries use MAL's own
    "media_type" field (TV/OVA/ONA/Special import as Series, Movie imports as Movie).
  </p>

  <?php if ($imported): ?>
    <div class="notice"><strong>✓</strong> Imported "<?= h($imported['title']) ?>" as a <?= h(ucfirst($imported['type'])) ?> —
      <a href="<?= $imported['type'] === 'movie' ? 'movies-form.php?id=' . $imported['id'] : 'series-form.php?id=' . $imported['id'] ?>">open it to finish editing</a>.
    </div>
  <?php endif; ?>

  <?php if (!$tmdbKey && !$malId): ?>
    <div class="notice" style="border-color:#713038;color:#ff9ca4;">
      No API keys configured yet. Go to <a href="settings.php">Site Settings</a> and add
      your TMDB API key and/or MAL Client ID first.
    </div>
  <?php endif; ?>

  <!-- Import by ID -->
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2>Import by ID</h2></div>
    <div class="panel-body">
      <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="hidden" name="action" value="lookup">
        <select name="source" style="background:#0f111b;border:1px solid var(--border);color:#e9ebf4;border-radius:8px;padding:9px 10px;">
          <option value="tmdb_movie" <?= $source === 'tmdb_movie' ? 'selected' : '' ?>>TMDB — Movie ID</option>
          <option value="tmdb_tv" <?= $source === 'tmdb_tv' ? 'selected' : '' ?>>TMDB — TV ID</option>
          <option value="mal" <?= $source === 'mal' ? 'selected' : '' ?>>MyAnimeList — Anime ID</option>
        </select>
        <input type="text" name="lookup_id" value="<?= h($_GET['lookup_id'] ?? '') ?>" placeholder="e.g. 59193" required
               style="width:160px;background:#0f111b;border:1px solid var(--border);color:#e9ebf4;border-radius:8px;padding:9px 10px;">
        <button type="submit" class="btn primary">Fetch</button>
      </form>

      <?php if ($idLookupError): ?>
        <div class="notice" style="border-color:#713038;color:#ff9ca4;margin-top:16px;"><?= h($idLookupError) ?></div>
      <?php endif; ?>

      <?php if ($idLookup): ?>
        <div style="display:flex;gap:16px;margin-top:16px;align-items:flex-start;">
          <?php if ($idLookup['poster']): ?><img src="<?= h($idLookup['poster']) ?>" alt="" style="width:90px;border-radius:6px;"><?php endif; ?>
          <div style="flex:1;">
            <strong><?= h($idLookup['title']) ?></strong>
            <span class="muted"> — detected as: <?= h($idLookup['bucket_label']) ?></span>
            <p class="muted" style="margin-top:6px;"><?= h(mb_strimwidth(strip_tags($idLookup['overview']), 0, 220, '...')) ?></p>
            <?php if ($idLookup['score'] !== null): ?><p class="muted">Score: <?= h(round($idLookup['score'], 2)) ?></p><?php endif; ?>
            <?php import_button($idLookup); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Search by title -->
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2>Search by Title</h2></div>
    <div class="panel-body">
      <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="source" style="background:#0f111b;border:1px solid var(--border);color:#e9ebf4;border-radius:8px;padding:9px 10px;">
          <option value="tmdb_movie" <?= $source === 'tmdb_movie' ? 'selected' : '' ?>>TMDB — Movies</option>
          <option value="tmdb_tv" <?= $source === 'tmdb_tv' ? 'selected' : '' ?>>TMDB — TV/Series</option>
          <option value="mal" <?= $source === 'mal' ? 'selected' : '' ?>>MyAnimeList — Anime</option>
        </select>
        <input type="text" name="q" value="<?= h($query) ?>" placeholder="Search title..." required
               style="flex:1;min-width:200px;background:#0f111b;border:1px solid var(--border);color:#e9ebf4;border-radius:8px;padding:9px 10px;">
        <button type="submit" class="btn primary">Search</button>
      </form>
    </div>
  </div>

  <?php if ($searchError): ?>
    <div class="notice" style="border-color:#713038;color:#ff9ca4;margin-bottom:20px;"><?= h($searchError) ?></div>
  <?php endif; ?>

  <?php if ($results): ?>
  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th></th><th>Title</th><th>Type</th><th>Year</th><th>Score</th><th>Overview</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr>
              <td style="width:60px;">
                <?php if ($r['poster']): ?><img src="<?= h($r['poster']) ?>" alt="" style="width:46px;border-radius:4px;"><?php endif; ?>
              </td>
              <td><?= h($r['title']) ?></td>
              <td class="muted"><?= h($r['bucket_label']) ?></td>
              <td class="muted"><?= h($r['year']) ?></td>
              <td><?= $r['score'] !== null ? h(round($r['score'], 1)) : '—' ?></td>
              <td class="muted"><?= h(mb_strimwidth(strip_tags($r['overview']), 0, 80, '...')) ?></td>
              <td style="white-space:nowrap;"><?php import_button($r); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php elseif ($query !== '' && !$searchError): ?>
    <p class="muted">No results for "<?= h($query) ?>".</p>
  <?php endif; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
