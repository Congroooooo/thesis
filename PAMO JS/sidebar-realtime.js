// PAMO Sidebar Real-time Updates
// Updates notification badges and other sidebar elements in real-time

document.addEventListener("DOMContentLoaded", function () {
  // Only run on PAMO pages
  if (!document.querySelector(".sidebar")) return;

  let lastBadgeCheck = null;

  // Function to update pending orders badge (global scope)
  window.updatePendingOrdersBadge = async function () {
    try {
      const formData = new FormData();
      formData.append("action", "get_pending_count");

      const response = await fetch("../Includes/order_operations.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        const ordersNavItem = document.querySelector(
          '.nav-links li[onclick*="orders.php"]'
        );
        if (ordersNavItem) {
          const existingBadge = ordersNavItem.querySelector(".notif-badge");
          const pendingCount = data.pending_count;

          if (pendingCount > 0) {
            if (existingBadge) {
              // Update existing badge
              if (existingBadge.textContent != pendingCount) {
                existingBadge.textContent = pendingCount;
                // Flash animation for badge update
                existingBadge.style.animation = "badgeFlash 0.6s ease-in-out";
                setTimeout(() => (existingBadge.style.animation = ""), 600);
              }
            } else {
              // Create new badge
              const newBadge = document.createElement("span");
              newBadge.className = "notif-badge";
              newBadge.textContent = pendingCount;
              newBadge.style.animation = "badgeAppear 0.5s ease-out";
              ordersNavItem.appendChild(newBadge);
            }
          } else {
            // Remove badge if no pending orders
            if (existingBadge) {
              existingBadge.style.animation = "badgeDisappear 0.3s ease-in";
              setTimeout(() => {
                if (existingBadge.parentNode) {
                  existingBadge.parentNode.removeChild(existingBadge);
                }
              }, 300);
            }
          }
        }
      }
    } catch (error) {
      // Silently handle errors to avoid console spam
    }
  };

  // Initial badge update
  updatePendingOrdersBadge();

  // Update badge every 15 seconds (balanced with order polling)
  setInterval(updatePendingOrdersBadge, 15000);
});

// Add CSS for badge animations if not already present
if (!document.querySelector("#badge-animations-css")) {
  const style = document.createElement("style");
  style.id = "badge-animations-css";
  style.textContent = `
        @keyframes badgeFlash {
            0% { transform: scale(1); background-color: #dc3545; }
            50% { transform: scale(1.2); background-color: #28a745; }
            100% { transform: scale(1); background-color: #dc3545; }
        }
        
        @keyframes badgeAppear {
            0% { 
                opacity: 0; 
                transform: scale(0); 
            }
            70% { 
                transform: scale(1.1); 
            }
            100% { 
                opacity: 1; 
                transform: scale(1); 
            }
        }
        
        @keyframes badgeDisappear {
            0% { 
                opacity: 1; 
                transform: scale(1); 
            }
            100% { 
                opacity: 0; 
                transform: scale(0); 
            }
        }
        
        .notif-badge {
            transition: all 0.3s ease;
        }
    `;
  document.head.appendChild(style);
}
