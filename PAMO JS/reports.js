// Initialize table when the document is loaded
document.addEventListener("DOMContentLoaded", function () {
  // Initialize date inputs
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");

  // Disable end date until start date is selected
  endDate.disabled = true;

  startDate.addEventListener("change", function () {
    endDate.disabled = false;
    endDate.min = this.value;
    if (endDate.value && endDate.value < this.value) {
      endDate.value = "";
    }
  });

  // Add search functionality
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      const type = document.getElementById("reportType")?.value || "inventory";
      ajaxLoadReport(type, 1);
    });
  }

  // Add event listener for Apply Filters button
  const applyFiltersBtn = document.getElementById("applyFiltersBtn");
  if (applyFiltersBtn) {
    applyFiltersBtn.addEventListener("click", function () {
      const type = document.getElementById("reportType")?.value || "inventory";
      ajaxLoadReport(type, 1);
    });
  }
});

function loadTableData() {
  const tbody = document.querySelector("#reportTable tbody");
  tbody.innerHTML = "";

  reportData.forEach((row) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
            <td>${formatDate(row.date)}</td>
            <td>${row.item}</td>
            <td>${row.category}</td>
            <td>${row.quantity}</td>
            <td>$${row.amount.toFixed(2)}</td>
            <td><span class="status-cell status-${
              row.status
            }">${capitalizeFirst(row.status)}</span></td>
        `;
    tbody.appendChild(tr);
  });
}

// Utility to collect all current filter/search values
function getCurrentReportFilters() {
  return {
    search: document.getElementById("searchInput")?.value || "",
    startDate: document.getElementById("startDate")?.value || "",
    endDate: document.getElementById("endDate")?.value || "",
    type: document.getElementById("reportType")?.value || "inventory",
    stockStatus: document.getElementById("stockStatus")?.value || "",
  };
}

// AJAX-based pagination for reports (now always sends all filters)
function ajaxLoadReport(type, page = 1, extraParams = {}) {
  const reportDiv = document.getElementById(type + "Report");
  if (!reportDiv) return;
  showLoading();
  // Always collect all filters
  const filters = getCurrentReportFilters();
  const params = { ...filters, ...extraParams, type, page };
  const query = new URLSearchParams(params).toString();
  fetch("../PAMO_PAGES/includes/fetch_reports.php?" + query)
    .then((res) => res.json())
    .then((data) => {
      // Insert table HTML
      if (reportDiv) reportDiv.innerHTML = data.table;
      // Remove any existing pagination after this reportDiv
      let next = reportDiv ? reportDiv.nextElementSibling : null;
      if (next && next.classList.contains("pagination")) {
        next.remove();
      }
      // Insert pagination after the reportDiv (outside the table)
      if (data.pagination && reportDiv) {
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = data.pagination;
        reportDiv.parentNode.insertBefore(
          tempDiv.firstElementChild,
          reportDiv.nextSibling
        );
      }
      hideLoading();
      if (type === "sales") {
        // Show the total-amount display and set the value from backend
        const totalDisplay = document.querySelector(".total-amount-display");
        if (totalDisplay) {
          totalDisplay.style.display = "block";
          const totalAmountSpan = document.getElementById("totalSalesAmount");
          if (totalAmountSpan) {
            totalAmountSpan.textContent =
              "₱" +
              Number(data.grand_total || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              });
          }
        }
      }
    })
    .catch((err) => {
      hideLoading();
      showNotification("Error loading report: " + err.message);
    });
}

// Search input triggers paginated search (reset to page 1)
const searchInput = document.getElementById("searchInput");
if (searchInput) {
  searchInput.addEventListener("input", function () {
    const type = document.getElementById("reportType")?.value || "inventory";
    ajaxLoadReport(type, 1);
  });
}

// Date filter changes (manual input)
const startDate = document.getElementById("startDate");
if (startDate) {
  startDate.addEventListener("change", function () {
    const type = document.getElementById("reportType")?.value || "inventory";
    ajaxLoadReport(type, 1);
  });
}
const endDate = document.getElementById("endDate");
if (endDate) {
  endDate.addEventListener("change", function () {
    const type = document.getElementById("reportType")?.value || "inventory";
    ajaxLoadReport(type, 1);
  });
}

// Daily filter
function applyDailyFilter() {
  const dailyButton = document.querySelector(".daily-filter-btn");
  const monthlyButton = document.querySelector(".monthly-filter-btn");
  if (dailyButton && dailyButton.classList.contains("active")) {
    dailyButton.classList.remove("active");
    clearDates();
    return;
  }
  const today = new Date();
  const formattedDate = today.toLocaleDateString("en-CA"); // 'YYYY-MM-DD'
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");
  if (startDate) startDate.value = formattedDate;
  if (endDate) endDate.value = formattedDate;
  if (dailyButton) dailyButton.classList.add("active");
  if (monthlyButton) monthlyButton.classList.remove("active");
  const type = document.getElementById("reportType")?.value || "inventory";
  ajaxLoadReport(type, 1);
}

// Monthly filter
function applyMonthlyFilter() {
  const dailyButton = document.querySelector(".daily-filter-btn");
  const monthlyButton = document.querySelector(".monthly-filter-btn");
  if (monthlyButton && monthlyButton.classList.contains("active")) {
    monthlyButton.classList.remove("active");
    clearDates();
    return;
  }
  const today = new Date();
  const currentYear = today.getFullYear();
  const currentMonth = today.getMonth();
  const firstDayOfMonth = new Date(currentYear, currentMonth, 1);
  const lastDayOfMonth = new Date(currentYear, currentMonth + 1, 0);
  const formatDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");
  if (startDate) startDate.value = formatDate(firstDayOfMonth);
  if (endDate) endDate.value = formatDate(lastDayOfMonth);
  if (monthlyButton) monthlyButton.classList.add("active");
  if (dailyButton) dailyButton.classList.remove("active");
  const type = document.getElementById("reportType")?.value || "inventory";
  ajaxLoadReport(type, 1);
}

// Clear date filters and reset pagination
function clearDates() {
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");
  if (startDate) startDate.value = "";
  if (endDate) {
    endDate.value = "";
    endDate.disabled = true;
  }
  const dailyBtn = document.querySelector(".daily-filter-btn");
  const monthlyBtn = document.querySelector(".monthly-filter-btn");
  if (dailyBtn) dailyBtn.classList.remove("active");
  if (monthlyBtn) monthlyBtn.classList.remove("active");
  const totalDisplay = document.querySelector(".total-amount-display");
  if (totalDisplay) totalDisplay.style.display = "none";
  const totalSalesAmount = document.getElementById("totalSalesAmount");
  if (totalSalesAmount) totalSalesAmount.textContent = "₱0.00";
  const type = document.getElementById("reportType")?.value || "inventory";
  ajaxLoadReport(type, 1);
}

// Report type change
function changeReportType() {
  const reportType = document.getElementById("reportType").value;

  // For monthly report, do a full page reload to get server-side rendered content
  if (reportType === "monthly") {
    window.location.href = "?type=monthly&page=1";
    return;
  }

  document.getElementById("inventoryReport").style.display = "none";
  document.getElementById("salesReport").style.display = "none";
  document.getElementById("auditReport").style.display = "none";
  document.getElementById(reportType + "Report").style.display = "block";
  const totalDisplay = document.querySelector(".total-amount-display");
  if (totalDisplay) {
    totalDisplay.style.display = "none";
  }

  // Show/hide stock status filter based on report type
  const stockStatusFilter = document.getElementById("stockStatusFilter");
  if (stockStatusFilter) {
    stockStatusFilter.style.display =
      reportType === "inventory" ? "block" : "none";
    // Reset stock status when switching away from inventory
    if (reportType !== "inventory") {
      const stockStatusSelect = document.getElementById("stockStatus");
      if (stockStatusSelect) {
        stockStatusSelect.value = "";
      }
    }
  }

  ajaxLoadReport(reportType, 1);
}

// Pagination click handler (always sends current filters)
document.addEventListener("click", function (e) {
  if (e.target.closest(".pagination a")) {
    const link = e.target.closest(".pagination a");
    if (
      link.getAttribute("href") &&
      link.getAttribute("href").indexOf("page=") !== -1
    ) {
      e.preventDefault();
      const url = new URL(link.href, window.location.origin);
      const type =
        url.searchParams.get("type") ||
        document.getElementById("reportType").value;
      const page = url.searchParams.get("page") || 1;
      ajaxLoadReport(type, page);
    }
  }
});

// Apply stock status filter
function applyStockStatusFilter() {
  const type = document.getElementById("reportType")?.value || "inventory";
  if (type === "inventory") {
    ajaxLoadReport(type, 1);
  }
}

// On initial load, load the current report via AJAX
window.addEventListener("DOMContentLoaded", function () {
  const reportType = document.getElementById("reportType").value;
  // Skip AJAX loading for monthly report (it's server-side rendered)
  if (reportType !== "monthly") {
    ajaxLoadReport(reportType, 1);
  }
});

function exportToExcel() {
  const reportType = document.getElementById("reportType").value;
  // Collect current filters
  const filters = getCurrentReportFilters();
  const params = new URLSearchParams(filters).toString();
  let exportUrl = "";
  if (reportType === "sales") {
    exportUrl = "../PAMO_PAGES/includes/export_sales_report.php?" + params;
  } else if (reportType === "audit") {
    exportUrl = "../PAMO_PAGES/includes/export_audit_report.php?" + params;
  } else {
    alert("Excel export is not available for this report type.");
    return;
  }
  window.open(exportUrl, "_blank");
}

function changePage(direction) {
  // Implement pagination logic here
  const currentPage = document.getElementById("currentPage");
  const pageNum = parseInt(currentPage.textContent.split(" ")[1]);
  const totalPages = parseInt(currentPage.textContent.split(" ")[3]);

  if (direction === "next" && pageNum < totalPages) {
    currentPage.textContent = `Page ${pageNum + 1} of ${totalPages}`;
  } else if (direction === "prev" && pageNum > 1) {
    currentPage.textContent = `Page ${pageNum - 1} of ${totalPages}`;
  }
}

// Utility functions
function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString();
}

function capitalizeFirst(string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
}

function showLoading() {
  const tables = document.querySelectorAll(".report-table");
  tables.forEach((table) => {
    table.style.opacity = "0.5";
  });
}

function hideLoading() {
  const tables = document.querySelectorAll(".report-table");
  tables.forEach((table) => {
    table.style.opacity = "1";
  });
}

function showNotification(message) {
  const notification = document.createElement("div");
  notification.className = "notification";
  notification.textContent = message;
  document.body.appendChild(notification);

  notification.style.display = "block";
  setTimeout(() => {
    notification.style.display = "none";
    notification.remove();
  }, 3000);
}

function logout() {
  showLogoutConfirmation();
}

// Monthly Inventory Report Functions
function loadMonthlyReport(year, month) {
  const monthlyReportDiv = document.getElementById("monthlyReport");
  if (!monthlyReportDiv) return;

  // Show loading indicator
  monthlyReportDiv.innerHTML =
    '<div style="text-align: center; padding: 40px;"><div class="loading-spinner"></div><p>Loading monthly report...</p></div>';

  // Fetch monthly report data
  fetch(
    `../PAMO_DASHBOARD_BACKEND/monthly_reports_api.php?action=get_monthly_inventory&year=${year}&month=${month}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayMonthlyInventoryTable(data.data, data.pagination);
      } else {
        monthlyReportDiv.innerHTML = `<div style="text-align: center; padding: 40px; color: #dc3545;"><p>${
          data.message || "Failed to load monthly report"
        }</p></div>`;
      }
    })
    .catch((error) => {
      console.error("Error loading monthly report:", error);
      monthlyReportDiv.innerHTML =
        '<div style="text-align: center; padding: 40px; color: #dc3545;"><p>Error loading monthly report</p></div>';
    });
}

function displayMonthlyInventoryTable(data, pagination) {
  if (!data || data.length === 0) {
    return '<div style="text-align: center; padding: 40px;"><p>No inventory data available for this period.</p></div>';
  }

  let html = `
    <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
      <table style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="background: #f8f9fa;">
            <th style="padding: 15px 12px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Item Code</th>
            <th style="padding: 15px 12px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Item Name</th>
            <th style="padding: 15px 12px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Category</th>
            <th style="padding: 15px 12px; text-align: center; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Beginning</th>
            <th style="padding: 15px 12px; text-align: center; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Deliveries</th>
            <th style="padding: 15px 12px; text-align: center; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Sales</th>
            <th style="padding: 15px 12px; text-align: center; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Ending</th>
            <th style="padding: 15px 12px; text-align: right; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6;">Value</th>
          </tr>
        </thead>
        <tbody>
  `;

  let currentCategory = "";
  data.forEach((item) => {
    if (currentCategory !== item.category) {
      currentCategory = item.category;
      html += `
        <tr style="background: #e9ecef;">
          <td colspan="8" style="padding: 10px 12px; font-weight: 600; color: #495057;">
            ${item.category}
          </td>
        </tr>
      `;
    }

    html += `
      <tr style="border-bottom: 1px solid #dee2e6;">
        <td style="padding: 12px; color: #495057;">${item.item_code}</td>
        <td style="padding: 12px; color: #495057;">${item.item_name}</td>
        <td style="padding: 12px; color: #6c757d; font-size: 14px;"></td>
        <td style="padding: 12px; text-align: center; color: #495057;">${Number(
          item.beginning_quantity
        ).toLocaleString()}</td>
        <td style="padding: 12px; text-align: center; color: #28a745;">${Number(
          item.new_delivery_total
        ).toLocaleString()}</td>
        <td style="padding: 12px; text-align: center; color: #dc3545;">${Number(
          item.sales_total
        ).toLocaleString()}</td>
        <td style="padding: 12px; text-align: center; font-weight: 600; color: ${
          item.ending_quantity > 0 ? "#28a745" : "#dc3545"
        };">
          ${Number(item.ending_quantity).toLocaleString()}
        </td>
        <td style="padding: 12px; text-align: right; color: #495057;">₱${Number(
          item.ending_value
        ).toLocaleString("en-US", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}</td>
      </tr>
    `;
  });

  html += `
        </tbody>
      </table>
    </div>
  `;

  // Add pagination if needed
  if (pagination && pagination.total_pages > 1) {
    html += generatePagination(pagination);
  }

  return html;
}

function generatePagination(pagination) {
  let html = `
    <div style="display: flex; justify-content: between; align-items: center; padding: 20px; background: white; border-top: 1px solid #dee2e6;">
      <div>
        Showing ${
          (pagination.current_page - 1) * pagination.limit + 1
        } to ${Math.min(
    pagination.current_page * pagination.limit,
    pagination.total_records
  )} of ${pagination.total_records} entries
      </div>
      <div style="display: flex; gap: 5px;">
  `;

  // Previous button
  if (pagination.current_page > 1) {
    html += `<button onclick="loadMonthlyReportPage(${
      pagination.current_page - 1
    })" style="padding: 8px 12px; border: 1px solid #dee2e6; background: white; color: #495057; border-radius: 4px; cursor: pointer;">Previous</button>`;
  }

  // Page numbers
  for (
    let i = Math.max(1, pagination.current_page - 2);
    i <= Math.min(pagination.total_pages, pagination.current_page + 2);
    i++
  ) {
    const isActive = i === pagination.current_page;
    html += `<button onclick="loadMonthlyReportPage(${i})" style="padding: 8px 12px; border: 1px solid #dee2e6; background: ${
      isActive ? "#007bff" : "white"
    }; color: ${
      isActive ? "white" : "#495057"
    }; border-radius: 4px; cursor: pointer;">${i}</button>`;
  }

  // Next button
  if (pagination.current_page < pagination.total_pages) {
    html += `<button onclick="loadMonthlyReportPage(${
      pagination.current_page + 1
    })" style="padding: 8px 12px; border: 1px solid #dee2e6; background: white; color: #495057; border-radius: 4px; cursor: pointer;">Next</button>`;
  }

  html += `
      </div>
    </div>
  `;

  return html;
}

function loadMonthlyReportPage(page) {
  const monthSelector = document.getElementById("monthSelector");
  const yearSelector = document.getElementById("yearSelector");

  if (monthSelector && yearSelector) {
    const month = monthSelector.value;
    const year = yearSelector.value;

    // Update URL and reload
    window.location.href = `?type=monthly&month=${month}&year=${year}&page=${page}`;
  }
}

// Export monthly report to Excel
function exportMonthlyReport() {
  const monthSelector = document.getElementById("monthSelector");
  const yearSelector = document.getElementById("yearSelector");

  if (monthSelector && yearSelector) {
    const month = monthSelector.value;
    const year = yearSelector.value;

    // Open the export URL directly to download Excel file
    const exportUrl = `../PAMO_PAGES/includes/export_monthly_report.php?year=${year}&month=${month}`;
    window.open(exportUrl, "_blank");
  }
}
