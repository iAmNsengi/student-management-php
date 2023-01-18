<?php
class User {
    private $conn;
    private $table_name = "Users";

    public $id;
    public $username;
    public $password;
    public $role;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Method to check if the username already exists
    public function usernameExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $this->username);
        $stmt->execute();

        // If a row is returned, the username exists
        return $stmt->rowCount() > 0;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 SET username=:username, password=:password, role=:role";
        
        $stmt = $this->conn->prepare($query);
        
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":role", $this->role);
        
        return $stmt->execute();
    }

    public function login() {
        $query = "SELECT id, password, role FROM " . $this->table_name . " 
                 WHERE username = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->username);
        $stmt->execute();
        
        return $stmt;
    }

    // Add these methods to the User class
    
    public function isTeacher() {
        return $this->role === 'Teacher';
    }
    
    public function isStudent() {
        return $this->role === 'Student';
    }
    
    public function getProfile() {
        if ($this->isStudent()) {
            $query = "SELECT s.*, u.username, u.role 
                     FROM Students s 
                     JOIN Users u ON s.user_id = u.id 
                     WHERE u.id = ?";
        } else {
            $query = "SELECT t.*, u.username, u.role 
                     FROM Teachers t 
                     JOIN Users u ON t.user_id = u.id 
                     WHERE u.id = ?";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}