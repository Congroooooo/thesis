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

// Dynamic lively colors for categories (deterministic per label)
const CATEGORY_COLOR_CACHE_KEY = "pamo_category_colors_v1";
let CATEGORY_COLORS = {};
try {
  CATEGORY_COLORS =
    JSON.parse(localStorage.getItem(CATEGORY_COLOR_CACHE_KEY) || "{}") || {};
} catch (_) {
  CATEGORY_COLORS = {};
}

function hashString(str) {
  let h = 0;
  for (let i = 0; i < str.length; i++) {
    h = (h << 5) - h + str.charCodeAt(i);
    h |= 0;
  }
  return Math.abs(h);
}

function colorForCategory(label) {
  const key = String(label || "").trim() || "__unknown__";
  if (CATEGORY_COLORS[key]) return CATEGORY_COLORS[key];
  const hue = hashString(key) % 360; // spread hues
  const fill = `hsl(${hue}, 75%, 70%)`; // lively pastel
  // Persist
  CATEGORY_COLORS[key] = fill;
  try {
    localStorage.setItem(
      CATEGORY_COLOR_CACHE_KEY,
      JSON.stringify(CATEGORY_COLORS)
    );
  } catch (_) {}
  return fill;
}

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

// STI Color Palette Generator
function generateSTIColors(count) {
  const stiBlue = "#0047BA";
  const stiYellow = "#FFD100";
  const colors = [];

  if (count <= 2) {
    colors.push(stiBlue, stiYellow);
  } else {
    // Generate complementary colors based on STI palette
    for (let i = 0; i < count; i++) {
      const hue = i * (360 / count);
      if (i === 0) colors.push(stiBlue);
      else if (i === 1) colors.push(stiYellow);
      else {
        // Generate variations of blue and yellow tones
        if (i % 2 === 0) {
          colors.push(`hsl(${220 + i * 15}, 75%, ${60 + i * 5}%)`);
        } else {
          colors.push(`hsl(${45 + i * 10}, 85%, ${65 + i * 3}%)`);
        }
      }
    }
  }

  return colors.slice(0, count);
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
  const backgroundColors = generateSTIColors(labels.length);
  const borderColors = backgroundColors.map(() => "#ffffff");

  if (stockPieChart) stockPieChart.destroy();
  stockPieChart = new Chart(ctx, {
    type: "doughnut", // Changed to doughnut for modern look
    data: {
      labels,
      datasets: [
        {
          data: quantities,
          backgroundColor: backgroundColors,
          borderColor: borderColors,
          borderWidth: 3,
          hoverOffset: 8,
          hoverBorderWidth: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "65%", // Creates the doughnut hole
      animation: {
        animateRotate: true,
        animateScale: true,
        duration: 2000,
        easing: "easeOutQuart",
      },
      plugins: {
        legend: {
          display: false, // We'll use custom legend
        },
        tooltip: {
          backgroundColor: "rgba(255, 255, 255, 0.95)",
          titleColor: "#2d3748",
          bodyColor: "#4a5568",
          borderColor: "#0047BA",
          borderWidth: 2,
          padding: 16,
          cornerRadius: 12,
          titleFont: {
            family: "Poppins",
            weight: "600",
            size: 14,
          },
          bodyFont: {
            family: "Inter",
            weight: "500",
            size: 13,
          },
          callbacks: {
            title: function (context) {
              return context[0].label;
            },
            label: function (context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((context.parsed / total) * 100).toFixed(1);
              return `Stock: ${context.parsed} units (${percentage}%)`;
            },
            labelColor: function (context) {
              return {
                borderColor: context.dataset.borderColor[context.dataIndex],
                backgroundColor:
                  context.dataset.backgroundColor[context.dataIndex],
                borderWidth: 2,
              };
            },
          },
        },
      },
      onHover: (event, activeElements) => {
        event.native.target.style.cursor =
          activeElements.length > 0 ? "pointer" : "default";
      },
    },
  });

  // --- Custom HTML Legend with STI Colors ---
  let legendContainer = document.getElementById("stockPieChartLegend");
  if (!legendContainer) {
    legendContainer = document.createElement("div");
    legendContainer.id = "stockPieChartLegend";
    canvas.parentNode.appendChild(legendContainer);
  }

  // Build modern legend HTML
  const itemsPerRow = Math.ceil(labels.length / 2);
  let legendHTML = '<div class="custom-pie-legend-row">';
  labels.forEach((label, idx) => {
    if (idx > 0 && idx % itemsPerRow === 0) {
      legendHTML += '</div><div class="custom-pie-legend-row">';
    }
    const color = backgroundColors[idx];
    const quantity = quantities[idx];
    legendHTML += `
      <span class="custom-pie-legend-item">
        <span class="legend-color-box" style="background:${color}; border-color: rgba(255,255,255,0.9)"></span>
        <span class="legend-text">
          <strong>${label}</strong>
          <br>
          <small style="color: #6b7280;">${quantity} units</small>
        </span>
      </span>`;
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
          label: "Sales Performance",
          data: sales,
          borderColor: "#0047BA",
          backgroundColor: "rgba(0, 71, 186, 0.1)",
          fill: true,
          tension: 0.4, // Smooth curved lines
          pointBackgroundColor: "#FFD100",
          pointBorderColor: "#0047BA",
          pointBorderWidth: 3,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointHitRadius: 20,
          pointHoverBackgroundColor: "#FFD100",
          pointHoverBorderColor: "#0047BA",
          pointHoverBorderWidth: 4,
          borderWidth: 3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 2000,
        easing: "easeOutQuart",
      },
      interaction: {
        mode: "nearest",
        intersect: false,
      },
      plugins: {
        legend: {
          display: true,
          position: "top",
          labels: {
            font: {
              family: "Poppins",
              size: 12,
              weight: "500",
            },
            color: "#4a5568",
            usePointStyle: true,
            pointStyle: "circle",
            padding: 20,
          },
        },
        tooltip: {
          backgroundColor: "rgba(255, 255, 255, 0.95)",
          borderColor: "#0047BA",
          borderWidth: 2,
          titleColor: "#2d3748",
          bodyColor: "#4a5568",
          padding: 16,
          cornerRadius: 12,
          displayColors: true,
          titleFont: {
            family: "Poppins",
            weight: "600",
            size: 14,
          },
          bodyFont: {
            family: "Inter",
            weight: "500",
            size: 13,
          },
          caretPadding: 10,
          mode: "index",
          intersect: false,
          callbacks: {
            title: function (context) {
              return `Date: ${context[0].label}`;
            },
            label: function (context) {
              const idx = context.dataIndex;
              const point = filteredData[idx];
              let label = `Sales: ₱${Number(
                point.total_sales
              ).toLocaleString()}`;
              if (!category) {
                if (point.category) label += `\nCategory: ${point.category}`;
              } else if (category === "Tertiary-Uniform") {
                if (point.course) label += `\nCourse: ${point.course}`;
              }
              return label;
            },
            labelColor: function (context) {
              return {
                borderColor: "#0047BA",
                backgroundColor: "#FFD100",
                borderWidth: 2,
              };
            },
          },
        },
      },
      scales: {
        x: {
          grid: {
            color: "rgba(0, 71, 186, 0.08)",
            lineWidth: 1,
          },
          ticks: {
            font: {
              family: "Inter",
              size: 11,
              weight: "400",
            },
            color: "#6b7280",
            maxTicksLimit: 8,
          },
        },
        y: {
          grid: {
            color: "rgba(0, 71, 186, 0.08)",
            lineWidth: 1,
          },
          ticks: {
            font: {
              family: "Inter",
              size: 11,
              weight: "400",
            },
            color: "#6b7280",
            callback: function (value) {
              return "₱" + value.toLocaleString();
            },
          },
        },
      },
      elements: {
        point: {
          pointStyle: "circle",
          pointRadius: 6,
          pointHoverRadius: 8,
          pointHitRadius: 20,
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
