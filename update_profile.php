<?php
require_once 'includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$name = sanitizeInput($_POST['name']);
$email = sanitizeInput($_POST['email']);
$class_id = sanitizeInput($_POST['class_id']);
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    header('Location: profile.php?error=Current password is incorrect');
    exit;
}

// Check if email is already taken by another user
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $user_id]);
if ($stmt->rowCount() > 0) {
    header('Location: profile.php?error=Email address is already taken');
    exit;
}

// Update user information
$updates = [];
$params = [];

if ($name !== $user['name']) {
    $updates[] = "name = ?";
    $params[] = $name;
}

if ($email !== $user['email']) {
    $updates[] = "email = ?";
    $params[] = $email;
}

if ($class_id !== $user['class_id']) {
    $updates[] = "class_id = ?";
    $params[] = $class_id;
}

// Handle password change if provided
if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        header('Location: profile.php?error=New passwords do not match');
        exit;
    }
    
    if (strlen($new_password) < 6) {
        header('Location: profile.php?error=Password must be at least 6 characters long');
        exit;
    }
    
    $updates[] = "password = ?";
    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
}

// If there are updates to make
if (!empty($updates)) {
    $params[] = $user_id;
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute($params)) {
        // Update session name if it was changed
        if ($name !== $user['name']) {
            $_SESSION['name'] = $name;
        }
        header('Location: profile.php?success=Profile updated successfully');
    } else {
        header('Location: profile.php?error=Failed to update profile');
    }
} else {
    header('Location: profile.php?success=No changes were made');
}
exit; 