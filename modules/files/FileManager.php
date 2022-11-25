<?php
class FileManager {
    private $upload_dir = 'uploads/';
    
    public function __construct() {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0777, true);
        }
    }
    
    public function uploadFile($file, $type, $related_id) {
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_ext;
        $upload_path = $this->upload_dir . $type . '/' . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            return $this->saveFileRecord($new_filename, $type, $related_id);
        }
        return false;
    }
    
    private function saveFileRecord($filename, $type, $related_id) {
        global $conn;
        $query = "INSERT INTO Files (filename, type, related_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$filename, $type, $related_id]);
    }
}