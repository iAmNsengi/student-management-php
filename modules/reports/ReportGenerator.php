<?php
class ReportGenerator {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function generateAttendanceReport($class = null, $start_date = null, $end_date = null) {
        $query = "SELECT s.full_name, c.name as class, 
                 COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
                 COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_days,
                 ROUND(COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(*), 2) as attendance_percentage
                 FROM Students s
                 LEFT JOIN Attendance a ON s.id = a.student_id
                 WHERE (:class IS NULL OR s.class = :class)
                 AND (:start_date IS NULL OR a.date >= :start_date)
                 AND (:end_date IS NULL OR a.date <= :end_date)
                 GROUP BY s.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class', $class);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function generateGradeReport($class = null) {
        $query = "SELECT s.full_name, c.name as course_name,
                 AVG(g.grade) as average_grade,
                 MAX(g.grade) as highest_grade,
                 MIN(g.grade) as lowest_grade
                 FROM Students s
                 JOIN Grades g ON s.id = g.student_id
                 JOIN Courses c ON g.course_id = c.id
                 WHERE (:class IS NULL OR s.class = :class)
                 GROUP BY s.id, c.id";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class', $class);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
