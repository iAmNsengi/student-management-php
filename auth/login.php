<?php
session_start();
require_once "../config/database.php";
require_once "../models/User.php";

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
        header("Location: ../dashboard.php");
    } else {
        $login_err = "Invalid username or password.";
    }
}
?>