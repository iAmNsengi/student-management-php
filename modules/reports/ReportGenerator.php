<?php
class ReportGenerator {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function generateAttendanceReport($class = null, $start_date = null, $end_date = null) {
        $query = "SELECT 
                    s.full_name,
                    s.class,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                    CASE 
                        WHEN COUNT(*) = 0 THEN 0
                        ELSE ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*), 2)
                    END as attendance_percentage
                 FROM Students s
                 LEFT JOIN Attendance a ON s.id = a.student_id
                 LEFT JOIN Courses c ON a.course_id = c.id
                 WHERE 1=1";  // Always true condition to make adding conditions easier
        
        $params = array();
        
        if ($class !== null) {
            $query .= " AND s.class = :class";
            $params[':class'] = $class;
        }
        if ($start_date !== null) {
            $query .= " AND a.date >= :start_date";
            $params[':start_date'] = $start_date;
        }
        if ($end_date !== null) {
            $query .= " AND a.date <= :end_date";
            $params[':end_date'] = $end_date;
        }
        
        $query .= " GROUP BY s.id, s.full_name, s.class";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function generateGradeReport($class = null) {
        $query = "SELECT 
                    s.full_name,
                    c.name as course_name,
                    ROUND(AVG(g.grade), 2) as average_grade,
                    MAX(g.grade) as highest_grade,
                    MIN(g.grade) as lowest_grade
                 FROM Students s
                 JOIN Grades g ON s.id = g.student_id
                 JOIN Courses c ON g.course_id = c.id
                 WHERE 1=1";
        
        $params = array();
        
        if ($class !== null) {
            $query .= " AND s.class = :class";
            $params[':class'] = $class;
        }
        
        $query .= " GROUP BY s.id, s.full_name, c.id, c.name";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
