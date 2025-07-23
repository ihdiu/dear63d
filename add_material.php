<?php
require_once 'includes/functions.php';
requireLogin();
requireCR();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$section_id = (int)$_POST['section_id'];
$title = sanitizeInput($_POST['title']);
$description = sanitizeInput($_POST['description']);
$url = sanitizeInput($_POST['url']);

// Get course_id for redirect
$section = getSectionById($section_id);
if (!$section) {
    header('Location: dashboard.php?error=Invalid section');
    exit;
}
$course_id = $section['course_id'];

// Get course details for notification
$course = getCourseById($course_id);

// Handle file uploads
$uploaded_files = [];
$file_urls = [];
$file_types = [];

if (isset($_FILES['file']) && !empty($_FILES['file']['name'][0])) {
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_extensions = ['pdf', 'pptx','ppt','zip','rar','xlsx','csv', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];
    $file_count = count($_FILES['file']['name']);

    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['file']['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $_FILES['file']['name'][$i],
                'type' => $_FILES['file']['type'][$i],
                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                'error' => $_FILES['file']['error'][$i],
                'size' => $_FILES['file']['size'][$i]
            ];

            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_extensions)) {
                header("Location: course.php?id=$course_id&section=$section_id&error=Invalid file type for {$file['name']}. Allowed types: " . implode(', ', $allowed_extensions));
                exit;
            }

            // Store original filename for Telegram
            $original_filename = $file['name'];
            
            // Create a unique filename for storage
            $sanitized_name = preg_replace('/[^a-zA-Z0-9-]/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
            $file_name = $sanitized_name . '-' . uniqid() . '.' . $file_ext;
            $file_url = $upload_dir . $file_name;

            if (move_uploaded_file($file['tmp_name'], $file_url)) {
                $uploaded_files[] = [
                    'original_name' => $original_filename,
                    'file_url' => $file_url,
                    'file_type' => $file_ext
                ];
            }
        }
    }
} elseif (!empty($url)) {
    $file_urls[] = $url;
    $file_types[] = 'url';
}

// Ensure either files or URL is provided
if (empty($uploaded_files) && empty($file_urls)) {
    header("Location: course.php?id=$course_id&section=$section_id&error=Please provide either files or URL");
    exit;
}

// Get the base URL for the course
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$course_url = $base_url . "/course.php?id=" . $course_id . "&section=" . $section_id;
$host = $_SERVER['HTTP_HOST'];
$subdomain = explode('.', $host)[0];
// Insert materials and send to Telegram
$success = true;
$file_count = count($uploaded_files);
foreach ($uploaded_files as $index => $file) {
    // Create unique title for multiple files
    $material_title = $title;
    if ($file_count > 1) {
        $file_ext = pathinfo($file['original_name'], PATHINFO_EXTENSION);
        $file_name = pathinfo($file['original_name'], PATHINFO_FILENAME);
        $material_title = $title . ' - ' . $file_name . ' (' . ($index + 1) . '/' . $file_count . ')';
    }

    // Insert material
    $stmt = $conn->prepare("INSERT INTO materials (section_id, title, description, file_url, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt->execute([$section_id, $material_title, $description, $file['file_url'], $file['file_type'], $_SESSION['user_id']])) {
        $success = false;
        continue;
    }

    // Send to Telegram
    if (!empty($course['telegram_chat_id']) && !empty($course['telegram_thread_id'])) {
        $absolute_file_path = realpath($file['file_url']);
        if ($absolute_file_path && file_exists($absolute_file_path)) {
            // Create a temporary copy with original filename
            $temp_dir = sys_get_temp_dir();
            $temp_file = $temp_dir . '/' . $file['original_name'];
            copy($absolute_file_path, $temp_file);
            
            // Send file with course link as caption
            sendTelegramFile('-100'.$course['telegram_chat_id'], $course['telegram_thread_id'], $temp_file, "🌐 Access all Materials:\n {$subdomain}.dear63.engineer");
            
            // Clean up temporary file
            unlink($temp_file);
        }
    }
}

// Handle URL if provided
if (!empty($url)) {
    $material_title = $title;
    if ($file_count > 0) {
        $material_title = $title . ' - URL';
    }
    
    $stmt = $conn->prepare("INSERT INTO materials (section_id, title, description, file_url, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt->execute([$section_id, $material_title, $description, $url, 'url', $_SESSION['user_id']])) {
        $success = false;
    } else {
        // Send URL to Telegram
        if (!empty($course['telegram_chat_id']) && !empty($course['telegram_thread_id'])) {
            $message = "🔗 *URL:* " . $url . "\n\n🌐 *Access all Materials:* " . 'd.dear63.engineer';
            sendTelegramMessage('-100'.$course['telegram_chat_id'], $course['telegram_thread_id'], $message);
        }
    }
}

if ($success) {
    header("Location: course.php?id=$course_id&section=$section_id&success=Materials added successfully");
} else {
    header("Location: course.php?id=$course_id&section=$section_id&error=Failed to add some materials");
}
exit; 