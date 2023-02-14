<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication for all endpoints except login
if (!isset($_SESSION['user_id']) && !in_array($_GET['endpoint'] ?? '', ['login', 'register'])) {
    debug_log("Unauthorized access attempt");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please login.']);
    exit;
}

require_once "../config/database.php";

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get user role from session
$role = $_SESSION['role'] ?? null;
debug_log("User role: " . $role);

function debug_log($message, $data = null) {
    error_log("DEBUG: " . $message);
    if ($data) {
        error_log("DATA: " . print_r($data, true));
    }
}

// Get the endpoint from the URL
$endpoint = $_GET['endpoint'] ?? '';
debug_log("Requested endpoint: " . $endpoint);

// Role-based access control array
$allowed_endpoints = [
    'Student' => [
        'view_grades', 
        'view_courses', 
        'view_attendance', 
        'update_profile', 
        'view_profile',
        'get_profile', 
        'view_today_classes',
        'view_overview',
        'view_reports',
        "enroll_course"
    ],
    'Teacher' => [
        'view_grades', 
        'add_grade', 
        'view_courses', 
        'create_course', 
        'view_attendance', 
        'mark_attendance', 
        'view_students', 
        'update_profile', 
        'view_profile',
        'get_profile', 
        'view_today_classes',
        'view_overview',
        'view_reports'
    ]
];

// Check if the endpoint is allowed for the user's role
if (!in_array($endpoint, ['login', 'register']) && 
    (!isset($allowed_endpoints[$role]) || !in_array($endpoint, $allowed_endpoints[$role]))) {
    debug_log("Access denied for endpoint: " . $endpoint);
    http_response_code(403);
    echo json_encode(['error' => 'Access denied for this endpoint']);
    exit;
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
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            debug_log("Error retrieving grades: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'add_grade':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['student_id'], $data['course_id'], $data['grade'])) {
                throw new Exception('Missing required fields');
            }

            // Check if a grade already exists
            $checkQuery = "SELECT id FROM Grades 
                          WHERE student_id = :student_id 
                          AND course_id = :course_id";
            
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':student_id', $data['student_id'], PDO::PARAM_INT);
            $checkStmt->bindValue(':course_id', $data['course_id'], PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                // Update existing grade
                $query = "UPDATE Grades 
                         SET grade = :grade, 
                             created_at = NOW() 
                         WHERE student_id = :student_id 
                         AND course_id = :course_id";
            } else {
                // Insert new grade
                $query = "INSERT INTO Grades (student_id, course_id, grade, created_at) 
                         VALUES (:student_id, :course_id, :grade, NOW())";
            }

            $stmt = $db->prepare($query);
            $stmt->bindValue(':student_id', $data['student_id'], PDO::PARAM_INT);
            $stmt->bindValue(':course_id', $data['course_id'], PDO::PARAM_INT);
            $stmt->bindValue(':grade', $data['grade'], PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Failed to save grade');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
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

    case 'mark_attendance':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['student_id']) || !isset($data['course_id']) || 
            !isset($data['date']) || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        try {
            // Verify teacher owns the course
            $stmt = $db->prepare("SELECT 1 FROM Courses WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$data['course_id'], $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Unauthorized to mark attendance for this course');
            }

            $query = "INSERT INTO Attendance (student_id, course_id, date, status) 
                     VALUES (:student_id, :course_id, :date, :status)
                     ON DUPLICATE KEY UPDATE status = :status";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':student_id', $data['student_id']);
            $stmt->bindParam(':course_id', $data['course_id']);
            $stmt->bindParam(':date', $data['date']);
            $stmt->bindParam(':status', $data['status']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            } else {
                throw new Exception('Failed to mark attendance');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'view_courses':
        try {
            // Base query to get all courses with teacher names
            $query = "SELECT c.*, t.full_name as teacher_name 
                     FROM Courses c 
                     LEFT JOIN Teachers t ON c.teacher_id = t.user_id";
            
            // Add role-specific filters
            if ($role === 'Student') {
                // For students, show all courses but mark enrolled ones
                $query = "SELECT c.*, t.full_name as teacher_name,
                                CASE WHEN sc.course_id IS NOT NULL THEN true ELSE false END as is_enrolled
                         FROM Courses c 
                         LEFT JOIN Teachers t ON c.teacher_id = t.user_id
                         LEFT JOIN Student_Courses sc ON c.id = sc.course_id 
                         AND sc.student_id = (SELECT id FROM Students WHERE user_id = :user_id)";
                
                $stmt = $db->prepare($query);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            } else if ($role === 'Teacher') {
                // For teachers, show all courses but highlight their own
                $query = "SELECT c.*, t.full_name as teacher_name,
                                CASE WHEN c.teacher_id = :user_id THEN true ELSE false END as is_teaching
                         FROM Courses c 
                         LEFT JOIN Teachers t ON c.teacher_id = t.user_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            } else {
                // For any other case, just show all courses
                $stmt = $db->prepare($query);
            }
            
            debug_log("SQL Query: " . $query);
            
            $stmt->execute();
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            debug_log("Query results:", $courses);
            
            echo json_encode(['success' => true, 'data' => $courses]);
        } catch (PDOException $e) {
            debug_log("Database error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'update_profile':
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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name']) || !isset($data['schedule'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        try {
            $query = "INSERT INTO Courses (name, teacher_id, schedule) VALUES (:name, :teacher_id, :schedule)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
            $stmt->bindParam(':schedule', $data['schedule']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Course created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create course']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'view_students':
        try {
            $courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
            
            if ($courseId) {
                // Course-specific student list with grades
                $query = "SELECT 
                            s.id,
                            s.full_name,
                            COALESCE(g.grade, 'No grade') as current_grade,
                            g.created_at as grade_date
                        FROM Students s
                        INNER JOIN Student_Courses sc ON s.id = sc.student_id
                        LEFT JOIN (
                            SELECT 
                                student_id,
                                grade,
                                created_at,
                                ROW_NUMBER() OVER (PARTITION BY student_id ORDER BY created_at DESC) as rn
                            FROM Grades
                            WHERE course_id = :course_id
                        ) g ON s.id = g.student_id AND g.rn = 1
                        WHERE sc.course_id = :course_id
                        ORDER BY s.full_name";

                $stmt = $db->prepare($query);
                $stmt->bindValue(':course_id', $courseId, PDO::PARAM_INT);
                $stmt->execute();
                
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format students with grade information
                $formattedStudents = array_map(function($student) {
                    return [
                        'id' => intval($student['id']),
                        'full_name' => htmlspecialchars($student['full_name']),
                        'current_grade' => $student['current_grade'],
                        'grade_date' => $student['grade_date'] ? date('Y-m-d', strtotime($student['grade_date'])) : null
                    ];
                }, $students);
            } else {
                // General student list (no course filter)
                $query = "SELECT id, full_name FROM Students ORDER BY full_name";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format students without grade information
                $formattedStudents = array_map(function($student) {
                    return [
                        'id' => intval($student['id']),
                        'full_name' => htmlspecialchars($student['full_name'])
                    ];
                }, $students);
            }

            echo json_encode([
                'success' => true,
                'data' => $formattedStudents,
                'message' => count($formattedStudents) . ' students found' . 
                            ($courseId ? " for course " . $courseId : "")
            ]);

        } catch (Exception $e) {
            error_log("Error in view_students: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'view_today_classes':
        debug_log("Viewing today's classes for user: " . $_SESSION['user_id'] . " with role: " . $role);
        
        try {
            $today = date('l'); // Gets current day name (Monday, Tuesday, etc.)
            if ($role === 'Teacher') {
                $query = "SELECT c.* 
                         FROM Courses c 
                         WHERE c.teacher_id = ? 
                         AND c.schedule LIKE ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_SESSION['user_id'],
                    "%$today%"
                ]);
            } else {
                $query = "SELECT c.* 
                         FROM Courses c 
                         JOIN Student_Courses sc ON c.id = sc.course_id 
                         JOIN Students s ON sc.student_id = s.id 
                         WHERE s.user_id = ? 
                         AND c.schedule LIKE ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_SESSION['user_id'],
                    "%$today%"
                ]);
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            debug_log("Error retrieving today's classes: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'view_overview':
        try {
            if ($role === 'Student') {
                $query = "SELECT 
                            (SELECT COUNT(*) 
                             FROM Courses) as courses_count,
                            
                            (SELECT COALESCE(AVG(g.grade), 0)
                             FROM Grades g) as average_grade,
                            
                            (SELECT 
                                CASE 
                                    WHEN COUNT(*) = 0 THEN 0 
                                    ELSE (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                                END
                             FROM Attendance a) as attendance_rate";
            } else {
                $query = "SELECT 
                            (SELECT COUNT(*) 
                             FROM Students) as students_count,
                            
                            (SELECT COUNT(*) 
                             FROM Courses) as courses_count,
                            
                            (SELECT COALESCE(AVG(grade), 0)
                             FROM Grades) as average_grade";
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $overview = $stmt->fetch(PDO::FETCH_ASSOC);
            $overview['role'] = $role;
            
            // Convert numeric strings to proper numbers
            $overview['courses_count'] = intval($overview['courses_count']);
            $overview['average_grade'] = $overview['average_grade'] ? round(floatval($overview['average_grade']), 2) : 0;
            if (isset($overview['attendance_rate'])) {
                $overview['attendance_rate'] = $overview['attendance_rate'] ? round(floatval($overview['attendance_rate']), 2) : 0;
            }
            if (isset($overview['students_count'])) {
                $overview['students_count'] = intval($overview['students_count']);
            }
            
            debug_log("Overview data:", $overview);
            echo json_encode($overview);
        } catch (PDOException $e) {
            debug_log("Error in view_overview: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        try {
            $query = "SELECT * FROM Users WHERE username = :username";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $data['username']);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($data['password'], $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];

                echo json_encode(['success' => true, 'message' => 'Login successful']);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid username or password']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        break;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['username']) || !isset($data['password']) || !isset($data['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        try {
            // Check if username already exists
            $query = "SELECT id FROM Users WHERE username = :username";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $data['username']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Username already exists']);
                exit;
            }

            // Hash the password
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

            // Insert new user
            $query = "INSERT INTO Users (username, password, role) VALUES (:username, :password, :role)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $data['username']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':role', $data['role']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registration successful']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Registration failed']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'view_reports':
        require_once "../modules/reports/ReportGenerator.php";
        $reporter = new ReportGenerator($db);
        
        $report_type = $_GET['type'] ?? '';
        $class = $_GET['class'] ?? null;
        $start_date = $_GET['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? null;
        
        try {
            switch($report_type) {
                case 'attendance':
                    $data = $reporter->generateAttendanceReport($class, $start_date, $end_date);
                    break;
                    
                case 'grades':
                    $data = $reporter->generateGradeReport($class);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid report type. Available types: attendance, grades']);
                    exit;
            }
            
            // Format the data for better readability
            if ($report_type === 'attendance') {
                foreach ($data as &$record) {
                    $record['attendance_percentage'] = round(floatval($record['attendance_percentage']), 2);
                    $record['present_days'] = intval($record['present_days']);
                    $record['absent_days'] = intval($record['absent_days']);
                }
            } else if ($report_type === 'grades') {
                foreach ($data as &$record) {
                    $record['average_grade'] = round(floatval($record['average_grade']), 2);
                    $record['highest_grade'] = round(floatval($record['highest_grade']), 2);
                    $record['lowest_grade'] = round(floatval($record['lowest_grade']), 2);
                }
            }
            
            debug_log("Generated $report_type report:", $data);
            echo json_encode([
                'success' => true,
                'report_type' => $report_type,
                'filters' => [
                    'class' => $class,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'data' => $data
            ]);
            
        } catch (PDOException $e) {
            debug_log("Error generating report: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'enroll_course':
        try {
            // Debug session information
            error_log("Session data: " . print_r($_SESSION, true));
            error_log("POST data: " . file_get_contents('php://input'));

            // Check if user is logged in and is a student
            if (!isset($_SESSION['user_id'])) {
                error_log("No user_id in session");
                throw new Exception('Not authenticated');
            }

            if (!isset($_SESSION['role'])) {
                error_log("No role in session");
                throw new Exception('Role not set');
            }

            if ($_SESSION['role'] !== 'Student') {
                error_log("Invalid role: " . $_SESSION['role']);
                throw new Exception('Only students can enroll in courses');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            error_log("Decoded data: " . print_r($data, true));
            
            if (!isset($data['course_id'])) {
                error_log("No course_id in request");
                throw new Exception('Course ID is required');
            }

            // Verify the course exists
            $courseQuery = "SELECT 1 FROM Courses WHERE id = :course_id";
            $courseStmt = $db->prepare($courseQuery);
            $courseStmt->bindValue(':course_id', $data['course_id'], PDO::PARAM_INT);
            $courseStmt->execute();
            
            if (!$courseStmt->fetch()) {
                error_log("Course not found: " . $data['course_id']);
                throw new Exception('Course not found');
            }

            // Check if already enrolled
            $checkQuery = "SELECT 1 FROM Student_Courses 
                          WHERE student_id = :student_id 
                          AND course_id = :course_id";
            
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':student_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $checkStmt->bindValue(':course_id', $data['course_id'], PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                error_log("Already enrolled - Student: {$_SESSION['user_id']}, Course: {$data['course_id']}");
                throw new Exception('Already enrolled in this course');
            }

            // Enroll in course - Modified query without enrolled_date
            $query = "INSERT INTO Student_Courses (student_id, course_id) 
                     VALUES (:student_id, :course_id)";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':student_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':course_id', $data['course_id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                error_log("Enrollment successful - Student: {$_SESSION['user_id']}, Course: {$data['course_id']}");
                echo json_encode([
                    'success' => true,
                    'message' => 'Successfully enrolled in course'
                ]);
            } else {
                error_log("Enrollment failed - " . print_r($stmt->errorInfo(), true));
                throw new Exception('Failed to enroll in course');
            }
        } catch (Exception $e) {
            error_log("Enrollment error: " . $e->getMessage());
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'view_profile':
        try {
            if ($role === 'Student') {
                $query = "SELECT s.*, u.username, u.role 
                         FROM Students s 
                         JOIN Users u ON s.user_id = u.id 
                         WHERE s.user_id = :user_id";
            } else {
                $query = "SELECT t.*, u.username, u.role 
                         FROM Teachers t 
                         JOIN Users u ON t.user_id = u.id 
                         WHERE t.user_id = :user_id";
            }
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($profile) {
                // Remove sensitive information
                unset($profile['password']);
                echo json_encode(['success' => true, 'data' => $profile]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Profile not found']);
            }
        } catch (PDOException $e) {
            debug_log("Error in view_profile: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
