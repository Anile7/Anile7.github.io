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
            }
        } catch (e) {
            // ignore
        }
    }
    
    updateAuthUI(loggedIn, login = '') {
        const authBlock = document.getElementById("auth-block");
        if (!authBlock) return;
        
        if (loggedIn) {
            authBlock.innerHTML = `
                <div id="auth-status" style="background: #d4edda; padding: 15px; border-radius: 5px;">
                     Вы вошли как <strong>${login}</strong>
                    <button id="logout-btn" class="btn btn-sm btn-secondary">Выйти</button>
                </div>
            `;
            document.getElementById("logout-btn")?.addEventListener("click", () => this.logout());
        } else {
            authBlock.innerHTML = `
                <div id="login-form" style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <h3>Вход для редактирования данных</h3>
                    <input type="text" id="login-username" placeholder="Логин" class="form-control mb-2">
                    <input type="password" id="login-password" placeholder="Пароль" class="form-control mb-2">
                    <button id="login-btn" class="btn btn-primary">Войти</button>
                    <small class="text-muted">Нет аккаунта? Отправьте форму, и вы получите логин и пароль.</small>
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
                location.reload(); // обновляем страницу для обновления UI
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
            location.reload();
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
