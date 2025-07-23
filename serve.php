<?php
require_once 'includes/functions.php';

if (!isset($_GET['id']) || !isset($_GET['name'])) {
    header('Location: dashboard.php');
    exit;
}

$material_id = (int)$_GET['id'];
$requested_name = basename($_GET['name']); // sanitize filename

// Fetch material from database
$stmt = $conn->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: dashboard.php?error=Material not found');
    exit;
}

// File path resolution
$base_dir = $_SERVER['DOCUMENT_ROOT'];
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
    header('Location: dashboard.php?error=File not found');
    exit;
}

// Detect extension for MIME type
$extension = strtolower(pathinfo($requested_name, PATHINFO_EXTENSION));
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

// Send file headers
header('Content-Type: ' . ($content_types[$extension] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $requested_name . '"; filename*=UTF-8\'\'' . rawurlencode($requested_name));
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Stream file
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
