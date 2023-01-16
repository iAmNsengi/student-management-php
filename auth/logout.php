<?php
session_start();
require_once "../config/database.php";
require_once "../models/User.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

session_unset(); 
session_destroy(); 

header("Location: ./login.php"); 
exit;
?>