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
const serviceTypeRadios = document.querySelectorAll('input[name="serviceType"]');
const optionsGroup = document.getElementById('optionsGroup');
const optionsSelect = document.getElementById('options');
const propertyGroup = document.getElementById('propertyGroup');
const propertyCheckbox = document.getElementById('property');
const totalPriceElement = document.getElementById('totalPrice');

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
    const quantity = parseInt(quantityInput.value) || 1;
    const selectedType = document.querySelector('input[name="serviceType"]:checked').value;
    
    let basePrice = basePrices[selectedType];
    let total = basePrice * quantity;

    if (isNAN(quantity) || quantity<1) {
        errorDiv.style.display='block';
        quantityİnput.style.borderColor='#dc3545';
        resultDiv.style.display='none';
        return;
    }
    
    // Добавляем стоимость опции для премиум услуги
    if (selectedType === 'premium') {
        const selectedOption = optionsSelect.value;
        total *= optionMultipliers[selectedOption];
    }
    
    // Добавляем стоимость свойства для кастомной услуги
    if (selectedType === 'custom' && propertyCheckbox.checked) {
        total += propertyPrice * quantity;
    }
    
    return total;
}

// Функция для обновления отображаемой цены
function updatePriceDisplay() {
    const total = calculateTotalPrice();
    totalPriceElement.textContent = `${total} руб.`;
}

// Добавляем обработчики событий
serviceTypeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        updateFormVisibility();
        updatePriceDisplay();
    });
});

quantityInput.addEventListener('input', updatePriceDisplay);
optionsSelect.addEventListener('change', updatePriceDisplay);
propertyCheckbox.addEventListener('change', updatePriceDisplay);

// Инициализация при загрузке страницы
updateFormVisibility();

updatePriceDisplay();
