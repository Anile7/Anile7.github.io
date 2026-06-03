class FeedbackForm {
    constructor() {
        this.feedbackForm = document.getElementById("feedbackForm");
        this.submitBtn = document.getElementById("submitBtn");
        this.STORAGE_KEY = "feedbackFormData";
        this.init();
    }

    init() {
        this.restoreFormData();
        this.checkAuthStatus();
        this.feedbackForm.addEventListener("submit", (e) => this.handleSubmit(e));
        this.feedbackForm.addEventListener("input", () => this.saveFormData());
        
        // Кнопки авторизации
        const loginBtn = document.getElementById("login-btn");
        if (loginBtn) {
            loginBtn.addEventListener("click", () => this.login());
        }
        
        const logoutBtn = document.getElementById("logout-btn");
        if (logoutBtn) {
            logoutBtn.addEventListener("click", () => this.logout());
        }
    }
    
    async checkAuthStatus() {
    try {
        const response = await fetch("auth.php?action=check");
        const result = await response.json();
        if (result.logged_in) {
            this.updateAuthUI(true, result.login);
        } else {
            this.updateAuthUI(false);
        }
    } catch (e) {
        console.error("Ошибка проверки авторизации:", e);
    }
}

updateAuthUI(loggedIn, login = '') {
    const authBlock = document.getElementById("auth-block");
    if (!authBlock) return;
    
    if (loggedIn) {
        authBlock.innerHTML = `
            <div style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 16px; padding: 20px; border: 1px solid rgba(255, 255, 255, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="background: #10b981; width: 10px; height: 10px; border-radius: 50%; box-shadow: 0 0 8px #10b981;"></div>
                        <span style="color: #a0aec0;">Вы вошли как</span>
                        <span style="background: rgba(255, 60, 60, 0.2); color: #ff3c3c; padding: 5px 16px; border-radius: 40px; font-weight: 600; font-size: 14px;">
                            ${login}
                        </span>
                    </div>
                    <button id="logout-btn" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; padding: 8px 24px; border-radius: 40px; cursor: pointer;">Выйти</button>
                </div>
            </div>
        `;
        document.getElementById("logout-btn")?.addEventListener("click", () => this.logout());
    } else {
        authBlock.innerHTML = `
            <div style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 16px; padding: 20px; border: 1px solid rgba(255, 255, 255, 0.1);">
                <div style="margin-bottom: 16px;">
                    <h3 style="color: #fff; font-size: 18px; margin: 0 0 8px 0;">Вход для редактирования данных</h3>
                    <p style="color: #a0aec0; font-size: 13px; margin: 0;">Уже есть аккаунт? Войдите, чтобы изменить свои данные</p>
                </div>
                <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 15px;">
                    <div style="flex: 1; min-width: 180px;">
                        <label style="display: block; font-size: 12px; color: #a0aec0; margin-bottom: 6px;">Логин</label>
                        <input type="text" id="login-username" placeholder="Ваш логин" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #2d3748; background: rgba(0,0,0,0.4); color: #fff;">
                    </div>
                    <div style="flex: 1; min-width: 180px;">
                        <label style="display: block; font-size: 12px; color: #a0aec0; margin-bottom: 6px;">Пароль</label>
                        <input type="password" id="login-password" placeholder="Ваш пароль" style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #2d3748; background: rgba(0,0,0,0.4); color: #fff;">
                    </div>
                    <button id="login-btn" style="background: linear-gradient(135deg, #ff3c3c 0%, #e60000 100%); border: none; color: #fff; padding: 12px 32px; border-radius: 40px; cursor: pointer; font-weight: 600;">Войти</button>
                </div>
                <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.08);">
                    <small style="color: #718096;">Нет аккаунта? Отправьте форму, и вы получите логин и пароль.</small>
                </div>
            </div>
        `;
        document.getElementById("login-btn")?.addEventListener("click", () => this.login());
    }
}
    
    async login() {
        const login = document.getElementById("login-username")?.value;
        const password = document.getElementById("login-password")?.value;
        
        if (!login || !password) {
            this.showMessage("Введите логин и пароль", "error");
            return;
        }
        
        try {
            const response = await fetch("auth.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ login, password })
            });
            const result = await response.json();
            
            if (result.success) {
                this.showMessage(result.message, "success");
                // Обновляем блок авторизации без перезагрузки страницы
                this.updateAuthUI(true, login);
            } else {
                this.showMessage(result.error, "error");
            }
        } catch (error) {
            this.showMessage("Ошибка соединения", "error");
        }
    }
    
    async logout() {
        try {
            await fetch("auth.php?action=logout");
            this.updateAuthUI(false);
            this.showMessage("Вы вышли из системы", "success");
            this.feedbackForm.reset();
        } catch (error) {
            this.showMessage("Ошибка при выходе", "error");
        }
    }

    saveFormData() {
        const formData = {
            name: document.getElementById("field-name-1")?.value || "",
            phone: document.getElementById("phone")?.value || "",
            email: document.getElementById("field-email")?.value || "",
            comment: document.getElementById("field-name-2")?.value || "",
            agree: document.getElementById("agree")?.checked || false,
        };
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(formData));
    }

    restoreFormData() {
        const savedData = localStorage.getItem(this.STORAGE_KEY);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                if (document.getElementById("field-name-1")) document.getElementById("field-name-1").value = data.name || "";
                if (document.getElementById("phone")) document.getElementById("phone").value = data.phone || "";
                if (document.getElementById("field-email")) document.getElementById("field-email").value = data.email || "";
                if (document.getElementById("field-name-2")) document.getElementById("field-name-2").value = data.comment || "";
                if (document.getElementById("agree")) document.getElementById("agree").checked = data.agree || false;
            } catch (error) {
                console.error("Ошибка восстановления данных:", error);
                this.clearFormData();
            }
        }
    }

    clearFormData() {
        localStorage.removeItem(this.STORAGE_KEY);
    }

    showMessage(message, type = "success") {
        const existingMessage = document.querySelector(".form-message");
        if (existingMessage) existingMessage.remove();

        const messageDiv = document.createElement("div");
        messageDiv.className = `form-message alert alert-${type === "success" ? "success" : "danger"} mt-3`;
        messageDiv.style.whiteSpace = "pre-line";
        messageDiv.textContent = message;

        this.feedbackForm.appendChild(messageDiv);
        setTimeout(() => messageDiv.remove(), 30000);
    }

    async handleSubmit(e) {
        e.preventDefault();

        if (!this.feedbackForm.checkValidity()) {
            this.showMessage("Пожалуйста, заполните все обязательные поля правильно", "error");
            return;
        }

        const originalText = this.submitBtn.textContent;
        this.submitBtn.disabled = true;
        this.submitBtn.textContent = "Отправка...";

        const formData = new FormData(this.feedbackForm);
        const formObject = {};
        formData.forEach((value, key) => {
            formObject[key] = value;
        });

        // Подготовка данных для API
        const apiData = {
            full_name: formObject["field-name-1"] || "",
            phone: formObject.phone || "",
            email: formObject["field-email"] || "",
            birth_date: "2000-01-01",
            gender: "female",
            languages: ["JavaScript"],
            biography: formObject["field-name-2"] || "",
            contract: formObject.agree ? 1 : 0
        };

        try {
            const response = await fetch("api.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(apiData)
            });

            const result = await response.json();

            if (result.success) {
                let message = result.message;
                if (result.login) {
                    message = `${result.message}\n\n Ваш логин: ${result.login}\n Ваш пароль: ${result.password}\n\nСохраните эти данные для входа и редактирования!`;
                }
                this.showMessage(message, "success");
                this.feedbackForm.reset();
                this.clearFormData();
                
                // Если пользователь только что создал аккаунт, обновляем блок авторизации
                if (result.login) {
                    setTimeout(() => location.reload(), 20000);
                }
            } else if (result.errors) {
                const errorMsg = Object.values(result.errors).join(". ");
                this.showMessage(errorMsg, "error");
            } else {
                this.showMessage(result.error || "Неизвестная ошибка", "error");
            }
        } catch (error) {
            console.error("Ошибка:", error);
            this.showMessage("Ошибка соединения с сервером", "error");
        } finally {
            this.submitBtn.disabled = false;
            this.submitBtn.textContent = originalText;
        }
    }
}

document.addEventListener("DOMContentLoaded", () => {
    new FeedbackForm();
});
