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
    
    $stmt = $user->login();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($row && password_verify($_POST['password'], $row['password'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['role'] = $row['role'];
        header("Location: ../index.php");
    } else {
        $login_err = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Login</title>
    <script>
        async function handleLogin(event) {
            event.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const response = await fetch('../api/endpoints.php?endpoint=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username, password }),
                    credentials: 'include' // Important for session cookies
                });

                const data = await response.json();

                if (data.success) {
                    alert('Login successful!');
                    window.location.href = '../dashboard.php'; // Redirect to dashboard
                } else {
                    alert(data.error || 'Login failed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error logging in. Please try again.');
            }
        }
    </script>
</head>
<body>
    <div class="login-container">
        <form id="login-form" onsubmit="handleLogin(event)">
            <h2>Login</h2>
            <?php if(!empty($login_err)): ?>
                <div class="alert alert-danger"><?php echo $login_err; ?></div>
            <?php endif; ?>
            <div class="form-group">
                <input type="text" id="username" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-primary">Login</button>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </form>
    </div>
</body>
</html>