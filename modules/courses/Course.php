<?php
class Course {
    private $conn;
    private $table_name = "Courses";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function createCourse($name, $teacher_id, $schedule) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (name, teacher_id, schedule) 
                     VALUES (:name, :teacher_id, :schedule)";
            
            $stmt = $this->conn->prepare($query);
            
            // Clean the data
            $name = htmlspecialchars(strip_tags($name));
            $schedule = htmlspecialchars(strip_tags($schedule));
            
            // Bind the parameters
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":teacher_id", $teacher_id);
            $stmt->bindParam(":schedule", $schedule);
            
            if($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Course created successfully',
                    'id' => $this->conn->lastInsertId()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Unable to create course'
            ];
            
        } catch(PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    public function getCourses() {
        try {
            $query = "SELECT c.*, t.full_name as teacher_name 
                     FROM " . $this->table_name . " c 
                     LEFT JOIN Teachers t ON c.teacher_id = t.user_id 
                     ORDER BY c.name";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getTeacherCourses($teacher_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE teacher_id = :teacher_id 
                     ORDER BY name";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":teacher_id", $teacher_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
}