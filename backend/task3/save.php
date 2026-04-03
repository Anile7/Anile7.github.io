<?php
// save.php

$db_host = 'localhost';
$db_name = 'u82574';        
$db_user = 'u82574';          
$db_pass = '3923359'; 

function redirectError($msg) {
    header("Location: index.php?error=" . urlencode($msg));
    exit;
}
function redirectSuccess($msg) {
    header("Location: index.php?success=" . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectError("Некорректный метод запроса");
}

$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$birth_date = $_POST['birth_date'] ?? '';
$gender = $_POST['gender'] ?? '';
$languages = $_POST['languages'] ?? [];
$biography = trim($_POST['biography'] ?? '');
$contract = isset($_POST['contract']) ? 1 : 0;


if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
    redirectError("ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов).");
}

if (!preg_match('/^[\+\d\s\(\)\-]{5,20}$/', $phone)) {
    redirectError("Телефон должен содержать цифры, пробелы, скобки, дефисы, плюс (5-20 символов).");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectError("Введите корректный email адрес.");
}

$birth = DateTime::createFromFormat('Y-m-d', $birth_date);
if (!$birth || $birth > new DateTime()) {
    redirectError("Дата рождения указана неверно или находится в будущем.");
}

if (!in_array($gender, ['male', 'female'])) {
    redirectError("Выберите пол.");
}

$allowed_langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
if (empty($languages)) {
    redirectError("Выберите хотя бы один язык программирования.");
}
foreach ($languages as $lang) {
    if (!in_array($lang, $allowed_langs)) {
        redirectError("Недопустимый язык: " . htmlspecialchars($lang));
    }
}

if (strlen($biography) > 1000) {
    redirectError("Биография не должна превышать 1000 символов.");
}

if (!$contract) {
    redirectError("Необходимо подтвердить ознакомление с контрактом.");
}

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    $sql = "INSERT INTO submissions (full_name, phone, email, birth_date, gender, biography, contract_agreed)
            VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name' => $full_name,
        ':phone' => $phone,
        ':email' => $email,
        ':birth_date' => $birth_date,
        ':gender' => $gender,
        ':biography' => $biography,
        ':contract' => $contract
    ]);
    $submission_id = $pdo->lastInsertId();

    $stmtLangId = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
    $stmtLink = $pdo->prepare("INSERT INTO submission_languages (submission_id, language_id) VALUES (?, ?)");
    
    foreach ($languages as $langName) {
        $stmtLangId->execute([$langName]);
        $langId = $stmtLangId->fetchColumn();
        if ($langId) {
            $stmtLink->execute([$submission_id, $langId]);
        } else {
            throw new Exception("Язык $langName не найден в справочнике");
        }
    }

    $pdo->commit();
    redirectSuccess("Данные успешно сохранены! Спасибо за заполнение анкеты.");

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("DB Error: " . $e->getMessage());
    redirectError("Ошибка базы данных. Попробуйте позже.");
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Logic Error: " . $e->getMessage());
    redirectError("Внутренняя ошибка: " . $e->getMessage());
}