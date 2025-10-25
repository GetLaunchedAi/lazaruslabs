document.addEventListener("DOMContentLoaded", () => {
  const slides = document.querySelectorAll(".hero-slideshow .slide");
  let index = 0;

  function showSlide(i) {
    slides.forEach((slide, idx) => {
      slide.classList.remove("active");
      if (idx === i) slide.classList.add("active");
    });
  }

  showSlide(index);
  setInterval(() => {
    index = (index + 1) % slides.length;
    showSlide(index);
  }, 4000); // 4 seconds per slide
});
