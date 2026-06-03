<?php
// api.php – веб-сервис для приёма данных формы
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешён. Используйте POST.']);
    exit();
}

// Чтение входных данных (JSON или XML)
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат JSON.']);
        exit();
    }
} elseif (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
    $raw = file_get_contents('php://input');
    $xml = simplexml_load_string($raw);
    if ($xml === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат XML.']);
        exit();
    }
    $input = json_decode(json_encode($xml), true);
} else {
    $input = $_POST;
}

// Подключение к БД
$db_host = 'localhost';
$db_name = 'u82574';
$db_user = 'u82574';
$db_pass = '3923359';

// Функция для валидации
function validateFormData($data) {
    $errors = [];
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $data['full_name'] ?? '')) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов).';
    }
    if (!preg_match('/^[\+\d\s\(\)\-]{5,20}$/', $data['phone'] ?? '')) {
        $errors['phone'] = 'Телефон должен содержать цифры, пробелы, скобки, дефисы, плюс (5-20 символов).';
    }
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email адрес.';
    }
    $birth = DateTime::createFromFormat('Y-m-d', $data['birth_date'] ?? '');
    if (!$birth || $birth > new DateTime()) {
        $errors['birth_date'] = 'Дата рождения указана неверно или находится в будущем.';
    }
    if (!in_array($data['gender'] ?? '', ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }
    $allowed_langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
    $languages = $data['languages'] ?? [];
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowed_langs)) {
                $errors['languages'] = 'Недопустимый язык: ' . htmlspecialchars($lang);
                break;
            }
        }
    }
    if (strlen($data['biography'] ?? '') > 1000) {
        $errors['biography'] = 'Биография не должна превышать 1000 символов.';
    }
    if (empty($data['contract'])) {
        $errors['contract'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }
    return $errors;
}

// Функции для работы с БД
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

function createUser($submission_id, $login, $password_hash, $pdo) {
    $sql = "INSERT INTO users (login, password_hash, submission_id) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login, $password_hash, $submission_id]);
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

function generateLogin() {
    return 'user_' . uniqid();
}

function generatePassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, 8);
}

// --- ОСНОВНАЯ ЛОГИКА ---
session_start();
$is_authorized = !empty($_SESSION['user_id']);
$user_submission_id = null;

if ($is_authorized) {
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT submission_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_submission_id = $stmt->fetchColumn();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка сервера. Попробуйте позже.']);
        exit();
    }
}

// Валидация
$errors = validateFormData($input);
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['errors' => $errors]);
    exit();
}

// Подготовка данных
$data = [
    'full_name' => trim($input['full_name']),
    'phone' => trim($input['phone']),
    'email' => trim($input['email']),
    'birth_date' => $input['birth_date'],
    'gender' => $input['gender'],
    'languages' => $input['languages'],
    'biography' => trim($input['biography'] ?? ''),
    'contract' => !empty($input['contract']) ? 1 : 0
];

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    if ($is_authorized && $user_submission_id) {
        // Обновление существующей записи
        updateSubmission($user_submission_id, $data, $pdo);
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Данные успешно обновлены.',
            'profile_url' => "http://u82574.kubsu-dev.ru/task5/?edit=" . $user_submission_id
        ]);
    } else {
        // Новая запись
        $submission_id = insertSubmission($data, $pdo);
        saveLanguages($submission_id, $data['languages'], $pdo);
        
        $login = generateLogin();
        $password = generatePassword();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        createUser($submission_id, $login, $password_hash, $pdo);
        
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Данные успешно сохранены.',
            'login' => $login,
            'password' => $password,
            'profile_url' => "http://u82574.kubsu-dev.ru/task5/?edit=" . $submission_id
        ]);
    }
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка базы данных. Попробуйте позже.']);
}
