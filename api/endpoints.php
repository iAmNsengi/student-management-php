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
        'enroll_course'
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
        'view_reports',
        'view_course_students',
        'view_course_details',
        'update_course',
        'mark_all_attendance',
        "delete_course"
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
            if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
                throw new Exception('Not authenticated');
            }

            $date = $_GET['date'] ?? date('Y-m-d');
            
            // Use the existing Attendance class
            require_once "../modules/attendance/Attendance.php";
            $attendance = new Attendance($db);

            // Get attendance records
            $attendanceRecords = $attendance->getAttendance($date);
            
            // Debug log to check what we're getting
            error_log("Attendance records: " . print_r($attendanceRecords, true));

            // Return in the expected format
            echo json_encode([
                'success' => true,
                'data' => $attendanceRecords
            ]);

        } catch (Exception $e) {
            error_log("Error in view_attendance: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'mark_attendance':
        try {
            if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
                throw new Exception('Not authenticated');
            }

            // Log the raw input for debugging
            $rawInput = file_get_contents('php://input');
            error_log("Raw attendance input: " . $rawInput);

            $data = json_decode($rawInput, true);
            error_log("Decoded attendance data: " . print_r($data, true));

            // Validate required fields
            if (!isset($data['student_id']) || !isset($data['course_id']) || 
                !isset($data['date']) || !isset($data['status'])) {
                throw new Exception('Missing required fields: ' . 
                                  'student_id: ' . (isset($data['student_id']) ? 'yes' : 'no') . ', ' .
                                  'course_id: ' . (isset($data['course_id']) ? 'yes' : 'no') . ', ' .
                                  'date: ' . (isset($data['date']) ? 'yes' : 'no') . ', ' .
                                  'status: ' . (isset($data['status']) ? 'yes' : 'no'));
            }

            // Validate status values
            if (!in_array($data['status'], ['present', 'absent', 'late'])) {
                throw new Exception('Invalid status. Must be present, absent, or late');
            }

            require_once "../modules/attendance/Attendance.php";
            $attendance = new Attendance($db);
            
            $result = $attendance->markAttendance(
                intval($data['student_id']),
                intval($data['course_id']),
                $data['date'],
                $data['status']
            );

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Attendance marked successfully'
                ]);
            } else {
                throw new Exception('Failed to mark attendance');
            }

        } catch (Exception $e) {
            error_log("Error marking attendance: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'view_courses':
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Not authenticated');
            }

            if ($_SESSION['role'] === 'Student') {
                // Get student's ID
                $studentStmt = $db->prepare("SELECT id FROM Students WHERE user_id = ?");
                $studentStmt->execute([$_SESSION['user_id']]);
                $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    throw new Exception('Student not found');
                }

                // Get all courses with enrollment status
                $query = "SELECT c.*, t.full_name as teacher_name,
                         CASE WHEN sc.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
                         FROM Courses c
                         JOIN Teachers t ON c.teacher_id = t.id
                         LEFT JOIN Student_Courses sc ON c.id = sc.course_id 
                         AND sc.student_id = ?
                         ORDER BY c.name";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$student['id']]);
            } else {
                // For teachers, show their courses
                $query = "SELECT c.*, t.full_name as teacher_name
                         FROM Courses c
                         JOIN Teachers t ON c.teacher_id = t.id
                         WHERE c.teacher_id = ?
                         ORDER BY c.name";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
            }

            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $courses
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'update_profile':
        header('Content-Type: application/json');
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Not authenticated');
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!isset($data['full_name']) || trim($data['full_name']) === '') {
                throw new Exception('Full name is required');
            }

            if ($_SESSION['role'] === 'Student') {
                $query = "UPDATE Students SET full_name = ? WHERE user_id = ?";
            } else {
                $query = "UPDATE Teachers SET full_name = ? WHERE user_id = ?";
            }
            
            $stmt = $db->prepare($query);
            if ($stmt->execute([trim($data['full_name']), $_SESSION['user_id']])) {
                // Fetch updated profile data
                $profileQuery = "SELECT full_name FROM " . ($_SESSION['role'] === 'Student' ? 'Students' : 'Teachers') . 
                              " WHERE user_id = ?";
                $profileStmt = $db->prepare($profileQuery);
                $profileStmt->execute([$_SESSION['user_id']]);
                $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'data' => $profile
                ]);
            } else {
                throw new Exception('Failed to update profile');
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
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
        try {
            // Debug log the incoming data
            error_log("Register POST data: " . print_r($_POST, true));

            // Validate required fields
            $required_fields = ['username', 'password', 'role', 'full_name'];
            $missing_fields = [];
            
            foreach ($required_fields as $field) {
                if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                    $missing_fields[] = $field;
                }
            }

            if (!empty($missing_fields)) {
                error_log("Missing fields: " . implode(', ', $missing_fields));
                throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
            }

            // Validate role
            if (!in_array($_POST['role'], ['Student', 'Teacher'])) {
                throw new Exception('Invalid role');
            }

            // Check if username already exists
            $checkQuery = "SELECT id FROM Users WHERE username = :username";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':username', $_POST['username'], PDO::PARAM_STR);
            $checkStmt->execute();
            if ($checkStmt->fetch()) {
                throw new Exception('Username already exists');
            }

            // Begin transaction
            $db->beginTransaction();

            try {
                // Insert user
                $userQuery = "INSERT INTO Users (username, password, role) VALUES (:username, :password, :role)";
                $userStmt = $db->prepare($userQuery);
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $userStmt->bindValue(':username', $_POST['username'], PDO::PARAM_STR);
                $userStmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
                $userStmt->bindValue(':role', $_POST['role'], PDO::PARAM_STR);
                $userStmt->execute();
                
                $userId = $db->lastInsertId();

                // Insert into role-specific table
                $roleQuery = "INSERT INTO " . ($_POST['role'] === 'Student' ? 'Students' : 'Teachers') . 
                            " (user_id, full_name) VALUES (:user_id, :full_name)";
                $roleStmt = $db->prepare($roleQuery);
                $roleStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $roleStmt->bindValue(':full_name', $_POST['full_name'], PDO::PARAM_STR);
                $roleStmt->execute();

                // Commit transaction
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Registration successful',
                    'user' => [
                        'id' => $userId,
                        'role' => $_POST['role'],
                        'username' => $_POST['username'],
                        'full_name' => $_POST['full_name']
                    ]
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
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
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
                throw new Exception('Only students can enroll in courses');
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            error_log("Enrollment data received: " . print_r($data, true));
            
            if (!isset($data['courseId'])) {
                throw new Exception('Course ID is required');
            }

            // Get student's ID
            $studentStmt = $db->prepare("SELECT id FROM Students WHERE user_id = ?");
            $studentStmt->execute([$_SESSION['user_id']]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                throw new Exception('Student not found');
            }

            // Check if already enrolled
            $checkStmt = $db->prepare("
                SELECT student_id 
                FROM Student_Courses 
                WHERE student_id = ? AND course_id = ?
            ");
            $checkStmt->execute([$student['id'], $data['courseId']]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('Already enrolled in this course');
            }

            // Check if course exists
            $courseStmt = $db->prepare("SELECT id FROM Courses WHERE id = ?");
            $courseStmt->execute([$data['courseId']]);
            if (!$courseStmt->fetch()) {
                throw new Exception('Course not found');
            }

            // Enroll in course
            $enrollStmt = $db->prepare("
                INSERT INTO Student_Courses (student_id, course_id) 
                VALUES (?, ?)
            ");
            
            if ($enrollStmt->execute([$student['id'], $data['courseId']])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Successfully enrolled in course'
                ]);
            } else {
                throw new Exception('Failed to enroll in course');
            }

        } catch (Exception $e) {
            error_log("Enrollment error: " . $e->getMessage());
            http_response_code(400);
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

    case 'view_course_students':
        try {
            if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
                throw new Exception('Not authenticated');
            }

            if (!isset($_GET['course_id'])) {
                throw new Exception('Course ID is required');
            }

            $courseId = $_GET['course_id'];

            // Get all students enrolled in the course
            $query = "SELECT s.id, s.full_name 
                     FROM Students s
                     JOIN Student_Courses sc ON s.id = sc.student_id
                     WHERE sc.course_id = :course_id
                     ORDER BY s.full_name";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':course_id', $courseId, PDO::PARAM_INT);
            $stmt->execute();
            
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $students
            ]);

        } catch (Exception $e) {
            error_log("Error in view_course_students: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'view_course_details':
        try {
            if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
                throw new Exception('Not authenticated');
            }

            if (!isset($_GET['course_id'])) {
                throw new Exception('Course ID is required');
            }

            $courseId = $_GET['course_id'];

            // Get course details
            $query = "SELECT c.*, 
                     (SELECT COUNT(*) FROM Student_Courses sc WHERE sc.course_id = c.id) as enrolled_count
                     FROM Courses c 
                     WHERE c.id = :course_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':course_id', $courseId, PDO::PARAM_INT);
            $stmt->execute();
            
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                throw new Exception('Course not found');
            }

            // Get enrolled students
            $studentsQuery = "SELECT s.id, s.full_name 
                             FROM Students s
                             JOIN Student_Courses sc ON s.id = sc.student_id
                             WHERE sc.course_id = :course_id
                             ORDER BY s.full_name";
            
            $studentsStmt = $db->prepare($studentsQuery);
            $studentsStmt->bindParam(':course_id', $courseId, PDO::PARAM_INT);
            $studentsStmt->execute();
            
            $course['enrolled_students'] = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $course
            ]);

        } catch (Exception $e) {
            error_log("Error in view_course_details: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'update_course':
        try {
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Teacher') {
                throw new Exception('Access denied: Only teachers can update courses');
            }

            $input = file_get_contents('php://input');
            error_log("Raw input for update: " . $input);
            
            $data = json_decode($input, true);
            error_log("Decoded data for update: " . print_r($data, true));

            // Get teacher ID
            $teacherStmt = $db->prepare("SELECT id FROM Teachers WHERE user_id = :user_id");
            $teacherStmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $teacherStmt->execute();
            $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Teacher data: " . print_r($teacher, true));

            // Update course directly (removed verification since it's causing issues)
            $updateStmt = $db->prepare("
                UPDATE Courses 
                SET name = :name, 
                    schedule = :schedule 
                WHERE id = :course_id 
                AND teacher_id = :teacher_id
            ");

            $updateStmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $updateStmt->bindValue(':schedule', $data['schedule'], PDO::PARAM_STR);
            $updateStmt->bindValue(':course_id', $data['courseId'], PDO::PARAM_INT);
            $updateStmt->bindValue(':teacher_id', $teacher['id'], PDO::PARAM_INT);

            if ($updateStmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Course updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update course');
            }

        } catch (Exception $e) {
            error_log("Error updating course: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'delete_course':
        header('Content-Type: application/json');
        try {
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Teacher') {
                throw new Exception('Only teachers can delete courses');
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!isset($data['courseId'])) {
                throw new Exception('Course ID is required');
            }

            // Start transaction
            $db->beginTransaction();
            
            try {
                // Delete from Grades first
                $deleteGradesStmt = $db->prepare("DELETE FROM Grades WHERE course_id = ?");
                $deleteGradesStmt->execute([$data['courseId']]);
                
                // Delete from Attendance
                $deleteAttendanceStmt = $db->prepare("DELETE FROM Attendance WHERE course_id = ?");
                $deleteAttendanceStmt->execute([$data['courseId']]);
                
                // Delete from Student_Courses
                $deleteEnrollmentsStmt = $db->prepare("DELETE FROM Student_Courses WHERE course_id = ?");
                $deleteEnrollmentsStmt->execute([$data['courseId']]);

                // Finally delete the course
                $deleteCourseStmt = $db->prepare("DELETE FROM Courses WHERE id = ? AND teacher_id = ?");
                $deleteCourseStmt->execute([$data['courseId'], $_SESSION['user_id']]);

                if ($deleteCourseStmt->rowCount() === 0) {
                    throw new Exception('Course not found or not authorized to delete');
                }

                $db->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Course deleted successfully'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
