<?php
// Include vendor autoloader first
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

require_once 'includes/functions.php';

// Include Google Calendar integration if available
if (file_exists('includes/google_calendar.php')) {
    require_once 'includes/google_calendar.php';
}

// Check if the tasks table exists, if not create it
ensureTasksTableExists();

// Handle task creation, editing, and deletion
if (isLoggedIn() && isCR()) {
    if (isset($_POST['add_task'])) {
        $title = sanitizeInput($_POST['title']);
        $task_type = sanitizeInput($_POST['task_type']);
        $course_code = sanitizeInput($_POST['course_code']);
        $course_title = sanitizeInput($_POST['course_title']);
        $instructions = sanitizeInput($_POST['instructions']);
        $deadline = sanitizeInput($_POST['deadline']);
        $message = "\n🆕 <b>New Task Added</b>\n\n<b>Title:</b> <b>" . htmlspecialchars($title) . "</b>\n\n📚 <b>Task Type:</b> $task_type\n🏷️ <b>Course Code:</b> $course_code\n📖 <b>Course Title:</b> $course_title\n\n📝 <b>Instructions:</b> <blockquote>" . htmlspecialchars($instructions) . "</blockquote>\n⏰ <b>Deadline:</b> $deadline\n";
        $message_id = send_to_telegram($message, 1);
        if (createTask($title, $task_type, $course_code, $course_title, $instructions, $deadline, $message_id)) {
            $success_message = "Task added successfully!";
        } else {
            $error_message = "Failed to add task.";
        }
    }

    if (isset($_POST['edit_task'])) {
        $task_id = $_POST['task_id'];
        $title = sanitizeInput($_POST['title']);
        $task_type = sanitizeInput($_POST['task_type']);
        $course_code = sanitizeInput($_POST['course_code']);
        $course_title = sanitizeInput($_POST['course_title']);
        $instructions = sanitizeInput($_POST['instructions']);
        $deadline = sanitizeInput($_POST['deadline']);
        $status = sanitizeInput($_POST['status']);

        if (updateTask($task_id, $title, $task_type, $course_code, $course_title, $instructions, $deadline, $status)) {
            $success_message = "Task updated successfully!";
            // Fetch message_id, created_by, and created_at for the task
            $stmt = $conn->prepare("SELECT message_id, created_by, created_at FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task_row = $stmt->fetch();
            if ($task_row && !empty($task_row['message_id'])) {
                $created_at = new DateTime($task_row['created_at']);
                $now = new DateTime();
                $interval = $created_at->diff($now);
                if ($interval->days > 2) {
                    $warning_message = "The bot cannot edit the Telegram message because it is older than 48 hours.";
                } else {
                    $edit_message = "\n✏️ <b>Task Updated</b>\n\n<b>Title:</b> <b>" . htmlspecialchars($title) . "</b>\n\n📚 <b>Task Type:</b> $task_type\n🏷️ <b>Course Code:</b> $course_code\n📖 <b>Course Title:</b> $course_title\n\n📝 <b>Instructions:</b> <blockquote>" . htmlspecialchars($instructions) . "</blockquote>\n\n⏰ <b>Deadline:</b> $deadline\n";
                    edit_bot_message($edit_message, $task_row['created_by'], $task_row['message_id']);
                }
            }
        } else {
            $error_message = "Failed to update task.";
        }
    }

    if (isset($_POST['delete_task']) && isset($_POST['id'])) {
        $task_id = $_POST['id'];
        // Fetch created_by, message_id, and created_at before deleting
        $stmt = $conn->prepare("SELECT created_by, message_id, created_at FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task_row = $stmt->fetch();

        if (deleteTask($task_id)) {
            $success_message = "Task deleted successfully!";
            if ($task_row && !empty($task_row['message_id'])) {
                $created_at = new DateTime($task_row['created_at']);
                $now = new DateTime();
                $interval = $created_at->diff($now);
                if ($interval->days > 2) {
                    $warning_message = "The bot cannot delete the Telegram message because it is older than 48 hours.";
                } else {
                    delete_telegram_message($task_row['created_by'], $task_row['message_id']);
                }
            }
        } else {
            $error_message = "Failed to delete task.";
        }
    }
}

// Get upcoming and completed tasks
$upcoming_tasks = getUpcomingTasks();
$completed_tasks = getCompletedTasks();

// Get the full host, e.g., d.dear63.engineer
$host = $_SERVER['HTTP_HOST'];

// Extract subdomain
$subdomain = explode('.', $host)[0];

// Map subdomains to creator IDs
$creatorMap = [
    '192' => 2,
    'localhost' => 1,
    'e' => 8,
    // Add more if needed
];

// Get the creator ID from the subdomain
$creator_id = isset($creatorMap[$subdomain]) ? $creatorMap[$subdomain] : null;

if ($creator_id === null) {
    // Handle error if subdomain is not mapped
    die("Unknown subdomain: $subdomain");
}

// Handle success and error messages
$error = isset($error_message) ? $error_message : (isset($_GET['error']) ? $_GET['error'] : '');
$success = isset($success_message) ? $success_message : (isset($_GET['success']) ? $_GET['success'] : '');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Tasks - Dear 63_D</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
        }

        .card-header {
            background-color: rgba(0, 0, 0, 0.03);
            padding: 0.75rem;
            display: flex;
            justify-content: space-between;
        }

        .card-body {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
        }

        .card-text {
            font-size: 0.85rem;
            margin-bottom: auto;
            flex-grow: 1;
        }

        .card-footer {
            padding: 0.75rem 1rem;
            background: transparent;
            border-top: none;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn-sm i {
            margin-right: 0.25rem;
        }

        .view-more {
            color: var(--primary-color);
            font-size: 0.75rem;
            margin-left: 0.25rem;
            cursor: pointer;
        }

        @media (max-width: 575.98px) {
            .col-6.mb-4 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .card-header {
                padding: 0.5rem;
                flex-wrap: wrap;
            }

            .badge {
                font-size: 0.65rem;
                margin-bottom: 0.25rem;
            }

            .btn-sm span {
                display: none;
            }

            .btn-sm i {
                margin-right: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="user-info">
            <div class="user-avatar">
                <?php if (isLoggedIn()): ?>
                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                <?php else: ?>
                    <i class="bi bi-person"></i>
                <?php endif; ?>
            </div>
            <div>
                <?php if (isLoggedIn()): ?>
                    <h5 class="mb-0"><?php echo htmlspecialchars($_SESSION['name']); ?></h5>
                    <small class="text-muted"><?php echo isCR() ? 'Class Representative' : 'Student'; ?></small>
                <?php else: ?>
                    <h5 class="mb-0">Welcome</h5>
                    <small class="text-muted">Browse Tasks</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Desktop Sidebar -->
    <div class="d-none d-md-block col-md-3 col-lg-2 px-0 sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4">Dear
                <?php if ($creator_id == 2) {
                    echo '63_D';
                } elseif ($creator_id === 8) {
                    echo '63_E';
                } else {
                    echo '63';
                } ?>
            </h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="tasks.php">
                        <i class="bi bi-check2-square"></i> Tasks
                    </a>
                </li>
                <?php if (isLoggedIn() && isCR()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="create_course.php">
                            <i class="bi bi-plus-circle"></i> Create Course
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="bi bi-check-circle me-2"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($warning_message)): ?>
                <!-- Warning Modal -->
                <div class="modal fade" id="warningModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title">Warning</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <p class="mb-0"><?php echo htmlspecialchars($warning_message); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var warningModal = new bootstrap.Modal(document.getElementById('warningModal'));
                    warningModal.show();
                });
                </script>
            <?php endif; ?>

            <div class="row">
                <div class="col-12 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">Task Management</h4>
                            <p class="text-muted mb-0">Manage your course tasks and deadlines</p>
                        </div>
                        <?php if (isLoggedIn() && isCR()): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#addTaskModal">
                                <i class="bi bi-plus-circle"></i> Add New Task
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Tasks Section -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Upcoming Tasks</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcoming_tasks)): ?>
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <div>No upcoming tasks available.</div>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($upcoming_tasks as $task):
                                        $deadline = new DateTime($task['deadline']);
                                        $now = new DateTime();
                                        $days_remaining = $now->diff($deadline)->days;
                                        $badgeClass = 'bg-success';

                                        if ($days_remaining <= 1) {
                                            $badgeClass = 'bg-danger';
                                        } elseif ($days_remaining <= 3) {
                                            $badgeClass = 'bg-warning';
                                        }
                                        ?>
                                        <div class="col-6 col-md-4 col-lg-3 mb-4">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <span
                                                        class="badge bg-primary"><?php echo htmlspecialchars($task['task_type']); ?></span>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <i class="bi bi-calendar-event"></i>
                                                        <?php echo $deadline->format('M d'); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                                    <h6 class="card-subtitle mb-2 text-muted">
                                                        <?php echo htmlspecialchars($task['course_code']) . ' - ' . htmlspecialchars($task['course_title']); ?>
                                                    </h6>

                                                    <?php if (!empty($task['instructions'])): ?>
                                                        <p class="card-text">
                                                            <?php echo htmlspecialchars(substr($task['instructions'], 0, 30)) . (strlen($task['instructions']) > 30 ? '...' : ''); ?>
                                                            <?php if (strlen($task['instructions']) > 30): ?>
                                                                <span class="view-more"><i class="bi bi-eye-fill"></i></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    <?php endif; ?>

                                                    <?php if ($days_remaining <= 1): ?>
                                                        <p class="text-danger mb-0"><strong>Due today!</strong></p>
                                                    <?php elseif ($days_remaining > 0): ?>
                                                        <p class="text-muted mb-0"><?php echo $days_remaining; ?> days left</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                            data-bs-toggle="modal" data-bs-target="#viewTaskModal"
                                                            data-task-id="<?php echo $task['id']; ?>"
                                                            data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                            data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>"
                                                            data-task-course-code="<?php echo htmlspecialchars($task['course_code']); ?>"
                                                            data-task-course-title="<?php echo htmlspecialchars($task['course_title']); ?>"
                                                            data-task-instructions="<?php echo htmlspecialchars($task['instructions']); ?>"
                                                            data-task-deadline="<?php echo htmlspecialchars($task['deadline']); ?>"
                                                            data-task-status="<?php echo htmlspecialchars($task['status']); ?>">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                        <?php if (isLoggedIn() && isCR()): ?>
                                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                                data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                                                data-task-id="<?php echo $task['id']; ?>"
                                                                data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                                data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>"
                                                                data-task-course-code="<?php echo htmlspecialchars($task['course_code']); ?>"
                                                                data-task-course-title="<?php echo htmlspecialchars($task['course_title']); ?>"
                                                                data-task-instructions="<?php echo htmlspecialchars($task['instructions']); ?>"
                                                                data-task-deadline="<?php echo htmlspecialchars($task['deadline']); ?>"
                                                                data-task-status="<?php echo htmlspecialchars($task['status']); ?>">
                                                                <i class="bi bi-pencil"></i> Edit
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                                data-bs-toggle="modal" data-bs-target="#deleteTaskModal"
                                                                data-task-id="<?php echo $task['id']; ?>"
                                                                data-task-title="<?php echo htmlspecialchars($task['title']); ?>">
                                                                <i class="bi bi-trash"></i> Delete
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Completed Tasks Section -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Completed Tasks</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($completed_tasks)): ?>
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <div>No completed tasks available.</div>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($completed_tasks as $task):
                                        $completed_date = new DateTime($task['updated_at']);
                                        ?>
                                        <div class="col-6 col-md-4 col-lg-3 mb-4">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <span
                                                        class="badge bg-secondary"><?php echo htmlspecialchars($task['task_type']); ?></span>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle"></i> Done
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                                    <h6 class="card-subtitle mb-2 text-muted">
                                                        <?php echo htmlspecialchars($task['course_code']) . ' - ' . htmlspecialchars($task['course_title']); ?>
                                                    </h6>

                                                    <?php if (!empty($task['instructions'])): ?>
                                                        <p class="card-text">
                                                            <?php echo htmlspecialchars(substr($task['instructions'], 0, 30)) . (strlen($task['instructions']) > 30 ? '...' : ''); ?>
                                                            <?php if (strlen($task['instructions']) > 30): ?>
                                                                <span class="view-more"><i class="bi bi-eye-fill"></i></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    <?php endif; ?>

                                                    <p class="text-muted mb-0">Completed on:
                                                        <?php echo $completed_date->format('M d, Y'); ?>
                                                    </p>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                            data-bs-toggle="modal" data-bs-target="#viewTaskModal"
                                                            data-task-id="<?php echo $task['id']; ?>"
                                                            data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                            data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>"
                                                            data-task-course-code="<?php echo htmlspecialchars($task['course_code']); ?>"
                                                            data-task-course-title="<?php echo htmlspecialchars($task['course_title']); ?>"
                                                            data-task-instructions="<?php echo htmlspecialchars($task['instructions']); ?>"
                                                            data-task-deadline="<?php echo htmlspecialchars($task['deadline']); ?>"
                                                            data-task-status="<?php echo htmlspecialchars($task['status']); ?>">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                        <?php if (isLoggedIn() && isCR()): ?>
                                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                                data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                                                data-task-id="<?php echo $task['id']; ?>"
                                                                data-task-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                                data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>"
                                                                data-task-course-code="<?php echo htmlspecialchars($task['course_code']); ?>"
                                                                data-task-course-title="<?php echo htmlspecialchars($task['course_title']); ?>"
                                                                data-task-instructions="<?php echo htmlspecialchars($task['instructions']); ?>"
                                                                data-task-deadline="<?php echo htmlspecialchars($task['deadline']); ?>"
                                                                data-task-status="<?php echo htmlspecialchars($task['status']); ?>">
                                                                <i class="bi bi-pencil"></i> Edit
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                                data-bs-toggle="modal" data-bs-target="#deleteTaskModal"
                                                                data-task-id="<?php echo $task['id']; ?>"
                                                                data-task-title="<?php echo htmlspecialchars($task['title']); ?>">
                                                                <i class="bi bi-trash"></i> Delete
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isLoggedIn() && isCR()): ?>
            <?php
            // Check if Google Calendar is available
            $calendarAvailable = class_exists('GoogleCalendarManager') && file_exists('config/google_service_account.json');
            ?>
            <div class="alert alert-info d-flex align-items-center justify-content-center text-center">
                <div>
                    <i class="bi bi-calendar-check me-2"></i>
                    <strong>Google Calendar Integration:</strong>
                    <?php if ($calendarAvailable): ?>
                        <span class="text-success">Active</span>

                    <?php else: ?>
                        <span class="text-warning">Not Available</span>
                        <small class="d-block">
                            <?php if (!class_exists('GoogleCalendarManager')): ?>
                                Google Calendar class not found. Check includes/google_calendar.php
                            <?php elseif (!file_exists('config/google_service_account.json')): ?>
                                Service account file not found. Check config/google_service_account.json
                            <?php else: ?>
                                Install Google API Client: composer require google/apiclient
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bottom Navigation for Mobile -->
    <nav class="bottom-nav">
        <div class="container">
            <div class="row">
                <div class="col">
                    <a href="dashboard.php" class="nav-link">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </div>
                <div class="col">
                    <a href="tasks.php" class="nav-link active">
                        <i class="bi bi-check2-square"></i>
                        <span>Tasks</span>
                    </a>
                </div>
                <?php if (isLoggedIn() && isCR()): ?>
                    <div class="col">
                        <a href="create_course.php" class="nav-link">
                            <i class="bi bi-plus-circle"></i>
                            <span>Create</span>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                    <div class="col">
                        <a href="profile.php" class="nav-link">
                            <i class="bi bi-person"></i>
                            <span>Profile</span>
                        </a>
                    </div>
                    <div class="col">
                        <a href="logout.php" class="nav-link">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="col">
                        <a href="login.php" class="nav-link">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Login</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- View Task Modal - Available to all users -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow rounded-4 border-0">
                <div class="modal-header bg-gradient-primary text-white rounded-top-4" style="background: linear-gradient(90deg, #4f8cff 0%, #6a82fb 100%);">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="viewTaskModalLabel">
                        <i class="bi bi-eye-fill"></i> Task Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <h4 id="view_task_title" class="mb-3 text-center text-primary"></h4>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <p class="mb-2"><span class="badge bg-primary"><i class="bi bi-clipboard-check me-1"></i> <span id="view_task_type"></span></span></p>
                            <p class="mb-1"><i class="bi bi-book me-1 text-info"></i> <strong>Course:</strong> <span id="view_course_code"></span> - <span id="view_course_title"></span></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1"><i class="bi bi-calendar-event me-1 text-success"></i> <strong>Deadline:</strong> <span id="view_deadline"></span></p>
                            <p class="mb-1"><i class="bi bi-flag me-1 text-warning"></i> <strong>Status:</strong> <span id="view_status"></span></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="d-flex align-items-center gap-2 text-secondary"><i class="bi bi-info-circle"></i> Instructions</h6>
                        <div id="view_instructions" class="p-3 bg-light rounded-3 border border-1"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i> Close</button>
                    <?php if (isLoggedIn() && isCR()): ?>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editTaskModal" id="view_edit_btn">
                            <i class="bi bi-pencil-square me-1"></i> Edit Task
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isLoggedIn() && isCR()): ?>
        <!-- Add Task Modal - Only for CR users -->
        <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow rounded-4">
                    <div class="modal-header bg-primary text-white rounded-top-4">
                        <h5 class="modal-title" id="addTaskModalLabel"><i class="bi bi-plus-circle me-2"></i>Add New Task</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="tasks.php" method="post">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control form-control-lg rounded-3" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="task_type" class="form-label">Task Type</label>
                                <select class="form-select form-select-lg rounded-3" id="task_type" name="task_type" required>
                                    <option value="">Select Task Type</option>
                                    <option value="Presentation">Presentation</option>
                                    <option value="Assignment">Assignment</option>
                                    <option value="Lab Report">Lab Report</option>
                                    <option value="Lab Evaluation">Lab Evaluation</option>
                                    <option value="Quiz">Quiz</option>
                                    <option value="Mid Term">Mid Term</option>
                                    <option value="Final Exam">Final Exam</option>
                                    <option value="Project">Project</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="course_code" class="form-label">Course Code</label>
                                    <input type="text" class="form-control form-control-lg rounded-3" id="course_code" name="course_code" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="course_title" class="form-label">Course Title</label>
                                    <input type="text" class="form-control form-control-lg rounded-3" id="course_title" name="course_title" required>
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <label for="instructions" class="form-label">Instructions</label>
                                <textarea class="form-control form-control-lg rounded-3" id="instructions" name="instructions" rows="3" placeholder="Add any details or instructions..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="deadline" class="form-label">Deadline</label>
                                <input type="datetime-local" class="form-control form-control-lg rounded-3" id="deadline" name="deadline" required>
                            </div>
                        </div>
                        <div class="modal-footer bg-light rounded-bottom-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary px-4" name="add_task">Add Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Task Modal - Only for CR users -->
        <div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow rounded-4">
                    <div class="modal-header bg-info text-white rounded-top-4">
                        <h5 class="modal-title" id="editTaskModalLabel"><i class="bi bi-pencil me-2"></i>Edit Task</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="tasks.php" method="post">
                        <input type="hidden" name="task_id" id="edit_task_id">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="edit_title" class="form-label">Title</label>
                                <input type="text" class="form-control form-control-lg rounded-3" id="edit_title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_task_type" class="form-label">Task Type</label>
                                <select class="form-select form-select-lg rounded-3" id="edit_task_type" name="task_type" required>
                                    <option value="">Select Task Type</option>
                                    <option value="Presentation">Presentation</option>
                                    <option value="Assignment">Assignment</option>
                                    <option value="Lab Report">Lab Report</option>
                                    <option value="Lab Evaluation">Lab Evaluation</option>
                                    <option value="Quiz">Quiz</option>
                                    <option value="Mid Term">Mid Term</option>
                                    <option value="Final Exam">Final Exam</option>
                                    <option value="Project">Project</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit_course_code" class="form-label">Course Code</label>
                                    <input type="text" class="form-control form-control-lg rounded-3" id="edit_course_code" name="course_code" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_course_title" class="form-label">Course Title</label>
                                    <input type="text" class="form-control form-control-lg rounded-3" id="edit_course_title" name="course_title" required>
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <label for="edit_instructions" class="form-label">Instructions</label>
                                <textarea class="form-control form-control-lg rounded-3" id="edit_instructions" name="instructions" rows="3" placeholder="Add any details or instructions..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="edit_deadline" class="form-label">Deadline</label>
                                <input type="datetime-local" class="form-control form-control-lg rounded-3" id="edit_deadline" name="deadline" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select form-select-lg rounded-3" id="edit_status" name="status" required>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer bg-light rounded-bottom-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-info px-4 text-white" name="edit_task">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Task Modal - Only for CR users -->
        <div class="modal fade" id="deleteTaskModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="tasks.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Task</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this task?</p>
                            <p class="text-danger" id="deleteTaskTitle"></p>
                            <p class="text-muted">This action cannot be undone.</p>
                            <input type="hidden" name="id" id="deleteTaskId">
                            <input type="hidden" name="delete_task" value="1">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Add click event to view-more indicators
            document.querySelectorAll('.view-more').forEach(function (element) {
                element.addEventListener('click', function () {
                    // Find the closest card and trigger the View button's click
                    const card = this.closest('.card');
                    if (card) {
                        const viewButton = card.querySelector('button[data-bs-target="#viewTaskModal"]');
                        if (viewButton) {
                            viewButton.click();
                        }
                    }
                });
            });
            // Handle View Modal - Available to all users
            const viewTaskModal = document.getElementById('viewTaskModal');
            if (viewTaskModal) {
                viewTaskModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    // Extract task data from button attributes
                    const taskId = button.getAttribute('data-task-id');
                    const title = button.getAttribute('data-task-title');
                    const taskType = button.getAttribute('data-task-type');
                    const courseCode = button.getAttribute('data-task-course-code');
                    const courseTitle = button.getAttribute('data-task-course-title');
                    const instructions = button.getAttribute('data-task-instructions');
                    const deadline = button.getAttribute('data-task-deadline');
                    const status = button.getAttribute('data-task-status');

                    // Set modal content
                    document.getElementById('view_task_title').textContent = title;
                    document.getElementById('view_task_type').textContent = taskType;
                    document.getElementById('view_course_code').textContent = courseCode;
                    document.getElementById('view_course_title').textContent = courseTitle;
                    document.getElementById('view_instructions').textContent = instructions;

                    // Format deadline
                    const deadlineDate = new Date(deadline);
                    const formattedDeadline = deadlineDate.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    document.getElementById('view_deadline').textContent = formattedDeadline;

                    // Format status with appropriate badge
                    const statusElement = document.getElementById('view_status');
                    statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    statusElement.className = status === 'completed' ? 'badge bg-success' : 'badge bg-primary';

                    <?php if (isLoggedIn() && isCR()): ?>
                        // Update the edit button to transfer data (only for CR users)
                        const editButton = document.getElementById('view_edit_btn');
                        if (editButton) {
                            editButton.setAttribute('data-task-id', taskId);
                            editButton.setAttribute('data-task-title', title);
                            editButton.setAttribute('data-task-type', taskType);
                            editButton.setAttribute('data-task-course-code', courseCode);
                            editButton.setAttribute('data-task-course-title', courseTitle);
                            editButton.setAttribute('data-task-instructions', instructions);
                            editButton.setAttribute('data-task-deadline', deadline);
                            editButton.setAttribute('data-task-status', status);
                        }
                    <?php endif; ?>
                });
            }

            <?php if (isLoggedIn() && isCR()): ?>
                // Handle Edit Modal - Only for CR users
                const editTaskModal = document.getElementById('editTaskModal');
                if (editTaskModal) {
                    editTaskModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;

                        // Extract task data from button attributes
                        const taskId = button.getAttribute('data-task-id');
                        const title = button.getAttribute('data-task-title');
                        const taskType = button.getAttribute('data-task-type');
                        const courseCode = button.getAttribute('data-task-course-code');
                        const courseTitle = button.getAttribute('data-task-course-title');
                        const instructions = button.getAttribute('data-task-instructions');
                        const deadline = button.getAttribute('data-task-deadline');
                        const status = button.getAttribute('data-task-status');

                        // Set modal form values
                        document.getElementById('edit_task_id').value = taskId;
                        document.getElementById('edit_title').value = title;
                        document.getElementById('edit_task_type').value = taskType;
                        document.getElementById('edit_course_code').value = courseCode;
                        document.getElementById('edit_course_title').value = courseTitle;
                        document.getElementById('edit_instructions').value = instructions;

                        // Format deadline for datetime-local input
                        const deadlineDate = new Date(deadline);
                        const formattedDeadline = deadlineDate.toISOString().slice(0, 16);
                        document.getElementById('edit_deadline').value = formattedDeadline;

                        document.getElementById('edit_status').value = status;
                    });
                }
            <?php endif; ?>

            const deleteTaskModal = document.getElementById('deleteTaskModal');
            if (deleteTaskModal) {
                deleteTaskModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const taskId = button.getAttribute('data-task-id');
                    const taskTitle = button.getAttribute('data-task-title');
                    document.getElementById('deleteTaskId').value = taskId;
                    document.getElementById('deleteTaskTitle').textContent = taskTitle;
                });
            }
        });
    </script>
</body>

</html>