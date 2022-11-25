<?php
header('Content-Type: application/json');
require_once "../../config/database.php";
require_once "../../modules/reports/ReportGenerator.php";

$database = new Database();
$db = $database->getConnection();
$reporter = new ReportGenerator($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? '';
    $class = $_GET['class'] ?? null;
    
    switch($type) {
        case 'attendance':
            $data = $reporter->generateAttendanceReport(
                $class,
                $_GET['start_date'] ?? null,
                $_GET['end_date'] ?? null
            );
            break;
        case 'grades':
            $data = $reporter->generateGradeReport($class);
            break;
        default:
            http_response_code(400);
            $data = ['error' => 'Invalid report type'];
    }
    
    echo json_encode($data);
}