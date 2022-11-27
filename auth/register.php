<?php
session_start();
require_once "../config/database.php";
require_once "../models/User.php";

// Check if the user is logged in, if not then redirect to home page
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $user->username = $_POST['username'];
    $user->password = $_POST['password'];
    $user->role = $_POST['role'];
    
    if($user->create()) {
        $user_id = $db->lastInsertId();
        
        // Create corresponding entry in Students or Teachers table
        if($user->role == 'Student') {
            $query = "INSERT INTO Students (user_id, full_name, class) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $_POST['full_name'], $_POST['class']]);
        } elseif($user->role == 'Teacher') {
            $query = "INSERT INTO Teachers (user_id, full_name, department) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $_POST['full_name'], $_POST['department']]);
        }
        
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Student Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <h2>Register</h2>
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <div class="form-group">
                <input type="text" name="full_name" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <select name="role" id="role" required>
                    <option value="">Select Role</option>
                    <option value="Student">Student</option>
                    <option value="Teacher">Teacher</option>
                </select>
            </div>
            <div class="form-group student-fields" style="display:none;">
                <input type="text" name="class" placeholder="Class">
            </div>
            <div class="form-group teacher-fields" style="display:none;">
                <input type="text" name="department" placeholder="Department">
            </div>
            <button type="submit" class="btn-primary">Register</button>
        </form>
    </div>
    <script>
        document.getElementById('role').addEventListener('change', function() {
            document.querySelector('.student-fields').style.display = 
                this.value === 'Student' ? 'block' : 'none';
            document.querySelector('.teacher-fields').style.display = 
                this.value === 'Teacher' ? 'block' : 'none';
        });
    </script>
</body>
</html>