<?php
header('Content-Type: application/json');
session_start();
require_once "../config/database.php";

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get the endpoint from the URL
$endpoint = $_GET['endpoint'] ?? '';

// Role-based access control
$role = $_SESSION['role'];
$allowed_endpoints = [
    'Student' => [
        'view_grades',
        'view_attendance',
        'view_courses',
        'view_profile'
    ],
    'Teacher' => [
        'view_grades',
        'add_grade',
        'view_attendance',
        'mark_attendance',
        'view_courses',
        'create_course',
        'view_students',
        'view_profile'
    ]
];

// Check if user has permission to access the endpoint
if (!in_array($endpoint, $allowed_endpoints[$role])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access forbidden']);
    exit;
}

// Handle different endpoints
switch ($endpoint) {
    case 'view_grades':
        require_once "../modules/grades/Grade.php";
        $grade = new Grade($db);
        
        if ($role === 'Student') {
            $data = $grade->getStudentGrades($_SESSION['user_id']);
        } else {
            $student_id = $_GET['student_id'] ?? null;
            if (!$student_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Student ID required']);
                exit;
            }
            $data = $grade->getStudentGrades($student_id);
        }
        echo json_encode($data);
        break;

    case 'add_grade':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        require_once "../modules/grades/Grade.php";
        $grade = new Grade($db);
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $grade->addGrade(
            $data['student_id'],
            $data['course_id'],
            $data['grade']
        );
        
        echo json_encode(['success' => $result]);
        break;

    case 'view_attendance':
        require_once "../modules/attendance/Attendance.php";
        $attendance = new Attendance($db);
        
        if ($role === 'Student') {
            $data = $attendance->getStudentAttendance($_SESSION['user_id']);
        } else {
            $date = $_GET['date'] ?? date('Y-m-d');
            $data = $attendance->getAttendanceByDate($date);
        }
        echo json_encode($data);
        break;

    case 'mark_attendance':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        require_once "../modules/attendance/Attendance.php";
        $attendance = new Attendance($db);
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $attendance->markAttendance(
            $data['student_id'],
            $data['date'],
            $data['status']
        );
        
        echo json_encode(['success' => $result]);
        break;

    case 'view_courses':
        require_once "../modules/courses/Course.php";
        $course = new Course($db);
        
        if ($role === 'Student') {
            $data = $course->getStudentCourses($_SESSION['user_id']);
        } else {
            $data = $course->getCourses();
        }
        echo json_encode($data);
        break;

    case 'create_course':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        require_once "../modules/courses/Course.php";
        $course = new Course($db);
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $course->createCourse(
            $data['name'],
            $_SESSION['user_id'],
            $data['schedule']
        );
        
        echo json_encode(['success' => $result]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
