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
        'get_profile', 
        'view_today_classes',
        'view_overview'
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
        'get_profile', 
        'view_today_classes',
        'view_overview'
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

        if (!isset($data['student_id']) || !isset($data['course_id']) || !isset($data['date']) || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        try {
            $query = "INSERT INTO Attendance (student_id, course_id, date, status) VALUES (:student_id, :course_id, :date, :status)
                      ON DUPLICATE KEY UPDATE status = :status";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':student_id', $data['student_id']);
            $stmt->bindParam(':course_id', $data['course_id']);
            $stmt->bindParam(':date', $data['date']);
            $stmt->bindParam(':status', $data['status']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
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
                            (SELECT COUNT(DISTINCT student_id) 
                             FROM Courses) as students_count,
                            
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

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
