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

// At the top of the file, after session_start():
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add this function for debugging
function debug_log($message, $data = null) {
    error_log("DEBUG: " . $message);
    if ($data) {
        error_log("DATA: " . print_r($data, true));
    }
}

// Handle different endpoints
switch ($endpoint) {
    case 'view_grades':
        debug_log("Viewing grades for user: " . $_SESSION['user_id'] . " with role: " . $role);
        
        try {
            if ($role === 'Student') {
                $query = "SELECT g.*, c.name as course_name 
                         FROM Grades g 
                         JOIN Courses c ON g.course_id = c.id 
                         JOIN Students s ON g.student_id = s.id 
                         WHERE s.user_id = ?";
            } else {
                $query = "SELECT g.*, c.name as course_name, s.full_name as student_name 
                         FROM Grades g 
                         JOIN Courses c ON g.course_id = c.id 
                         JOIN Students s ON g.student_id = s.id 
                         JOIN Teachers t ON c.teacher_id = t.user_id 
                         WHERE t.user_id = ?";
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            debug_log("Grades retrieved:", $grades);
            echo json_encode($grades);
        } catch (PDOException $e) {
            debug_log("Error retrieving grades: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
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

    case 'update_profile':
        error_log('Profile update attempt - User ID: ' . $_SESSION['user_id'] . ', Role: ' . $role);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['full_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        try {
            if ($role === 'Student') {
                $query = "UPDATE Students SET full_name = :full_name WHERE user_id = :user_id";
            } else {
                $query = "UPDATE Teachers SET full_name = :full_name WHERE user_id = :user_id";
            }
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'create_course':
        debug_log("Creating course for teacher: " . $_SESSION['user_id']);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            debug_log("Invalid method: " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        if ($role !== 'Teacher') {
            debug_log("Unauthorized role: " . $role);
            http_response_code(403);
            echo json_encode(['error' => 'Only teachers can create courses']);
            exit;
        }
        
        require_once "../modules/courses/Course.php";
        $course = new Course($db);
        
        $data = json_decode(file_get_contents('php://input'), true);
        debug_log("Received course data:", $data);
        
        if (!isset($data['name']) || !isset($data['schedule'])) {
            debug_log("Missing required fields");
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        try {
            $query = "INSERT INTO Courses (name, teacher_id, schedule) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$data['name'], $_SESSION['user_id'], $data['schedule']])) {
                $result = [
                    'success' => true,
                    'message' => 'Course created successfully',
                    'id' => $db->lastInsertId()
                ];
                debug_log("Course created successfully", $result);
                echo json_encode($result);
            } else {
                debug_log("Failed to create course");
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create course'
                ]);
            }
        } catch (PDOException $e) {
            debug_log("Database error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
        break;

    case 'view_students':
        if ($role !== 'Teacher') {
            http_response_code(403);
            echo json_encode(['error' => 'Only teachers can view student list']);
            exit;
        }
        
        try {
            $query = "SELECT s.*, u.username 
                     FROM Students s 
                     JOIN Users u ON s.user_id = u.id 
                     ORDER BY s.full_name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'view_today_classes':
        if ($role !== 'Teacher') {
            http_response_code(403);
            echo json_encode(['error' => 'Only teachers can view today\'s classes']);
            exit;
        }
        
        try {
            $today = date('l'); // Gets current day name (Monday, Tuesday, etc.)
            $query = "SELECT c.* 
                     FROM Courses c 
                     WHERE c.teacher_id = ? 
                     AND c.schedule LIKE ?";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                "%$today%"
            ]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'view_courses':
        try {
            if ($role === 'Student') {
                $query = "SELECT c.*, t.full_name as teacher_name 
                         FROM Courses c 
                         JOIN Student_Courses sc ON c.id = sc.course_id 
                         JOIN Students s ON sc.student_id = s.id 
                         JOIN Teachers t ON c.teacher_id = t.user_id 
                         WHERE s.user_id = ?";
            } else {
                $query = "SELECT c.*, t.full_name as teacher_name 
                         FROM Courses c 
                         JOIN Teachers t ON c.teacher_id = t.user_id 
                         WHERE t.user_id = ?";
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'view_attendance':
        try {
            if ($role === 'Student') {
                $query = "SELECT a.*, c.name as course_name 
                         FROM Attendance a 
                         JOIN Students s ON a.student_id = s.id 
                         JOIN Courses c ON a.course_id = c.id 
                         WHERE s.user_id = ?";
            } else {
                $date = $_GET['date'] ?? date('Y-m-d');
                $query = "SELECT a.*, s.full_name as student_name, c.name as course_name 
                         FROM Attendance a 
                         JOIN Students s ON a.student_id = s.id 
                         JOIN Courses c ON a.course_id = c.id 
                         JOIN Teachers t ON c.teacher_id = t.user_id 
                         WHERE t.user_id = ? AND DATE(a.date) = ?";
            }
            
            $stmt = $db->prepare($query);
            $params = ($role === 'Student') ? [$_SESSION['user_id']] : [$_SESSION['user_id'], $date];
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
