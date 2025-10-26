const slider = document.querySelector('.slider');
const dotsContainer = document.querySelector('.pager');
const leftArrow = document.querySelector('.arrow.left');
const rightArrow = document.querySelector('.arrow.right');

let currentIndex = 0;
const totalImages = 8;

function getVisibleCount() {
  return window.innerWidth <= 768 ? 1 : 3;
}

// Количество страниц
function getTotalPages() {
  const visibleCount = getVisibleCount();
  return Math.ceil(totalImages / visibleCount);
}

// Создание кружков
function createDots() {
  const totalPages = getTotalPages();
  dotsContainer.innerHTML = '';
  for (let i = 0; i < totalPages; i++) {
    const dot = document.createElement('span');
    dot.classList.add('dot');
    if (i === currentIndex) dot.classList.add('active');
    dotsContainer.appendChild(dot);
  }
}

function updateSlider() {
  const visibleCount = getVisibleCount();
  const shift = -(currentIndex * 100);
  slider.style.transform = `translateX(${shift}%)`;

  const dots = document.querySelectorAll('.dot');
  dots.forEach((dot, i) => dot.classList.toggle('active', i === currentIndex));

  leftArrow.disabled = currentIndex === 0;
  rightArrow.disabled = currentIndex === getTotalPages() - 1;
}

rightArrow.addEventListener('click', () => {
  if (currentIndex < getTotalPages() - 1) {
    currentIndex++;
    updateSlider();
  }
});

leftArrow.addEventListener('click', () => {
  if (currentIndex > 0) {
    currentIndex--;
    updateSlider();
  }
});

window.addEventListener('resize', () => {
  const totalPages = getTotalPages();
  if (currentIndex >= totalPages) currentIndex = totalPages - 1;
  createDots();
  updateSlider();
});

createDots();
updateSlider();
