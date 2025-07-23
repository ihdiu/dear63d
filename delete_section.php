<?php
require_once 'includes/functions.php';
requireLogin();
requireCR();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$section_id = (int)$_POST['section_id'];
$course_id = (int)$_POST['course_id'];

// Get section details
$stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch();

if (!$section) {
    header("Location: course.php?id=$course_id&error=Section not found");
    exit;
}

// Get all materials in this section
$stmt = $conn->prepare("SELECT * FROM materials WHERE section_id = ?");
$stmt->execute([$section_id]);
$materials = $stmt->fetchAll();

// Start transaction
$conn->beginTransaction();

try {
    // Delete all materials in this section
    foreach ($materials as $material) {
        // Delete the file if it exists
        if ($material['file_type'] !== 'url' && file_exists($material['file_url'])) {
            unlink($material['file_url']);
        }
    }
    
    // Delete all materials from database
    $stmt = $conn->prepare("DELETE FROM materials WHERE section_id = ?");
    $stmt->execute([$section_id]);
    
    // Delete the section
    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    
    // Commit transaction
    $conn->commit();
    
    header("Location: course.php?id=$course_id&success=Section and all its materials deleted successfully");
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    header("Location: course.php?id=$course_id&error=Failed to delete section");
}
exit; 