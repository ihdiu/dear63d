<?php
require_once 'includes/functions.php';
require_once 'includes/google_calendar.php';

if (!isLoggedIn() || !isCR()) {
    header('Location: login.php');
    exit;
}

$calendarManager = new GoogleCalendarManager();
$testResult = $calendarManager->testConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Calendar Status - Dear 63</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card" style="max-width: 500px;">
            <div class="card-body text-center">
                <h4 class="card-title mb-4">
                    <i class="bi bi-calendar-check text-primary"></i>
                    Google Calendar Status
                </h4>
                
                <?php if ($testResult['success']): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Calendar Connected Successfully!</strong><br>
                        Calendar: <?php echo htmlspecialchars($testResult['calendar_name']); ?><br>
                        ID: <?php echo htmlspecialchars($testResult['calendar_id']); ?>
                    </div>
                    <p class="text-success mb-4">Your tasks will now automatically sync with Google Calendar!</p>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong>Connection Failed!</strong><br>
                        Error: <?php echo htmlspecialchars($testResult['error']); ?>
                    </div>
                    <p class="text-danger mb-4">Please check your configuration and try again.</p>
                <?php endif; ?>
                
                <a href="tasks.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Tasks
                </a>
            </div>
        </div>
    </div>
</body>
</html>