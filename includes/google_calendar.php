<?php
require_once __DIR__ . '/../vendor/autoload.php';

class GoogleCalendarManager {
    private $client;
    private $service;
    private $calendarId;
    
    public function __construct() {
        $this->client = new Google_Client();
        
        // Set up service account authentication
        $this->client->setAuthConfig(__DIR__ . '/../config/google_service_account.json');
        $this->client->setScopes([
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events'
        ]);
        
        // Use the service account's calendar
        $this->calendarId = '57f5fc468749bbae1c5a9cb2240aab0aebd98c621e9e88bfee9a6132a00ca26d@group.calendar.google.com';
        
        $this->service = new Google_Service_Calendar($this->client);
    }
    
    public function createEvent($task) {
        try {
            $event = new Google_Service_Calendar_Event([
                'summary' => $task['title'],
                'description' => "Course: {$task['course_code']} - {$task['course_title']}\n\nType: {$task['task_type']}\n\nInstructions: {$task['instructions']}",
                'start' => [
                    'dateTime' => date('c', strtotime($task['deadline'])),
                    'timeZone' => 'Asia/Dhaka',
                ],
                'end' => [
                    'dateTime' => date('c', strtotime($task['deadline'] . ' +1 hour')),
                    'timeZone' => 'Asia/Dhaka',
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60], // 1 day before
                        ['method' => 'popup', 'minutes' => 60], // 1 hour before
                    ],
                ],
                'colorId' => $this->getColorIdForTaskType($task['task_type']),
            ]);
            
            $createdEvent = $this->service->events->insert($this->calendarId, $event);
            return $createdEvent->getId();
            
        } catch (Exception $e) {
            error_log("Google Calendar Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateEvent($eventId, $task) {
        try {
            $event = $this->service->events->get($this->calendarId, $eventId);
            
            $event->setSummary($task['title']);
            $event->setDescription("Course: {$task['course_code']} - {$task['course_title']}\n\nType: {$task['task_type']}\n\nInstructions: {$task['instructions']}");
            
            $start = new Google_Service_Calendar_EventDateTime();
            $start->setDateTime(date('c', strtotime($task['deadline'])));
            $start->setTimeZone('Asia/Dhaka');
            $event->setStart($start);
            
            $end = new Google_Service_Calendar_EventDateTime();
            $end->setDateTime(date('c', strtotime($task['deadline'] . ' +1 hour')));
            $end->setTimeZone('Asia/Dhaka');
            $event->setEnd($end);
            
            $event->setColorId($this->getColorIdForTaskType($task['task_type']));
            
            $updatedEvent = $this->service->events->update($this->calendarId, $eventId, $event);
            return $updatedEvent->getId();
            
        } catch (Exception $e) {
            error_log("Google Calendar Update Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteEvent($eventId) {
        try {
            $this->service->events->delete($this->calendarId, $eventId);
            return true;
        } catch (Exception $e) {
            error_log("Google Calendar Delete Error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getColorIdForTaskType($taskType) {
        $colorMap = [
            'Assignment' => '1', // Blue
            'Presentation' => '2', // Green
            'Lab Report' => '3', // Purple
            'Lab Evaluation' => '4', // Red
            'Quiz' => '5', // Yellow
            'Mid Term' => '6', // Orange
            'Final Exam' => '7', // Red
            'Project' => '8', // Purple
            'Other' => '9', // Gray
        ];
        
        return $colorMap[$taskType] ?? '1';
    }
    
    // Test connection method
    public function testConnection() {
        try {
            // First try to get the calendar directly
            $calendar = $this->service->calendars->get($this->calendarId);
            return [
                'success' => true,
                'calendar_name' => $calendar->getSummary(),
                'calendar_id' => $this->calendarId
            ];
        } catch (Exception $e) {
            // If that fails, try to create the calendar
            try {
                $calendar = new Google_Service_Calendar_Calendar();
                $calendar->setSummary('Dear 63 Tasks');
                $calendar->setTimeZone('Asia/Dhaka');
                
                $createdCalendar = $this->service->calendars->insert($calendar);
                
                // Update the calendar ID to the newly created one
                $this->calendarId = $createdCalendar->getId();
                
                return [
                    'success' => true,
                    'calendar_name' => $createdCalendar->getSummary(),
                    'calendar_id' => $this->calendarId,
                    'message' => 'Calendar created successfully'
                ];
            } catch (Exception $e2) {
                return [
                    'success' => false,
                    'error' => $e2->getMessage(),
                    'original_error' => $e->getMessage()
                ];
            }
        }
    }
}
?>