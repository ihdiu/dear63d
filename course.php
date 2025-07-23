<?php
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$course_id = (int)$_GET['id'];
$course = getCourseById($course_id);

if (!$course) {
    header('Location: dashboard.php');
    exit;
}

$sections = getSections($course_id);
$selected_section = isset($_GET['section']) ? (int)$_GET['section'] : null;
$materials = $selected_section ? getMaterials($selected_section) : [];
$current_section = $selected_section ? getSectionById($selected_section) : null;

$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title><?php echo htmlspecialchars($course['name']); ?> - Dear 63</title>
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
                    <h5 class="mb-0">Guest User</h5>
                    <small class="text-muted">Viewing as Public</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Desktop Sidebar -->
    <div class="d-none d-md-block col-md-3 col-lg-2 px-0 sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4">Dear 63</h4>
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

            <div class="row">
                <!-- Course Info -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="card-title mb-1"><?php echo htmlspecialchars($course['name']); ?></h4>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                </div>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($course['semester']); ?></span>
                            </div>
                            <p class="mt-3 mb-0"><?php echo htmlspecialchars($course['description']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Sections and Materials -->
                <div class="col-12 col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Materials</h5>
                            <?php if (isLoggedIn() && isCR()): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                                <i class="bi bi-plus"></i> Add Section
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="list-group list-group-flush section-list">
                            <?php foreach ($sections as $section): ?>
                            <a href="?id=<?php echo $course_id; ?>&section=<?php echo $section['id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $selected_section === $section['id'] ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($section['title']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($section['type']); ?></small>
                                    </div>
                                    <i class="bi bi-chevron-right"></i>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-8">
                    <?php if ($current_section): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($current_section['title']); ?></h5>
                            <?php if (isLoggedIn() && isCR()): ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                                    <i class="bi bi-plus"></i> Add Material
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteSectionModal"
                                        data-section-id="<?php echo $current_section['id']; ?>"
                                        data-section-title="<?php echo htmlspecialchars($current_section['title']); ?>">
                                    <i class="bi bi-trash"></i> Delete Section
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4"><?php echo htmlspecialchars($current_section['description']); ?></p>
                            
                            <?php if (empty($materials)): ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="bi bi-info-circle me-2"></i>
                                <div>No materials available for this section yet.</div>
                            </div>
                            <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($materials as $material): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($material['title']); ?></h6>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($material['description']); ?></p>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <?php if (!empty($material['file_url'])): ?>
                                            <a href="download.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                            <?php endif; ?>
                                            <?php if (!empty($material['file_type']) && $material['file_type'] === 'url'): ?>
                                            <a href="<?php echo htmlspecialchars($material['file_url']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-link-45deg"></i> Visit
                                            </a>
                                            <?php endif; ?>
                                            <?php if (isLoggedIn() && isCR()): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteMaterialModal"
                                                    data-material-id="<?php echo $material['id']; ?>"
                                                    data-material-title="<?php echo htmlspecialchars($material['title']); ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-info-circle me-2"></i>
                        <div>Select a section to view its materials.</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Section Modal -->
    <?php if (isLoggedIn() && isCR()): ?>
    <div class="modal fade" id="addSectionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow rounded-4">
                <div class="modal-header bg-primary text-white rounded-top-4">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Section</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_section.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <div class="mb-3">
                            <label for="title" class="form-label">Section Title</label>
                            <input type="text" class="form-control form-control-lg rounded-3" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Section Type</label>
                            <select class="form-select form-select-lg rounded-3" id="type" name="type" required>
                                <option value="Lecture">Lecture</option>
                                <option value="Chapter">Chapter</option>
                                <option value="Assignment">Assignment</option>
                                <option value="Resource">Resource</option>
                                <option value="Syllabus">Syllabus</option>
                                <option value="all">all</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control form-control-lg rounded-3" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Add New</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow rounded-4">
                <div class="modal-header bg-success text-white rounded-top-4">
                    <h5 class="modal-title"><i class="bi bi-plus-square me-2"></i>Add New Material</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_material.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                        <div class="mb-3">
                            <label for="title" class="form-label">Material Title</label>
                            <input type="text" class="form-control form-control-lg rounded-3" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control form-control-lg rounded-3" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="file" class="form-label">File Upload</label>
                            <input type="file" class="form-control form-control-lg rounded-3" id="file" name="file[]" multiple>
                            <div class="form-text">You can select multiple files or provide a URL below</div>
                        </div>
                        <div class="mb-3">
                            <label for="url" class="form-label">URL</label>
                            <input type="url" class="form-control form-control-lg rounded-3" id="url" name="url">
                            <div class="form-text">Provide a URL if not uploading a file</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4">Add Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Delete Material Modal -->
    <?php if (isLoggedIn() && isCR()): ?>
    <div class="modal fade" id="deleteMaterialModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow rounded-4">
                <div class="modal-header bg-danger text-white rounded-top-4">
                    <h5 class="modal-title"><i class="bi bi-trash"></i> Delete Material</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <p>Are you sure you want to delete this material?</p>
                    <p class="text-danger fw-bold" id="deleteMaterialTitle"></p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete_material.php" method="POST" class="d-inline">
                        <input type="hidden" name="material_id" id="deleteMaterialId">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                        <button type="submit" class="btn btn-danger px-4">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Delete Section Modal -->
    <?php if (isLoggedIn() && isCR()): ?>
    <div class="modal fade" id="deleteSectionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow rounded-4">
                <div class="modal-header bg-warning text-dark rounded-top-4">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="alert alert-warning d-flex align-items-center justify-content-center mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Warning: This will delete the section and all its materials!
                    </div>
                    <p>Are you sure you want to delete this section?</p>
                    <p class="text-danger fw-bold" id="deleteSectionTitle"></p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete_section.php" method="POST" class="d-inline">
                        <input type="hidden" name="section_id" id="deleteSectionId">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <button type="submit" class="btn btn-warning px-4 text-dark">Delete Section</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Delete Material Modal Handler
    document.addEventListener('DOMContentLoaded', function() {
        const deleteMaterialModal = document.getElementById('deleteMaterialModal');
        if (deleteMaterialModal) {
            deleteMaterialModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const materialId = button.getAttribute('data-material-id');
                const materialTitle = button.getAttribute('data-material-title');
                
                document.getElementById('deleteMaterialId').value = materialId;
                document.getElementById('deleteMaterialTitle').textContent = materialTitle;
            });
        }

        // Delete Section Modal Handler
        const deleteSectionModal = document.getElementById('deleteSectionModal');
        if (deleteSectionModal) {
            deleteSectionModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const sectionId = button.getAttribute('data-section-id');
                const sectionTitle = button.getAttribute('data-section-title');
                
                document.getElementById('deleteSectionId').value = sectionId;
                document.getElementById('deleteSectionTitle').textContent = sectionTitle;
            });
        }
    });
    </script>
</body>
</html> 