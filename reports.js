document.addEventListener("DOMContentLoaded", () => {
  const chartModal = document.getElementById("chartModal");
  const modalContent = chartModal.querySelector("div");
  const closeBtn = document.getElementById("closeChartModal");
  const expandedCtx = document.getElementById("expandedChart").getContext("2d");
  const modalTitle = document.getElementById("modalTitle");

  let expandedChart;

  // -------------------------------
  // Function to initialize a chart
  // -------------------------------
  function createChart(
    ctx,
    { type, labels, data, title, color, options = {} },
    maintainAspectRatio = false
  ) {
    return new Chart(ctx, {
      type,
      data: {
        labels,
        datasets: [
          {
            label: title,
            data,
            backgroundColor: color || "rgba(99,102,241,0.8)",
            borderColor: color || "rgba(99,102,241,1)",
            borderWidth: 1,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: {} },
          ...options.plugins,
        },
        scales: { y: { beginAtZero: true }, ...options.scales },
        layout: {
          padding: options.padding || {
            top: 10,
            bottom: 10,
            left: 10,
            right: 10,
          },
        },
      },
    });
  }

  // -------------------------------
  // Initialize preview charts
  // -------------------------------
  Object.keys(window.chartData).forEach((chartId) => {
    const canvasElement = document.getElementById(chartId);
    if (!canvasElement) return;
    const ctx = canvasElement.getContext("2d");
    const chartInfo = window.chartData[chartId];

    // Use a bar chart for topLeave preview
    const type = chartId === "topLeave" ? "bar" : chartInfo.type || "line";
    createChart(ctx, { ...chartInfo, type }, false);
  });

  // -------------------------------
  // Open modal on chart click
  // -------------------------------
  document.querySelectorAll("[data-chart]").forEach((preview) => {
    preview.addEventListener("click", () => {
      const chartId = preview.getAttribute("data-chart");
      const chartInfo = window.chartData[chartId];

      modalTitle.textContent = chartInfo.title;

      if (expandedChart) expandedChart.destroy();

      // Use top 10 for topLeave, fullLabels/fullData otherwise
      const labels =
        chartId === "topLeave"
          ? chartInfo.fullLabels || chartInfo.labels
          : chartInfo.fullLabels || chartInfo.labels;
      const data =
        chartId === "topLeave"
          ? chartInfo.fullData || chartInfo.data
          : chartInfo.fullData || chartInfo.data;

      expandedChart = createChart(
        expandedCtx,
        { ...chartInfo, labels, data, type: "bar" }, // ensure full chart is bar
        true
      );

      chartModal.classList.remove("hidden");
      setTimeout(() => {
        modalContent.classList.remove("scale-95", "opacity-0");
        modalContent.classList.add("scale-100", "opacity-100");
      }, 10);
    });
  });

  // -------------------------------
  // Close modal
  // -------------------------------
  closeBtn.addEventListener("click", () => {
    modalContent.classList.remove("scale-100", "opacity-100");
    modalContent.classList.add("scale-95", "opacity-0");
    setTimeout(() => chartModal.classList.add("hidden"), 300);
  });
});
