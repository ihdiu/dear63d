<?php
require_once 'includes/functions.php';
requireLogin();
requireCR();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$course_id = (int)$_POST['course_id'];

// Get course details
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND created_by = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: dashboard.php?error=Course not found or unauthorized');
    exit;
}

// Get all sections in this course
$stmt = $conn->prepare("SELECT * FROM sections WHERE course_id = ?");
$stmt->execute([$course_id]);
$sections = $stmt->fetchAll();

// Start transaction
$conn->beginTransaction();

try {
    // For each section, delete all materials
    foreach ($sections as $section) {
        // Get all materials in this section
        $stmt = $conn->prepare("SELECT * FROM materials WHERE section_id = ?");
        $stmt->execute([$section['id']]);
        $materials = $stmt->fetchAll();
        
        // Delete all material files
        foreach ($materials as $material) {
            if ($material['file_type'] !== 'url' && file_exists($material['file_url'])) {
                unlink($material['file_url']);
            }
        }
        
        // Delete all materials from database
        $stmt = $conn->prepare("DELETE FROM materials WHERE section_id = ?");
        $stmt->execute([$section['id']]);
    }
    
    // Delete all sections
    $stmt = $conn->prepare("DELETE FROM sections WHERE course_id = ?");
    $stmt->execute([$course_id]);
    
    // Delete all enrollments
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE course_id = ?");
    $stmt->execute([$course_id]);
    
    // Delete the course
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    
    // Commit transaction
    $conn->commit();
    
    header('Location: dashboard.php?success=Course and all its contents deleted successfully');
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    header('Location: dashboard.php?error=Failed to delete course');
}
exit; 