<?php
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$material_id = (int)$_GET['id'];

// Get material details
$stmt = $conn->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: dashboard.php?error=Material not found');
    exit;
}

// If it's a URL (not a file), redirect directly
if ($material['file_type'] === 'url') {
    header('Location: ' . $material['file_url']);
    exit;
}

// Extract original filename from file_url
$original_filename = basename($material['file_url']);
// Clean unique hash if exists, e.g. file-abc1234567890d.pdf → file.pdf
$original_filename = preg_replace('/-[a-f0-9]{13}\./', '.', $original_filename);

// Detect Telegram in-app browser
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_telegram = strpos($user_agent, 'Telegram') !== false;

if ($is_telegram) {
    // Redirect to serve.php with filename in URL
    $redirect_url = "serve.php?id={$material_id}&name=" . rawurlencode($original_filename);
    header("Location: $redirect_url");
    exit;
}

// For normal browsers, handle direct download (same logic as before)

// Get the base directory of the website
$base_dir = $_SERVER['DOCUMENT_ROOT'];

// Try multiple possible paths
$possible_paths = [
    $material['file_url'],
    $base_dir . '/' . $material['file_url'],
    __DIR__ . '/' . $material['file_url'],
    dirname(__DIR__) . '/' . $material['file_url']
];

$file_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $file_path = $path;
        break;
    }
}

if (!$file_path) {
    error_log("File not found. Attempted paths:");
    foreach ($possible_paths as $path) {
        error_log("- " . $path);
    }
    header('Location: dashboard.php?error=File not found');
    exit;
}

// Determine MIME type
$extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
$content_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    'csv' => 'text/csv'
];

// Set headers
header('Content-Type: ' . ($content_types[$extension] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $original_filename . '"; filename*=UTF-8\'\'' . rawurlencode($original_filename));
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Stream the file
$handle = fopen($file_path, 'rb');
if ($handle) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
} else {
    header('Location: dashboard.php?error=Could not open file');
}
exit;
