<?php
// Handles the "poster by URL or by file upload" pattern used in the admin forms.
// Call after validating $_POST — pass the file input name and the URL field's
// already-trimmed value. Returns the path/URL to store, or $urlValue unchanged
// if no file was uploaded.

function handle_poster_upload($fileInputName, $urlValue) {
    if (empty($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $urlValue;
    }
    $file = $_FILES[$fileInputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $urlValue; // silently fall back to whatever URL was typed
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return $urlValue; // not an image we accept — keep the URL field instead
    }

    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    $filename = 'poster_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $destination = $uploadsDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return 'uploads/' . $filename;
    }
    return $urlValue;
}
