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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    // Load initial data
    loadPanelData('overview');
    </script>

    <style>
    .dashboard-container {
        display: flex;
        min-height: 100vh;
    }

    .sidebar {
        width: 250px;
        background: #2c3e50;
        color: white;
        padding: 20px;
    }

    .main-content {
        flex: 1;
        padding: 20px;
        background: #f5f6fa;
    }

    .profile-section {
        text-align: center;
        padding: 20px 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .profile-image {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        margin-bottom: 10px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: white;
        text-decoration: none;
        transition: 0.3s;
        margin: 5px 0;
        border-radius: 5px;
    }

    .nav-item:hover, .nav-item.active {
        background: rgba(255,255,255,0.1);
    }

    .nav-item i {
        margin-right: 10px;
    }

    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .panel {
        display: none;
    }

    .panel.active {
        display: block;
    }

    .data-grid {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
    }

    .btn-primary {
        background: #3498db;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
    }

    .btn-primary:hover {
        background: #2980b9;
    }

    .filters {
        margin: 20px 0;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    </style>
</body>
</html>
