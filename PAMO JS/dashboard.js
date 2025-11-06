function logout() {
  showLogoutConfirmation();
}

function redirectToLowStock() {
  sessionStorage.setItem("applyLowStockFilter", "true");
  window.location.href = "inventory.php";
}

let stockPieChart, salesLineChart;

// Chart.js plugin for center text in donut chart
const centerTextPlugin = {
  id: "centerText",
  afterDatasetsDraw(chart) {
    if (chart.config.type !== "doughnut") return;

    const {
      ctx,
      chartArea: { width, height },
    } = chart;
    const centerX = width / 2;
    const centerY = height / 2;

    ctx.save();

    // Calculate total - ensure proper number conversion
    const total = chart.data.datasets[0].data.reduce((sum, value) => {
      return sum + (parseInt(value, 10) || 0);
    }, 0);

    // Draw total number
    ctx.font = "bold 36px 'Segoe UI'";
    ctx.fillStyle = "#1e293b";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(total.toLocaleString(), centerX, centerY - 12);

    // Draw label
    ctx.font = "600 13px 'Segoe UI'";
    ctx.fillStyle = "#64748b";
    ctx.fillText("Total Items", centerX, centerY + 20);

    ctx.restore();
  },
};

// Register the plugin
if (typeof Chart !== "undefined") {
  Chart.register(centerTextPlugin);
}

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

async function fetchStockData(category = "", subcategory = "") {
  const params = new URLSearchParams({ category, subcategory });
  try {
    const res = await fetch(
      `../PAMO_DASHBOARD_BACKEND/api_inventory_stocks.php?${params}`
    );
    if (!res.ok) {
      console.error("Stock endpoint error:", res.status, res.statusText);
      return [];
    }
    const data = await res.json();
    return data;
  } catch (error) {
    console.error("Fetch stock data error:", error);
    return [];
  }
}

async function fetchSalesData(category, subcategory, period) {
  const params = new URLSearchParams({ category, subcategory, period });
  try {
    const res = await fetch(
      `../PAMO_DASHBOARD_BACKEND/api_sales_performance.php?${params}`
    );
    if (!res.ok) {
      console.error("Sales endpoint error:", res.status, res.statusText);
      return [];
    }
    const data = await res.json();
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
  const quantities = data.map((d) => parseInt(d.quantity, 10) || 0);

  // Modern color palette with STI brand integration
  const modernColors = [
    { bg: "rgba(0, 114, 188, 0.85)", border: "#0072bc" },
    { bg: "rgba(253, 240, 5, 0.85)", border: "#fdf005" },
    { bg: "rgba(16, 185, 129, 0.85)", border: "#10b981" },
    { bg: "rgba(249, 115, 22, 0.85)", border: "#f97316" },
    { bg: "rgba(139, 92, 246, 0.85)", border: "#8b5cf6" },
    { bg: "rgba(236, 72, 153, 0.85)", border: "#ec4899" },
  ];

  const backgroundColors = labels.map(
    (_, idx) => modernColors[idx % modernColors.length].bg
  );
  const borderColors = labels.map(
    (_, idx) => modernColors[idx % modernColors.length].border
  );

  if (stockPieChart) stockPieChart.destroy();
  stockPieChart = new Chart(ctx, {
    type: "doughnut", // Changed from pie to doughnut
    data: {
      labels,
      datasets: [
        {
          data: quantities,
          backgroundColor: backgroundColors,
          borderColor: "#ffffff",
          borderWidth: 4,
          hoverOffset: 12,
          hoverBorderWidth: 5,
          borderRadius: 8,
          spacing: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: "65%", // Creates donut effect
      plugins: {
        centerText: {
          enabled: true, // Ensure plugin is active
        },
        legend: {
          display: false,
        },
        tooltip: {
          backgroundColor: "#ffffff",
          titleColor: "#1e293b",
          bodyColor: "#475569",
          borderColor: "#0072bc",
          borderWidth: 2,
          padding: 14,
          displayColors: true,
          cornerRadius: 10,
          bodyFont: { weight: "600", size: 14 },
          titleFont: { weight: "700", size: 15 },
          caretSize: 8,
          caretPadding: 10,
          callbacks: {
            label: function (context) {
              const label = context.label || "";
              const value = context.parsed || 0;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((value / total) * 100).toFixed(1);
              return `${label}: ${value} units (${percentage}%)`;
            },
          },
        },
      },
      animation: {
        animateRotate: true,
        animateScale: true,
        duration: 1500,
        easing: "easeInOutQuart",
      },
      interaction: {
        mode: "nearest",
        intersect: true,
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
    const color = modernColors[idx % modernColors.length];
    const quantity = quantities[idx];
    legendHTML += `<span class="custom-pie-legend-item">
      <span class="legend-color-box" style="background:${color.bg}; border-color:${color.border}"></span>
      ${label} <span style="color: #64748b; font-weight: 500;">(${quantity})</span>
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

  const canvas = document.getElementById("salesLineChart");
  if (!canvas) {
    console.error("salesLineChart canvas not found");
    return;
  }
  const ctx = canvas.getContext("2d");

  // Get canvas height for proper gradient
  const canvasHeight = canvas.height || 360;

  // Create gradient for line
  const gradientStroke = ctx.createLinearGradient(0, 0, 0, canvasHeight);
  gradientStroke.addColorStop(0, "#0072bc");
  gradientStroke.addColorStop(1, "#3b82f6");

  // Create gradient fill
  const gradientFill = ctx.createLinearGradient(0, 0, 0, canvasHeight);
  gradientFill.addColorStop(0, "rgba(0, 114, 188, 0.25)");
  gradientFill.addColorStop(0.5, "rgba(59, 130, 246, 0.12)");
  gradientFill.addColorStop(1, "rgba(59, 130, 246, 0.02)");

  // Get current filter values
  const category = document.getElementById("salesCategoryFilter").value;
  const subcategory = document.getElementById("salesSubcategoryFilter").value;

  if (salesLineChart) salesLineChart.destroy();
  salesLineChart = new Chart(ctx, {
    type: "line",
    data: {
      labels,
      datasets: [
        {
          data: sales,
          borderColor: gradientStroke,
          backgroundColor: gradientFill,
          fill: true,
          pointBackgroundColor: "#ffffff",
          pointBorderColor: "#0072bc",
          pointBorderWidth: 3,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointHoverBackgroundColor: "#fdf005",
          pointHoverBorderColor: "#0072bc",
          pointHoverBorderWidth: 3,
          pointHitRadius: 20,
          borderWidth: 3,
          tension: 0.4, // Smooth curves
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: "nearest",
        intersect: false,
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          backgroundColor: "#ffffff",
          borderColor: "#0072bc",
          borderWidth: 2,
          titleColor: "#1e293b",
          bodyColor: "#475569",
          padding: 16,
          displayColors: false,
          bodyFont: { weight: "600", size: 14 },
          titleFont: { weight: "700", size: 15 },
          cornerRadius: 12,
          caretSize: 8,
          caretPadding: 12,
          position: "nearest",
          yAlign: "bottom",
          xAlign: "center",
          callbacks: {
            title: function (context) {
              return context[0].label;
            },
            label: function (context) {
              const idx = context.dataIndex;
              const point = filteredData[idx];
              let label = `Sales: ${point.total_sales} units`;
              if (!category && point.category) {
                label += `\nCategory: ${point.category}`;
              } else if (subcategory && point.subcategory) {
                label += `\nSubcategory: ${point.subcategory}`;
              }
              return label;
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            color: "rgba(0, 114, 188, 0.08)",
            drawBorder: false,
            lineWidth: 1,
          },
          ticks: {
            color: "#64748b",
            font: {
              size: 12,
              weight: "500",
            },
            padding: 10,
          },
        },
        x: {
          grid: {
            color: "rgba(0, 114, 188, 0.05)",
            drawBorder: false,
            lineWidth: 1,
          },
          ticks: {
            color: "#64748b",
            font: {
              size: 12,
              weight: "500",
            },
            padding: 10,
            maxRotation: 45,
            minRotation: 45,
            autoSkip: true,
            autoSkipPadding: 15,
            maxTicksLimit:
              window.innerWidth < 1200 ? 6 : window.innerWidth < 1400 ? 8 : 10,
            callback: function (value, index, ticks) {
              const label = this.getLabelForValue(value);
              // For smaller screens, show shorter date format
              if (window.innerWidth < 1200) {
                // Format: MM-DD
                const parts = label.split("-");
                if (parts.length === 3) {
                  return `${parts[1]}-${parts[2]}`;
                }
              }
              return label;
            },
          },
        },
      },
      layout: {
        padding: {
          left: 10,
          right: 10,
          top: 10,
          bottom: 10,
        },
      },
      elements: {
        line: {
          borderCapStyle: "round",
          borderJoinStyle: "round",
        },
      },
      animation: {
        duration: 1500,
        easing: "easeInOutQuart",
      },
    },
  });
}

async function populateSalesCategoryDropdown() {
  const select = document.getElementById("salesCategoryFilter");
  try {
    const res = await fetch(
      "../PAMO Inventory backend/api_categories_list.php"
    );
    if (!res.ok) throw new Error("Failed to fetch categories");
    const categories = await res.json();
    select.innerHTML = '<option value="">All</option>';
    categories.forEach((category) => {
      const opt = document.createElement("option");
      opt.value = category.id;
      opt.textContent = category.name;
      opt.dataset.hasSubcategories = category.has_subcategories;
      select.appendChild(opt);
    });
  } catch (e) {
    console.error("Error loading sales categories:", e);
  }
}

async function populateSalesSubcategoryDropdown(categoryId) {
  const select = document.getElementById("salesSubcategoryFilter");
  if (!categoryId) {
    select.innerHTML = '<option value="">All</option>';
    return;
  }

  try {
    const res = await fetch(
      `../PAMO Inventory backend/api_subcategories_list.php?category_id=${categoryId}`
    );
    if (!res.ok) throw new Error("Failed to fetch subcategories");
    const subcategories = await res.json();
    select.innerHTML = '<option value="">All</option>';
    subcategories.forEach((subcategory) => {
      const opt = document.createElement("option");
      opt.value = subcategory.id;
      opt.textContent = subcategory.name;
      select.appendChild(opt);
    });
  } catch (e) {
    console.error("Error loading sales subcategories:", e);
  }
}

// --- SALES ANALYTICS ---
async function updateSalesAnalytics() {
  const category = document.getElementById("salesCategoryFilter").value;
  const subcategory = document.getElementById("salesSubcategoryFilter").value;
  const period = document.getElementById("salesPeriodFilter").value;
  const salesData = await fetchSalesData(category, subcategory, period);
  renderSalesLineChart(salesData);
}

// --- INVENTORY ANALYTICS (always overview) ---
async function updateInventoryAnalytics() {
  const stockData = await fetchStockData();
  renderStockPieChart(stockData);
}

function handleSalesCategoryChange() {
  const categorySelect = document.getElementById("salesCategoryFilter");
  const subcategoryLabel = document.getElementById("subcategoryLabel");
  const subcategorySelect = document.getElementById("salesSubcategoryFilter");

  const selectedOption = categorySelect.options[categorySelect.selectedIndex];
  const hasSubcategories = selectedOption?.dataset.hasSubcategories === "1";

  if (categorySelect.value && hasSubcategories) {
    // Show subcategory dropdown and populate it
    subcategoryLabel.style.display = "block";
    populateSalesSubcategoryDropdown(categorySelect.value);
  } else {
    // Hide subcategory dropdown and clear it
    subcategoryLabel.style.display = "none";
    subcategorySelect.innerHTML = '<option value="">All</option>';
  }

  updateSalesAnalytics();
}

// Real-time Pending Orders Count Update
let lastPendingCount = null;

async function updatePendingOrdersCount() {
  try {
    const formData = new FormData();
    formData.append("action", "get_pending_count");

    const response = await fetch("../Includes/order_operations.php", {
      method: "POST",
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      const pendingCountElement = document.getElementById(
        "pending-orders-count"
      );
      if (pendingCountElement) {
        const newCount = data.pending_count;

        // Only update if count has changed
        if (lastPendingCount !== newCount) {
          pendingCountElement.textContent = newCount.toLocaleString();

          // Add flash animation on count change
          if (lastPendingCount !== null) {
            pendingCountElement.style.animation = "countFlash 0.6s ease-in-out";
            setTimeout(() => (pendingCountElement.style.animation = ""), 600);
          }

          lastPendingCount = newCount;
        }
      }
    }
  } catch (error) {
    console.error("Error updating pending orders count:", error);
  }
}

window.addEventListener("DOMContentLoaded", async () => {
  await populateSalesCategoryDropdown();
  handleSalesCategoryChange();
  document
    .getElementById("salesCategoryFilter")
    .addEventListener("change", handleSalesCategoryChange);
  document
    .getElementById("salesSubcategoryFilter")
    .addEventListener("change", updateSalesAnalytics);
  document
    .getElementById("salesPeriodFilter")
    .addEventListener("change", updateSalesAnalytics);
  updateSalesAnalytics();
  updateInventoryAnalytics();

  // Initialize real-time pending orders count
  updatePendingOrdersCount();

  // Update pending orders count every 30 seconds (same interval as sidebar)
  setInterval(updatePendingOrdersCount, 30000);
});

// Handle window resize to update chart responsiveness
let resizeTimer;
window.addEventListener("resize", () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => {
    // Re-render the sales chart with updated screen size settings
    if (salesLineChart) {
      updateSalesAnalytics();
    }
  }, 250);
});
