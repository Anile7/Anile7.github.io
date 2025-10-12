document.addEventListener('DOMContentLoaded', function() {
    // Объект с информацией о товарах
    const products = {
        '1500': { name: 'Смартфон', price: 1500 },
        '2500': { name: 'Ноутбук', price: 2500 },
        '800': { name: 'Наушники', price: 800 },
        '3500': { name: 'Планшет', price: 3500 }
    };

    // Получаем элементы DOM после загрузки
    const productSelect = document.getElementById('product');
    const quantityInput = document.getElementById('quantity');
    const calculateButton = document.getElementById('calculateBtn');
    const resultDiv = document.getElementById('result');
    const errorDiv = document.getElementById('quantityError');

    // Функция расчета стоимости
    function calculateOrder() {
        // Сбрасываем стили ошибок
        errorDiv.style.display = 'none';
        quantityInput.style.borderColor = '#ddd';
        
        // Получаем выбранные значения
        const selectedPrice = productSelect.value;
        const quantity = parseInt(quantityInput.value);
        
        // Валидация ввода
        if (isNaN(quantity) || quantity < 1) {
            errorDiv.style.display = 'block';
            quantityInput.style.borderColor = '#dc3545';
            resultDiv.style.display = 'none';
            return;
        }
        
        // Получаем информацию о выбранном товаре
        const selectedProduct = products[selectedPrice];
        
        // Вычисляем стоимость
        const totalCost = selectedProduct.price * quantity;
        
        // Форматируем вывод
        const formattedCost = totalCost.toLocaleString('ru-RU');
        
        // Отображаем результат
        resultDiv.innerHTML = `
            Стоимость заказа: <span style="color: #28a745;">${formattedCost} руб.</span><br>
            <small>${selectedProduct.name} × ${quantity} шт.</small>
        `;
        resultDiv.style.display = 'block';
    }

    // Подключаем обработчики событий
    calculateButton.addEventListener('click', calculateOrder);
    
    // Обработчик для клавиши Enter в поле количества
    quantityInput.addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            calculateOrder();
        }
    });

    // Дополнительные обработчики для улучшения UX
    productSelect.addEventListener('change', function() {
        // Можно добавить логику при смене товара
        console.log('Выбран товар:', products[this.value].name);
    });

    quantityInput.addEventListener('input', function() {
        // Сбрасываем ошибку при вводе
        if (errorDiv.style.display === 'block') {
            errorDiv.style.display = 'none';
            quantityInput.style.borderColor = '#ddd';
        }
    });

});