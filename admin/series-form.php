<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/api_clients.php';

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

$id = $_GET['id'] ?? null;
$series = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->execute([$id]);
    $series = $stmt->fetch();
}

// Server-side direct fetch if requested via GET ?fetch_source=...&fetch_id=...
if (isset($_GET['fetch_source']) && !empty($_GET['fetch_id'])) {
    $src = $_GET['fetch_source'];
    $fId = trim($_GET['fetch_id']);
    $meta = null;
    if ($src === 'mal') {
        $res = mal_details(get_setting('mal_client_id'), $fId);
        if ($res['ok']) $meta = parse_metadata_item('mal', $res['data']);
    } else {
        $res = tmdb_details(get_setting('tmdb_api_key'), 'tv', $fId);
        if ($res['ok']) $meta = parse_metadata_item('tmdb_tv', $res['data']);
    }
    if ($meta) {
        $genreIds = match_or_create_genres($pdo, $meta['genres']);
        $meta['genre_ids'] = $genreIds;
        $series = array_merge($series ?: [], $meta);
        if (isset($_GET['auto_save'])) {
            $_POST = $meta;
            $_SERVER['REQUEST_METHOD'] = 'POST';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $original_title = trim($_POST['original_title'] ?? '');
    $synopsis = trim($_POST['synopsis'] ?? '');
    $poster = handle_poster_upload('poster_file', trim($_POST['poster'] ?? ''));
    $genreIds = $_POST['genre_ids'] ?? [];
    $status = trim($_POST['status'] ?? 'Ongoing');
    $score = (float)($_POST['score'] ?? 0);
    $vote_count = (int)($_POST['vote_count'] ?? 0);
    $episodes_count = (int)($_POST['episodes_count'] ?? 0);
    $aired = trim($_POST['aired'] ?? '');
    $premiered = trim($_POST['premiered'] ?? '');
    $broadcast = trim($_POST['broadcast'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $rating = trim($_POST['rating'] ?? '');
    $quality = trim($_POST['quality'] ?? 'HD');
    $studio = trim($_POST['studio'] ?? '');
    $country = trim($_POST['country'] ?? 'Japan');
    $mal_id = !empty($_POST['mal_id']) ? (int)$_POST['mal_id'] : null;
    $tmdb_id = !empty($_POST['tmdb_id']) ? (int)$_POST['tmdb_id'] : null;
    $slug = slugify($title);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE series SET 
            title=?, slug=?, original_title=?, synopsis=?, poster=?, status=?, score=?, vote_count=?,
            episodes_count=?, aired=?, premiered=?, broadcast=?, source=?, duration=?, rating=?,
            quality=?, studio=?, country=?, mal_id=?, tmdb_id=?
            WHERE id=?");
        $stmt->execute([
            $title, $slug, $original_title, $synopsis, $poster, $status, $score, $vote_count,
            $episodes_count, $aired, $premiered, $broadcast, $source, $duration, $rating,
            $quality, $studio, $country, $mal_id, $tmdb_id, $id
        ]);
        set_item_genres($pdo, 'series', $id, $genreIds);
        admin_flash('Series updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO series (
            title, slug, original_title, synopsis, poster, status, score, vote_count,
            episodes_count, aired, premiered, broadcast, source, duration, rating,
            quality, studio, country, mal_id, tmdb_id
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $title, $slug, $original_title, $synopsis, $poster, $status, $score, $vote_count,
            $episodes_count, $aired, $premiered, $broadcast, $source, $duration, $rating,
            $quality, $studio, $country, $mal_id, $tmdb_id
        ]);
        $newId = (int)$pdo->lastInsertId();
        set_item_genres($pdo, 'series', $newId, $genreIds);
        admin_flash('Series created successfully.');
    }
    header('Location: series.php');
    exit;
}

$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();
$selectedGenreIds = $id ? array_column(get_item_genres($pdo, 'series', $id), 'id') : ($series['genre_ids'] ?? []);

$admin_page_title = $series ? 'Edit Series: ' . h($series['title'] ?? '') : 'Add Series';
$admin_active = 'series';
include __DIR__ . '/includes/layout_top.php';
?>

  <!-- QUICK FETCH TOOLBAR FROM MAL / TMDB -->
  <div class="panel" style="margin-bottom:20px;background:#151622;border:1px solid var(--border);">
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
      <h2 style="font-size:16px;">⚡ Auto-Fetch Metadata (MyAnimeList / TMDB)</h2>
      <span class="muted" style="font-size:12px;">Auto-fills all information, alternative titles, statistics, and genres</span>
    </div>
    <div class="panel-body">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <select id="fetchSource" style="padding:8px 12px;background:#0d0e17;color:#fff;border:1px solid #333;border-radius:6px;">
          <option value="mal">MyAnimeList (MAL / Jikan)</option>
          <option value="tmdb_tv">The Movie Database (TMDB TV)</option>
        </select>
        <input type="text" id="fetchQuery" placeholder="Enter MAL ID (e.g. 5114) or title / TMDB ID..." 
               style="flex:1;min-width:240px;background:#0d0e17;color:#fff;border:1px solid #333;border-radius:6px;padding:8px 12px;">
        <button type="button" id="btnAutoFill" class="btn primary" onclick="runFetch(false)">
          ⚡ Fetch & Auto-Fill
        </button>
        <button type="button" id="btnAutoSave" class="btn" style="background:#2a2b3d;" onclick="runFetch(true)">
          ⚡ Fetch & Save Directly
        </button>
      </div>
      <div id="fetchStatus" style="margin-top:10px;font-size:13px;display:none;"></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form id="seriesForm" method="post" enctype="multipart/form-data">
        <div class="form-grid">
          
          <!-- Basic Info -->
          <div class="field">
            <label>Title <span style="color:var(--primary,#e53637)">*</span></label>
            <input type="text" name="title" id="inpTitle" required value="<?= h($series['title'] ?? '') ?>" placeholder="e.g. Fullmetal Alchemist: Brotherhood">
          </div>

          <div class="field">
            <label>Alternative / Japanese Title (Original Title)</label>
            <input type="text" name="original_title" id="inpOriginalTitle" value="<?= h($series['original_title'] ?? '') ?>" placeholder="e.g. 鋼の錬金術師 FULLMETAL ALCHEMIST">
          </div>

          <div class="field">
            <label>Status</label>
            <select name="status" id="inpStatus">
              <?php foreach (['Ongoing', 'Completed', 'Upcoming'] as $st): ?>
                <option <?= ($series['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Quality</label>
            <select name="quality" id="inpQuality">
              <?php foreach (['4K', 'Blu-ray', 'FHD', 'HD', 'MD', 'SD'] as $q): ?>
                <option <?= ($series['quality'] ?? 'HD') === $q ? 'selected' : '' ?>><?= $q ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Score (0-10)</label>
            <input type="number" step="0.1" min="0" max="10" name="score" id="inpScore" value="<?= h($series['score'] ?? '0') ?>">
          </div>

          <div class="field">
            <label>Scored by (Users / Vote Count)</label>
            <input type="number" name="vote_count" id="inpVoteCount" value="<?= h($series['vote_count'] ?? '0') ?>">
          </div>

          <div class="field">
            <label>Episodes Count</label>
            <input type="number" name="episodes_count" id="inpEpisodesCount" value="<?= h($series['episodes_count'] ?? '0') ?>" placeholder="e.g. 24">
          </div>

          <div class="field">
            <label>Duration</label>
            <input type="text" name="duration" id="inpDuration" value="<?= h($series['duration'] ?? '') ?>" placeholder="e.g. 24 min. per ep.">
          </div>

          <div class="field">
            <label>Aired Date Range</label>
            <input type="text" name="aired" id="inpAired" value="<?= h($series['aired'] ?? '') ?>" placeholder="e.g. Apr 5, 2009 to Jul 4, 2010">
          </div>

          <div class="field">
            <label>Premiered Season</label>
            <input type="text" name="premiered" id="inpPremiered" value="<?= h($series['premiered'] ?? '') ?>" placeholder="e.g. Spring 2009">
          </div>

          <div class="field">
            <label>Broadcast Schedule</label>
            <input type="text" name="broadcast" id="inpBroadcast" value="<?= h($series['broadcast'] ?? '') ?>" placeholder="e.g. Sundays at 17:00 (JST)">
          </div>

          <div class="field">
            <label>Source</label>
            <input type="text" name="source" id="inpSource" value="<?= h($series['source'] ?? '') ?>" placeholder="e.g. Manga, Light novel, Original">
          </div>

          <div class="field">
            <label>Rating / Age Classification</label>
            <input type="text" name="rating" id="inpRating" value="<?= h($series['rating'] ?? '') ?>" placeholder="e.g. PG-13, R - 17+">
          </div>

          <div class="field">
            <label>Studio</label>
            <input type="text" name="studio" id="inpStudio" value="<?= h($series['studio'] ?? '') ?>" placeholder="e.g. Bones, MAPPA, Ufotable">
          </div>

          <div class="field">
            <label>Country</label>
            <input type="text" name="country" id="inpCountry" value="<?= h($series['country'] ?? 'Japan') ?>" placeholder="e.g. Japan">
          </div>

          <div class="field">
            <label>MAL ID</label>
            <input type="number" name="mal_id" id="inpMalId" value="<?= h($series['mal_id'] ?? '') ?>" placeholder="e.g. 5114">
          </div>

          <div class="field">
            <label>TMDB ID</label>
            <input type="number" name="tmdb_id" id="inpTmdbId" value="<?= h($series['tmdb_id'] ?? '') ?>" placeholder="e.g. 46261">
          </div>

          <!-- Genres Multi-Select -->
          <div class="field full">
            <label>Genres & Themes (Selected automatically when fetching)</label>
            <div id="genresContainer" style="display:flex;flex-wrap:wrap;gap:6px 16px;background:#0f111b;border:1px solid var(--border);border-radius:8px;padding:12px;max-height:180px;overflow-y:auto;">
              <?php foreach ($genres as $g): ?>
                <label style="display:flex;align-items:center;gap:6px;font-weight:normal;min-width:140px;" data-genre-id="<?= (int)$g['id'] ?>">
                  <input type="checkbox" name="genre_ids[]" value="<?= (int)$g['id'] ?>" <?= in_array($g['id'], $selectedGenreIds) ? 'checked' : '' ?> style="width:15px;height:15px;">
                  <span><?= h($g['name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Poster URL and File -->
          <div class="field">
            <label>Poster Image URL</label>
            <input type="text" name="poster" id="inpPoster" placeholder="img/trending/trend-1.jpg or https://..." value="<?= h($series['poster'] ?? '') ?>">
          </div>
          <div class="field">
            <label>...or Upload Image</label>
            <input type="file" name="poster_file" accept="image/*">
          </div>

          <div class="field full" id="posterPreviewWrap" style="<?= empty($series['poster']) ? 'display:none;' : '' ?>">
            <label>Poster Preview</label>
            <img id="posterPreview" src="<?= !empty($series['poster']) ? ((strpos($series['poster'], 'http') === 0) ? h($series['poster']) : '../' . h($series['poster'])) : '' ?>" alt="" style="max-height:140px;border-radius:6px;border:1px solid #333;">
          </div>

          <!-- Synopsis -->
          <div class="field full">
            <label>Synopsis / Description</label>
            <textarea name="synopsis" id="inpSynopsis" rows="5"><?= h($series['synopsis'] ?? '') ?></textarea>
          </div>

        </div>

        <div style="margin-top:20px;display:flex;gap:10px;">
          <button type="submit" class="btn primary"><?= $series ? 'Save Changes' : 'Create Series' ?></button>
          <a href="series.php" class="btn">Cancel</a>
        </div>
      </form>
    </div>
  </div>

<script>
async function runFetch(autoSave) {
  const source = document.getElementById('fetchSource').value;
  const query = document.getElementById('fetchQuery').value.trim();
  const statusBox = document.getElementById('fetchStatus');

  if (!query) {
    statusBox.style.display = 'block';
    statusBox.style.color = '#ff7875';
    statusBox.innerHTML = 'Please enter an ID or title to fetch.';
    return;
  }

  statusBox.style.display = 'block';
  statusBox.style.color = '#faad14';
  statusBox.innerHTML = 'Fetching metadata from ' + (source === 'mal' ? 'MyAnimeList' : 'TMDB') + '...';

  try {
    const res = await fetch('api_fetch.php?source=' + encodeURIComponent(source) + '&id=' + encodeURIComponent(query));
    const json = await res.json();

    if (!json.ok) {
      statusBox.style.color = '#ff7875';
      statusBox.innerHTML = 'Error: ' + (json.error || 'Could not fetch data');
      return;
    }

    const d = json.data;
    statusBox.style.color = '#52c41a';
    statusBox.innerHTML = '✓ Successfully fetched data for <strong>' + (d.title || '') + '</strong>!';

    // Fill form fields directly
    if (d.title) document.getElementById('inpTitle').value = d.title;
    if (d.original_title) document.getElementById('inpOriginalTitle').value = d.original_title;
    if (d.status) document.getElementById('inpStatus').value = d.status;
    if (d.quality) document.getElementById('inpQuality').value = d.quality;
    if (d.score) document.getElementById('inpScore').value = d.score;
    if (d.vote_count) document.getElementById('inpVoteCount').value = d.vote_count;
    if (d.episodes_count) document.getElementById('inpEpisodesCount').value = d.episodes_count;
    if (d.duration) document.getElementById('inpDuration').value = d.duration;
    if (d.aired) document.getElementById('inpAired').value = d.aired;
    if (d.premiered) document.getElementById('inpPremiered').value = d.premiered;
    if (d.broadcast) document.getElementById('inpBroadcast').value = d.broadcast;
    if (d.source) document.getElementById('inpSource').value = d.source;
    if (d.rating) document.getElementById('inpRating').value = d.rating;
    if (d.studio) document.getElementById('inpStudio').value = d.studio;
    if (d.country) document.getElementById('inpCountry').value = d.country;
    if (d.mal_id) document.getElementById('inpMalId').value = d.mal_id;
    if (d.tmdb_id) document.getElementById('inpTmdbId').value = d.tmdb_id;
    if (d.synopsis) document.getElementById('inpSynopsis').value = d.synopsis;

    if (d.poster) {
      document.getElementById('inpPoster').value = d.poster;
      const preview = document.getElementById('posterPreview');
      preview.src = d.poster;
      document.getElementById('posterPreviewWrap').style.display = 'block';
    }

    // Auto-select genres
    if (Array.isArray(d.genre_ids)) {
      const checkboxes = document.querySelectorAll('#genresContainer input[type="checkbox"]');
      checkboxes.forEach(cb => {
        if (d.genre_ids.includes(parseInt(cb.value))) {
          cb.checked = true;
        }
      });
    }

    if (autoSave) {
      statusBox.innerHTML += ' Saving now...';
      document.getElementById('seriesForm').submit();
    }
  } catch (err) {
    statusBox.style.color = '#ff7875';
    statusBox.innerHTML = 'Request failed: ' + err.message;
  }
}
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
