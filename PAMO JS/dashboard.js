function logout() {
  if (confirm("Are you sure you want to log out?")) {
    window.location.href = "../Pages/logout.php";
  }
}

function clearActivities() {
  if (confirm("Are you sure you want to clear all activities?")) {
    fetch("../PAMO PAGES/clear_activities.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.querySelector(".activity-list").innerHTML =
            "<p class='no-activities'>No recent activities</p>";
        } else {
          alert(
            "Failed to clear activities: " + (data.error || "Unknown error")
          );
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred while clearing activities");
      });
  }
}

function redirectToLowStock() {
  console.log("redirectToLowStock called");
  sessionStorage.setItem("applyLowStockFilter", "true");
  console.log("Session storage set, redirecting to inventory.php");
  console.log("Current location:", window.location.href);
  window.location.href = "inventory.php";
}

let stockPieChart, salesLineChart;

// Unique color mapping for each inventory category (custom user colors)
const CATEGORY_COLORS = {
  "Tertiary-Uniform": "#0d1a4a", // Dark Blue
  "SHS-Uniform": "#4fc3f7", // Light Blue
  "STI-Accessories": "#ff9800", // Orange
  "STI-Shirts": "#43a047", // Green
  "STI-Jacket": "#e53935", // Red
  "SHS-PE": "#ffd600", // Yellow
  "Tertiary-PE": "#8e24aa", // Purple
};

async function fetchStockData(category = "", course = "") {
  const params = new URLSearchParams({ category, course });
  try {
    const res = await fetch(
      `../PAMO_DASHBOARD_BACKEND/api_inventory_stocks.php?${params}`
    );
    if (!res.ok) {
      console.error("Stock endpoint error:", res.status, res.statusText);
      return [];
    }
    const data = await res.json();
    console.log("Stock Data:", data);
    return data;
  } catch (error) {
    console.error("Fetch stock data error:", error);
    return [];
  }
}

async function fetchSalesData(category, course, period) {
  const params = new URLSearchParams({ category, course, period });
  try {
    const res = await fetch(
      `../PAMO_DASHBOARD_BACKEND/api_sales_performance.php?${params}`
    );
    if (!res.ok) {
      console.error("Sales endpoint error:", res.status, res.statusText);
      return [];
    }
    const data = await res.json();
    console.log("Sales Data:", data);
    return data;
  } catch (error) {
    console.error("Fetch sales data error:", error);
    return [];
  }
}

function renderStockPieChart(data) {
  const canvas = document.getElementById("stockPieChart");
  if (!canvas) {
    console.error("stockPieChart canvas not found");
    return;
  }
  const ctx = canvas.getContext("2d");
  const labels = data.map((d) => d.category);
  const quantities = data.map((d) => d.quantity);
  const backgroundColors = labels.map(
    (label) => CATEGORY_COLORS[label] || "#cccccc"
  );

  if (stockPieChart) stockPieChart.destroy();
  stockPieChart = new Chart(ctx, {
    type: "pie",
    data: {
      labels,
      datasets: [
        {
          data: quantities,
          backgroundColor: backgroundColors,
        },
      ],
    },
    options: {
      plugins: {
        legend: {
          display: false, // Disable built-in legend
        },
      },
    },
  });

  // --- Custom HTML Legend ---
  let legendContainer = document.getElementById("stockPieChartLegend");
  if (!legendContainer) {
    legendContainer = document.createElement("div");
    legendContainer.id = "stockPieChartLegend";
    canvas.parentNode.appendChild(legendContainer);
  }
  // Build legend HTML (two rows)
  const itemsPerRow = Math.ceil(labels.length / 2);
  let legendHTML = '<div class="custom-pie-legend-row">';
  labels.forEach((label, idx) => {
    if (idx > 0 && idx % itemsPerRow === 0) {
      legendHTML += '</div><div class="custom-pie-legend-row">';
    }
    legendHTML += `<span class="custom-pie-legend-item"><span class="legend-color-box" style="background:${
      CATEGORY_COLORS[label] || "#cccccc"
    }"></span>${label}</span>`;
  });
  legendHTML += "</div>";
  legendContainer.innerHTML = legendHTML;
}

function renderSalesLineChart(data) {
  // Filter out invalid data points
  const filteredData = data.filter(
    (d) => d.total_sales !== null && d.total_sales !== undefined && d.date
  );
  const labels = filteredData.map((d) => d.date);
  const sales = filteredData.map((d) => Number(d.total_sales));

  // Debug logging
  console.log("Labels:", labels);
  console.log("Sales:", sales);
  console.log("Raw data:", data);

  const canvas = document.getElementById("salesLineChart");
  if (!canvas) {
    console.error("salesLineChart canvas not found");
    return;
  }
  const ctx = canvas.getContext("2d");

  // Get current filter values
  const category = document.getElementById("salesCategoryFilter").value;
  const course = document.getElementById("salesCourseFilter").value;

  if (salesLineChart) salesLineChart.destroy();
  salesLineChart = new Chart(ctx, {
    type: "line",
    data: {
      labels,
      datasets: [
        {
          // label: "Sales", // Remove the label so legend and text are not shown
          data: sales,
          borderColor: "#4caf50",
          fill: false,
          pointBackgroundColor: "#2196f3",
          pointRadius: 5,
          pointHoverRadius: 7,
          pointHitRadius: 15, // Increase clickable area
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: "nearest",
        intersect: true,
      },
      plugins: {
        legend: {
          display: false, // Remove the rectangle legend
        },
        tooltip: {
          backgroundColor: "#fff",
          borderColor: "#4caf50",
          borderWidth: 1,
          titleColor: "#222",
          bodyColor: "#222",
          padding: 12,
          displayColors: false,
          bodyFont: { weight: "bold" },
          position: "nearest", // Ensures tooltip is close to the point
          yAlign: "top", // Always show tooltip above the dot
          callbacks: {
            label: function (context) {
              const idx = context.dataIndex;
              const point = filteredData[idx];
              let label = `Sold: ${point.total_sales}`;
              if (!category) {
                if (point.category) label += ` | Category: ${point.category}`;
              } else if (category === "Tertiary-Uniform") {
                if (point.course) label += ` | Course: ${point.course}`;
              }
              return label;
            },
          },
        },
      },
      elements: {
        point: {
          pointStyle: "circle",
          pointRadius: 5,
          pointHoverRadius: 7,
          pointHitRadius: 15,
        },
      },
    },
  });
}

async function populateSalesCategoryDropdown() {
  const select = document.getElementById("salesCategoryFilter");
  try {
    const res = await fetch(
      "../PAMO_DASHBOARD_BACKEND/api_inventory_categories.php"
    );
    if (!res.ok) throw new Error("Failed to fetch categories");
    const categories = await res.json();
    select.innerHTML = '<option value="">All</option>';
    categories.forEach((cat) => {
      const opt = document.createElement("option");
      opt.value = cat;
      opt.textContent = cat;
      select.appendChild(opt);
    });
  } catch (e) {
    console.error("Error loading sales categories:", e);
  }
}

async function populateSalesCourseDropdown() {
  const select = document.getElementById("salesCourseFilter");
  try {
    const res = await fetch("../PAMO_DASHBOARD_BACKEND/api_courses.php");
    if (!res.ok) throw new Error("Failed to fetch courses");
    const courses = await res.json();
    select.innerHTML = '<option value="">All</option>';
    courses.forEach((course) => {
      const opt = document.createElement("option");
      opt.value = course;
      opt.textContent = course;
      select.appendChild(opt);
    });
  } catch (e) {
    console.error("Error loading sales courses:", e);
  }
}

// --- SALES ANALYTICS ---
async function updateSalesAnalytics() {
  const category = document.getElementById("salesCategoryFilter").value;
  const courseSelect = document.getElementById("salesCourseFilter");
  let course = "";
  if (category === "Tertiary-Uniform") {
    course = courseSelect.value;
    courseSelect.disabled = false;
  } else {
    courseSelect.value = "";
    courseSelect.disabled = true;
  }
  const period = document.getElementById("salesPeriodFilter").value;
  const salesData = await fetchSalesData(category, course, period);
  renderSalesLineChart(salesData);
}

// --- INVENTORY ANALYTICS (always overview) ---
async function updateInventoryAnalytics() {
  const stockData = await fetchStockData();
  renderStockPieChart(stockData);
}

function handleSalesCategoryChange() {
  const category = document.getElementById("salesCategoryFilter").value;
  const courseSelect = document.getElementById("salesCourseFilter");
  if (category === "Tertiary-Uniform") {
    courseSelect.disabled = false;
  } else {
    courseSelect.value = "";
    courseSelect.disabled = true;
  }
  updateSalesAnalytics();
}

window.addEventListener("DOMContentLoaded", async () => {
  await Promise.all([
    populateSalesCategoryDropdown(),
    populateSalesCourseDropdown(),
  ]);
  handleSalesCategoryChange();
  document
    .getElementById("salesCategoryFilter")
    .addEventListener("change", handleSalesCategoryChange);
  document
    .getElementById("salesCourseFilter")
    .addEventListener("change", updateSalesAnalytics);
  document
    .getElementById("salesPeriodFilter")
    .addEventListener("change", updateSalesAnalytics);
  updateSalesAnalytics();
  updateInventoryAnalytics();
});
