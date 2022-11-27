<?php
session_start();
require_once "../../config/database.php";
require_once "../../modules/reports/ReportGenerator.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports Dashboard - Student Management System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard">
        <nav class="sidebar">
        </nav>
        
        <main class="content">
            <div class="reports-container">
                <h2>Reports Dashboard</h2>
                
                <div class="report-filters">
                    <div class="filter-group">
                        <label for="report-type">Report Type:</label>
                        <select id="report-type" onchange="updateFilters()">
                            <option value="attendance">Attendance Report</option>
                            <option value="grades">Grade Report</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" id="date-filters">
                        <label for="start-date">Start Date:</label>
                        <input type="date" id="start-date">
                        
                        <label for="end-date">End Date:</label>
                        <input type="date" id="end-date">
                    </div>
                    
                    <div class="filter-group">
                        <label for="class-filter">Class:</label>
                        <select id="class-filter">
                            <option value="">All Classes</option>
                            <?php
                            $database = new Database();
                            $db = $database->getConnection();
                            $query = "SELECT DISTINCT class FROM Students ORDER BY class";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . htmlspecialchars($row['class']) . "'>" . 
                                     htmlspecialchars($row['class']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <button class="btn-primary" onclick="generateReport()">Generate Report</button>
                </div>
                
                <div class="report-output">
                    <div class="chart-container">
                        <canvas id="report-chart"></canvas>
                    </div>
                    <div id="report-table" class="report-table"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
    let currentChart = null;

    function updateFilters() {
        const reportType = document.getElementById('report-type').value;
        const dateFilters = document.getElementById('date-filters');
        dateFilters.style.display = reportType === 'attendance' ? 'block' : 'none';
    }

    async function generateReport() {
        const type = document.getElementById('report-type').value;
        const classFilter = document.getElementById('class-filter').value;
        
        const filters = {
            class: classFilter
        };
        
        if (type === 'attendance') {
            filters.start_date = document.getElementById('start-date').value;
            filters.end_date = document.getElementById('end-date').value;
        }
        
        try {
            const queryParams = new URLSearchParams(filters);
            const response = await fetch(`../../api/reports/generate.php?type=${type}&${queryParams}`);
            const data = await response.json();
            
            if (type === 'attendance') {
                renderAttendanceReport(data);
            } else if (type === 'grades') {
                renderGradeReport(data);
            }
        } catch (error) {
            console.error('Error generating report:', error);
            alert('Error generating report. Please try again.');
        }
    }

    function renderAttendanceReport(data) {
        const ctx = document.getElementById('report-chart').getContext('2d');
        
        if (currentChart) {
            currentChart.destroy();
        }
        
        currentChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(item => item.full_name),
                datasets: [{
                    label: 'Attendance Percentage',
                    data: data.map(item => item.attendance_percentage),
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
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

        // Generate table view
        const tableContainer = document.getElementById('report-table');
        tableContainer.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Present Days</th>
                        <th>Absent Days</th>
                        <th>Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.map(item => `
                        <tr>
                            <td>${item.full_name}</td>
                            <td>${item.present_days}</td>
                            <td>${item.absent_days}</td>
                            <td>${item.attendance_percentage}%</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function renderGradeReport(data) {
        const ctx = document.getElementById('report-chart').getContext('2d');
        
        if (currentChart) {
            currentChart.destroy();
        }
        
        currentChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [...new Set(data.map(item => item.course_name))],
                datasets: [{
                    label: 'Average Grade',
                    data: data.map(item => item.average_grade),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    tension: 0.1
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

        // Generate table view
        const tableContainer = document.getElementById('report-table');
        tableContainer.innerHTML = `
            <table class="data-table">
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
                    ${data.map(item => `
                        <tr>
                            <td>${item.full_name}</td>
                            <td>${item.course_name}</td>
                            <td>${parseFloat(item.average_grade).toFixed(2)}</td>
                            <td>${item.highest_grade}</td>
                            <td>${item.lowest_grade}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    // Initialize filters on page load
    updateFilters();
    </script>

    <style>
    .reports-container {
        padding: 20px;
    }

    .report-filters {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .filter-group {
        margin-bottom: 15px;
    }

    .filter-group label {
        display: inline-block;
        margin-right: 10px;
        min-width: 100px;
    }

    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        height: 400px;
    }

    .report-table {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    </style>
</body>
</html>