<?php
class Grade {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function addGrade($student_id, $course_id, $grade) {
        $query = "INSERT INTO Grades (student_id, course_id, grade) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$student_id, $course_id, $grade]);
    }
    
    public function getStudentGrades($student_id) {
        $query = "SELECT g.*, c.name as course_name 
                 FROM Grades g 
                 JOIN Courses c ON g.course_id = c.id 
                 WHERE g.student_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}