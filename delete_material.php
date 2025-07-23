<?php
require_once 'includes/functions.php';
requireLogin();
requireCR();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$material_id = (int)$_POST['material_id'];
$course_id = (int)$_POST['course_id'];
$section_id = (int)$_POST['section_id'];

// Get material details
$stmt = $conn->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    header("Location: course.php?id=$course_id&section=$section_id&error=Material not found");
    exit;
}

// Delete the material
$stmt = $conn->prepare("DELETE FROM materials WHERE id = ?");
if ($stmt->execute([$material_id])) {
    // If it was a file upload, delete the file
    if ($material['file_type'] !== 'url' && file_exists($material['file_url'])) {
        unlink($material['file_url']);
    }
    
    header("Location: course.php?id=$course_id&section=$section_id&success=Material deleted successfully");
} else {
    header("Location: course.php?id=$course_id&section=$section_id&error=Failed to delete material");
}
exit; 