<?php
require_once 'includes/functions.php';
requireLogin();
requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$course_id = (int)$_POST['course_id'];

// Check if already enrolled
$stmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
if ($stmt->rowCount() > 0) {
    header("Location: course.php?id=$course_id&error=You are already enrolled in this course");
    exit;
}

// Add enrollment
$stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
if ($stmt->execute([$_SESSION['user_id'], $course_id])) {
    header("Location: course.php?id=$course_id&success=Successfully enrolled in the course");
} else {
    header("Location: dashboard.php?error=Failed to enroll in the course");
}
exit; 