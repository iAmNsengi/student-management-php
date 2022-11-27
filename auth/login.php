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
    <title>Login - Student Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <h2>Login</h2>
            <?php if(!empty($login_err)): ?>
                <div class="alert alert-danger"><?php echo $login_err; ?></div>
            <?php endif; ?>
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-primary">Login</button>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </form>
    </div>
</body>
</html>