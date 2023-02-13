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
                <div class="panel-content">
                    <div class="filter-section">
                        <select id="course-filter" class="form-control">
                            <option value="">Select Course</option>
                        </select>
                    </div>
                    <div id="manage-grades-list">
                        <!-- Students and grades will be loaded here -->
                    </div>
                </div>
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
              
        </main>
    </div>
 <?php include_once __DIR__ . '/modals/addCourse.modal.html'; ?>
 <?php include_once __DIR__ . '/modals/markAttendance.html'; ?>
 <?php include_once __DIR__ . '/modals/addGrade.modal.html'; ?>

 <script src="./scripts/updateOverviewChart.js"></script>
    <script>
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
                const panel = this.dataset.panel;
                
                if (!panel) {
                    console.warn('No panel specified in data-panel attribute');
                    return;
                }
                
                // Update active states
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
                
                this.classList.add('active');
                const targetPanel = document.getElementById(panel);
                if (targetPanel) {
                    targetPanel.classList.add('active');
                    loadPanelData(panel);
                } else {
                    console.warn(`Panel ${panel} not found`);
                }
            }
        });
    });

    // Load panel data based on selected panel
    async function loadPanelData(panel) {
        if (!panel) {
            console.log('No panel specified');
            return;
        }
        
        // Make sure the panel exists before trying to load its data
        const panelElement = document.getElementById(panel);
        if (!panelElement) {
            console.log(`Panel ${panel} not found`);
            return;
        }
        
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
                case 'reports':
                    await loadReports();
                    break;
                case 'profile':
                    await loadProfile();
                    break;
                default:
                    console.warn(`No loader defined for panel: ${panel}`);
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
            updateDashboardCards(data);
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
                if (gradesResponse.length > 0) {
                    const average = gradesResponse.reduce((acc, curr) => acc + parseFloat(curr.grade), 0) / gradesResponse.length;
                    document.getElementById('average-grade').textContent = `${average.toFixed(1)}%`;
                } else {
                    document.getElementById('average-grade').textContent = '0';
                }
            } catch (error) {
                console.error('Error loading grades:', error);
                document.getElementById('average-grade').textContent = 'Error';
            }
        }
        
        if (document.getElementById('attendance-rate')) {
            const attendanceResponse = await fetch('api/endpoints.php?endpoint=view_attendance');
            const attendanceData = await attendanceResponse.json();
            
            const presentCount = attendanceData?.filter(a => a?.status === 'present').length;
            const rate = ((presentCount / attendanceData?.length) * 100 ) || 0;
            document.getElementById('attendance-rate').textContent = rate.toFixed(2) + '%';
        }
        
        // Teacher-specific cards
        if (document.getElementById('students-count')) {
            const studentsResponse = await fetch('api/endpoints.php?endpoint=view_students');
            const studentsData = await studentsResponse.json();
            console.log(studentsData, '------ students data');
            
            document.getElementById('students-count').textContent = studentsData.data.length;
        }
        
        if (document.getElementById('active-courses')) {
            const coursesResponse = await fetch('api/endpoints.php?endpoint=view_courses');
            const coursesData = await coursesResponse.json();
            document.getElementById('active-courses').textContent = coursesData?.data?.length;
        }
        
        if (document.getElementById('today-classes')) {
            const todayResponse = await fetch('api/endpoints.php?endpoint=view_today_classes');
            const todayData = await todayResponse.json();
            document.getElementById('today-classes').textContent = todayData.length;
        }
    }

    // Load initial data when page loads
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

        // Initialize dropdowns
        populateCourseDropdowns();

        // For students - load available courses
        if (document.getElementById('courses-list')) {
            loadAvailableCourses();
        }

        // For teachers - course selection for grading and attendance
        const courseFilter = document.getElementById('course-filter');
        if (courseFilter) {
            courseFilter.addEventListener('change', (e) => {
                loadStudentsForGrading(e.target.value);
            });
        }

        // For teachers - attendance management
        const attendanceCourse = document.getElementById('attendance-course');
        const attendanceDate = document.getElementById('attendance-date');
        if (attendanceCourse && attendanceDate) {
            attendanceCourse.addEventListener('change', () => {
                loadStudentsForAttendance(attendanceCourse.value, attendanceDate.value);
            });
            attendanceDate.addEventListener('change', () => {
                loadStudentsForAttendance(attendanceCourse.value, attendanceDate.value);
            });
        }

        // Add grade form submission handler
        const addGradeForm = document.getElementById('addGradeForm');
        if (addGradeForm) {
            addGradeForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                try {
                    const response = await fetch('api/endpoints.php?endpoint=add_grade', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            student_id: this.gradeStudent.value,
                            course_id: this.gradeCourse.value,
                            grade: this.grade.value,
                            notes: this.notes?.value || ''
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        alert('Grade added successfully!');
                        document.getElementById('addGradeModal').style.display = 'none';
                        loadStudentsForGrading(this.gradeCourse.value); // Refresh the list
                        this.reset();
                    } else {
                        alert(data.error || 'Error adding grade');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error adding grade');
                }
            });
        }
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
        const modal = document.getElementById('addCourseModal');
        modal.style.display = 'block';
        
        // Close modal when clicking the X
        document.querySelector('#addCourseModal .close').onclick = function() {
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    }

   

    // Course dropdown population and management
    async function populateCourseDropdowns() {
        try {
            const response = await fetchAPI('view_courses');
            console.log(response, 'populate dropdowns----------');
            
            if (response.success) {
                // Update all course dropdowns
                const courseDropdowns = [
                    document.getElementById('course-filter'),
                    document.getElementById('attendance-course'),
                    document.getElementById('gradeCourse')
                ];

                const courseOptions = response.data.map(course => 
                    `<option value="${course.id}">${course.name}</option>`
                ).join('');

                courseDropdowns.forEach(dropdown => {
                    if (dropdown) {
                        dropdown.innerHTML = '<option value="">Select Course</option>' + courseOptions;
                    }
                });
            }
        } catch (error) {
            console.error('Error loading courses:', error);
        }
    }

    // Grade management functions
    async function showAddGradeModal(studentId) {
        const modal = document.getElementById('addGradeModal');
        const courseId = document.getElementById('course-filter').value;
        
        // Set hidden form values
        document.getElementById('gradeStudent').value = studentId;
        document.getElementById('gradeCourse').value = courseId;
        
        // Show modal
        modal.style.display = 'block';
        
        // Close modal when clicking the X
        document.querySelector('.close').onclick = function() {
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    }

    // Profile update
    async function handleProfileUpdate(e) {
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
                    full_name: document.getElementById('full-name').value,
                    email: document.getElementById('email')?.value,
                    phone: document.getElementById('phone')?.value
                })
            });
            
            const data = await response.json();
            if (data.success) {
                alert('Profile updated successfully!');
                // Update the displayed name in the sidebar if it exists
                const nameDisplay = document.querySelector('.profile-section h3');
                if (nameDisplay) {
                    nameDisplay.textContent = document.getElementById('full-name').value;
                }
            } else {
                alert(data.error || 'Error updating profile');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error updating profile. Please try again.');
        } finally {
            submitButton.textContent = originalButtonText;
            submitButton.disabled = false;
        }
    }

    // Load data functions
    async function loadCourses() {
        try {
            const panel = document.querySelector('.panel.active').id;
            const response = await fetchAPI('view_courses');
            
            if (response.success) {
                if (panel === 'my-courses') {
                    // Student view
                    const coursesList = document.querySelector('#courses-list');
                    if (coursesList) {
                        coursesList.innerHTML = response.data.length ? response.data.map(course => `
                            <div class="course-card">
                                <h3>${course.name}</h3>
                                <p><i class="fas fa-clock"></i> Schedule: ${course.schedule}</p>
                                <p><i class="fas fa-chalkboard-teacher"></i> Teacher: ${course.teacher_name || 'Not Assigned'}</p>
                                ${course.is_enrolled 
                                    ? `<p class="enrollment-status enrolled">
                                        <i class="fas fa-check-circle"></i> Enrolled
                                       </p>`
                                    : `<button class="btn-enroll" onclick="enrollInCourse(${course.id})">
                                        <i class="fas fa-plus-circle"></i> Enroll
                                       </button>`
                                }
                            </div>
                        `).join('') : '<p class="no-courses">No courses found</p>';
                    }
                } else if (panel === 'manage-courses') {
                    // Teacher view
                    const coursesList = document.querySelector('#manage-courses-list');
                    if (coursesList) {
                        coursesList.innerHTML = `
                            <table class="data-grid">
                                <thead>
                                    <tr>
                                        <th>Course Name</th>
                                        <th>Schedule</th>
                                        <th>Students Enrolled</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${response.data.length ? response.data.map(course => `
                                        <tr>
                                            <td>${course.name}</td>
                                            <td>${course.schedule}</td>
                                            <td>${course.enrolled_count || 0}</td>
                                            <td class="action-buttons">
                                                <button class="btn-edit" onclick="editCourse(${course.id})">Edit</button>
                                                <button class="btn-delete" onclick="deleteCourse(${course.id})">Delete</button>
                                            </td>
                                        </tr>
                                    `).join('') : '<tr><td colspan="4">No courses found</td></tr>'}
                                </tbody>
                            </table>
                        `;
                    }
                }
            } else {
                console.error('Failed to load courses:', response.error);
            }
        } catch (error) {
            console.error('Error loading courses:', error);
        }
    }

    async function loadGrades() {
        try {
            const activePanel = document.querySelector('.panel.active');
            if (!activePanel) {
                console.log('No active panel found');
                return;
            }
            
            const panel = activePanel.id;
            console.log('Loading grades for panel:', panel); // Debug log

            if (panel === 'my-grades') {
                // Student view
                const response = await fetchAPI('view_grades');
                const gradesList = document.querySelector('#grades-list');
                if (gradesList) {
                    gradesList.innerHTML = `
                        <table class="data-grid">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Grade</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${response.length ? response.map(grade => `
                                    <tr>
                                        <td>${grade.course_name}</td>
                                        <td>${grade.grade}%</td>
                                        <td>${grade.date}</td>
                                    </tr>
                                `).join('') : '<tr><td colspan="3">No grades found</td></tr>'}
                            </tbody>
                        </table>
                    `;
                }
            } else if (panel === 'manage-grades') {
                console.log('Initializing teacher grades view'); // Debug log
                const gradesList = document.querySelector('#manage-grades-list');
                const courseFilter = document.querySelector('#course-filter');
                
                // Initialize course filter if it doesn't exist
                if (!courseFilter) {
                    console.error('Course filter not found');
                    return;
                }

                // Load courses into dropdown
                const coursesResponse = await fetchAPI('view_courses');
                console.log('Courses response:', coursesResponse); // Debug log

                if (coursesResponse.success && coursesResponse.data) {
                    courseFilter.innerHTML = `
                        <option value="">Select Course</option>
                        ${coursesResponse.data.map(course => 
                            `<option value="${course.id}">${course.name}</option>`
                        ).join('')}
                    `;

                    // Add change event listener to course filter
                    courseFilter.addEventListener('change', async (e) => {
                        const selectedCourseId = e.target.value;
                        if (selectedCourseId) {
                            await loadStudentsForGrading(selectedCourseId);
                        } else {
                            if (gradesList) {
                                gradesList.innerHTML = '<p>Please select a course to view and manage grades.</p>';
                            }
                        }
                    });

                    // Show initial message
                    if (gradesList) {
                        gradesList.innerHTML = '<p>Please select a course to view and manage grades.</p>';
                    }
                } else {
                    console.error('Failed to load courses');
                    if (gradesList) {
                        gradesList.innerHTML = '<p class="error-message">Error loading courses. Please try again.</p>';
                    }
                }
            }
        } catch (error) {
            console.error('Error in loadGrades:', error);
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

    // Add missing functions for reports and profile
    async function loadReports() {
        try {
            const reportContainer = document.getElementById('report-container');
            if (!reportContainer) return;

            // Clear previous content
            reportContainer.innerHTML = `
                <div class="report-filters">
                    <select id="report-class" class="form-control">
                        <option value="">All Classes</option>
                        <option value="10A">Class 10A</option>
                        <option value="10B">Class 10B</option>
                    </select>
                    <input type="date" id="report-start-date" class="form-control">
                    <input type="date" id="report-end-date" class="form-control">
                </div>
                <div id="report-results"></div>
            `;
        } catch (error) {
            console.error('Error loading reports:', error);
        }
    }

    async function generateReport(type) {
        try {
            const classFilter = document.getElementById('report-class').value;
            const startDate = document.getElementById('report-start-date').value;
            const endDate = document.getElementById('report-end-date').value;
            
            let url = `api/reports/generate.php?type=${type}`;
            if (classFilter) url += `&class=${classFilter}`;
            if (startDate) url += `&start_date=${startDate}`;
            if (endDate) url += `&end_date=${endDate}`;

            const response = await fetch(url);
            const data = await response.json();

            const reportResults = document.getElementById('report-results');
            if (!reportResults) return;

            if (type === 'attendance') {
                reportResults.innerHTML = `
                    <h3>Attendance Report</h3>
                    <table class="data-grid">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Present Days</th>
                                <th>Absent Days</th>
                                <th>Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(record => `
                                <tr>
                                    <td>${record.full_name}</td>
                                    <td>${record.class}</td>
                                    <td>${record.present_days}</td>
                                    <td>${record.absent_days}</td>
                                    <td>${record.attendance_percentage}%</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else if (type === 'grades') {
                reportResults.innerHTML = `
                    <h3>Grades Report</h3>
                    <table class="data-grid">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Average Grade</th>
                                <th>Highest Grade</th>
                                <th>Lowest Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(record => `
                                <tr>
                                    <td>${record.full_name}</td>
                                    <td>${record.course_name}</td>
                                    <td>${record.average_grade}%</td>
                                    <td>${record.highest_grade}%</td>
                                    <td>${record.lowest_grade}%</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }
        } catch (error) {
            console.error('Error generating report:', error);
            document.getElementById('report-results').innerHTML = `
                <div class="error-message">
                    Error generating report. Please try again.
                </div>
            `;
        }
    }

    async function loadProfile() {
        try {
            const response = await fetchAPI('view_profile');
            if (response.success) {
                const fullNameInput = document.getElementById('full-name');
                if (fullNameInput && response.data.full_name) {
                    fullNameInput.value = response.data.full_name;
                }
            }
        } catch (error) {
            console.error('Error loading profile:', error);
        }
    }

    // For Students - Course Enrollment
    async function loadAvailableCourses() {
        try {
            const response = await fetch('api/endpoints.php?endpoint=view_courses');
            const data = await response.json();
            
            const coursesList = document.getElementById('courses-list');
            if (!coursesList) return;

            coursesList.innerHTML = `
                <table class="data-grid">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Teacher</th>
                            <th>Schedule</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.data.map(course => `
                            <tr>
                                <td>${course.name}</td>
                                <td>${course.teacher_name}</td>
                                <td>${course.schedule}</td>
                                <td>
                                    ${course.is_enrolled ? 
                                        '<span class="enrolled-badge">Enrolled</span>' :
                                        `<button class="btn-enroll" onclick="enrollInCourse(${course.id})">
                                            Enroll
                                        </button>`
                                    }
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            console.error('Error loading courses:', error);
        }
    }

    async function enrollInCourse(courseId) {
        try {
            const response = await fetchAPI('enroll_course', {
                method: 'POST',
                body: { course_id: courseId }
            });

            if (response.success) {
                // Show success message
                const messageDiv = document.createElement('div');
                messageDiv.className = 'alert alert-success';
                messageDiv.innerHTML = 'Successfully enrolled in course!';
                document.querySelector('.panel.active').insertBefore(messageDiv, document.querySelector('.panel.active').firstChild);
                
                // Remove message after 3 seconds
                setTimeout(() => messageDiv.remove(), 3000);
                
                // Refresh the courses list
                await loadCourses();
            } else {
                // Show error message
                const messageDiv = document.createElement('div');
                messageDiv.className = 'alert alert-danger';
                messageDiv.innerHTML = response.error || 'Failed to enroll in course';
                document.querySelector('.panel.active').insertBefore(messageDiv, document.querySelector('.panel.active').firstChild);
                
                // Remove message after 3 seconds
                setTimeout(() => messageDiv.remove(), 3000);
            }
        } catch (error) {
            console.error('Error:', error);
            // Show error message
            const messageDiv = document.createElement('div');
            messageDiv.className = 'alert alert-danger';
            messageDiv.innerHTML = 'Failed to enroll in course. Please try again.';
            document.querySelector('.panel.active').insertBefore(messageDiv, document.querySelector('.panel.active').firstChild);
            
            // Remove message after 3 seconds
            setTimeout(() => messageDiv.remove(), 3000);
        }
    }

    // For Teachers - Grade Management
    async function loadStudentsForGrading(courseId) {
        try {
            console.log('Loading students for course:', courseId);
            
            if (!courseId) {
                console.error('No course ID provided');
                return;
            }

            // Construct the endpoint with the course_id parameter
            const response = await fetchAPI(`view_students&course_id=${courseId}`);
            console.log('Students response:', response);
            
            const gradesList = document.getElementById('manage-grades-list');
            if (!gradesList) {
                console.error('Grades list container not found');
                return;
            }

            // Show loading state
            gradesList.innerHTML = '<p>Loading students...</p>';

            if (!response || !response.data) {
                gradesList.innerHTML = '<p class="error-message">No data received from server</p>';
                return;
            }

            const students = response.data;
            
            if (students.length === 0) {
                gradesList.innerHTML = '<p>No students enrolled in this course.</p>';
                return;
            }

            gradesList.innerHTML = `
                <table class="data-grid">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Current Grade</th>
                            <th>Grade Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${students.map(student => `
                            <tr>
                                <td>${student.full_name}</td>
                                <td>${student.current_grade || 'No grade'}</td>
                                <td>${student.grade_date ? new Date(student.grade_date).toLocaleDateString() : 'N/A'}</td>
                                <td>
                                    <button class="btn-primary" 
                                            onclick="showAddGradeModal(${student.id}, ${courseId})">
                                        ${student.current_grade === 'No grade' ? 'Add Grade' : 'Update Grade'}
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            console.error('Error loading students:', error);
            const gradesList = document.getElementById('manage-grades-list');
            if (gradesList) {
                gradesList.innerHTML = '<p class="error-message">Error loading students. Please try again.</p>';
            }
        }
    }

    // For Teachers - Attendance Management
    async function loadStudentsForAttendance(courseId, date) {
        try {
            const response = await fetch(
                `api/endpoints.php?endpoint=view_students&course_id=${courseId}`
            );
            const data = await response.json();
            
            const attendanceList = document.getElementById('attendance-list');
            if (!attendanceList) return;

            attendanceList.innerHTML = `
                <table class="data-grid">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.map(student => `
                            <tr>
                                <td>${student.full_name}</td>
                                <td>
                                    <select id="attendance-status-${student.id}" class="attendance-select">
                                        <option value="present">Present</option>
                                        <option value="absent">Absent</option>
                                        <option value="late">Late</option>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn-mark-attendance" 
                                            onclick="markAttendance(${student.id}, ${courseId})">
                                        Mark
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            console.error('Error loading students for attendance:', error);
        }
    }

    async function markAttendance(studentId, courseId) {
        const status = document.getElementById(`attendance-status-${studentId}`).value;
        const date = document.getElementById('attendance-date').value;
        
        try {
            const response = await fetch('api/endpoints.php?endpoint=mark_attendance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    student_id: studentId,
                    course_id: courseId,
                    date: date,
                    status: status
                })
            });
            
            const data = await response.json();
            if (data.success) {
                alert('Attendance marked successfully!');
            } else {
                alert(data.error || 'Failed to mark attendance');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to mark attendance');
        }
    }

    async function handleAddCourse(e) {
        e.preventDefault();
        
        const submitButton = this.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        submitButton.textContent = 'Creating...';
        submitButton.disabled = true;
        
        try {
            const schedule = `${document.getElementById('courseSchedule').value} ${document.getElementById('courseTime').value}`;
            
            const response = await fetch('api/endpoints.php?endpoint=create_course', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    name: document.getElementById('courseName').value,
                    schedule: schedule,
                    description: document.getElementById('courseDescription').value
                })
            });
            
            const data = await response.json();
            if (data.success) {
                alert('Course created successfully!');
                document.getElementById('addCourseModal').style.display = 'none';
                this.reset();
                // Refresh the courses list if it exists
                if (typeof loadCourses === 'function') {
                    loadCourses();
                }
            } else {
                alert(data.error || 'Error creating course');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error creating course. Please try again.');
        } finally {
            submitButton.textContent = originalButtonText;
            submitButton.disabled = false;
        }
    }

    // Add these styles for the alerts
    const styles = `
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    `;

    // Add styles to the document
    const styleSheet = document.createElement("style");
    styleSheet.innerText = styles;
    document.head.appendChild(styleSheet);
    </script>
</body>
</html>
