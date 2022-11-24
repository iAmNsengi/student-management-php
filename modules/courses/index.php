<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Management</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Course Management</h2>
        <div class="course-grid">
            <?php
            $database = new Database();
            $db = $database->getConnection();
            $course = new Course($db);
            $courses = $course->getCourses();
            
            foreach($courses as $course): ?>
                <div class="course-card">
                    <h3><?php echo htmlspecialchars($course['name']); ?></h3>
                    <p>Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                    <p>Schedule: <?php echo htmlspecialchars($course['schedule']); ?></p>
                    <div class="course-actions">
                        <button onclick="editCourse(<?php echo $course['id']; ?>)">Edit</button>
                        <button onclick="viewAttendance(<?php echo $course['id']; ?>)">Attendance</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>