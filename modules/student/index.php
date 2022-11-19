<?php
session_start();
require_once "../../config/database.php";

class StudentManager {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAllStudents() {
        $query = "SELECT s.*, u.username FROM Students s 
                 JOIN Users u ON s.user_id = u.id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function addStudent($data) {
        $this->conn->beginTransaction();
        try {
            // First create user
            $user_query = "INSERT INTO Users (username, password, role) 
                          VALUES (?, ?, 'Student')";
            $stmt = $this->conn->prepare($user_query);
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->execute([$data['username'], $password]);
            $user_id = $this->conn->lastInsertId();
            
            // Then create student
            $student_query = "INSERT INTO Students (user_id, full_name, class) 
                            VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($student_query);
            $stmt->execute([$user_id, $data['full_name'], $data['class']]);
            
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}

// Example usage in the view:
$database = new Database();
$db = $database->getConnection();
$studentManager = new StudentManager($db);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add':
                $studentManager->addStudent($_POST);
                break;
            // Add other cases for update and delete
        }
    }
}

$students = $studentManager->getAllStudents();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Manage Students</h2>
        <button class="btn-primary" onclick="showAddForm()">Add New Student</button>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Class</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($students as $student): ?>
                <tr>
                    <td><?php echo $student['id']; ?></td>
                    <td><?php echo $student['username']; ?></td>
                    <td><?php echo $student['full_name']; ?></td>
                    <td><?php echo $student['class']; ?></td>
                    <td>
                        <button onclick="editStudent(<?php echo $student['id']; ?>)">Edit</button>
                        <button onclick="deleteStudent(<?php echo $student['id']; ?>)">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
