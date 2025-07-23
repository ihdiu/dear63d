<?php
require_once 'includes/functions.php';
requireLogin();
requireCR();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $course_code = sanitizeInput($_POST['course_code']);
    $description = sanitizeInput($_POST['description']);
    $semester = sanitizeInput($_POST['semester']);
    $telegram_topic_link = sanitizeInput($_POST['telegram_topic_link']);
    
    if (empty($name) || empty($course_code) || empty($description) || empty($semester)) {
        $error = 'All fields are required';
    } else {
        if (createCourse($name, $course_code, $description, $semester, $telegram_topic_link)) {
            $success = 'Course created successfully';
        } else {
            $error = 'Failed to create course';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course - Dear 63_D</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
            </div>
            <div>
                <h5 class="mb-0"><?php echo htmlspecialchars($_SESSION['name']); ?></h5>
                <small class="text-muted">Class Representative</small>
            </div>
        </div>
    </div>

    <!-- Desktop Sidebar -->
    <div class="d-none d-md-block col-md-3 col-lg-2 px-0 sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4">Dear 63_D</h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tasks.php">
                        <i class="bi bi-check2-square"></i> Tasks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="create_course.php">
                        <i class="bi bi-plus-circle"></i> Create Course
                    </a>
                </li>
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
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Create New Course</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <div><?php echo $error; ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="bi bi-check-circle me-2"></i>
                                <div><?php echo $success; ?></div>
                            </div>
                            <?php endif; ?>

                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Course Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                    <div class="invalid-feedback">Please enter a course name</div>
                                </div>

                                <div class="mb-3">
                                    <label for="course_code" class="form-label">Course Code</label>
                                    <input type="text" class="form-control" id="course_code" name="course_code" required>
                                    <div class="invalid-feedback">Please enter a course code</div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                                    <div class="invalid-feedback">Please enter a description</div>
                                </div>

                                <div class="mb-3">
                                    <label for="semester" class="form-label">Semester</label>
                                    <input type="text" class="form-control" id="semester" name="semester" required>
                                    <div class="invalid-feedback">Please enter the semester</div>
                                </div>

                                <div class="mb-3">
                                    <label for="telegram_topic_link" class="form-label">Telegram Topic Link</label>
                                    <input type="text" class="form-control" id="telegram_topic_link" name="telegram_topic_link"
                                        placeholder="https://t.me/c/2302994371/847">
                                    <p class="text-muted">
                                        This link will be used to send notifications about new materials to the specific course thread.
                                    </p>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Create Course
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                    <a href="tasks.php" class="nav-link">
                        <i class="bi bi-check2-square"></i>
                        <span>Tasks</span>
                    </a>
                </div>
                <div class="col">
                    <a href="create_course.php" class="nav-link active">
                        <i class="bi bi-plus-circle"></i>
                        <span>Create</span>
                    </a>
                </div>
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
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html> 