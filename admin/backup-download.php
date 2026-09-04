<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/db_backup.php';

$type = $_GET['type'] === 'full' ? 'full' : 'content';
$tables = $type === 'full' ? all_backup_tables($pdo) : content_backup_tables();

$sql = generate_sql_dump($pdo, $tables);
$filename = 'anistream-' . $type . '-backup-' . date('Y-m-d_His') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql));
echo $sql;
