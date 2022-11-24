<?php
header('Content-Type: application/json');
require_once "../config/database.php";
require_once "../modules/attendance/Attendance.php";

$database = new Database();
$db = $database->getConnection();
$attendance = new Attendance($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $attendance->markAttendance(
        $data['student_id'],
        $data['date'],
        $data['status']
    );
    echo json_encode(['success' => $result]);
}
?>