<?php
// auth.php
header('Content-Type: application/json; charset=utf-8');
session_start();

$db_host = 'localhost';
$db_name = 'u82574';
$db_user = 'u82574';
$db_pass = '3923359';

$action = $_GET['action'] ?? '';

// === ПРОВЕРКА СОСТОЯНИЯ АВТОРИЗАЦИИ ===
if ($action === 'check') {
    $logged_in = !empty($_SESSION['user_id']);
    echo json_encode([
        'logged_in' => $logged_in,
        'login' => $logged_in ? ($_SESSION['user_login'] ?? '') : ''
    ]);
    exit();
}

// === ВЫХОД ===
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Вы вышли из системы']);
    exit();
}

// === ВХОД ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $login = trim($input['login'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($login) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Введите логин и пароль']);
        exit();
    }

    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT id, login, password_hash FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            echo json_encode(['success' => true, 'message' => 'Вход выполнен успешно']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Неверный логин или пароль']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
    }
    exit();
}

// Если попали сюда — неизвестное действие
echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
