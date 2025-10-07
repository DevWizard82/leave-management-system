document.addEventListener("DOMContentLoaded", function () {
  const activityCtx = document.getElementById("activityChart").getContext("2d");
  new Chart(activityCtx, {
    type: "line",
    data: {
      labels: ["Jan", "Fév", "Mar", "Avr", "Mai", "Juin"],
      datasets: [
        {
          label: "Utilisateurs actifs",
          data: [65, 78, 90, 81, 95, 102],
          borderColor: "rgb(99, 102, 241)",
          backgroundColor: "rgba(99, 102, 241, 0.1)",
          tension: 0.4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  });

  const leaveCtx = document.getElementById("leaveChart").getContext("2d");
  new Chart(leaveCtx, {
    type: "doughnut",
    data: {
      labels: [
        "Congé annuel",
        "Congé maladie",
        "Congé personnel",
        "Congé de maternité",
      ],
      datasets: [
        {
          data: [45, 25, 20, 10],
          backgroundColor: [
            "rgb(99, 102, 241)",
            "rgb(34, 197, 94)",
            "rgb(251, 146, 60)",
            "rgb(168, 85, 247)",
          ],
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "bottom",
        },
      },
    },
  });

  const attendanceCtx = document
    .getElementById("attendanceChart")
    .getContext("2d");
  new Chart(attendanceCtx, {
    type: "bar",
    data: {
      labels: ["Jan", "Fév", "Mar", "Avr", "Mai", "Juin"],
      datasets: [
        {
          label: "Taux de présence %",
          data: [92, 88, 94, 91, 96, 94],
          backgroundColor: "rgba(99, 102, 241, 0.8)",
          borderColor: "rgb(99, 102, 241)",
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
        },
      },
    },
  });
});

function openAddLeave(userId) {
  // Redirect to add_leave.php with user ID
  window.location.href = "add_leave.php?id=" + userId;
}
