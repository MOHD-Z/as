<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$languages = $pdo->query("SELECT * FROM languages ORDER BY is_default DESC, name ASC")->fetchAll();
$selectedLang = $_GET['lang'] ?? ($_POST['lang'] ?? 'ar');

// Save translation changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_translations') {
    $lang = $_POST['lang'];
    $translations = $_POST['trans'] ?? [];

    $stmt = $pdo->prepare("INSERT INTO translations (lang_code, trans_key, trans_val) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE trans_val = VALUES(trans_val)");

    foreach ($translations as $key => $val) {
        $stmt->execute([$lang, $key, trim($val)]);
    }

    // Also support adding a new custom translation key
    if (!empty($_POST['new_key']) && !empty($_POST['new_val'])) {
        $cleanKey = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($_POST['new_key'])));
        $stmt->execute([$lang, $cleanKey, trim($_POST['new_val'])]);
    }

    admin_flash('Translations saved successfully for ' . strtoupper($lang) . '.');
    header('Location: translations.php?lang=' . urlencode($lang));
    exit;
}

// Key definitions with human-friendly descriptions & groupings
$frontendGroups = [
    'Homepage Sections' => [
        'trending_now' => ['Trending Now', 'Header for trending series/movies'],
        'last_movies' => ['Last Movies', 'Header for recently added movies'],
        'popular_series' => ['Popular Series', 'Header for popular TV series'],
        'last_episodes' => ['Last Episodes', 'Header for latest released episodes'],
        'our_blog' => ['Our Blog', 'Header for blog section and sidebar'],
        'view_all' => ['View All', 'Button linking to full listing page'],
        'watch_now' => ['Watch Now', 'Hero carousel CTA button'],
    ],
    'Content & Navigation' => [
        'episodes' => ['Episodes', 'Label and title for episodes'],
        'movies' => ['Movies', 'Label and title for movies'],
        'series' => ['Series', 'Label and title for series'],
        'genres' => ['Genres', 'Dropdown and page title for genres'],
        'homepage' => ['Homepage', 'Main nav title'],
        'my_list' => ['My List', 'User saved watchlist/favorites link'],
        'continue_watching' => ['Continue Watching', 'Section title on user dashboard'],
    ],
    'Sorting & Filtering' => [
        'sort' => ['Sort', 'Dropdown label for sorting items'],
        'newest' => ['Newest', 'Sort option: latest first'],
        'oldest' => ['Oldest', 'Sort option: oldest first'],
        'most_viewed' => ['Most Viewed', 'Sort option: highest views'],
    ],
    'Auth & Account' => [
        'login' => ['Login', 'Page title and heading for login'],
        'login_btn' => ['Login button', 'Text on the login submit button'],
        'no_account' => ['No account? Sign up here', 'Text linking from login to signup'],
        'signup' => ['Sign Up', 'Page title and heading for registration'],
        'signup_btn' => ['Sign Up button', 'Text on the registration submit button'],
        'have_account' => ['Already have an account? Login here', 'Text linking from signup to login'],
        'logout' => ['Logout', 'User logout link text'],
    ],
    'User Lists & Messages' => [
        'favorites' => ['Favorites', 'Favorites tab/section heading'],
        'no_favorites' => ['No favorites yet', 'Empty state when favorites list is empty'],
        'watchlist' => ['Watchlist', 'Watchlist tab/section heading'],
        'watchlist_empty' => ['Your watchlist is empty.', 'Empty state for watchlist'],
        'no_results_found' => ['No results found for', 'Empty search results message'],
        'seasons_and_episodes' => ['Seasons & Episodes', 'Episodes list header on TV details'],
        'no_episodes_yet' => ['No episodes added yet.', 'Empty state on series with no episodes'],
        'search_placeholder' => ['Search anime or movie...', 'Search modal input placeholder text'],
    ]
];

$adminGroups = [
    'Admin Navigation' => [
        'nav_dashboard' => ['Dashboard', 'Sidebar link to Overview dashboard'],
        'nav_movies' => ['Movies', 'Sidebar link to Movies management'],
        'nav_series' => ['Series', 'Sidebar link to Series management'],
        'nav_episodes' => ['Episodes', 'Sidebar link to Episodes management'],
        'nav_bulk_episodes' => ['Bulk Add Episodes', 'Sidebar link to Bulk Episode Creator'],
        'nav_genres' => ['Genres', 'Sidebar link to Genres management'],
        'nav_blog' => ['Blog', 'Sidebar link to Blog posts management'],
        'nav_import' => ['Import (TMDB/MAL)', 'Sidebar link to Metadata Importer'],
        'nav_sources' => ['Video Sources', 'Sidebar link to Video Servers & Streams'],
        'nav_homepage' => ['Homepage Sections', 'Sidebar link to Homepage Section builder'],
        'nav_menus' => ['Header & Footer Menus', 'Sidebar link to Navigation menu manager'],
        'nav_settings' => ['Site Settings', 'Sidebar link to Settings (General, Layout, Cards)'],
        'nav_translations' => ['Translations', 'Sidebar link to this Translation Manager'],
        'nav_users' => ['Users', 'Sidebar link to User Accounts'],
        'nav_languages' => ['Languages', 'Sidebar link to System Languages'],
        'nav_reports' => ['Reports', 'Sidebar link to Broken Video Reports'],
        'nav_backups' => ['Backups', 'Sidebar group title for Backups'],
        'nav_backup_content' => ['Content Backup', 'Sidebar link to Content-only backup'],
        'nav_backup_full' => ['Full Website Backup', 'Sidebar link to Full site backup'],
        'nav_view_site' => ['View Site', 'Sidebar link opening the frontend'],
        'nav_logout' => ['Logout', 'Sidebar link to exit admin session'],
        'admin_administration' => ['Administration', 'Subtitle under admin logo'],
    ],
    'Admin Actions' => [
        'save_changes' => ['Save Changes', 'Button label to commit changes'],
        'cancel' => ['Cancel', 'Button label to discard or go back'],
        'add_new' => ['Add New', 'Button label to create new item'],
        'edit' => ['Edit', 'Button label to modify an item'],
        'delete' => ['Delete', 'Button label to remove an item'],
    ]
];

// Fetch current translations from database for selected language
$stmt = $pdo->prepare("SELECT trans_key, trans_val FROM translations WHERE lang_code = ?");
$stmt->execute([$selectedLang]);
$currentTrans = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $currentTrans[$row['trans_key']] = $row['trans_val'];
}

$admin_page_title = __('nav_translations', 'Translations');
$admin_active = 'translations';
include __DIR__ . '/includes/layout_top.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
  <div>
    <p class="muted" style="margin:0;">
      Translate frontend website titles, buttons, and admin panel texts into Arabic or any active language.
    </p>
  </div>

  <div style="display:flex;align-items:center;gap:10px;">
    <label style="margin:0;font-weight:600;">Active Language:</label>
    <select onchange="window.location.href='translations.php?lang='+this.value" style="padding:8px 14px;border-radius:6px;background:var(--card, #1c1c28);color:#fff;border:1px solid var(--border, #333);cursor:pointer;font-size:14px;">
      <?php foreach ($languages as $l): ?>
        <option value="<?= h($l['code']) ?>" <?= $selectedLang === $l['code'] ? 'selected' : '' ?>>
          <?= h($l['name']) ?> (<?= h(strtoupper($l['code'])) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<form method="post">
  <input type="hidden" name="action" value="save_translations">
  <input type="hidden" name="lang" value="<?= h($selectedLang) ?>">

  <!-- TAB 1: WEBSITE FRONTEND STRINGS -->
  <div class="panel" style="margin-bottom:24px;">
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
      <h2>🌐 Website Frontend Titles & Buttons</h2>
      <button type="submit" class="btn primary">Save Translations</button>
    </div>
    <div class="panel-body">
      <?php foreach ($frontendGroups as $groupTitle => $items): ?>
        <div class="section-title" style="margin-top:16px;margin-bottom:12px;color:var(--primary, #e53637);"><?= h($groupTitle) ?></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:14px;margin-bottom:18px;">
          <?php foreach ($items as $k => [$orig, $desc]):
              $val = $currentTrans[$k] ?? ($selectedLang === 'en' ? $orig : '');
          ?>
            <div class="field" style="background:#161622;padding:12px;border-radius:8px;border:1px solid #28283c;">
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <label style="margin:0;font-size:13px;font-weight:600;color:#ddd;"><?= h($orig) ?></label>
                <code style="font-size:11px;color:#888;"><?= h($k) ?></code>
              </div>
              <input type="text" name="trans[<?= h($k) ?>]" value="<?= h($val) ?>" placeholder="<?= h($orig) ?>" style="font-size:14px;">
              <small class="muted" style="font-size:11px;"><?= h($desc) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TAB 2: ADMIN PANEL STRINGS -->
  <div class="panel" style="margin-bottom:24px;">
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
      <h2>⚙️ Admin Panel Navigation & Controls (Arabic / Multi-language)</h2>
      <button type="submit" class="btn primary">Save Translations</button>
    </div>
    <div class="panel-body">
      <p class="muted" style="margin-bottom:16px;">
        Translates all sidebar navigation items, admin section headers, and core action buttons for administrators.
      </p>
      <?php foreach ($adminGroups as $groupTitle => $items): ?>
        <div class="section-title" style="margin-top:16px;margin-bottom:12px;color:var(--primary, #e53637);"><?= h($groupTitle) ?></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:14px;margin-bottom:18px;">
          <?php foreach ($items as $k => [$orig, $desc]):
              $val = $currentTrans[$k] ?? ($selectedLang === 'en' ? $orig : '');
          ?>
            <div class="field" style="background:#161622;padding:12px;border-radius:8px;border:1px solid #28283c;">
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <label style="margin:0;font-size:13px;font-weight:600;color:#ddd;"><?= h($orig) ?></label>
                <code style="font-size:11px;color:#888;"><?= h($k) ?></code>
              </div>
              <input type="text" name="trans[<?= h($k) ?>]" value="<?= h($val) ?>" placeholder="<?= h($orig) ?>" style="font-size:14px;">
              <small class="muted" style="font-size:11px;"><?= h($desc) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ADD CUSTOM TRANSLATION -->
  <div class="panel" style="margin-bottom:24px;">
    <div class="panel-head"><h2>➕ Add Custom Translation Key</h2></div>
    <div class="panel-body">
      <div class="form-grid">
        <div class="field">
          <label>Translation Key (e.g. custom_header)</label>
          <input type="text" name="new_key" placeholder="key_name">
        </div>
        <div class="field">
          <label>Translated Text</label>
          <input type="text" name="new_val" placeholder="Text to show...">
        </div>
      </div>
      <div style="margin-top:16px;">
        <button type="submit" class="btn primary">Save Translations & Add Key</button>
      </div>
    </div>
  </div>
</form>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
