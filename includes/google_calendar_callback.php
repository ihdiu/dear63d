<?php
require_once 'includes/functions.php';

// Service account doesn't need OAuth callback
// Just redirect to tasks page
header('Location: tasks.php?success=Google Calendar is ready to use!');
exit;
?>