<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');


$db_host = 'localhost';
$db_name = 'u82574';
$db_user = 'u82574';
$db_pass = '3923359';

// --- Функции для работы с cookies ---
function saveErrorsToCookie($errors) {
    setcookie('form_errors', serialize($errors), 0, '/');
}
function getAndClearErrors() {
    $errors = [];
    if (isset($_COOKIE['form_errors'])) {
        $errors = unserialize($_COOKIE['form_errors']);
        setcookie('form_errors', '', time() - 3600, '/');
    }
    return $errors;
}
function saveOldInputToCookie($input) {
    setcookie('form_old_input', serialize($input), 0, '/');
}
function getAndClearOldInput() {
    $input = [];
    if (isset($_COOKIE['form_old_input'])) {
        $input = unserialize($_COOKIE['form_old_input']);
        setcookie('form_old_input', '', time() - 3600, '/');
    }
    return $input;
}
function saveDefaultValuesToCookie($data) {
    $expire = time() + 365 * 24 * 3600;
    setcookie('default_values', serialize($data), $expire, '/');
}
function getDefaultValues() {
    if (isset($_COOKIE['default_values'])) {
        return unserialize($_COOKIE['default_values']);
    }
    return [];
}

// --- Генерация логина и пароля ---
function generateLogin() {
    return 'user_' . uniqid();
}
function generatePassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, 8);
}

// --- Валидация ---
function validateForm($input) {
    $errors = [];
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $input['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов).';
    }
    if (!preg_match('/^[\+\d\s\(\)\-]{5,20}$/', $input['phone'])) {
        $errors['phone'] = 'Телефон должен содержать цифры, пробелы, скобки, дефисы, плюс (5-20 символов).';
    }
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email адрес.';
    }
    $birth = DateTime::createFromFormat('Y-m-d', $input['birth_date']);
    if (!$birth || $birth > new DateTime()) {
        $errors['birth_date'] = 'Дата рождения указана неверно или находится в будущем.';
    }
    if (!in_array($input['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }
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
    if (strlen($input['biography']) > 1000) {
        $errors['biography'] = 'Биография не должна превышать 1000 символов.';
    }
    if (empty($input['contract'])) {
        $errors['contract'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }
    return $errors;
}

// --- Функции для работы с БД ---
function insertSubmission($data, $pdo) {
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
    return $pdo->lastInsertId();
}

function saveLanguages($submission_id, $languages, $pdo) {
    $stmtLangId = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
    $stmtLink = $pdo->prepare("INSERT INTO submission_languages (submission_id, language_id) VALUES (?, ?)");
    foreach ($languages as $langName) {
        $stmtLangId->execute([$langName]);
        $langId = $stmtLangId->fetchColumn();
        if ($langId) {
            $stmtLink->execute([$submission_id, $langId]);
        }
    }
}

function updateSubmission($submission_id, $data, $pdo) {
    $sql = "UPDATE submissions SET 
            full_name = :full_name,
            phone = :phone,
            email = :email,
            birth_date = :birth_date,
            gender = :gender,
            biography = :biography,
            contract_agreed = :contract
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name' => $data['full_name'],
        ':phone' => $data['phone'],
        ':email' => $data['email'],
        ':birth_date' => $data['birth_date'],
        ':gender' => $data['gender'],
        ':biography' => $data['biography'],
        ':contract' => $data['contract'],
        ':id' => $submission_id
    ]);
    
    $stmtDel = $pdo->prepare("DELETE FROM submission_languages WHERE submission_id = ?");
    $stmtDel->execute([$submission_id]);
    
    $stmtLangId = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
    $stmtLink = $pdo->prepare("INSERT INTO submission_languages (submission_id, language_id) VALUES (?, ?)");
    foreach ($data['languages'] as $langName) {
        $stmtLangId->execute([$langName]);
        $langId = $stmtLangId->fetchColumn();
        if ($langId) {
            $stmtLink->execute([$submission_id, $langId]);
        }
    }
}

function createUser($submission_id, $login, $password_hash, $pdo) {
    $sql = "INSERT INTO users (login, password_hash, submission_id) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login, $password_hash, $submission_id]);
}

// --- Обработка POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $errors = validateForm($input);

    if (empty($errors)) {
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();

            session_start();
            $is_authorized = !empty($_SESSION['user_id']);
            $user_submission_id = null;
            
            if ($is_authorized) {
                $stmt = $pdo->prepare("SELECT submission_id FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_submission_id = $stmt->fetchColumn();
            }

            if ($is_authorized && $user_submission_id) {
                updateSubmission($user_submission_id, $input, $pdo);
                $pdo->commit();
                saveDefaultValuesToCookie($input);
                $msg = "Данные успешно обновлены!";
                header("Location: ?success=" . urlencode($msg));
                exit;
            } else {
                $submission_id = insertSubmission($input, $pdo);
                saveLanguages($submission_id, $input['languages'], $pdo);
                
                $login = generateLogin();
                $password = generatePassword();
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                createUser($submission_id, $login, $password_hash, $pdo);
                
                $pdo->commit();
                saveDefaultValuesToCookie($input);
                
                $msg = "Данные успешно сохранены!<br>Ваш логин: <strong>$login</strong><br>Ваш пароль: <strong>$password</strong>";
                header("Location: ?success=" . urlencode($msg));
                exit;
            }
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            saveOldInputToCookie($input);
            saveErrorsToCookie(['general' => 'Ошибка базы данных: ' . $e->getMessage()]);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } else {
        saveOldInputToCookie($input);
        saveErrorsToCookie($errors);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- Обработка GET ---
$messages = [];
$errors = [];
$values = [];

if (isset($_GET['success'])) {
    $messages[] = '<div class="success">' . htmlspecialchars($_GET['success']) . '</div>';
}

$errors = getAndClearErrors();
$old_input = getAndClearOldInput();
$default_values = getDefaultValues();
$values = array_merge($default_values, $old_input);

session_start();
$is_authorized = !empty($_SESSION['user_id']);

if ($is_authorized) {
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT submission_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $submission_id = $stmt->fetchColumn();
        
        if ($submission_id) {
            $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
            $stmt->execute([$submission_id]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT pl.name FROM submission_languages sl 
                                   JOIN programming_languages pl ON sl.language_id = pl.id 
                                   WHERE sl.submission_id = ?");
            $stmt->execute([$submission_id]);
            $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($submission) {
                $values['full_name'] = $submission['full_name'];
                $values['phone'] = $submission['phone'];
                $values['email'] = $submission['email'];
                $values['birth_date'] = $submission['birth_date'];
                $values['gender'] = $submission['gender'];
                $values['biography'] = $submission['biography'];
                $values['contract'] = $submission['contract_agreed'];
                $values['languages'] = $langs;
            }
            $messages[] = '<div class="info">Вы вошли как ' . htmlspecialchars($_SESSION['user_login']) . '. Можете редактировать свои данные.</div>';
        }
    } catch (Exception $e) {
        // Игнорируем ошибку, просто показываем форму
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Задание 5 – Анкета с авторизацией</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; }
        main { max-width: 960px; margin: 20px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; }
        form { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-weight: bold; }
        input, select, textarea { padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; width: 100%; box-sizing: border-box; }
        .radio-group { display: flex; gap: 20px; align-items: center; }
        .radio-group label { font-weight: normal; display: flex; align-items: center; gap: 5px; }
        button { background: #4A90E2; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; width: auto; align-self: flex-start; }
        button:hover { background: #357ABD; }
        .error { border: 2px solid red !important; background-color: #ffe6e6 !important; }
        .error-message { color: red; font-size: 0.9em; margin-top: 5px; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin: 20px 0; }
        .info { color: #0c5460; background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin: 20px 0; }
        @media (max-width: 768px) { .radio-group { flex-wrap: wrap; } }
    </style>
</head>
<body>
<main>
    <h1>Анкета разработчика (с авторизацией)</h1>
    <?php foreach ($messages as $msg): ?>
        <?= $msg ?>
    <?php endforeach; ?>
    
    <form action="" method="POST">
        <?php if ($is_authorized): ?>
            <div style="text-align: right; margin-bottom: 20px;">
                Вы вошли как <strong><?= htmlspecialchars($_SESSION['user_login']) ?></strong>
                <a href="logout.php">Выйти</a>
            </div>
        <?php else: ?>
            <div style="text-align: right; margin-bottom: 20px;">
                <a href="login.php">Войти</a> для редактирования данных
            </div>
        <?php endif; ?>

        <!-- ФИО -->
        <div class="form-group">
            <label>ФИО:</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($values['full_name'] ?? '') ?>" class="<?= !empty($errors['full_name']) ? 'error' : '' ?>">
            <?php if (!empty($errors['full_name'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['full_name']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Телефон -->
        <div class="form-group">
            <label>Телефон:</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($values['phone'] ?? '') ?>" class="<?= !empty($errors['phone']) ? 'error' : '' ?>">
            <?php if (!empty($errors['phone'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group">
            <label>E-mail:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($values['email'] ?? '') ?>" class="<?= !empty($errors['email']) ? 'error' : '' ?>">
            <?php if (!empty($errors['email'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Дата рождения -->
        <div class="form-group">
            <label>Дата рождения:</label>
            <input type="date" name="birth_date" value="<?= htmlspecialchars($values['birth_date'] ?? '') ?>" class="<?= !empty($errors['birth_date']) ? 'error' : '' ?>">
            <?php if (!empty($errors['birth_date'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['birth_date']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Пол -->
        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="female" <?= (($values['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
                <label><input type="radio" name="gender" value="male" <?= (($values['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
            </div>
            <?php if (!empty($errors['gender'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Языки -->
        <div class="form-group">
            <label>Любимые языки программирования:</label>
            <select name="languages[]" multiple size="6" class="<?= !empty($errors['languages']) ? 'error' : '' ?>">
                <?php
                $selected_langs = $values['languages'] ?? [];
                $langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
                foreach ($langs as $lang):
                    $selected = in_array($lang, $selected_langs) ? 'selected' : '';
                ?>
                    <option value="<?= $lang ?>" <?= $selected ?>><?= $lang ?></option>
                <?php endforeach; ?>
            </select>
            <small>Зажмите Ctrl (Cmd) для выбора нескольких</small>
            <?php if (!empty($errors['languages'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Биография -->
        <div class="form-group">
            <label>Биография:</label>
            <textarea name="biography" rows="5" class="<?= !empty($errors['biography']) ? 'error' : '' ?>"><?= htmlspecialchars($values['biography'] ?? '') ?></textarea>
            <?php if (!empty($errors['biography'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['biography']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Чекбокс контракта -->
        <div class="form-group">
            <label>
                <input type="checkbox" name="contract" value="1" <?= (($values['contract'] ?? 0) == 1) ? 'checked' : '' ?>>
                Я ознакомлен(а) с условиями контракта
            </label>
            <?php if (!empty($errors['contract'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['contract']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit">Сохранить</button>
    </form>
</main>
</body>
</html>