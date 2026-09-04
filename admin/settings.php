<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$activeTab = $_GET['tab'] ?? ($_POST['tab'] ?? 'general');

// Handle image upload helper
function handle_settings_upload($fileKey) {
    if (empty($_FILES[$fileKey]['tmp_name']) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/svg+xml', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fileKey]['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) {
        return null;
    }

    $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION) ?: 'png';
    $targetDir = __DIR__ . '/../uploads';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $filename = 'site_' . $fileKey . '_' . time() . '.' . $ext;
    $dest = $targetDir . '/' . $filename;
    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
        return 'uploads/' . $filename;
    }
    return null;
}

$upsert = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

// 1. General Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'general') {
    $upsert->execute(['site_name', trim($_POST['site_name'] ?? 'AniStream')]);
    
    // Icon
    $uploadedIcon = handle_settings_upload('site_icon_file');
    if ($uploadedIcon) {
        $upsert->execute(['site_icon', $uploadedIcon]);
    } elseif (isset($_POST['site_icon'])) {
        $upsert->execute(['site_icon', trim($_POST['site_icon'])]);
    }

    // Logo
    $uploadedLogo = handle_settings_upload('site_logo_file');
    if ($uploadedLogo) {
        $upsert->execute(['site_logo', $uploadedLogo]);
    } elseif (isset($_POST['site_logo'])) {
        $upsert->execute(['site_logo', trim($_POST['site_logo'])]);
    }

    // SEO
    $upsert->execute(['seo_meta_title', trim($_POST['seo_meta_title'] ?? '')]);
    $upsert->execute(['seo_meta_description', trim($_POST['seo_meta_description'] ?? '')]);
    $upsert->execute(['seo_meta_keywords', trim($_POST['seo_meta_keywords'] ?? '')]);

    admin_flash('General settings updated successfully.');
    header('Location: settings.php?tab=general');
    exit;
}

// 2. API Keys Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'api_keys') {
    $upsert->execute(['tmdb_api_key', trim($_POST['tmdb_api_key'] ?? '')]);
    $upsert->execute(['mal_client_id', trim($_POST['mal_client_id'] ?? '')]);

    admin_flash('API keys saved.');
    header('Location: settings.php?tab=api_keys');
    exit;
}

// 3. Layout Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'layout') {
    // Homepage Sidebar
    $upsert->execute(['sidebar_enabled', isset($_POST['sidebar_enabled']) ? '1' : '0']);
    $upsert->execute(['sidebar_content', in_array($_POST['sidebar_content'] ?? '', ['views_only', 'views_and_blog'], true) ? $_POST['sidebar_content'] : 'views_and_blog']);

    // Slider
    $upsert->execute(['slider_enabled', isset($_POST['slider_enabled']) ? '1' : '0']);
    $upsert->execute(['slider_show_genres', isset($_POST['slider_show_genres']) ? '1' : '0']);
    $upsert->execute(['slider_show_synopsis_btn', isset($_POST['slider_show_synopsis_btn']) ? '1' : '0']);

    admin_flash('Layout settings saved.');
    header('Location: settings.php?tab=layout');
    exit;
}

// 4. Media Cards Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'media_cards') {
    // Homepage Cards
    $upsert->execute(['home_card_show_genres', isset($_POST['home_card_show_genres']) ? '1' : '0']);
    $upsert->execute(['home_card_show_type', isset($_POST['home_card_show_type']) ? '1' : '0']);
    $upsert->execute(['home_card_show_views', isset($_POST['home_card_show_views']) ? '1' : '0']);
    $upsert->execute(['home_card_show_duration', isset($_POST['home_card_show_duration']) ? '1' : '0']);

    // Other Pages Cards
    $upsert->execute(['other_card_show_genres', isset($_POST['other_card_show_genres']) ? '1' : '0']);
    $upsert->execute(['other_card_show_type', isset($_POST['other_card_show_type']) ? '1' : '0']);
    $upsert->execute(['other_card_show_views', isset($_POST['other_card_show_views']) ? '1' : '0']);
    $upsert->execute(['other_card_show_duration', isset($_POST['other_card_show_duration']) ? '1' : '0']);
    $upsert->execute(['other_card_show_score', isset($_POST['other_card_show_score']) ? '1' : '0']);

    admin_flash('Media card settings saved.');
    header('Location: settings.php?tab=media_cards');
    exit;
}

$settings = [];
foreach ($pdo->query("SELECT * FROM site_settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$admin_page_title = 'Site Settings';
$admin_active = 'settings';
include __DIR__ . '/includes/layout_top.php';
?>

<div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:12px;flex-wrap:wrap;">
  <a href="?tab=general" class="btn <?= $activeTab === 'general' ? 'primary' : '' ?>">General</a>
  <a href="?tab=api_keys" class="btn <?= $activeTab === 'api_keys' ? 'primary' : '' ?>">API Keys (TMDB / MAL)</a>
  <a href="?tab=layout" class="btn <?= $activeTab === 'layout' ? 'primary' : '' ?>">Layout</a>
  <a href="?tab=media_cards" class="btn <?= $activeTab === 'media_cards' ? 'primary' : '' ?>">Media Cards</a>
</div>

<?php if ($activeTab === 'general'): ?>
  <!-- SECTION 1: GENERAL -->
  <div class="panel">
    <div class="panel-head"><h2>General Settings</h2></div>
    <div class="panel-body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="form" value="general">
        <input type="hidden" name="tab" value="general">

        <div class="field" style="max-width:500px;margin-bottom:18px;">
          <label>Site Name</label>
          <input type="text" name="site_name" value="<?= h($settings['site_name'] ?? 'AniStream') ?>" required>
          <small class="muted">Displayed in the header, title tags, and admin panel.</small>
        </div>

        <div class="form-grid" style="margin-bottom:20px;">
          <div class="field">
            <label>Site Icon (Browser tab + Top of Admin Panel)</label>
            <input type="text" name="site_icon" placeholder="img/favicon.png or URL" value="<?= h($settings['site_icon'] ?? '') ?>">
            <div style="margin-top:8px;">
              <small class="muted">Or upload a new icon image (PNG, ICO, SVG):</small><br>
              <input type="file" name="site_icon_file" accept="image/*">
            </div>
            <?php if (!empty($settings['site_icon'])): ?>
              <div style="margin-top:10px;display:flex;align-items:center;gap:10px;">
                <span class="muted">Current:</span>
                <img src="<?= (strpos($settings['site_icon'], 'http') === 0) ? h($settings['site_icon']) : '../' . ltrim(h($settings['site_icon']), '/') ?>" style="width:28px;height:28px;border-radius:4px;object-fit:cover;border:1px solid #444;">
              </div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label>Site Logo (Website Header & Footer)</label>
            <input type="text" name="site_logo" placeholder="img/logo.png or URL" value="<?= h($settings['site_logo'] ?? '') ?>">
            <div style="margin-top:8px;">
              <small class="muted">Or upload a new logo image (PNG, WEBP, SVG):</small><br>
              <input type="file" name="site_logo_file" accept="image/*">
            </div>
            <?php if (!empty($settings['site_logo'])): ?>
              <div style="margin-top:10px;display:flex;align-items:center;gap:10px;">
                <span class="muted">Current:</span>
                <img src="<?= (strpos($settings['site_logo'], 'http') === 0) ? h($settings['site_logo']) : '../' . ltrim(h($settings['site_logo']), '/') ?>" style="max-height:36px;background:#111;padding:4px;border-radius:4px;">
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="section-title">SEO (Search Engine Optimization)</div>
        <div class="field" style="margin-bottom:14px;">
          <label>Meta Title</label>
          <input type="text" name="seo_meta_title" placeholder="AniStream - Watch Anime & Movies Online Free" value="<?= h($settings['seo_meta_title'] ?? '') ?>">
        </div>

        <div class="field" style="margin-bottom:14px;">
          <label>Meta Description</label>
          <textarea name="seo_meta_description" rows="3" placeholder="Watch anime series and movies online in HD quality for free..."><?= h($settings['seo_meta_description'] ?? '') ?></textarea>
        </div>

        <div class="field" style="margin-bottom:20px;">
          <label>Meta Keywords</label>
          <input type="text" name="seo_meta_keywords" placeholder="anime, watch anime, anime streaming, movies, episodes" value="<?= h($settings['seo_meta_keywords'] ?? '') ?>">
        </div>

        <div>
          <button type="submit" class="btn primary">Save General Settings</button>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($activeTab === 'api_keys'): ?>
  <!-- SECTION 2: API KEYS -->
  <div class="panel">
    <div class="panel-head"><h2>API Keys — TMDB / MyAnimeList</h2></div>
    <div class="panel-body">
      <p class="muted" style="margin-bottom:16px;">
        Used by the <a href="import.php">Import (TMDB/MAL)</a> and series/movie forms to pull in metadata automatically. Keys remain private to your database. Note that MAL fetching will also use free public API fallback if no Client ID is provided.
      </p>
      <form method="post">
        <input type="hidden" name="form" value="api_keys">
        <input type="hidden" name="tab" value="api_keys">

        <div class="form-grid">
          <div class="field">
            <label>TMDB API Key (v3 auth)</label>
            <input type="text" name="tmdb_api_key" placeholder="e.g. 3a1b2c3d4e5f..." value="<?= h($settings['tmdb_api_key'] ?? '') ?>">
          </div>
          <div class="field">
            <label>MAL Client ID</label>
            <input type="text" name="mal_client_id" placeholder="Your MyAnimeList API Client ID" value="<?= h($settings['mal_client_id'] ?? '') ?>">
          </div>
        </div>
        <div style="margin-top:20px;">
          <button type="submit" class="btn primary">Save API Keys</button>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($activeTab === 'layout'): ?>
  <!-- SECTION 3: LAYOUT -->
  <div class="panel">
    <div class="panel-head"><h2>Layout Settings</h2></div>
    <div class="panel-body">
      <form method="post">
        <input type="hidden" name="form" value="layout">
        <input type="hidden" name="tab" value="layout">

        <div class="section-title" style="margin-top:0;">Homepage Sidebar</div>
        <div class="switch" style="margin-bottom:14px;">
          <div>
            <strong>Sidebar Visibility</strong>
            <small>Show or hide the sidebar on the homepage. When hidden, the media cards take up full width (6 per row instead of 4).</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="sidebar_enabled" <?= ($settings['sidebar_enabled'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="field" style="max-width:450px;margin-bottom:24px;">
          <label>What to Show in Sidebar</label>
          <select name="sidebar_content">
            <option value="views_and_blog" <?= ($settings['sidebar_content'] ?? 'views_and_blog') === 'views_and_blog' ? 'selected' : '' ?>>
              Most Viewed Series + Latest Blog Posts
            </option>
            <option value="views_only" <?= ($settings['sidebar_content'] ?? '') === 'views_only' ? 'selected' : '' ?>>
              Most Viewed Series + Most Viewed Movies
            </option>
          </select>
        </div>

        <div class="section-title">Hero Slider</div>
        <div class="switch" style="margin-bottom:14px;">
          <div>
            <strong>Slider Visibility</strong>
            <small>Show or hide the hero carousel banner at the top of the homepage.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="slider_enabled" <?= ($settings['slider_enabled'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:14px;">
          <div>
            <strong>Show Genres on Slides</strong>
            <small>Display the genre pill badge over the slide title.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="slider_show_genres" <?= ($settings['slider_show_genres'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:20px;">
          <div>
            <strong>Show Synopsis & "Watch Now" Button</strong>
            <small>Display the description excerpt and the Watch Now CTA button on hero slides.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="slider_show_synopsis_btn" <?= ($settings['slider_show_synopsis_btn'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div>
          <button type="submit" class="btn primary">Save Layout Settings</button>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($activeTab === 'media_cards'): ?>
  <!-- SECTION 4: MEDIA CARDS -->
  <div class="panel">
    <div class="panel-head"><h2>Media Cards Configuration</h2></div>
    <div class="panel-body">
      <form method="post">
        <input type="hidden" name="form" value="media_cards">
        <input type="hidden" name="tab" value="media_cards">

        <div class="section-title" style="margin-top:0;">Homepage Cards</div>
        <p class="muted" style="margin-bottom:14px;">Control which elements appear on media items displayed on the homepage sections.</p>

        <div class="switch" style="margin-bottom:12px;">
          <div>
            <strong>Genres Tag</strong>
            <small>Display genre labels underneath the card thumbnail.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="home_card_show_genres" <?= ($settings['home_card_show_genres'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:12px;">
          <div>
            <strong>Media Type Badge</strong>
            <small>Display the "SERIES" or "MOVIE" badge on top of the thumbnail.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="home_card_show_type" <?= ($settings['home_card_show_type'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:12px;">
          <div>
            <strong>Views Count</strong>
            <small>Display the view counter badge with an eye icon on the card.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="home_card_show_views" <?= ($settings['home_card_show_views'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:24px;">
          <div>
            <strong>Duration for Movies (or Episode Count for Series)</strong>
            <small>Display runtime (e.g. 110 min) for movies or episode count (e.g. 24 EP) for series.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="home_card_show_duration" <?= ($settings['home_card_show_duration'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="section-title">Cards on Other Pages (Series, Movies, Genres, Search)</div>
        <p class="muted" style="margin-bottom:14px;">Control which elements appear on media items on inner browse and search pages.</p>

        <div class="switch" style="margin-bottom:12px;">
          <div>
            <strong>Genres Tag</strong>
            <small>Display genre labels underneath the card thumbnail.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="other_card_show_genres" <?= ($settings['other_card_show_genres'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:12px;">
          <div>
            <strong>Media Type Badge</strong>
            <small>Display the "SERIES" or "MOVIE" badge on top of the thumbnail.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="other_card_show_type" <?= ($settings['other_card_show_type'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:12px;">
          <div>
            <strong>Views Count</strong>
            <small>Display the view counter badge on the card.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="other_card_show_views" <?= ($settings['other_card_show_views'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:12px;">
          <div>
            <strong>Duration for Movies (or Episode Count for Series)</strong>
            <small>Display runtime (e.g. 110 min) for movies or episode count for series.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="other_card_show_duration" <?= ($settings['other_card_show_duration'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div class="switch" style="margin-bottom:20px;">
          <div>
            <strong>Score / Rating</strong>
            <small>Display the star rating score (e.g. ★ 8.5) on media cards.</small>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="other_card_show_score" <?= ($settings['other_card_show_score'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
          </label>
        </div>

        <div>
          <button type="submit" class="btn primary">Save Media Card Settings</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
