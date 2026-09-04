<?php
$admin_page_title = $admin_page_title ?? 'Dashboard';
$admin_active = $admin_active ?? '';
function nav_active($key, $active) { return $key === $active ? ' active' : ''; }

$siteName = get_setting('site_name', 'AniStream');
$siteIcon = get_setting('site_icon', '');
$iconUrl = $siteIcon ? ((strpos($siteIcon, 'http') === 0) ? $siteIcon : '../' . ltrim($siteIcon, '/')) : '';
$adminLang = current_lang();
$rtl = is_rtl();
?>
<!DOCTYPE html>
<html lang="<?= h($adminLang) ?>" <?= $rtl ? 'dir="rtl"' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin | <?= h($admin_page_title) ?> - <?= h($siteName) ?></title>
<?php if ($iconUrl): ?>
<link rel="icon" href="<?= h($iconUrl) ?>" type="image/png">
<?php else: ?>
<link rel="icon" href="../img/logo.png" type="image/png">
<?php endif; ?>
<link rel="stylesheet" href="admin.css">
<?php if ($rtl): ?>
<style>
  body { direction: rtl; text-align: right; }
  .sidebar { border-right: none; border-left: 1px solid var(--border); }
  .nav-left { flex-direction: row-reverse; }
  .nav-item { text-align: right; }
  .topbar { flex-direction: row-reverse; }
  .page-head { flex-direction: row-reverse; }
  .form-grid { direction: rtl; }
  .actions { flex-direction: row-reverse; }
</style>
<?php endif; ?>
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="brand">
    <?php if ($iconUrl): ?>
      <img src="<?= h($iconUrl) ?>" alt="Logo" style="width:34px;height:34px;border-radius:8px;object-fit:cover;">
    <?php else: ?>
      <div class="brand-mark">AS</div>
    <?php endif; ?>
    <div>
      <strong><?= h($siteName) ?></strong>
      <small><?= __('admin_administration', 'Administration') ?></small>
    </div>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_dashboard', 'Overview') ?></div>
    <a class="nav-item<?= nav_active('dashboard', $admin_active) ?>" href="index.php">
      <span class="nav-left"><span class="ico">⌂</span><?= __('nav_dashboard', 'Dashboard') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_content', 'Content') ?></div>
    <a class="nav-item<?= nav_active('movies', $admin_active) ?>" href="movies.php">
      <span class="nav-left"><span class="ico">🎬</span><?= __('nav_movies', 'Movies') ?></span>
    </a>
    <a class="nav-item<?= nav_active('series', $admin_active) ?>" href="series.php">
      <span class="nav-left"><span class="ico">▣</span><?= __('nav_series', 'Series') ?></span>
    </a>
    <a class="nav-item<?= nav_active('episodes', $admin_active) ?>" href="episodes.php">
      <span class="nav-left"><span class="ico">▶</span><?= __('nav_episodes', 'Episodes') ?></span>
    </a>
    <a class="nav-item<?= nav_active('genres', $admin_active) ?>" href="genres.php">
      <span class="nav-left"><span class="ico">◇</span><?= __('nav_genres', 'Genres') ?></span>
    </a>
    <a class="nav-item<?= nav_active('blog', $admin_active) ?>" href="blog.php">
      <span class="nav-left"><span class="ico">▱</span><?= __('nav_blog', 'Blog') ?></span>
    </a>
    <a class="nav-item<?= nav_active('import', $admin_active) ?>" href="import.php">
      <span class="nav-left"><span class="ico">⇩</span><?= __('nav_import', 'Import (TMDB/MAL)') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_bulk_episodes', 'Bulk Add Episodes') ?></div>
    <a class="nav-item<?= nav_active('bulk_episodes', $admin_active) ?>" href="bulk-episodes.php">
      <span class="nav-left"><span class="ico">⚡</span><?= __('nav_bulk_episodes', 'Bulk Add Episodes') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_sources', 'Media') ?></div>
    <a class="nav-item<?= nav_active('sources', $admin_active) ?>" href="video_sources.php">
      <span class="nav-left"><span class="ico">⇄</span><?= __('nav_sources', 'Video Sources') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_appearance', 'Appearance & Settings') ?></div>
    <a class="nav-item<?= nav_active('homepage', $admin_active) ?>" href="homepage_sections.php">
      <span class="nav-left"><span class="ico">▥</span><?= __('nav_homepage', 'Homepage Sections') ?></span>
    </a>
    <a class="nav-item<?= nav_active('menus', $admin_active) ?>" href="menus.php">
      <span class="nav-left"><span class="ico">☰</span><?= __('nav_menus', 'Header & Footer Menus') ?></span>
    </a>
    <a class="nav-item<?= nav_active('settings', $admin_active) ?>" href="settings.php">
      <span class="nav-left"><span class="ico">✦</span><?= __('nav_settings', 'Site Settings') ?></span>
    </a>
    <a class="nav-item<?= nav_active('translations', $admin_active) ?>" href="translations.php">
      <span class="nav-left"><span class="ico">🌐</span><?= __('nav_translations', 'Translations') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_users_languages', 'Users & Languages') ?></div>
    <a class="nav-item<?= nav_active('users', $admin_active) ?>" href="users.php">
      <span class="nav-left"><span class="ico">♟</span><?= __('nav_users', 'Users') ?></span>
    </a>
    <a class="nav-item<?= nav_active('languages', $admin_active) ?>" href="languages.php">
      <span class="nav-left"><span class="ico">文</span><?= __('nav_languages', 'Languages') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_reports', 'Operations') ?></div>
    <a class="nav-item<?= nav_active('reports', $admin_active) ?>" href="reports.php">
      <span class="nav-left"><span class="ico">⚠</span><?= __('nav_reports', 'Reports') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_backups', 'Backups') ?></div>
    <a class="nav-item<?= nav_active('backup_content', $admin_active) ?>" href="backup.php?type=content">
      <span class="nav-left"><span class="ico">📦</span><?= __('nav_backup_content', 'Content Backup') ?></span>
    </a>
    <a class="nav-item<?= nav_active('backup_full', $admin_active) ?>" href="backup.php?type=full">
      <span class="nav-left"><span class="ico">💾</span><?= __('nav_backup_full', 'Full Website Backup') ?></span>
    </a>
  </div>

  <div class="nav-group">
    <div class="nav-title"><?= __('nav_account', 'Account') ?></div>
    <a class="nav-item" href="../index.php" target="_blank">
      <span class="nav-left"><span class="ico">↗</span><?= __('nav_view_site', 'View Site') ?></span>
    </a>
    <a class="nav-item" href="logout.php">
      <span class="nav-left"><span class="ico">●</span><?= __('nav_logout', 'Logout') ?></span>
    </a>
  </div>
</aside>

<main class="main">
<header class="topbar">
  <div class="search"></div>
  <div class="top-actions" style="display:flex;align-items:center;gap:12px;">
    <!-- Language Switcher -->
    <select onchange="window.location.href='?lang='+this.value" style="padding:6px 12px;border-radius:6px;background:#181824;color:#fff;border:1px solid #2e2e42;font-size:13px;cursor:pointer;">
      <option value="en" <?= $adminLang === 'en' ? 'selected' : '' ?>>English</option>
      <option value="ar" <?= $adminLang === 'ar' ? 'selected' : '' ?>>العربية</option>
    </select>
    <span class="muted"><?= h($_SESSION['admin_name'] ?? 'Admin') ?></span>
    <div class="avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
  </div>
</header>
<div class="content">
  <div class="page-head">
    <div>
      <h1><?= h($admin_page_title) ?></h1>
    </div>
    <?php if (!empty($admin_page_actions)): ?>
      <div class="actions"><?= $admin_page_actions ?></div>
    <?php endif; ?>
  </div>

  <?php $flash = admin_flash(); if ($flash): ?>
    <div class="notice"><strong>✓</strong> <?= h($flash) ?></div>
  <?php endif; ?>
