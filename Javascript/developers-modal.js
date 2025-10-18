// Developers Modal Logic

document.addEventListener("DOMContentLoaded", function () {
  const devLink = document.getElementById("developers-link");
  if (!devLink) return;

  // Create modal HTML
  const modalOverlay = document.createElement("div");
  modalOverlay.className = "developers-modal-overlay";
  modalOverlay.style.display = "none";
  modalOverlay.innerHTML = `
    <div class="developers-modal" role="dialog" aria-modal="true" tabindex="-1">
      <button class="developers-modal-close" aria-label="Close">&times;</button>
      <div class="developers-modal-title">Meet the Developers</div>
      <div class="developers-list">
        <div class="developer-card">
          <img class="developer-img" src="../Images/Balmes_Profile.png" alt="Balmes Nicko">
          <div class="developer-name">Balmes, Nicko</div>
          <div class="developer-role">Lead Developer || Full Stack Developer</div>
          <div class="developer-socials">
            <a href="https://balmesnicko.vercel.app/" target="_blank" aria-label="Portfolio"><i class="fas fa-globe"></i></a>
            <a href="https://github.com/Congroooooo" target="_blank" aria-label="GitHub"><i class="fab fa-github"></i></a>
            <a href="https://www.linkedin.com/in/nicko-balmes/" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
            <a href="https://www.facebook.com/congrooo/" target="_blank" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
          </div>
        </div>
        <div class="developer-card">
          <img class="developer-img" src="https://ui-avatars.com/api/?name=Jane+Smith" alt="de vera, Aaron David">
          <div class="developer-name">de vera, Aaron David</div>
          <div class="developer-role">UI/ UX Designer || Frontend Developer</div>
          <div class="developer-socials">
            <a href="#" target="_blank" aria-label="GitHub"><i class="fab fa-github"></i></a>
            <a href="#" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
            <a href="#" target="_blank" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
          </div>
        </div>
        <div class="developer-card">
          <img class="developer-img" src="https://ui-avatars.com/api/?name=Alex+Lee" alt="Garcia Reyn Alduz">
          <div class="developer-name">Garcia, Reyn Alduz</div>
          <div class="developer-role">Project Manager || Frontend Developer</div>
          <div class="developer-socials">
            <a href="#" target="_blank" aria-label="GitHub"><i class="fab fa-github"></i></a>
            <a href="#" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
            <a href="#" target="_blank" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
          </div>
        </div>
        <div class="developer-card">
          <img class="developer-img" src="https://ui-avatars.com/api/?name=Maria+Garcia" alt="Ibarra Lander">
          <div class="developer-name">Ibarra, Lander</div>
          <div class="developer-role">UI/ UX Designer || Frontend Developer</div>
          <div class="developer-socials">
            <a href="#" target="_blank" aria-label="GitHub"><i class="fab fa-github"></i></a>
            <a href="#" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
            <a href="#" target="_blank" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modalOverlay);

  // Open modal
  devLink.addEventListener("click", function (e) {
    e.preventDefault();
    // Force hide global loader if visible
    var pageLoader = document.getElementById("page-loader");
    if (pageLoader) {
      pageLoader.classList.add("hidden");
      pageLoader.style.display = "none";
      pageLoader.style.visibility = "hidden";
      pageLoader.style.opacity = "0";
    }
    modalOverlay.style.display = "flex";
    setTimeout(() => {
      modalOverlay.querySelector(".developers-modal").focus();
    }, 10);
  });

  // Close modal
  function closeModal() {
    modalOverlay.style.display = "none";
    // Also hide loader just in case
    var pageLoader = document.getElementById("page-loader");
    if (pageLoader) {
      pageLoader.classList.add("hidden");
      pageLoader.style.display = "none";
      pageLoader.style.visibility = "hidden";
      pageLoader.style.opacity = "0";
    }
  }
  modalOverlay.addEventListener("click", function (e) {
    if (e.target === modalOverlay) closeModal();
  });
  modalOverlay
    .querySelector(".developers-modal-close")
    .addEventListener("click", closeModal);
  document.addEventListener("keydown", function (e) {
    if (modalOverlay.style.display === "flex" && e.key === "Escape") {
      closeModal();
    }
  });
});
