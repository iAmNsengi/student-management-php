<?php
class MessageSystem {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function sendMessage($sender_id, $receiver_id, $subject, $content) {
        $query = "INSERT INTO Messages (sender_id, receiver_id, subject, content, sent_date) 
                 VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$sender_id, $receiver_id, $subject, $content]);
    }
    
    public function getInbox($user_id) {
        $query = "SELECT m.*, u.username as sender_name 
                 FROM Messages m 
                 JOIN Users u ON m.sender_id = u.id 
                 WHERE m.receiver_id = ? 
                 ORDER BY m.sent_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}