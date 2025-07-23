<?php
require_once 'vendor/autoload.php';
require_once 'includes/google_calendar.php';

echo "<h2>Quick Google Calendar Test</h2>";

try {
    $manager = new GoogleCalendarManager();
    echo "✅ Manager created successfully<br>";
    
    $result = $manager->testConnection();
    if ($result['success']) {
        echo "✅ Connection successful!<br>";
        echo "Calendar: " . $result['calendar_name'] . "<br>";
        echo "ID: " . $result['calendar_id'] . "<br>";
        if (isset($result['message'])) {
            echo "Note: " . $result['message'] . "<br>";
        }
    } else {
        echo "❌ Connection failed: " . $result['error'] . "<br>";
        if (isset($result['original_error'])) {
            echo "Original error: " . $result['original_error'] . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='tasks.php'>Back to Tasks</a>";
?> 