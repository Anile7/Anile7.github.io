// Базовая стоимость для каждого типа услуги
const basePrices = {
    basic: 100,
    premium: 200,
    custom: 150
};

// Множители стоимости для опций
const optionMultipliers = {
    standard: 1.0,
    advanced: 1.5,
    professional: 2.0
};

// Дополнительная стоимость для свойства
const propertyPrice = 50;

// Получаем элементы DOM
const quantityInput = document.getElementById('quantity');
const quantityError = document.getElementById('quantityError');
const serviceTypeRadios = document.querySelectorAll('input[name="serviceType"]');
const optionsGroup = document.getElementById('optionsGroup');
const optionsSelect = document.getElementById('options');
const propertyGroup = document.getElementById('propertyGroup');
const propertyCheckbox = document.getElementById('property');
const totalPriceElement = document.getElementById('totalPrice');

// Функция для проверки валидности количества
function validateQuantity() {
    const quantity = quantityInput.value.trim();
    const quantityNum = parseInt(quantity);
    
    // Очищаем предыдущие ошибки
    quantityError.style.display = 'none';
    quantityInput.classList.remove('error');
    
    // Проверка на пустое значение
    if (quantity === '') {
        showQuantityError('Пожалуйста, введите количество');
        return false;
    }
    
    // Проверка на число
    if (isNaN(quantity) || quantity === '') {
        showQuantityError('Количество должно быть числом');
        return false;
    }
    
    // Проверка на целое число
    if (!Number.isInteger(quantityNum)) {
        showQuantityError('Количество должно быть целым числом');
        return false;
    }
    
    // Проверка на отрицательные значения и ноль
    if (quantityNum <= 0) {
        showQuantityError('Количество должно быть больше нуля');
        return false;
    }
    
    // Проверка на слишком большое значение
    if (quantityNum > 1000) {
        showQuantityError('Количество не может превышать 1000');
        return false;
    }
    
    return true;
}

// Функция для отображения ошибки количества
function showQuantityError(message) {
    quantityError.textContent = message;
    quantityError.style.display = 'block';
    quantityInput.classList.add('error');
}

// Функция для обновления видимости дополнительных элементов
function updateFormVisibility() {
    const selectedType = document.querySelector('input[name="serviceType"]:checked').value;
    
    // Сбрасываем значения при смене типа
    optionsSelect.selectedIndex = 0;
    propertyCheckbox.checked = false;
    
    // Управляем видимостью элементов в зависимости от типа услуги
    switch(selectedType) {
        case 'basic':
            optionsGroup.classList.add('hidden');
            propertyGroup.classList.add('hidden');
            break;
        case 'premium':
            optionsGroup.classList.remove('hidden');
            propertyGroup.classList.add('hidden');
            break;
        case 'custom':
            optionsGroup.classList.add('hidden');
            propertyGroup.classList.remove('hidden');
            break;
    }
}

// Функция для расчета общей стоимости
function calculateTotalPrice() {
    // Проверяем валидность перед расчетом
    if (!validateQuantity()) {
        return 0;
    }
    
    const quantity = parseInt(quantityInput.value);
    const selectedType = document.querySelector('input[name="serviceType"]:checked').value;
    
    let basePrice = basePrices[selectedType];
    let total = basePrice * quantity;
    
    // Добавляем стоимость опции для премиум услуги
    if (selectedType === 'premium') {
        const selectedOption = optionsSelect.value;
        total *= optionMultipliers[selectedOption];
    }
    
    // Добавляем стоимость свойства для кастомной услуги
    if (selectedType === 'custom' && propertyCheckbox.checked) {
        total += propertyPrice * quantity;
    }
    
    return Math.round(total); // Округляем до целого числа
}

// Функция для обновления отображаемой цены
function updatePriceDisplay() {
    const total = calculateTotalPrice();
    if (total === 0) {
        totalPriceElement.textContent = '—';
    } else {
        totalPriceElement.textContent = `${total} руб.`;
    }
}

// Добавляем обработчики событий
serviceTypeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        updateFormVisibility();
        updatePriceDisplay();
    });
});

quantityInput.addEventListener('input', function() {
    validateQuantity();
    updatePriceDisplay();
});

quantityInput.addEventListener('blur', function() {
    validateQuantity();
    updatePriceDisplay();
});

optionsSelect.addEventListener('change', updatePriceDisplay);
propertyCheckbox.addEventListener('change', updatePriceDisplay);

// Инициализация при загрузке страницы
updateFormVisibility();
updatePriceDisplay();
