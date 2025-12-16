// Элементы DOM
const openFormBtn = document.getElementById('openFormBtn');
const overlay = document.getElementById('overlay');
const closeBtn = document.getElementById('closeBtn');
const feedbackForm = document.getElementById('feedbackForm');
const submitBtn = document.getElementById('submitBtn');
const successMessage = document.getElementById('successMessage');
const errorMessage = document.getElementById('errorMessage');

// Ключ для localStorage
const STORAGE_KEY = 'feedbackFormData';

// URL для отправки формы (замените на ваш URL с formcarry.com или slapform.com)
const FORM_SUBMIT_URL = 'https://formcarry.com/s/_aMhclWCpwJ';

// Открытие формы
openFormBtn.addEventListener('click', () => {
    overlay.style.display = 'flex';
    // Изменение URL с помощью History API
    history.pushState({ formOpen: true }, '', '#feedback-form');
    // Восстановление данных из localStorage
    restoreFormData();
});

// Закрытие формы
function closeForm() {
    overlay.style.display = 'none';
    // Возврат к исходному URL
    history.pushState({ formClosed: true }, '', window.location.pathname);
}

closeBtn.addEventListener('click', closeForm);

// Обработка нажатия кнопки "Назад" в браузере
window.addEventListener('popstate', (event) => {
    if (overlay.style.display === 'flex') {
        closeForm();
    }
});

// Сохранение данных формы в localStorage
function saveFormData() {
    const formData = {
        fullName: document.getElementById('fullName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        organization: document.getElementById('organization').value,
        message: document.getElementById('message').value,
        privacyPolicy: document.getElementById('privacyPolicy').checked
    };
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
}

// Восстановление данных формы из localStorage
function restoreFormData() {
    const savedData = localStorage.getItem(STORAGE_KEY);
    
    if (savedData) {
        const formData = JSON.parse(savedData);
        
        document.getElementById('fullName').value = formData.fullName || '';
        document.getElementById('email').value = formData.email || '';
        document.getElementById('phone').value = formData.phone || '';
        document.getElementById('organization').value = formData.organization || '';
        document.getElementById('message').value = formData.message || '';
        document.getElementById('privacyPolicy').checked = formData.privacyPolicy || false;
    }
}

// Очистка данных формы
function clearFormData() {
    localStorage.removeItem(STORAGE_KEY);
    feedbackForm.reset();
    hideMessages();
}

// Скрытие сообщений
function hideMessages() {
    successMessage.style.display = 'none';
    errorMessage.style.display = 'none';
}

// Показать сообщение об успехе
function showSuccessMessage() {
    hideMessages();
    successMessage.style.display = 'block';
}

// Показать сообщение об ошибке
function showErrorMessage() {
    hideMessages();
    errorMessage.style.display = 'block';
}

// ОТПРАВКА ФОРМЫ - ИСПРАВЛЕННЫЙ БЛОК
feedbackForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Показать состояние загрузки
    submitBtn.disabled = true;
    submitBtn.textContent = 'Отправка...';
    
    try {
        // Сбор данных формы в обычный объект (не FormData!)
        const formData = {
            fullName: document.getElementById('fullName').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value || '',
            organization: document.getElementById('organization').value || '',
            message: document.getElementById('message').value,
            privacyPolicy: document.getElementById('privacyPolicy').checked
        };
        
        // Отправка данных с помощью fetch в формате JSON
        const response = await fetch(FORM_SUBMIT_URL, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        if (response.ok) {
            showSuccessMessage();
            clearFormData();
            
            // Автоматическое закрытие формы через 2 секунды
            setTimeout(() => {
                closeForm();
            }, 2000);
        } else {
            // Пробуем получить текст ошибки от сервера
            const errorText = await response.text();
            console.error('Ошибка сервера:', errorText);
            throw new Error(`Ошибка отправки: ${response.status}`);
        }
    } catch (error) {
        console.error('Ошибка отправки формы:', error);
        showErrorMessage();
    } finally {
        // Сброс состояния кнопки
        submitBtn.disabled = false;
        submitBtn.textContent = 'Отправить';
    }
});

// Сохранение данных при вводе (с задержкой)
let saveTimeout;
feedbackForm.addEventListener('input', () => {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(saveFormData, 500);
});

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    // Если в URL есть хэш формы, открываем форму
    if (window.location.hash === '#feedback-form') {
        openFormBtn.click();
    }
});
