// Update overview chart
function updateOverviewChart(data) {
  const ctx = document.getElementById("overview-chart").getContext("2d");

  // Destroy existing chart if it exists
  if (window.overviewChart) {
    window.overviewChart.destroy();
  }

  // Create new chart based on user role
  if (data.role === "Student") {
    // Create a simple bar chart for student overview

    window.overviewChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: ["Courses", "Average Grade", "Attendance Rate"],
        datasets: [
          {
            label: "Student Overview",
            data: [
              data.courses_count || 0,
              parseFloat(data.average_grade) || 0,
              data.attendance_rate || 0,
            ],
            backgroundColor: [
              "rgba(255, 99, 132, 0.2)",
              "rgba(75, 192, 192, 0.2)",
              "rgba(54, 162, 235, 0.2)",
            ],
            borderColor: [
              "rgba(255, 99, 132, 1)",
              "rgba(75, 192, 192, 1)",
              "rgba(54, 162, 235, 1)",
            ],
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            title: {
              display: true,
              text: "Value",
            },
          },
        },
        plugins: {
          legend: {
            display: true,
            position: "top",
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const label = context.dataset.label || "";
                const value = context.parsed.y;
                const metric = context.label;

                if (metric === "Courses") {
                  return `${label}: ${value} courses`;
                } else if (metric === "Average Grade") {
                  return `${label}: ${value}%`;
                } else if (metric === "Attendance Rate") {
                  return `${label}: ${value}%`;
                }
                return `${label}: ${value}`;
              },
            },
          },
        },
      },
    });
  } else {
    // Teacher view chart (if needed)
    window.overviewChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: ["Total Students", "Active Courses", "Average Class Grade"],
        datasets: [
          {
            label: "Teacher Overview",
            data: [
              data.students_count || 0,
              data.courses_count || 0,
              parseFloat(data.class_average) || 0,
            ],
            backgroundColor: [
              "rgba(255, 99, 132, 0.2)",
              "rgba(75, 192, 192, 0.2)",
              "rgba(54, 162, 235, 0.2)",
            ],
            borderColor: [
              "rgba(255, 99, 132, 1)",
              "rgba(75, 192, 192, 1)",
              "rgba(54, 162, 235, 1)",
            ],
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Value",
            },
          },
        },
        plugins: {
          legend: {
            display: true,
            position: "top",
          },
        },
      },
    });
  }
}
