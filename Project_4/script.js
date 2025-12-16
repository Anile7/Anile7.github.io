const openFormBtn = document.getElementById('openFormBtn');
const overlay = document.getElementById('overlay');
const closeBtn = document.getElementById('closeBtn');
const feedbackForm = document.getElementById('feedbackForm');
const submitBtn = document.getElementById('submitBtn');
const successMessage = document.getElementById('successMessage');
const errorMessage = document.getElementById('errorMessage');

const STORAGE_KEY = 'feedbackFormData';

const FORM_SUBMIT_URL = 'https://formcarry.com/s/_aMhclWCpwJ';

openFormBtn.addEventListener('click', () => {
    overlay.style.display = 'flex';
    history.pushState({ formOpen: true }, '', '#feedback-form');
    restoreFormData();
});

function closeForm() {
    overlay.style.display = 'none';
    history.pushState({ formClosed: true }, '', window.location.pathname);
}

closeBtn.addEventListener('click', closeForm);

window.addEventListener('popstate', (event) => {
    if (overlay.style.display === 'flex') {
        closeForm();
    }
});

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

function clearFormData() {
    localStorage.removeItem(STORAGE_KEY);
    feedbackForm.reset();
    hideMessages();
}

function hideMessages() {
    successMessage.style.display = 'none';
    errorMessage.style.display = 'none';
}

function showSuccessMessage() {
    hideMessages();
    successMessage.style.display = 'block';
}

function showErrorMessage() {
    hideMessages();
    errorMessage.style.display = 'block';
}

feedbackForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Отправка...';
    
    try {
        const formData = {
            fullName: document.getElementById('fullName').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value || '',
            organization: document.getElementById('organization').value || '',
            message: document.getElementById('message').value,
            privacyPolicy: document.getElementById('privacyPolicy').checked
        };
        
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
            
            setTimeout(() => {
                closeForm();
            }, 2000);
        } else {
            const errorText = await response.text();
            console.error('Ошибка сервера:', errorText);
            throw new Error(`Ошибка отправки: ${response.status}`);
        }
    } catch (error) {
        console.error('Ошибка отправки формы:', error);
        showErrorMessage();
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Отправить';
    }
});

let saveTimeout;
feedbackForm.addEventListener('input', () => {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(saveFormData, 500);
});

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#feedback-form') {
        openFormBtn.click();
    }
});

