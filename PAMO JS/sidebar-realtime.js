document.addEventListener("DOMContentLoaded", function () {
  if (!document.querySelector(".sidebar")) return;

  let lastBadgeCheck = null;

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
              if (existingBadge.textContent != pendingCount) {
                existingBadge.textContent = pendingCount;
                existingBadge.style.animation = "badgeFlash 0.6s ease-in-out";
                setTimeout(() => (existingBadge.style.animation = ""), 600);
              }
            } else {
              const newBadge = document.createElement("span");
              newBadge.className = "notif-badge";
              newBadge.textContent = pendingCount;
              newBadge.style.animation = "badgeAppear 0.5s ease-out";
              ordersNavItem.appendChild(newBadge);
            }
          } else {
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
      // Silently handle errors
    }
  };

  updatePendingOrdersBadge();

  setInterval(updatePendingOrdersBadge, 15000);
});

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
