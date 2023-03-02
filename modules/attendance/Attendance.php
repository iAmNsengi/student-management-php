<?php
class Attendance {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAttendance($date) {
        try {
            $query = "SELECT a.*, s.full_name as student_name 
                     FROM Attendance a
                     JOIN Students s ON a.student_id = s.id
                     WHERE DATE(a.date) = :date";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAttendance: " . $e->getMessage());
            return [];
        }
    }
    
    public function markAttendance($studentId, $courseId, $date, $status) {
        try {
            // Check if attendance record exists
            $checkQuery = "SELECT id FROM Attendance 
                          WHERE student_id = :student_id 
                          AND course_id = :course_id
                          AND DATE(date) = :date";
            
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':student_id', $studentId);
            $checkStmt->bindParam(':course_id', $courseId);
            $checkStmt->bindParam(':date', $date);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                // Update existing record
                $query = "UPDATE Attendance 
                         SET status = :status 
                         WHERE student_id = :student_id 
                         AND course_id = :course_id
                         AND DATE(date) = :date";
            } else {
                // Insert new record
                $query = "INSERT INTO Attendance (student_id, course_id, date, status) 
                         VALUES (:student_id, :course_id, :date, :status)";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $studentId);
            $stmt->bindParam(':course_id', $courseId);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':status', $status);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in markAttendance: " . $e->getMessage());
            return false;
        }
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