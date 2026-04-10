<?php
// Включаем отображение ошибок для отладки (на время разработки)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = 'localhost';
$db_name = 'u82574';       
$db_user = 'u82574';      
$db_pass = '3923359';   

// --- Функции для работы с cookies ---
// Сохранить массив ошибок в cookies (сессионные, до конца сессии браузера)
function saveErrorsToCookie($errors) {
    setcookie('form_errors', serialize($errors), 0, '/'); // 0 - до закрытия браузера
}

// Получить массив ошибок из cookies и удалить их
function getAndClearErrors() {
    $errors = [];
    if (isset($_COOKIE['form_errors'])) {
        $errors = unserialize($_COOKIE['form_errors']);
        setcookie('form_errors', '', time() - 3600, '/'); // удаляем
    }
    return $errors;
}

// Сохранить введённые данные (чтобы подставить обратно в форму) - временно, до показа
function saveOldInputToCookie($input) {
    setcookie('form_old_input', serialize($input), 0, '/');
}

// Получить старые введённые данные и удалить
function getAndClearOldInput() {
    $input = [];
    if (isset($_COOKIE['form_old_input'])) {
        $input = unserialize($_COOKIE['form_old_input']);
        setcookie('form_old_input', '', time() - 3600, '/');
    }
    return $input;
}

// Сохранить успешно введённые данные на год (для предзаполнения формы)
function saveDefaultValuesToCookie($data) {
    $expire = time() + 365 * 24 * 3600;
    setcookie('default_values', serialize($data), $expire, '/');
}

// Получить значения по умолчанию (если есть)
function getDefaultValues() {
    if (isset($_COOKIE['default_values'])) {
        return unserialize($_COOKIE['default_values']);
    }
    return [];
}

// --- Валидация полей с регулярными выражениями ---
function validateForm($input) {
    $errors = [];

    // 1. ФИО: только буквы, пробелы, дефисы, от 1 до 150
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $input['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов).';
    }

    // 2. Телефон: цифры, пробелы, скобки, дефисы, плюс, от 5 до 20
    if (!preg_match('/^[\+\d\s\(\)\-]{5,20}$/', $input['phone'])) {
        $errors['phone'] = 'Телефон должен содержать цифры, пробелы, скобки, дефисы, плюс (5-20 символов).';
    }

    // 3. Email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email адрес.';
    }

    // 4. Дата рождения: не будущая, формат YYYY-MM-DD
    $birth = DateTime::createFromFormat('Y-m-d', $input['birth_date']);
    if (!$birth || $birth > new DateTime()) {
        $errors['birth_date'] = 'Дата рождения указана неверно или находится в будущем.';
    }

    // 5. Пол
    if (!in_array($input['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }

    // 6. Языки: хотя бы один и все из разрешённого списка
    $allowed_langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
    if (empty($input['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($input['languages'] as $lang) {
            if (!in_array($lang, $allowed_langs)) {
                $errors['languages'] = 'Недопустимый язык: ' . htmlspecialchars($lang);
                break;
            }
        }
    }

    // 7. Биография: не более 1000 символов (необязательно)
    if (strlen($input['biography']) > 1000) {
        $errors['biography'] = 'Биография не должна превышать 1000 символов.';
    }

    // 8. Чекбокс контракта
    if (empty($input['contract'])) {
        $errors['contract'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    return $errors;
}

// --- Сохранение в БД (используем prepared statements) ---
function saveToDatabase($data, $db_host, $db_name, $db_user, $db_pass) {
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        $sql = "INSERT INTO submissions (full_name, phone, email, birth_date, gender, biography, contract_agreed)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birth_date' => $data['birth_date'],
            ':gender' => $data['gender'],
            ':biography' => $data['biography'],
            ':contract' => $data['contract']
        ]);
        $submission_id = $pdo->lastInsertId();

        $stmtLangId = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
        $stmtLink = $pdo->prepare("INSERT INTO submission_languages (submission_id, language_id) VALUES (?, ?)");
        foreach ($data['languages'] as $langName) {
            $stmtLangId->execute([$langName]);
            $langId = $stmtLangId->fetchColumn();
            if ($langId) {
                $stmtLink->execute([$submission_id, $langId]);
            }
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo) $pdo->rollBack();
        error_log("DB Error: " . $e->getMessage());
        return false;
    }
}

// --- Основная логика ---
$errors = [];
$old_input = [];
$success_message = '';

// Если форма отправлена методом POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Собираем данные
    $input = [
        'full_name'  => trim($_POST['full_name'] ?? ''),
        'phone'      => trim($_POST['phone'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? '',
        'gender'     => $_POST['gender'] ?? '',
        'languages'  => $_POST['languages'] ?? [],
        'biography'  => trim($_POST['biography'] ?? ''),
        'contract'   => isset($_POST['contract']) ? 1 : 0
    ];

    // Валидация
    $errors = validateForm($input);

    if (empty($errors)) {
        // Успех: сохраняем в БД
        $saved = saveToDatabase($input, $db_host, $db_name, $db_user, $db_pass);
        if ($saved) {
            // Сохраняем введённые значения в cookies на год (для предзаполнения)
            $defaults = [
                'full_name'  => $input['full_name'],
                'phone'      => $input['phone'],
                'email'      => $input['email'],
                'birth_date' => $input['birth_date'],
                'gender'     => $input['gender'],
                'languages'  => $input['languages'],
                'biography'  => $input['biography']
            ];
            saveDefaultValuesToCookie($defaults);
            // Редирект с сообщением об успехе
            header("Location: ?success=1");
            exit;
        } else {
            // Ошибка БД
            $errors['general'] = 'Ошибка при сохранении в базу данных. Попробуйте позже.';
            saveOldInputToCookie($input);
            saveErrorsToCookie($errors);
            header("Location: ");
            exit;
        }
    } else {
        // Есть ошибки валидации: сохраняем ошибки и введённые данные в cookies, делаем редирект GET
        saveOldInputToCookie($input);
        saveErrorsToCookie($errors);
        header("Location: ");
        exit;
    }
}

// Если GET-запрос (или редирект после POST), читаем cookies
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $errors = getAndClearErrors();         // ошибки (одноразовые)
    $old_input = getAndClearOldInput();    // введённые данные (одноразовые)
    if (isset($_GET['success'])) {
        $success_message = 'Данные успешно сохранены!';
    }
}

// Получаем значения по умолчанию из долгосрочных cookies (для первого показа)
$default_values = getDefaultValues();

// Для полей приоритет: 1) старые введённые (при ошибке), 2) значения по умолчанию (из cookies), 3) пустые
function getFieldValue($fieldName, $old_input, $default_values) {
    if (isset($old_input[$fieldName])) {
        return htmlspecialchars($old_input[$fieldName]);
    } elseif (isset($default_values[$fieldName])) {
        return htmlspecialchars($default_values[$fieldName]);
    }
    return '';
}
function getMultipleSelectValue($fieldName, $old_input, $default_values) {
    if (isset($old_input[$fieldName])) {
        return $old_input[$fieldName];
    } elseif (isset($default_values[$fieldName])) {
        return $default_values[$fieldName];
    }
    return [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 4 – Анкета с валидацией и cookies</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        main {
            max-width: 960px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        label {
            font-weight: bold;
        }
        input, select, textarea {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
        }
        .error-field {
            border: 2px solid red;
            background-color: #ffe6e6;
        }
        .error-message {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        button {
            background: #4A90E2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: auto;
            align-self: flex-start;
        }
        button:hover {
            background: #357ABD;
        }
        .success {
            color: #155724;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .general-error {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<main>
    <h1>Анкета разработчика (с валидацией)</h1>

    <?php if ($success_message): ?>
        <div class="success"><?= $success_message ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['general'])): ?>
        <div class="general-error"><?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <!-- ФИО -->
        <div class="form-group">
            <label>ФИО:</label>
            <input type="text" name="full_name" value="<?= getFieldValue('full_name', $old_input, $default_values) ?>"
                   class="<?= isset($errors['full_name']) ? 'error-field' : '' ?>">
            <?php if (isset($errors['full_name'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['full_name']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Телефон -->
        <div class="form-group">
            <label>Телефон:</label>
            <input type="tel" name="phone" value="<?= getFieldValue('phone', $old_input, $default_values) ?>"
                   class="<?= isset($errors['phone']) ? 'error-field' : '' ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group">
            <label>E-mail:</label>
            <input type="email" name="email" value="<?= getFieldValue('email', $old_input, $default_values) ?>"
                   class="<?= isset($errors['email']) ? 'error-field' : '' ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Дата рождения -->
        <div class="form-group">
            <label>Дата рождения:</label>
            <input type="date" name="birth_date" value="<?= getFieldValue('birth_date', $old_input, $default_values) ?>"
                   class="<?= isset($errors['birth_date']) ? 'error-field' : '' ?>">
            <?php if (isset($errors['birth_date'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['birth_date']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Пол -->
        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="female" <?= (getFieldValue('gender', $old_input, $default_values) == 'female') ? 'checked' : '' ?>> Женский</label>
                <label><input type="radio" name="gender" value="male" <?= (getFieldValue('gender', $old_input, $default_values) == 'male') ? 'checked' : '' ?>> Мужской</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Языки -->
        <div class="form-group">
            <label>Любимые языки программирования (выберите несколько):</label>
            <select name="languages[]" multiple size="6" class="<?= isset($errors['languages']) ? 'error-field' : '' ?>">
                <?php
                $selected_langs = getMultipleSelectValue('languages', $old_input, $default_values);
                $langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
                foreach ($langs as $lang):
                    $selected = in_array($lang, $selected_langs) ? 'selected' : '';
                ?>
                    <option value="<?= $lang ?>" <?= $selected ?>><?= $lang ?></option>
                <?php endforeach; ?>
            </select>
            <small>Зажмите Ctrl (Cmd) для выбора нескольких</small>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Биография -->
        <div class="form-group">
            <label>Биография:</label>
            <textarea name="biography" rows="5" class="<?= isset($errors['biography']) ? 'error-field' : '' ?>"><?= getFieldValue('biography', $old_input, $default_values) ?></textarea>
            <?php if (isset($errors['biography'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['biography']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Чекбокс контракта -->
        <div class="form-group">
            <label>
                <input type="checkbox" name="contract" value="1" <?= (isset($old_input['contract']) && $old_input['contract'] == 1) ? 'checked' : (isset($default_values['contract']) ? '' : '') ?>>
                Я ознакомлен(а) с условиями контракта
            </label>
            <?php if (isset($errors['contract'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['contract']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit">Сохранить</button>
    </form>
</main>
</body>
</html>