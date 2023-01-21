<?php
class Database {
    private $host = "localhost";
    private $db_name = "student_management";
    private $username = "root";
    private $password = "";
    private $conn = null;

    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // First try to connect to the database directly
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // If database doesn't exist, create it
            if ($e->getCode() == 1049) {
                try {
                    $temp_conn = new PDO(
                        "mysql:host=" . $this->host,
                        $this->username,
                        $this->password
                    );
                    $temp_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Create database
                    $temp_conn->exec("CREATE DATABASE IF NOT EXISTS " . $this->db_name);
                    
                    // Connect to the newly created database
                    $this->conn = new PDO(
                        "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                        $this->username,
                        $this->password
                    );
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Import schema
                    $sql = file_get_contents(__DIR__ . '/seed.sql');
                    $this->conn->exec($sql);
                } catch(PDOException $e2) {
                    throw new Exception("Database setup failed: " . $e2->getMessage());
                }
            } else {
                throw new Exception("Connection failed: " . $e->getMessage());
            }
        }
        
        return $this->conn;
    }

    // Add this method to help with debugging
    public function testConnection() {
        try {
            $this->getConnection();
            return [
                'success' => true,
                'message' => 'Database connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}