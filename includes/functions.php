<?php
session_start();

// Include vendor autoloader for Google API Client
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Include Google Calendar class if available
if (file_exists(__DIR__ . '/google_calendar.php')) {
    require_once __DIR__ . '/google_calendar.php';
}

require_once __DIR__ . '/../config/database.php';

// Ensure database tables exist
function ensureTablesExist() {
    global $conn;
    
    try {
        // Remove database creation, only ensure tables exist
        // $conn->exec("CREATE DATABASE IF NOT EXISTS raisulme_class");
        // $conn->exec("USE raisulme_class");
        
        // Create users table
        $conn->exec("CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('CR', 'Student') NOT NULL,
            class_id VARCHAR(50),
            telegram varchar(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Create courses table
        $conn->exec("CREATE TABLE IF NOT EXISTS courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            course_code VARCHAR(20) NOT NULL,
            instructor_name VARCHAR(100),
            semester VARCHAR(50),
            tags VARCHAR(255),
            telegram_topic_link VARCHAR(255),
            telegram_chat_id VARCHAR(50),
            telegram_thread_id VARCHAR(50),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )");
        
        // Create enrollments table
        $conn->exec("CREATE TABLE IF NOT EXISTS enrollments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT,
            course_id INT,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id),
            UNIQUE KEY unique_enrollment (student_id, course_id)
        )");
        
        // Create sections table
        $conn->exec("CREATE TABLE IF NOT EXISTS sections (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT,
            title VARCHAR(100) NOT NULL,
            type ENUM('chapter', 'week', 'lecture') NOT NULL,
            description TEXT,
            section_order INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id)
        )");
        
        // Create materials table
        $conn->exec("CREATE TABLE IF NOT EXISTS materials (
            id INT PRIMARY KEY AUTO_INCREMENT,
            section_id INT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            file_url VARCHAR(255),
            file_type VARCHAR(50),
            uploaded_by INT,
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (section_id) REFERENCES sections(id),
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )");
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Ensure tasks table exists with calendar event ID
function ensureTasksTableExists() {
    global $conn;
    
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS tasks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            task_type VARCHAR(50) NOT NULL,
            course_code VARCHAR(50) NOT NULL,
            course_title VARCHAR(100) NOT NULL,
            instructions TEXT,
            deadline DATETIME NOT NULL,
            status ENUM('pending', 'completed') DEFAULT 'pending',
            calendar_event_id VARCHAR(255) NULL,
            created_by INT,
            message_id VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )");
        
        // Add calendar_event_id column if it doesn't exist
        $conn->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS calendar_event_id VARCHAR(255) NULL");
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Task functions
function createTask($title, $task_type, $course_code, $course_title, $instructions, $deadline, $message_id) {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("INSERT INTO tasks (title, task_type, course_code, course_title, instructions, deadline, created_by, message_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $task_type, $course_code, $course_title, $instructions, $deadline, $_SESSION['user_id'], $message_id]);
        
        $task_id = $conn->lastInsertId();
        
        // Get the created task
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        // Create Google Calendar event
        if (class_exists('GoogleCalendarManager')) {
            try {
                require_once __DIR__ . '/google_calendar.php';
                $calendarManager = new GoogleCalendarManager();
                $event_id = $calendarManager->createEvent($task);
                
                if ($event_id) {
                    $stmt = $conn->prepare("UPDATE tasks SET calendar_event_id = ? WHERE id = ?");
                    $stmt->execute([$event_id, $task_id]);
                    error_log("Google Calendar event created successfully: " . $event_id);
                } else {
                    error_log("Failed to create Google Calendar event for task: " . $task_id);
                }
            } catch (Exception $e) {
                error_log("Google Calendar Error in createTask: " . $e->getMessage());
            }
        } else {
            error_log("GoogleCalendarManager class not found in createTask");
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Create Task Error: " . $e->getMessage());
        return false;
    }
}

function updateTask($task_id, $title, $task_type, $course_code, $course_title, $instructions, $deadline, $status) {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        // Get current task to check if it has calendar event
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND created_by = ?");
        $stmt->execute([$task_id, $_SESSION['user_id']]);
        $current_task = $stmt->fetch();
        
        if (!$current_task) {
            throw new Exception("Task not found or unauthorized");
        }
        
        $stmt = $conn->prepare("UPDATE tasks 
                               SET title = ?, task_type = ?, course_code = ?, course_title = ?, 
                                   instructions = ?, deadline = ?, status = ? 
                               WHERE id = ? AND created_by = ?");
        $stmt->execute([$title, $task_type, $course_code, $course_title, $instructions, $deadline, $status, $task_id, $_SESSION['user_id']]);
        
        // Update Google Calendar event if exists
        if ($current_task['calendar_event_id'] && class_exists('GoogleCalendarManager')) {
            require_once __DIR__ . '/google_calendar.php';
            $calendarManager = new GoogleCalendarManager();
            
            $updated_task = [
                'title' => $title,
                'task_type' => $task_type,
                'course_code' => $course_code,
                'course_title' => $course_title,
                'instructions' => $instructions,
                'deadline' => $deadline,
                'status' => $status
            ];
            
            $calendarManager->updateEvent($current_task['calendar_event_id'], $updated_task);
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Update Task Error: " . $e->getMessage());
        return false;
    }
}

function deleteTask($task_id) {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        // Get task to check if it has calendar event
        $stmt = $conn->prepare("SELECT calendar_event_id FROM tasks WHERE id = ? AND created_by = ?");
        $stmt->execute([$task_id, $_SESSION['user_id']]);
        $task = $stmt->fetch();
        
        if (!$task) {
            throw new Exception("Task not found or unauthorized");
        }
        
        // Delete Google Calendar event if exists
        if ($task['calendar_event_id'] && class_exists('GoogleCalendarManager')) {
            require_once __DIR__ . '/google_calendar.php';
            $calendarManager = new GoogleCalendarManager();
            $calendarManager->deleteEvent($task['calendar_event_id']);
        }
        
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND created_by = ?");
        $stmt->execute([$task_id, $_SESSION['user_id']]);
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Delete Task Error: " . $e->getMessage());
        return false;
    }
}

function getUpcomingTasks() {
    global $conn;
    // All users see all pending tasks
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE status = 'pending' ORDER BY deadline ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getCompletedTasks() {
    global $conn;
    // All users see all completed tasks
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE status = 'completed' ORDER BY updated_at DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Call this function when the file is included
ensureTablesExist();

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isCR() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'CR';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Student';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

function requireCR() {
    requireLogin();
    if (!isCR()) {
        header('Location: /unauthorized.php');
        exit();
    }
}

function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        header('Location: /unauthorized.php');
        exit();
    }
}

// User functions
function registerUser($name, $email, $password, $role, $class_id) {
    global $conn;
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        throw new Exception("Email address is already registered");
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, class_id) 
                           VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$name, $email, $hashed_password, $role, $class_id]);
}

function loginUser($email, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        return true;
    }
    return false;
}

// Course functions
function createCourse($name, $course_code, $description, $semester, $telegram_topic_link) {
    global $conn;
    
    $telegram_info = parseTelegramTopicLink($telegram_topic_link);
    if (!$telegram_info) {
        throw new Exception("Invalid Telegram topic link format");
    }
    
    $stmt = $conn->prepare("INSERT INTO courses (name, course_code, description, semester, telegram_topic_link, telegram_chat_id, telegram_thread_id, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $name, 
        $course_code, 
        $description, 
        $semester, 
        $telegram_topic_link,
        $telegram_info['chat_id'],
        $telegram_info['thread_id'],
        $_SESSION['user_id']
    ]);
}

function getCourses() {
    global $conn;
    // All users see all courses
    $stmt = $conn->prepare("SELECT c.*, u.name as instructor_name FROM courses c LEFT JOIN users u ON c.created_by = u.id");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getCourseById($course_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT c.*, u.name as instructor_name 
                           FROM courses c 
                           LEFT JOIN users u ON c.created_by = u.id 
                           WHERE c.id = ?");
    $stmt->execute([$course_id]);
    return $stmt->fetch();
}


function getCoursesByCreator($creator_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT c.*, u.name as instructor_name 
                          FROM courses c 
                          LEFT JOIN users u ON c.created_by = u.id 
                          WHERE c.created_by = ?");
    $stmt->execute([$creator_id]);
    return $stmt->fetchAll();
}

// Material functions
function uploadMaterial($section_id, $title, $description, $file_url, $file_type) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO materials (section_id, title, description, file_url, file_type, uploaded_by) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$section_id, $title, $description, $file_url, $file_type, $_SESSION['user_id']]);
}

function getMaterials($section_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT m.*, u.name as uploader_name 
                           FROM materials m 
                           LEFT JOIN users u ON m.uploaded_by = u.id 
                           WHERE m.section_id = ? 
                           ORDER BY m.upload_date ASC, m.title ASC");
    $stmt->execute([$section_id]);
    return $stmt->fetchAll();
}

// Helper functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}

function formatDate($date) {
    return date('F j, Y, g:i a', strtotime($date));
}

function getSections($course_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM sections WHERE course_id = ? ORDER BY section_order ASC");
    $stmt->execute([$course_id]);
    return $stmt->fetchAll();
}

function getSectionById($section_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    return $stmt->fetch();
}

// Add function to parse Telegram topic link
function parseTelegramTopicLink($link) {
    // Example link: https://t.me/c/2302994371/847
    $pattern = '/t\.me\/c\/(\d+)\/(\d+)/';
    if (preg_match($pattern, $link, $matches)) {
        return [
            'chat_id' => $matches[1],
            'thread_id' => $matches[2]
        ];
    }
    return false;
}

// Add function to send Telegram message to specific thread
function sendTelegramMessage($chat_id, $thread_id, $message) {
    $bot_token = '7407270613:AAGRwD2YBI1aZ06kQdVXUJaYeMjgQs02D2Y';
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'message_thread_id' => $thread_id,
        'text' => $message,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($api_url, false, $context);
    return $result;
}

function sendTelegramFile($chat_id, $thread_id, $file_path, $caption) {
    $bot_token = '7407270613:AAGRwD2YBI1aZ06kQdVXUJaYeMjgQs02D2Y';
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendDocument";
    
    // Check if file exists
    if (!file_exists($file_path)) {
        error_log("File not found: " . $file_path);
        return false;
    }
    
    // Prepare the file for upload
    $file = new CURLFile($file_path);
    
    // Prepare the data
    $data = [
        'chat_id' => $chat_id,
        'message_thread_id' => $thread_id,
        'document' => $file,
        'caption' => $caption
    ];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Execute the request
    $result = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        error_log('Telegram file upload error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    return $result;
}




function send_to_telegram($message, $user_id){
    global $conn;

    // Fetch the user's telegram link from the database
    $stmt = $conn->prepare("SELECT telegram FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if (!$row || empty($row['telegram'])) {
        return false; // No telegram link available
    }

    $telegram_link = trim($row['telegram']);
    $bot_token = '7327177792:AAExbCpqKBqpqXAcA6S9oRiBzG4-5BAPhGg';
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    // If link matches t.me/c/<chat_id>/<thread_id>
    if (preg_match('#t\.me/c/(\d+)/(\d+)#', $telegram_link, $matches)) {
        $chat_id = '-100' . $matches[1];  // prepend -100 for supergroups
        $thread_id = $matches[2];

        $data = [
            'chat_id' => $chat_id,
            'message_thread_id' => $thread_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($api_url, false, $context);

        if ($result === FALSE) {
            return false;
        }

        $response = json_decode($result, true);
        

        if (isset($response['ok']) && $response['ok'] === true) {
            echo $response['result']['message_id'];
            return $response['result']['message_id']; // ✅ correct way to get message_id
        } else {
            // Optional: log or return the error message
            return false;
        }
    }

    return false;
}


function edit_bot_message($new_text, $user_id, $message_id){
    global $conn;
    // Fetch the user's telegram link from the database
    $stmt = $conn->prepare("SELECT telegram FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if (!$row || empty($row['telegram'])) {
        return false; // No telegram link available
    }
    $telegram_link = trim($row['telegram']);

    $bot_token = '7327177792:AAExbCpqKBqpqXAcA6S9oRiBzG4-5BAPhGg';
    $api_url = "https://api.telegram.org/bot{$bot_token}/editMessageText";

    // 1. Supergroup with thread id: https://t.me/c/2302994371/847
    if (preg_match('#t\\.me/c/(\\d+)/(\\d+)#', $telegram_link, $matches)) {
        $chat_id = '-100' . $matches[1];
        $thread_id = $matches[2];
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $new_text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
    }
    

    // Send the edit request
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($api_url, false, $context);

    if ($result === FALSE) {
        return false;
    }

    $response = json_decode($result, true);
    if (isset($response['ok']) && $response['ok'] === true) {
        return true; // Success
    } else {
        // Optional: log error
        // error_log("Edit failed: " . json_encode($response));
        return false;
    }
}

function delete_telegram_message($user_id, $message_id) {
    global $conn;

    // Fetch the user's telegram link from the database
    $stmt = $conn->prepare("SELECT telegram FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row || empty($row['telegram'])) {
        return false; // No telegram link available
    }

    $telegram_link = trim($row['telegram']);

    $bot_token = '7327177792:AAExbCpqKBqpqXAcA6S9oRiBzG4-5BAPhGg';
    $api_url = "https://api.telegram.org/bot{$bot_token}/deleteMessage";

    // Match links like https://t.me/c/2302994371/847
    if (preg_match('#t\.me/c/(\d+)/\d+#', $telegram_link, $matches)) {
        $chat_id = '-100' . $matches[1]; // Supergroup ID

        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($api_url, false, $context);

        if ($result === FALSE) {
            return false;
        }

        $response = json_decode($result, true);
        return isset($response['ok']) && $response['ok'] === true;
    }

    return false; // Invalid telegram link format
}

?> 