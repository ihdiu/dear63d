<?php
require_once 'includes/functions.php';
requireLogin();
requireCR();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$course_id = (int)$_POST['course_id'];
$title = sanitizeInput($_POST['title']);
$type = sanitizeInput($_POST['type']);
$description = sanitizeInput($_POST['description']);

// Get the highest section order
$stmt = $conn->prepare("SELECT MAX(section_order) as max_order FROM sections WHERE course_id = ?");
$stmt->execute([$course_id]);
$result = $stmt->fetch();
$section_order = ($result['max_order'] ?? 0) + 1;

// Insert the new section
$stmt = $conn->prepare("INSERT INTO sections (course_id, title, type, description, section_order) VALUES (?, ?, ?, ?, ?)");
if ($stmt->execute([$course_id, $title, $type, $description, $section_order])) {
    header("Location: course.php?id=$course_id&success=Section added successfully");
} else {
    header("Location: course.php?id=$course_id&error=Failed to add section");
}
exit; 