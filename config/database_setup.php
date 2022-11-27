<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Create connection to MySQL without selecting a database
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS student_management";
    $conn->exec($sql);
    
    // Select the database
    $conn->exec("USE student_management");
    
    // Create tables
    $sql = file_get_contents(__DIR__ . '/seed.sql');
    $conn->exec($sql);
    
    echo "Database and tables created successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}