<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/api_clients.php';

function slugify_movie($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

$id = $_GET['id'] ?? null;
$movie = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    $movie = $stmt->fetch();
}

// Quick delete video source directly from movie edit page
if (isset($_GET['delete_source']) && $id) {
    $pdo->prepare("DELETE FROM video_sources WHERE id = ? AND movie_id = ?")->execute([(int)$_GET['delete_source'], $id]);
    admin_flash('Video source deleted.');
    header('Location: movies-form.php?id=' . (int)$id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $original_title = trim($_POST['original_title'] ?? '');
    $synopsis = trim($_POST['synopsis'] ?? '');
    $poster = handle_poster_upload('poster_file', trim($_POST['poster'] ?? ''));
    $genreIds = $_POST['genre_ids'] ?? [];
    $runtime = (int)($_POST['runtime'] ?? 90);
    $score = (float)($_POST['score'] ?? 0);
    $vote_count = (int)($_POST['vote_count'] ?? 0);
    $user_score_percent = (int)($_POST['user_score_percent'] ?? round($score * 10));
    $release_date = trim($_POST['release_date'] ?? '');
    $original_language = trim($_POST['original_language'] ?? '');
    $quality = trim($_POST['quality'] ?? 'HD');
    $rating = trim($_POST['rating'] ?? '');
    $mal_id = !empty($_POST['mal_id']) ? (int)$_POST['mal_id'] : null;
    $tmdb_id = !empty($_POST['tmdb_id']) ? (int)$_POST['tmdb_id'] : null;
    $slug = slugify_movie($title);
    if (function_exists('ensure_database_schema')) {
        ensure_database_schema($pdo);
    }

    $movieId = $id;
    if ($id) {
        $stmt = $pdo->prepare("UPDATE movies SET 
            title=?, slug=?, original_title=?, synopsis=?, poster=?, runtime=?, score=?,
            vote_count=?, user_score_percent=?, release_date=?, original_language=?,
            quality=?, rating=?, mal_id=?, tmdb_id=?
            WHERE id=?");
        $stmt->execute([
            $title, $slug, $original_title, $synopsis, $poster, $runtime, $score,
            $vote_count, $user_score_percent, $release_date, $original_language,
            $quality, $rating, $mal_id, $tmdb_id, $id
        ]);
        set_item_genres($pdo, 'movie', $id, $genreIds);
        admin_flash('Movie updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO movies (
            title, slug, original_title, synopsis, poster, runtime, score,
            vote_count, user_score_percent, release_date, original_language,
            quality, rating, mal_id, tmdb_id
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $title, $slug, $original_title, $synopsis, $poster, $runtime, $score,
            $vote_count, $user_score_percent, $release_date, $original_language,
            $quality, $rating, $mal_id, $tmdb_id
        ]);
        $movieId = (int)$pdo->lastInsertId();
        set_item_genres($pdo, 'movie', $movieId, $genreIds);
        admin_flash('Movie created successfully.');
    }

    // Attach Video Source directly if provided
    $sourceUrl = trim($_POST['source_url'] ?? '');
    if ($sourceUrl !== '' && $movieId) {
        $serverName = trim($_POST['source_server'] ?? 'Server 1') ?: 'Server 1';
        $sourceQuality = trim($_POST['source_quality'] ?? 'HD') ?: 'HD';
        $pdo->prepare("INSERT INTO video_sources (movie_id, name, quality, url, priority, enabled) VALUES (?,?,?,?,1,1)")
            ->execute([$movieId, $serverName, $sourceQuality, $sourceUrl]);
    }

    header('Location: movies.php');
    exit;
}

$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();
$selectedGenreIds = $id ? array_column(get_item_genres($pdo, 'movie', $id), 'id') : [];

// Fetch existing video sources for this movie
$existingSources = [];
if ($id) {
    $srcStmt = $pdo->prepare("SELECT * FROM video_sources WHERE movie_id = ? ORDER BY priority ASC, id ASC");
    $srcStmt->execute([$id]);
    $existingSources = $srcStmt->fetchAll();
}

$admin_page_title = $movie ? 'Edit Movie: ' . h($movie['title']) : 'Add Movie';
$admin_active = 'movies';
include __DIR__ . '/includes/layout_top.php';
?>

  <!-- QUICK FETCH TOOLBAR -->
  <div class="panel" style="margin-bottom:20px;background:#151622;border:1px solid var(--border);">
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
      <h2 style="font-size:16px;">⚡ Auto-Fetch Metadata (TMDB / MyAnimeList)</h2>
      <span class="muted" style="font-size:12px;">Pulls title, release date, runtime, rating, genres & overview automatically</span>
    </div>
    <div class="panel-body">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <select id="fetchSource" style="padding:8px 12px;background:#0d0e17;color:#fff;border:1px solid #333;border-radius:6px;">
          <option value="tmdb_movie">The Movie Database (TMDB Movie)</option>
          <option value="mal">MyAnimeList (Anime Movie)</option>
        </select>
        <input type="text" id="fetchQuery" placeholder="Enter TMDB ID (e.g. 550) or title / MAL ID..." 
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
      <form id="movieForm" method="post" enctype="multipart/form-data">
        <div class="form-grid">

          <div class="field">
            <label>Title <span style="color:var(--primary,#e53637)">*</span></label>
            <input type="text" name="title" id="inpTitle" required value="<?= h($movie['title'] ?? '') ?>" placeholder="e.g. Your Name.">
          </div>

          <div class="field">
            <label>Original Title (Japanese / Original Language)</label>
            <input type="text" name="original_title" id="inpOriginalTitle" value="<?= h($movie['original_title'] ?? '') ?>" placeholder="e.g. 君の名は。">
          </div>

          <div class="field">
            <label>Release Date (YYYY-MM-DD)</label>
            <input type="text" name="release_date" id="inpReleaseDate" value="<?= h($movie['release_date'] ?? '') ?>" placeholder="e.g. 2016-08-26">
          </div>

          <div class="field">
            <label>Runtime (minutes)</label>
            <input type="number" name="runtime" id="inpRuntime" value="<?= h($movie['runtime'] ?? '90') ?>">
          </div>

          <div class="field">
            <label>Score / Vote Average (0-10)</label>
            <input type="number" step="0.1" min="0" max="10" name="score" id="inpScore" value="<?= h($movie['score'] ?? '0') ?>">
          </div>

          <div class="field">
            <label>Vote Count</label>
            <input type="number" name="vote_count" id="inpVoteCount" value="<?= h($movie['vote_count'] ?? '0') ?>">
          </div>

          <div class="field">
            <label>User Score Percent (0-100 %)</label>
            <input type="number" name="user_score_percent" id="inpUserScorePercent" value="<?= h($movie['user_score_percent'] ?? '0') ?>" placeholder="e.g. 85">
          </div>

          <div class="field">
            <label>Quality</label>
            <select name="quality" id="inpQuality">
              <?php foreach (['4K', 'Blu-ray', 'FHD', 'HD', 'MD', 'SD'] as $q): ?>
                <option <?= ($movie['quality'] ?? 'HD') === $q ? 'selected' : '' ?>><?= $q ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Original Language</label>
            <input type="text" name="original_language" id="inpOriginalLanguage" value="<?= h($movie['original_language'] ?? '') ?>" placeholder="e.g. ja, en">
          </div>

          <div class="field">
            <label>Rating / Age Classification</label>
            <input type="text" name="rating" id="inpRating" value="<?= h($movie['rating'] ?? '') ?>" placeholder="e.g. PG-13, R">
          </div>

          <div class="field">
            <label>TMDB ID</label>
            <input type="number" name="tmdb_id" id="inpTmdbId" value="<?= h($movie['tmdb_id'] ?? '') ?>" placeholder="e.g. 372058">
          </div>

          <div class="field">
            <label>MAL ID</label>
            <input type="number" name="mal_id" id="inpMalId" value="<?= h($movie['mal_id'] ?? '') ?>" placeholder="e.g. 32281">
          </div>

          <!-- Genres Multi-Select -->
          <div class="field full">
            <label>Genres (Selected automatically when fetching)</label>
            <div id="genresContainer" style="display:flex;flex-wrap:wrap;gap:6px 16px;background:#0f111b;border:1px solid var(--border);border-radius:8px;padding:12px;max-height:180px;overflow-y:auto;">
              <?php foreach ($genres as $g): ?>
                <label style="display:flex;align-items:center;gap:6px;font-weight:normal;min-width:140px;">
                  <input type="checkbox" name="genre_ids[]" value="<?= (int)$g['id'] ?>" <?= in_array($g['id'], $selectedGenreIds) ? 'checked' : '' ?> style="width:15px;height:15px;">
                  <span><?= h($g['name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- VIDEO SOURCE DIRECTLY FROM MOVIES FORM -->
          <div class="field full" style="background:#13151f;padding:16px;border-radius:8px;border:1px solid #28283c;margin-top:10px;">
            <div style="font-weight:600;margin-bottom:8px;color:#fff;display:flex;align-items:center;gap:8px;">
              <span>🎬</span> Video Source (Add Direct Movie Stream)
            </div>
            <p class="muted" style="margin-bottom:12px;font-size:13px;">
              Add a video stream or embed player directly here. When created, users will immediately be able to watch this movie!
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr 2fr;gap:12px;">
              <div>
                <label style="font-size:12px;">Server Name</label>
                <input type="text" name="source_server" value="Server 1" placeholder="e.g. Server 1">
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
                <input type="text" name="source_url" placeholder="https://domain.com/movie.mp4 or embed url">
              </div>
            </div>

            <?php if ($id && !empty($existingSources)): ?>
              <div style="margin-top:16px;border-top:1px solid #28283c;padding-top:12px;">
                <label style="font-size:13px;font-weight:600;color:#ccc;">Current Attached Video Sources (<?= count($existingSources) ?>):</label>
                <table class="table" style="margin-top:8px;">
                  <thead><tr><th>Server</th><th>Quality</th><th>URL</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($existingSources as $src): ?>
                      <tr>
                        <td><?= h($src['name']) ?></td>
                        <td><span class="badge"><?= h($src['quality']) ?></span></td>
                        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;"><?= h($src['url']) ?></td>
                        <td style="text-align:right;">
                          <a href="movies-form.php?id=<?= (int)$id ?>&delete_source=<?= (int)$src['id'] ?>"
                             class="btn danger" style="padding:4px 8px;font-size:12px;" onclick="return confirm('Delete this video source?')">Delete</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Poster URL and File -->
          <div class="field">
            <label>Poster Image URL</label>
            <input type="text" name="poster" id="inpPoster" placeholder="img/popular/popular-1.jpg or https://..." value="<?= h($movie['poster'] ?? '') ?>">
          </div>
          <div class="field">
            <label>...or Upload Image File</label>
            <input type="file" name="poster_file" accept="image/*">
          </div>

          <div class="field full" id="posterPreviewWrap" style="<?= empty($movie['poster']) ? 'display:none;' : '' ?>">
            <label>Poster Preview</label>
            <img id="posterPreview" src="<?= !empty($movie['poster']) ? ((strpos($movie['poster'], 'http') === 0) ? h($movie['poster']) : '../' . h($movie['poster'])) : '' ?>" alt="" style="max-height:140px;border-radius:6px;border:1px solid #333;">
          </div>

          <!-- Synopsis -->
          <div class="field full">
            <label>Synopsis / Plot Overview</label>
            <textarea name="synopsis" id="inpSynopsis" rows="5"><?= h($movie['synopsis'] ?? '') ?></textarea>
          </div>

        </div>

        <div style="margin-top:20px;display:flex;gap:10px;">
          <button type="submit" class="btn primary"><?= $movie ? 'Save Changes' : 'Create Movie' ?></button>
          <a href="movies.php" class="btn">Cancel</a>
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

    // Fill fields directly
    if (d.title) document.getElementById('inpTitle').value = d.title;
    if (d.original_title) document.getElementById('inpOriginalTitle').value = d.original_title;
    if (d.release_date) document.getElementById('inpReleaseDate').value = d.release_date;
    if (d.runtime) document.getElementById('inpRuntime').value = d.runtime;
    if (d.score) document.getElementById('inpScore').value = d.score;
    if (d.vote_count) document.getElementById('inpVoteCount').value = d.vote_count;
    if (d.user_score_percent) document.getElementById('inpUserScorePercent').value = d.user_score_percent;
    if (d.quality) document.getElementById('inpQuality').value = d.quality;
    if (d.original_language) document.getElementById('inpOriginalLanguage').value = d.original_language;
    if (d.rating) document.getElementById('inpRating').value = d.rating;
    if (d.tmdb_id) document.getElementById('inpTmdbId').value = d.tmdb_id;
    if (d.mal_id) document.getElementById('inpMalId').value = d.mal_id;
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
      document.getElementById('movieForm').submit();
    }
  } catch (err) {
    statusBox.style.color = '#ff7875';
    statusBox.innerHTML = 'Request failed: ' + err.message;
  }
}
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
