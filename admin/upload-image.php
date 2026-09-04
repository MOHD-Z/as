<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/upload.php';

header('Content-Type: application/json');

$url = handle_poster_upload('image', '');
if ($url === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed — check the file is a JPG/PNG/WEBP/GIF.']);
    exit;
}

// Build a root-relative URL (leading slash) so the <img> tag resolves
// correctly both here in the admin editor (which lives one folder deep,
// in /admin/) and later on the public page (which lives at the site
// root) — a plain relative path like "uploads/x.jpg" would break in one
// of those two contexts.
$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
echo json_encode(['url' => $baseDir . '/' . $url]);
