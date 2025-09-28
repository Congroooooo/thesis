let slideIndex = 0;
const slides = document.querySelectorAll(".slideshow img");

function showSlides() {
  slides.forEach((slide, index) => {
    slide.style.display = index === slideIndex ? "block" : "none";
  });
  slideIndex = (slideIndex + 1) % slides.length;
}

setInterval(showSlides, 2000);
showSlides();

let heroSlideIndex = 0;
const heroSlides = document.querySelectorAll(".hero-slide");

function showHeroSlides() {
  heroSlides.forEach((slide) => slide.classList.remove("active"));
  heroSlides[heroSlideIndex].classList.add("active");
  heroSlideIndex = (heroSlideIndex + 1) % heroSlides.length;
}

if (heroSlides.length > 0) {
  heroSlides[0].classList.add("active");
  setInterval(showHeroSlides, 6000);
}

const hamburger = document.querySelector(".hamburger");
const navLinks = document.querySelector(".nav-links");
const dropdowns = document.querySelectorAll(".dropdown");

hamburger.addEventListener("click", () => {
  hamburger.classList.toggle("active");
  navLinks.classList.toggle("active");
});

dropdowns.forEach((dropdown) => {
  dropdown.addEventListener("click", () => {
    dropdown.classList.toggle("active");
  });
});

// Close menu when clicking a link
document.querySelectorAll(".nav-links a").forEach((n) =>
  n.addEventListener("click", () => {
    hamburger.classList.remove("active");
    navLinks.classList.remove("active");
  })
);
