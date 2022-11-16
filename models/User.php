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
}