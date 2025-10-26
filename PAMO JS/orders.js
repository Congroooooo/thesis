// REMOVE or comment out the following lines to prevent JS errors:
// document.addEventListener("DOMContentLoaded", initializeOrders);

// Create order card
function createOrderCard(order) {
  const div = document.createElement("div");
  div.className = "order-card";

  div.innerHTML = `
        <div class="order-header">
            <div class="student-info">
                <img src="${
                  order.studentPhoto
                }" alt="Student Photo" class="student-photo">
                <div>
                    <h3>${order.studentName}</h3>
                    <p>Student ID: ${order.studentId}</p>
                    <p>Ordered: ${formatDate(order.orderDate)}</p>
                </div>
            </div>
            <span class="order-status status-${order.status}">${capitalizeFirst(
    order.status
  )}</span>
        </div>
        <div class="order-items-list">
            ${order.items
              .map(
                (item) => `
                <div class="item-card">
                    <span>${item.name} x${item.quantity}</span>
                    <span>$${item.price.toFixed(2)}</span>
                </div>
            `
              )
              .join("")}
        </div>
        <div class="total-section">
            <span>Total Amount:</span>
            <span>$${order.totalAmount.toFixed(2)}</span>
        </div>
        ${
          order.status === "pending"
            ? `
            <div class="action-buttons">
                <button class="btn-reject" onclick="openReviewModal(${order.id}, 'reject')">Reject</button>
                <button class="btn-approve" onclick="openReviewModal(${order.id}, 'approve')">Approve</button>
            </div>
        `
            : ""
        }
    `;
  return div;
}

// Tab switching
document.querySelectorAll(".tab").forEach((tab) => {
  tab.addEventListener("click", function () {
    document
      .querySelectorAll(".tab")
      .forEach((t) => t.classList.remove("active"));
    this.classList.add("active");
    // initializeOrders();
  });
});

// Modal functions
function openReviewModal(orderId, action) {
  const order = orders.find((o) => o.id === orderId);
  if (!order) return;

  const modal = document.getElementById("reviewModal");
  document.getElementById("studentName").textContent = order.studentName;
  document.getElementById(
    "studentId"
  ).textContent = `Student ID: ${order.studentId}`;
  document.getElementById("orderDate").textContent = `Ordered: ${formatDate(
    order.orderDate
  )}`;

  const itemsList = document.getElementById("itemsList");
  itemsList.innerHTML = order.items
    .map(
      (item) => `
        <div class="item-card">
            <span>${item.name} x${item.quantity}</span>
            <span>$${item.price.toFixed(2)}</span>
        </div>
    `
    )
    .join("");

  document.getElementById(
    "totalAmount"
  ).textContent = `$${order.totalAmount.toFixed(2)}`;

  modal.style.display = "block";
}

function approveOrder() {
  const pickupDate = document.getElementById("pickupDate").value;
  if (!pickupDate) {
    alert("Please set a pickup date");
    return;
  }

  // Here you would typically send this to your backend
  showNotification("Order approved! Student will be notified.");
  closeModal();
  // initializeOrders();
}

function rejectOrder() {
  const notes = document.getElementById("notes").value;
  // Here you would typically send this to your backend
  showNotification("Order rejected. Student will be notified.");
  closeModal();
  // initializeOrders();
}

function closeModal() {
  document.getElementById("reviewModal").style.display = "none";
}

// Utility functions
function formatDate(dateString) {
  return new Date(dateString).toLocaleString();
}

function capitalizeFirst(string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
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

// Close modal when clicking outside
window.onclick = function (event) {
  const modal = document.getElementById("reviewModal");
  if (event.target == modal) {
    closeModal();
  }
};

function logout() {
  showLogoutConfirmation();
}

// --- Unified Order Receipt Modal Logic ---

let currentOrderId = null;
let currentRejectionOrderId = null;

function showOrderReceipt(orderId) {
  currentOrderId = orderId;
  const order = (window.ORDERS || []).find(
    (o) => String(o.id) === String(orderId)
  );
  if (!order) {
    alert("[DEBUG] Order not found for orderId: " + orderId);
    return;
  }
  const orderItems = JSON.parse(order.items);
  const preparedByName =
    window.PAMO_USER && window.PAMO_USER.name ? window.PAMO_USER.name : "";
  const studentName = `${order.first_name} ${order.last_name}`;
  const studentIdNumber = order.id_number || "";
  const transactionNumber = order.order_number || "";
  const cashierName = order.cashier_name || "";
  const totalAmount = orderItems.reduce(
    (sum, item) => sum + item.price * item.quantity,
    0
  );

  // Determine if customer is an employee
  const roleCategory = (order.role_category || "").toUpperCase();
  const isEmployee = roleCategory === "EMPLOYEE";
  const customerCopyLabel = isEmployee ? "EMPLOYEE COPY" : "STUDENT COPY";
  const customerNameLabel = isEmployee ? "Employee Name:" : "Student Name:";
  const customerIdLabel = isEmployee ? "Employee No.:" : "Student No.:";

  function renderReceipt(copyLabel) {
    const dataRows = orderItems
      .map((item, i) => {
        let cleanName = item.item_name.replace(/\s*\([^)]*\)/, "");
        cleanName = cleanName.replace(/\s*-\s*[^-]*$/, "");
        return `<tr>
        <td>${cleanName} ${item.size || ""}</td>
        <td>${item.category || ""}</td>
        <td style="text-align:center;">${item.quantity}</td>
        <td style="text-align:right;">${parseFloat(item.price).toFixed(2)}</td>
        <td style="text-align:right;">${parseFloat(
          item.price * item.quantity
        ).toFixed(2)}</td>
        ${
          i === 0
            ? `<td class="signature-col" rowspan="${orderItems.length}">
          <table class="signature-table">
            <tr><td class="sig-label">Prepared by:</td></tr>
            <tr><td class="sig-box">${preparedByName}</td></tr>
            <tr><td class="sig-label">OR Issued by:</td></tr>
            <tr><td class="sig-box">${cashierName}<br><span style="font-weight:bold;">Cashier</span></td></tr>
            <tr><td class="sig-label">Released by & date:</td></tr>
            <tr><td class="sig-box"></td></tr>
            <tr><td class="sig-label">RECEIVED BY:</td></tr>
            <tr><td class="sig-box" style="height:40px;vertical-align:bottom;">
              <div style="height:24px;"></div>
              <div class="sig-name" style="font-weight:bold;text-decoration:underline;text-align:center;">${studentName}</div>
            </td></tr>
          </table>
        </td>`
            : ""
        }
      </tr>`;
      })
      .join("");
    const footerRow = `
      <tr>
        <td colspan="5" class="receipt-footer-cell">
          <b>ALL ITEMS ARE RECEIVED IN GOOD CONDITION</b><br>
          <span>(Exchange is allowed only within 3 days from the invoice date. Strictly no refund)</span>
        </td>
        <td class="receipt-footer-total">
          TOTAL AMOUNT: <span>${parseFloat(totalAmount).toFixed(2)}</span>
        </td>
      </tr>
    `;
    return `
      <div class="receipt-header-flex">
        <div class="receipt-header-logo"><img src="../Images/STI-LOGO.png" alt="STI Logo" /></div>
        <div class="receipt-header-center">
          <div class="sti-lucena">STI LUCENA</div>
          <div class="sales-issuance-slip">SALES ISSUANCE SLIP</div>
        </div>
        <div class="receipt-header-copy">${copyLabel}</div>
      </div>
      <div class="receipt-section">
        <table class="receipt-header-table">
          <tr>
            <td><b>${customerNameLabel}</b></td>
            <td>${studentName}</td>
            <td><b>${customerIdLabel}</b></td>
            <td>${studentIdNumber}</td>
            <td><b>DATE:</b></td>
            <td>${new Date(order.created_at).toLocaleDateString()}</td>
          </tr>
          <tr>
            <td><b>Issuance Slip No.:</b></td>
            <td>${transactionNumber}</td>
            <td><b>Invoice No.:</b></td>
            <td></td>
            <td colspan="2"></td>
          </tr>
        </table>
        <table class="receipt-main-table">
          <thead>
            <tr>
              <th>Item Description</th>
              <th>Item Type</th>
              <th>Qty</th>
              <th>SRP</th>
              <th>Amount</th>
              <th>Prepared by:</th>
            </tr>
          </thead>
          <tbody>
            ${dataRows}
            ${footerRow}
          </tbody>
        </table>
      </div>
    `;
  }
  const html = `
    <div class="receipt-a4">
      <div class="receipt-half">${renderReceipt("PAMO COPY")}</div>
      <div class="receipt-divider"></div>
      <div class="receipt-half">${renderReceipt(customerCopyLabel)}</div>
    </div>
  `;
  const receiptContainer = document.getElementById("orderReceiptBody");
  receiptContainer.innerHTML = ""; // Prevent duplication
  receiptContainer.innerHTML = html;
  document.getElementById("orderReceiptModal").style.display = "block";
}

function printOrderReceipt() {
  const receiptHtml = document.getElementById("orderReceiptBody").innerHTML;
  const printWindow = window.open("", "_blank", "width=900,height=1200");
  printWindow.document.write(`
    <html>
      <head>
        <title>Print Receipt</title>
        <link rel="stylesheet" href="../PAMO CSS/orders.css">
        <style>
          @media print {
            @page { size: A4; margin: 0; }
            html, body { width: 210mm; height: 297mm; margin: 0 !important; padding: 0 !important; background: #fff !important; }
            .receipt-a4 { width: 200mm !important; height: 287mm !important; margin: 0 !important; padding: 0 !important; }
            body * { visibility: visible !important; }
          }
        </style>
      </head>
      <body onload="window.print(); setTimeout(() => window.close(), 500);">
        <div id="printArea">${receiptHtml}</div>
      </body>
    </html>
  `);
  printWindow.document.close();
  setTimeout(function () {
    var modal = document.getElementById("orderReceiptModal");
    if (modal) {
      modal.style.display = "none";
      document.getElementById("orderReceiptBody").innerHTML = "";
    }
    // Reload the page to update the order status and UI
    setTimeout(function () {
      location.reload();
    }, 300);
  }, 500);
}

function closeOrderReceiptModal() {
  document.getElementById("orderReceiptModal").style.display = "none";
}

function confirmAndCompleteOrder() {
  if (currentOrderId) {
    updateOrderStatus(currentOrderId, "completed");
    closeOrderReceiptModal();
  }
}

document.addEventListener("click", function (e) {
  const btn = e.target.closest(".complete-btn");
  if (btn) {
    e.preventDefault();
    let orderId = btn.getAttribute("data-order-id");
    if (orderId) {
      updateOrderStatus(orderId, "completed", function () {
        showOrderReceipt(orderId);
      });
    } else {
      console.warn("[DEBUG] No orderId found on button");
    }
  }
});

// Update updateOrderStatus to accept a callback
function updateOrderStatus(orderId, status, callback, rejectionReason = null) {
  const data = new URLSearchParams();
  data.append("order_id", orderId);
  data.append("status", status);
  if (rejectionReason) {
    data.append("rejection_reason", rejectionReason);
  }

  fetch("update_order_status.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: data.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Update notification badge immediately after status change
        if (typeof updatePendingOrdersBadge === "function") {
          setTimeout(updatePendingOrdersBadge, 500); // Small delay to ensure DB is updated
        }

        if (typeof callback === "function") {
          callback();
        } else {
          location.reload();
        }
      } else {
        alert("Error updating order status: " + data.message);
        console.error("Error details:", data.debug);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error updating order status. Check console for details.");
    });
}

function showRejectionModal(orderId) {
  currentRejectionOrderId = orderId;
  document.getElementById("rejectionModal").style.display = "block";
  document.getElementById("rejectionReason").value = "";
}

function closeRejectionModal() {
  document.getElementById("rejectionModal").style.display = "none";
  currentRejectionOrderId = null;
}

function submitRejection() {
  const reason = document.getElementById("rejectionReason").value.trim();
  if (!reason) {
    alert("Please provide a reason for rejection");
    return;
  }

  if (currentRejectionOrderId) {
    updateOrderStatus(currentRejectionOrderId, "rejected", null, reason);
    closeRejectionModal();
  }
}
