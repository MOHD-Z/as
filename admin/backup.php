<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db_backup.php';

$backupType = $_GET['type'] ?? 'all';

// Full site zip download
if ($backupType === 'full_zip') {
    $tmpSql = tempnam(sys_get_temp_dir(), 'anistream_') . '.sql';
    file_put_contents($tmpSql, generate_sql_dump($pdo, all_backup_tables($pdo)));

    $zipPath = tempnam(sys_get_temp_dir(), 'anistream_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile($tmpSql, 'database.sql');

    $uploadsDir = __DIR__ . '/../uploads';
    if (is_dir($uploadsDir)) {
        foreach (new DirectoryIterator($uploadsDir) as $f) {
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), 'uploads/' . $f->getFilename());
            }
        }
    }
    $zip->close();

    $filename = 'anistream-full-backup-' . date('Y-m-d_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    unlink($tmpSql);
    unlink($zipPath);
    exit;
}

$tableCount = count(all_backup_tables($pdo));
$contentTableCount = count(content_backup_tables());
$uploadsDir = __DIR__ . '/../uploads';
$uploadsCount = is_dir($uploadsDir) ? count(glob($uploadsDir . '/*')) : 0;

$admin_page_title = ($backupType === 'content') ? __('nav_backup_content', 'Content Backup') : (($backupType === 'full') ? __('nav_backup_full', 'Full Website Backup') : __('nav_backups', 'Backups'));
$admin_active = ($backupType === 'content') ? 'backup_content' : (($backupType === 'full') ? 'backup_full' : 'backup_content');

include __DIR__ . '/includes/layout_top.php';
?>
  <p class="muted" style="margin-bottom:16px;">
    Backups download directly to your computer. Each is a clean, portable <code>.sql</code> file (or a <code>.zip</code> containing your database and uploads) you can restore later via phpMyAdmin or standard MySQL tools.
  </p>

  <?php if ($backupType === 'all' || $backupType === 'content'): ?>
  <div class="panel" style="margin-bottom:20px; <?= $backupType === 'content' ? 'border: 1px solid var(--primary, #e53637);' : '' ?>">
    <div class="panel-head">
      <h2>📦 <?= __('nav_backup_content', 'Content Backup (Content Only)') ?></h2>
    </div>
    <div class="panel-body">
      <p class="muted" style="margin-bottom:14px;">
        Backs up all media content only: <strong>Movies, Series, Seasons, Episodes, Genres, Video Sources, Reports, and Blog Posts</strong> (<?= $contentTableCount ?> tables).
        Does not touch admin accounts, user logins, or site settings, making it safe to restore or transfer to other environments.
      </p>
      <div style="display:flex;gap:12px;align-items:center;">
        <a href="backup-download.php?type=content" class="btn primary">
          <span>⤓</span> Download Content Backup (.sql)
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($backupType === 'all' || $backupType === 'full'): ?>
  <div class="panel" style="margin-bottom:20px; <?= $backupType === 'full' ? 'border: 1px solid var(--primary, #e53637);' : '' ?>">
    <div class="panel-head">
      <h2>💾 <?= __('nav_backup_full', 'Full Website Backup (Contents + Settings + Uploads)') ?></h2>
    </div>
    <div class="panel-body">
      <p class="muted" style="margin-bottom:14px;">
        Complete backup of everything: all content above, plus <strong>Site Settings, Layout preferences, Navigation menus, Homepage sections, Admin & User accounts, Languages, Translations, and uploaded images</strong> (<?= $tableCount ?> tables, <?= $uploadsCount ?> files).
      </p>
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <a href="backup.php?type=full_zip" class="btn primary">
          <span>⤓</span> Download Full Backup (.zip with DB + Uploads)
        </a>
        <a href="backup-download.php?type=full" class="btn">
          <span>⤓</span> Download Database Only (.sql)
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
