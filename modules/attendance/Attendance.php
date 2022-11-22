<?php
class Attendance {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function markAttendance($student_id, $date, $status) {
        $query = "INSERT INTO Attendance (student_id, date, status) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE status = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$student_id, $date, $status, $status]);
    }
    
    public function getAttendanceByDate($date) {
        $query = "SELECT a.*, s.full_name 
                 FROM Attendance a 
                 JOIN Students s ON a.student_id = s.id 
                 WHERE a.date = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}