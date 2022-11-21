<?php
class Course {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function createCourse($name, $teacher_id, $schedule) {
        $query = "INSERT INTO Courses (name, teacher_id, schedule) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$name, $teacher_id, $schedule]);
    }
    
    public function getCourses() {
        $query = "SELECT c.*, t.full_name as teacher_name 
                 FROM Courses c 
                 LEFT JOIN Teachers t ON c.teacher_id = t.id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}