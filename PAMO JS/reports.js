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
  fetch("../PAMO PAGES/includes/fetch_reports.php?" + query)
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
  document.getElementById("inventoryReport").style.display = "none";
  document.getElementById("salesReport").style.display = "none";
  document.getElementById("auditReport").style.display = "none";
  document.getElementById(reportType + "Report").style.display = "block";
  const totalDisplay = document.querySelector(".total-amount-display");
  if (totalDisplay) {
    totalDisplay.style.display = "none";
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

// On initial load, load the current report via AJAX
window.addEventListener("DOMContentLoaded", function () {
  const reportType = document.getElementById("reportType").value;
  ajaxLoadReport(reportType, 1);
});

function exportToExcel() {
  const reportType = document.getElementById("reportType").value;
  // Collect current filters
  const filters = getCurrentReportFilters();
  const params = new URLSearchParams(filters).toString();
  let exportUrl = "";
  if (reportType === "sales") {
    exportUrl = "../PAMO PAGES/includes/export_sales_report.php?" + params;
  } else if (reportType === "inventory") {
    exportUrl = "../PAMO PAGES/includes/export_inventory_report.php?" + params;
  } else if (reportType === "audit") {
    exportUrl = "../PAMO PAGES/includes/export_audit_report.php?" + params;
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
