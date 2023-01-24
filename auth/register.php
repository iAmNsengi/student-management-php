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
    <link rel="stylesheet" href="styles.css">
    <title>Register</title>
    <script>
        async function handleRegister(event) {
            event.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const role = document.getElementById('role').value;

            try {
                const response = await fetch('../api/endpoints.php?endpoint=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username, password, role }),
                    credentials: 'include' // Important for session cookies
                });

                const data = await response.json();

                if (data.success) {
                    alert('Registration successful!');
                    window.location.href = 'login.php'; // Redirect to login
                } else {
                    alert(data.error || 'Registration failed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error registering. Please try again.');
            }
        }
    </script>
</head>
<body>
    <form id="register-form" onsubmit="handleRegister(event)">
        <input type="text" id="username" placeholder="Username" required>
        <input type="password" id="password" placeholder="Password" required>
        <select id="role" required>
            <option value="Student">Student</option>
            <option value="Teacher">Teacher</option>
        </select>
        <button type="submit">Register</button>
            <p>Already have an account? <a href="login.php">Login here</a></p>

    </form>
</body>
</html>