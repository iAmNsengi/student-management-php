<?php
session_start();
require_once "config/database.php";
require_once "models/User.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Initialize database and user
$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->role = $_SESSION['role'];

// Get user profile
$profile = $user->getProfile();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="dashboard.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="scripts/fetchApi.js"></script>

</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="profile-section">
                <img src="assets/images/avatar-placeholder.png" alt="Profile" class="profile-image">
                <h3><?php echo htmlspecialchars($profile['full_name']); ?></h3>
                <p><?php echo htmlspecialchars($profile['role']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-panel="overview">
                    <i class="fas fa-home"></i> Overview
                </a>
                
                <?php if ($user->isStudent()): ?>
                <a href="#" class="nav-item" data-panel="my-courses">
                    <i class="fas fa-book"></i> My Courses
                </a>
                <a href="#" class="nav-item" data-panel="my-grades">
                    <i class="fas fa-chart-line"></i> My Grades
                </a>
                <a href="#" class="nav-item" data-panel="my-attendance">
                    <i class="fas fa-calendar-check"></i> My Attendance
                </a>
                <?php endif; ?>
                
                <?php if ($user->isTeacher()): ?>
                <a href="#" class="nav-item" data-panel="manage-courses">
                    <i class="fas fa-chalkboard-teacher"></i> Manage Courses
                </a>
                <a href="#" class="nav-item" data-panel="manage-grades">
                    <i class="fas fa-graduation-cap"></i> Manage Grades
                </a>
                <a href="#" class="nav-item" data-panel="manage-attendance">
                    <i class="fas fa-user-check"></i> Manage Attendance
                </a>
                <a href="#" class="nav-item" data-panel="reports">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <?php endif; ?>
                
                <a href="#" class="nav-item" data-panel="profile">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="panel active" id="overview">
                <h2>Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>!</h2>
                <div class="dashboard-cards">
                    <?php if ($user->isStudent()): ?>
                    <div class="card">
                        <h3>Courses</h3>
                        <div id="courses-count">Loading...</div>
                    </div>
                    <div class="card">
                        <h3>Average Grade</h3>
                        <div id="average-grade">Loading...</div>
                    </div>
                    <div class="card">
                        <h3>Attendance Rate</h3>
                        <div id="attendance-rate">Loading...</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user->isTeacher()): ?>
                    <div class="card">
                        <h3>Total Students</h3>
                        <div id="students-count">Loading...</div>
                    </div>
                    <div class="card">
                        <h3>Active Courses</h3>
                        <div id="active-courses">Loading...</div>
                    </div>
                    <div class="card">
                        <h3>Today's Classes</h3>
                        <div id="today-classes">Loading...</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="chart-container">
                    <canvas id="overview-chart"></canvas>
                </div>
            </div>

            <!-- Additional panels will be shown/hidden based on navigation -->
            <?php if ($user->isStudent()): ?>
            <div class="panel" id="my-courses">
                <h2>My Courses</h2>
                <div id="courses-list" class="data-grid"></div>
            </div>
            
            <div class="panel" id="my-grades">
                <h2>My Grades</h2>
                <div id="grades-list" class="data-grid"></div>
            </div>
            
            <div class="panel" id="my-attendance">
                <h2>My Attendance</h2>
                <div id="attendance-list" class="data-grid"></div>
            </div>
            <?php endif; ?>
            
            <?php if ($user->isTeacher()): ?>
            <div class="panel" id="manage-courses">
                <h2>Manage Courses</h2>
                <button class="btn-primary" onclick="showAddCourseModal()">Add New Course</button>
                <div id="manage-courses-list" class="data-grid"></div>
            </div>
            
            <div class="panel" id="manage-grades">
                <h2>Manage Grades</h2>
                <div class="filters">
                    <select id="course-filter">
                        <option value="">Select Course</option>
                    </select>
                </div>
                <div id="manage-grades-list" class="data-grid"></div>
            </div>
            
            <div class="panel" id="manage-attendance">
                <h2>Manage Attendance</h2>
                <div class="filters">
                    <input type="date" id="attendance-date" value="<?php echo date('Y-m-d'); ?>">
                    <select id="attendance-course">
                        <option value="">Select Course</option>
                    </select>
                </div>
                <div id="manage-attendance-list" class="data-grid"></div>
            </div>
            
            <div class="panel" id="reports">
                <h2>Reports</h2>
                <div class="report-options">
                    <button onclick="generateReport('attendance')">Attendance Report</button>
                    <button onclick="generateReport('grades')">Grades Report</button>
                </div>
                <div id="report-container"></div>
            </div>
            <?php endif; ?>
            
            <div class="panel" id="profile">
                <h2>My Profile</h2>
                <div class="profile-details">
                    <form id="profile-form">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" id="full-name" value="<?php echo htmlspecialchars($profile['full_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($profile['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo htmlspecialchars($profile['role']); ?>" disabled>
                        </div>
                        <button type="submit" class="btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </main>
    </div>


 



   

    <script>
    // Navigation handling
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
                const panel = this.dataset.panel;
                
                // Update active states
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById(panel)?.classList.add('active');
                
                // Load panel data
                loadPanelData(panel);
            }
        });
    });

    // Load panel data based on selected panel
    async function loadPanelData(panel) {
        try {
            switch(panel) {
                case 'overview':
                    await loadOverviewData();
                    break;
                case 'my-courses':
                case 'manage-courses':
                    await loadCourses();
                    break;
                case 'my-grades':
                case 'manage-grades':
                    await loadGrades();
                    break;
                case 'my-attendance':
                case 'manage-attendance':
                    await loadAttendance();
                    break;
            }
        } catch (error) {
            console.error('Error loading panel data:', error);
        }
    }

    // Load overview data
    async function loadOverviewData() {
        try {
            const response = await fetch('api/endpoints.php?endpoint=view_overview');
            const data = await response.json();
            
            // Update dashboard cards
            updateDashboardCards(data);
            
            // Update overview chart
            updateOverviewChart(data);
        } catch (error) {
            console.error('Error loading overview data:', error);
        }
    }

    // Update dashboard cards with actual data
    async function updateDashboardCards(data) {
        if (document.getElementById('courses-count')) {
            const coursesResponse = await fetch('api/endpoints.php?endpoint=view_courses');
            const coursesData = await coursesResponse.data
            document.getElementById('courses-count').textContent = coursesData?.length;
        }
        
        if (document.getElementById('average-grade')) {
            try {
                const gradesResponse = await fetchAPI('view_grades');
                if (gradesResponse.success && gradesResponse.data.length > 0) {
                    const grades = gradesResponse.data;
                    const average = grades.reduce((acc, curr) => acc + parseFloat(curr.grade), 0) / grades.length;
                    document.getElementById('average-grade').textContent = `${average.toFixed(1)}%`;
                } else {
                    document.getElementById('average-grade').textContent = 'N/A';
                }
            } catch (error) {
                console.error('Error loading grades:', error);
                document.getElementById('average-grade').textContent = 'Error';
            }
        }
        
        if (document.getElementById('attendance-rate')) {
            const attendanceResponse = await fetch('api/endpoints.php?endpoint=view_attendance');
            const attendanceData = await attendanceResponse?.data?.json();
            const presentCount = attendanceData?.filter(a => a?.status === 'present').length;
            const rate = (presentCount / attendanceData?.length) * 100;
            document.getElementById('attendance-rate').textContent = rate.toFixed(2) + '%';
        }
        
        // Teacher-specific cards
        if (document.getElementById('students-count')) {
            const studentsResponse = await fetch('api/endpoints.php?endpoint=view_students');
            const studentsData = await studentsResponse.json();
            document.getElementById('students-count').textContent = studentsData.length;
        }
        
        if (document.getElementById('active-courses')) {
            const coursesResponse = await fetch('api/endpoints.php?endpoint=view_courses');
            const coursesData = await coursesResponse.json();
            document.getElementById('active-courses').textContent = coursesData.length;
        }
        
        if (document.getElementById('today-classes')) {
            const todayResponse = await fetch('api/endpoints.php?endpoint=view_today_classes');
            const todayData = await todayResponse.json();
            document.getElementById('today-classes').textContent = todayData.length;
        }
    }

    // Update overview chart
    function updateOverviewChart(data) {
        const ctx = document.getElementById('overview-chart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (window.overviewChart) {
            window.overviewChart.destroy();
        }
        
        // Create new chart based on user role
        if (document.getElementById('average-grade')) {
            // Debug what's in data
            console.log('Chart data received:', data);
            
            // Ensure data is an array
            let chartData = [];
            if (Array.isArray(data)) {
                chartData = data.filter(item => item.grade != null);
            } else if (data && typeof data === 'object') {
                // If data is an object, try to convert it to array
                chartData = Object.values(data)?.filter(item => item?.grade != null);
            }
            
            console.log('Processed chart data:', chartData);
            
            window.overviewChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(item => item.course_name || 'Unknown Course'),
                    datasets: [{
                        label: 'Grades by Course',
                        data: chartData.map(item => parseFloat(item.grade) || 0),
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Grade (%)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Grade: ${context.parsed.y}%`;
                                }
                            }
                        }
                    }
                }
            });
        } else {
            // Teacher chart
            window.overviewChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Assignments', 'Attendance', 'Average Grade'],
                    datasets: [{
                        label: 'Class Statistics',
                        data: [
                            data.assignments_completed,
                            data.attendance_rate,
                            data.class_average
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(75, 192, 192, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }

    // Load initial data when page loads
    document.addEventListener('DOMContentLoaded', () => {
        loadPanelData('overview');
    });

    // Modal handling functions
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function hideModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modals when clicking on X or outside
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.onclick = function() {
            this.closest('.modal').style.display = 'none';
        }
    });

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Course management
    async function showAddCourseModal() {
        showModal('addCourseModal');
    }

    document.getElementById('addCourseForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitButton = this.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        submitButton.textContent = 'Creating...';
        submitButton.disabled = true;
        
        try {
            const response = await fetch('api/endpoints.php?endpoint=create_course', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    name: this.name.value,
                    schedule: this.schedule.value
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                hideModal('addCourseModal');
                loadCourses(); // Refresh the courses list
                this.reset();
                alert('Course created successfully!');
            } else {
                alert(data.message || 'Error creating course');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error creating course. Please try again.');
        } finally {
            // Reset button state
            submitButton.textContent = originalButtonText;
            submitButton.disabled = false;
        }
    });

    // Grade management
    async function showAddGradeModal() {
        try {
            // Load students and courses
            const studentsResponse = await fetch('api/endpoints.php?endpoint=view_students');
            const coursesResponse = await fetch('api/endpoints.php?endpoint=view_courses');
            
            const students = await studentsResponse.json();
            const courses = await coursesResponse.json();
            
            const studentSelect = document.getElementById('gradeStudent');
            const courseSelect = document.getElementById('gradeCourse');
            
            studentSelect.innerHTML = students.map(student => 
                `<option value="${student.id}">${student.full_name}</option>`
            ).join('');
            
            courseSelect.innerHTML = courses.map(course => 
                `<option value="${course.id}">${course.name}</option>`
            ).join('');
            
            showModal('addGradeModal');
        } catch (error) {
            console.error('Error:', error);
            alert('Error loading data');
        }
    }

    document.getElementById('addGradeForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        try {
            const response = await fetch('api/endpoints.php?endpoint=add_grade', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    student_id: this.student_id.value,
                    course_id: this.course_id.value,
                    grade: this.grade.value
                })
            });
            
            const data = await response.json();
            if (data.success) {
                hideModal('addGradeModal');
                loadGrades();
                this.reset();
            } else {
                alert('Error adding grade');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error adding grade');
        }
    });

    // Attendance management
    async function showMarkAttendanceModal() {
        try {
            const studentsResponse = await fetch('api/endpoints.php?endpoint=view_students');
            const students = await studentsResponse.json();
            
            const studentList = document.getElementById('attendanceStudentList');
            studentList.innerHTML = students.map(student => `
                <div class="attendance-row">
                    <label>${student.full_name}</label>
                    <select name="status_${student.id}">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                    </select>
                </div>
            `).join('');
            
            document.getElementById('attendanceDate').value = new Date().toISOString().split('T')[0];
            showModal('markAttendanceModal');
        } catch (error) {
            console.error('Error:', error);
            alert('Error loading students');
        }
    }

    document.getElementById('markAttendanceForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const date = this.date.value;
        const attendance = [];
        
        document.querySelectorAll('#attendanceStudentList .attendance-row').forEach(row => {
            const studentId = row.querySelector('select').name.split('_')[1];
            const status = row.querySelector('select').value;
            attendance.push({ student_id: studentId, status });
        });
        
        try {
            const response = await fetch('api/endpoints.php?endpoint=mark_attendance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date,
                    attendance
                })
            });
            
            const data = await response.json();
            if (data.success) {
                hideModal('markAttendanceModal');
                loadAttendance();
                this.reset();
            } else {
                alert('Error marking attendance');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error marking attendance');
        }
    });

    // Profile update
    document.getElementById('profile-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitButton = this.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        submitButton.textContent = 'Updating...';
        submitButton.disabled = true;
        
        try {
            const response = await fetch('api/endpoints.php?endpoint=update_profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    full_name: document.getElementById('full-name').value
                })
            });
            
            if (response.success) {
                alert('Profile updated successfully!');
                const nameDisplay = document.querySelector('.profile-section h3');
                if (nameDisplay) {
                    nameDisplay.textContent = document.getElementById('full-name').value;
                }
            } else {
                alert(response.message || 'Error updating profile');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error updating profile. Please try again.');
        } finally {
            submitButton.textContent = originalButtonText;
            submitButton.disabled = false;
        }
    });

    // Load data functions
    async function loadCourses() {
        try {
            const response = await fetchAPI('view_courses');
            console.log(response);
            
            if (response.success) {
                updateCoursesDisplay(response.data);
            } else {
                console.error('Failed to load courses:', response.error);
            }
        } catch (error) {
            console.error('Error loading courses:', error);
        }
    }

    function updateCoursesDisplay(courses) {
        const coursesList = document.querySelector('#courses-list');
        if (coursesList) {
            coursesList.innerHTML = courses.length ? courses.map(course => `
                <div class="course-card">
                    <h3>${course.name}</h3>
                    <p><i class="fas fa-clock"></i> Schedule: ${course.schedule}</p>
                    <p><i class="fas fa-chalkboard-teacher"></i> Teacher: ${course.teacher_name || 'Not Assigned'}</p>
                    ${course.is_enrolled !== undefined ? `
                        <p class="enrollment-status">
                            <i class="fas ${course.is_enrolled ? 'fa-check-circle' : 'fa-circle'}"></i>
                            ${course.is_enrolled ? 'Enrolled' : 'Not Enrolled'}
                        </p>
                    ` : ''}
                    ${course.is_teaching !== undefined ? `
                        <p class="teaching-status">
                            <i class="fas ${course.is_teaching ? 'fa-star' : 'fa-star-o'}"></i>
                            ${course.is_teaching ? 'Teaching' : 'Other Course'}
                        </p>
                    ` : ''}
                </div>
            `).join('') : '<p class="no-courses">No courses found</p>';
        }

        // Update courses count if element exists
        const coursesCount = document.getElementById('courses-count');
        if (coursesCount) {
            coursesCount.textContent = courses.length;
        }
    }

    async function loadGrades() {
        try {
            const response = await fetch('api/endpoints.php?endpoint=view_grades');
            const grades = await response.json();
            
            updateGradesList(grades);
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function updateGradesList(grades) {
        const gradesList = document.querySelector('#grades-list');
        if (gradesList) {
            gradesList.innerHTML = `
                <table class="data-grid">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${grades.length ? grades.map(grade => `
                            <tr>
                                <td>${grade.student_name}</td>
                                <td>${grade.course_name}</td>
                                <td>${grade.grade}%</td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="editGrade(${grade.id})">Edit</button>
                                    <button class="btn-delete" onclick="deleteGrade(${grade.id})">Delete</button>
                                </td>
                            </tr>
                        `).join('') : '<tr><td colspan="4">No grades found</td></tr>'}
                    </tbody>
                </table>
            `;
        }
    }

    async function loadAttendance() {
        try {
            const date = document.getElementById('attendance-date')?.value || new Date().toISOString().split('T')[0];
            const response = await fetch(`api/endpoints.php?endpoint=view_attendance&date=${date}`);
            const attendance = await response.json();
            
            const attendanceList = document.querySelector('#manage-attendance-list');
            if (attendanceList) {
                attendanceList.innerHTML = `
                    <table class="data-grid">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${attendance.map(record => `
                                <tr>
                                    <td>${record.student_name}</td>
                                    <td>${record.status}</td>
                                    <td>${record.date}</td>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick="editAttendance(${record.id})">Edit</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

  
    </script>

    <script>
    // Event listeners and initialization
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize all components
        loadCourses();
        loadOverviewData();
        loadGrades();
        loadAttendance();

        // Add event listeners for forms
        const profileForm = document.getElementById('profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', handleProfileUpdate);
        }

        const addCourseForm = document.getElementById('addCourseForm');
        if (addCourseForm) {
            addCourseForm.addEventListener('submit', handleAddCourse);
        }
    });
    </script>
</body>
</html>
