<?php
session_start();
require_once "../config/database.php";
require_once "../models/User.php";

// Check if the user is logged in, if not then redirect to home page
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error_message = ""; // Variable to hold error messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $user->username = $_POST['username'];
    $user->password = $_POST['password'];
    $user->role = $_POST['role'];
    
    // Check if the username already exists
    if ($user->usernameExists()) {
        $error_message = "Username already exists. Please choose another.";
    } else {
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
        } else {
            $error_message = "An error occurred while registering. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <h2>Create Account</h2>
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
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
                    <option value="">Select your role</option>
                    <option value="Student">Student</option>
                    <option value="Teacher">Teacher</option>
                </select>
            </div>
            <div class="form-group student-fields" style="display:none;">
                <input type="text" name="class" placeholder="Enter your class">
            </div>
            <div class="form-group teacher-fields" style="display:none;">
                <input type="text" name="department" placeholder="Enter your department">
            </div>
            <button type="submit" class="btn-primary">Create Account</button>
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('role').addEventListener('change', function() {
            const studentFields = document.querySelector('.student-fields');
            const teacherFields = document.querySelector('.teacher-fields');
            
            studentFields.style.display = this.value === 'Student' ? 'block' : 'none';
            teacherFields.style.display = this.value === 'Teacher' ? 'block' : 'none';
            
            if (this.value === 'Student') {
                document.querySelector('[name="class"]').required = true;
                document.querySelector('[name="department"]').required = false;
            } else if (this.value === 'Teacher') {
                document.querySelector('[name="class"]').required = false;
                document.querySelector('[name="department"]').required = true;
            }
        });
    </script>
</body>
</html>