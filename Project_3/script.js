const slider = document.querySelector('.slider');

const dots = document.querySelectorAll('.dot');

const leftArrow = document.querySelector('.arrow.left');

const rightArrow = document.querySelector('.arrow.right');



let currentIndex = 0;



// Определяем, сколько изображений показывать (3 на ПК, 1 на телефоне)

function getVisibleCount() {

  return window.innerWidth <= 768 ? 1 : 3;

}



function updateSlider() {

  const visibleCount = getVisibleCount();

  const shift = -(currentIndex * (100 / visibleCount));

  slider.style.transform = `translateX(${shift}%)`;



  dots.forEach(dot => dot.classList.remove('active'));

  dots[currentIndex] && dots[currentIndex].classList.add('active');

}



// Обработчики стрелок

rightArrow.addEventListener('click', () => {

  const visibleCount = getVisibleCount();

  const maxIndex = Math.ceil(8 / visibleCount) - 1;

  currentIndex = (currentIndex + 1 > maxIndex) ? 0 : currentIndex + 1;

  updateSlider();

});



leftArrow.addEventListener('click', () => {

  const visibleCount = getVisibleCount();

  const maxIndex = Math.ceil(8 / visibleCount) - 1;

  currentIndex = (currentIndex - 1 < 0) ? maxIndex : currentIndex - 1;

  updateSlider();

});



window.addEventListener('resize', updateSlider);



updateSlider();

