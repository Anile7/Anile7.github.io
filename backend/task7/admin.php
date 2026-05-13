<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);   // НЕ показываем ошибки пользователю
ini_set('log_errors', 1);       // Записываем ошибки в лог
header('Content-Type: text/html; charset=UTF-8');
header('Server: '); // Скрываем версию веб-сервера
header('X-Powered-By: '); // Скрываем версию PHP

$db_host = 'localhost';
$db_name = 'u82574';
$db_user = 'u82574';
$db_pass = '3923359';

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- HTTP-авторизация (проверка логина и пароля из таблицы admin) ---
$auth_ok = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
        $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && md5($_SERVER['PHP_AUTH_PW']) == $row['password_hash']) {
            $auth_ok = true;
        }
    } catch (Exception $e) {
        // Ошибка БД, авторизация не пройдена
    }
}

if (!$auth_ok) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    print('<h1>401 Требуется авторизация</h1>');
    print('<p>Доступ разрешён только администратору.</p>');
    exit();
}

// --- Обработка действий (удаление, редактирование) ---
$message = '';

// Удаление записи через POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Ошибка безопасности. Попробуйте ещё раз.";
    }
    $id = (int)$_POST['delete_id'];
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Запись #$id успешно удалена.";
    } catch (Exception $e) {
        $message = "Ошибка при удалении. Попробуйте позже.";
    }
}


// Редактирование записи (обработка POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Ошибка безопасности. Попробуйте ещё раз.";
    } else {
        $edit_id = (int)$_POST['edit_id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $birth_date = $_POST['birth_date'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $languages = $_POST['languages'] ?? [];
        $biography = trim($_POST['biography'] ?? '');
        $contract = isset($_POST['contract']) ? 1 : 0;
        
        // Простая валидация
        if (empty($full_name) || empty($phone) || empty($email) || empty($birth_date) || empty($gender)) {
            $message = "Ошибка: заполните все обязательные поля.";
        } else {
            try {
                $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();
                
                // Обновляем основную запись
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
                    ':full_name' => $full_name,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':birth_date' => $birth_date,
                    ':gender' => $gender,
                    ':biography' => $biography,
                    ':contract' => $contract,
                    ':id' => $edit_id
                ]);
                
                // Обновляем языки
                $stmtDel = $pdo->prepare("DELETE FROM submission_languages WHERE submission_id = ?");
                $stmtDel->execute([$edit_id]);
                
                $stmtLangId = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
                $stmtLink = $pdo->prepare("INSERT INTO submission_languages (submission_id, language_id) VALUES (?, ?)");
                foreach ($languages as $langName) {
                    $stmtLangId->execute([$langName]);
                    $langId = $stmtLangId->fetchColumn();
                    if ($langId) {
                        $stmtLink->execute([$edit_id, $langId]);
                    }
                }
                
                $pdo->commit();
                $message = "Запись #$edit_id успешно обновлена.";
            } catch (Exception $e) {
                if (isset($pdo)) $pdo->rollBack();
                $message = "Ошибка при редактировании. Попробуйте позже.";
            }
        }
    }
}

// --- Получение всех заявок для отображения ---
$submissions = [];
$languages_stats = [];

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Получаем все заявки с их языками
    $stmt = $pdo->query("SELECT * FROM submissions ORDER BY id DESC");
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Для каждой заявки получаем выбранные языки
    foreach ($submissions as &$sub) {
        $stmtLang = $pdo->prepare("SELECT pl.name FROM submission_languages sl 
                                   JOIN programming_languages pl ON sl.language_id = pl.id 
                                   WHERE sl.submission_id = ?");
        $stmtLang->execute([$sub['id']]);
        $sub['languages'] = $stmtLang->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Статистика по языкам: сколько пользователей любят каждый язык
    $stmtStat = $pdo->query("
        SELECT pl.name, COUNT(sl.submission_id) as cnt
        FROM programming_languages pl
        LEFT JOIN submission_languages sl ON pl.id = sl.language_id
        GROUP BY pl.id
        ORDER BY cnt DESC
    ");
    $languages_stats = $stmtStat->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Ошибка загрузки данных ";
}

// Определяем, какая запись редактируется (если передан параметр edit)
$editing_id = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing_data = null;
if ($editing_id) {
    foreach ($submissions as $sub) {
        if ($sub['id'] == $editing_id) {
            $editing_data = $sub;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель - Задание 6</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        h1, h2 { color: #333; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { border-collapse: collapse; width: 100%; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #4A90E2; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .actions a, .actions button { margin-right: 5px; padding: 4px 8px; text-decoration: none; border-radius: 4px; }
        .edit-btn { background: #ffc107; color: #333; border: none; cursor: pointer; }
        .delete-btn { background: #dc3545; color: white; border: none; cursor: pointer; }
        .stats { background: white; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .stats ul { list-style: none; padding-left: 0; }
        .stats li { padding: 5px 0; }
        .edit-form { background: white; padding: 20px; margin-top: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .edit-form input, .edit-form select, .edit-form textarea { width: 100%; padding: 6px; margin-bottom: 10px; }
        .edit-form button { background: #28a745; color: white; border: none; padding: 8px 16px; cursor: pointer; }
        .cancel-btn { background: #6c757d; margin-left: 10px; }
    </style>
</head>
<body>
    <h1>Админ-панель</h1>
    <p>Добро пожаловать, <?= htmlspecialchars($_SERVER['PHP_AUTH_USER']) ?>! <a href="?">Обновить страницу</a></p>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'успешно') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Статистика по языкам -->
    <div class="stats">
        <h2>Статистика по языкам программирования</h2>
        <ul>
            <?php foreach ($languages_stats as $lang): ?>
                <li><strong><?= htmlspecialchars($lang['name']) ?>:</strong> <?= $lang['cnt'] ?> пользователей</li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <!-- Таблица со всеми заявками -->
    <h2>Все заявки пользователей</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Дата рождения</th>
                <th>Пол</th>
                <th>Языки</th>
                <th>Биография</th>
                <th>Контракт</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($submissions)): ?>
                <tr><td colspan="10">Нет данных</td></tr>
            <?php else: ?>
                <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td><?= $sub['id'] ?></td>
                        <td><?= htmlspecialchars($sub['full_name']) ?></td>
                        <td><?= htmlspecialchars($sub['phone']) ?></td>
                        <td><?= htmlspecialchars($sub['email']) ?></td>
                        <td><?= htmlspecialchars($sub['birth_date']) ?></td>
                        <td><?= $sub['gender'] == 'male' ? 'Мужской' : 'Женский' ?></td>
                        <td><?= htmlspecialchars(implode(', ', $sub['languages'])) ?></td>
                        <td><?= htmlspecialchars(substr($sub['biography'], 0, 100)) ?>...</td>
                        <td><?= $sub['contract_agreed'] ? 'Да' : 'Нет' ?></td>
                        <td class="actions">
                            <a href="?edit=<?= $sub['id'] ?>" class="edit-btn" style="display: inline-block; text-align: center;">✏️ Редактировать</a>
                            <form method="post" style="display: inline;">
                            <input type="hidden" name="delete_id" value="<?= $sub['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Удалить запись #<?= $sub['id'] ?>?')">🗑️ Удалить</button>
                        </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Форма редактирования (показывается, если выбран edit) -->
    <?php if ($editing_id && $editing_data): ?>
        <div class="edit-form">
            <h2>Редактирование записи #<?= $editing_id ?></h2>
            <form method="post">
                <input type="hidden" name="edit_id" value="<?= $editing_id ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <label>ФИО:</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($editing_data['full_name']) ?>" required>
                
                <label>Телефон:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($editing_data['phone']) ?>" required>
                
                <label>Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($editing_data['email']) ?>" required>
                
                <label>Дата рождения:</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($editing_data['birth_date']) ?>" required>
                
                <label>Пол:</label>
                <select name="gender" required>
                    <option value="male" <?= $editing_data['gender'] == 'male' ? 'selected' : '' ?>>Мужской</option>
                    <option value="female" <?= $editing_data['gender'] == 'female' ? 'selected' : '' ?>>Женский</option>
                </select>
                
                <label>Языки (Ctrl+выбор):</label>
                <select name="languages[]" multiple size="6">
                    <?php
                    $all_langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
                    foreach ($all_langs as $lang):
                        $selected = in_array($lang, $editing_data['languages']) ? 'selected' : '';
                    ?>
                        <option value="<?= $lang ?>" <?= $selected ?>><?= $lang ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label>Биография:</label>
                <textarea name="biography" rows="4"><?= htmlspecialchars($editing_data['biography']) ?></textarea>
                
                <label>
                    <input type="checkbox" name="contract" value="1" <?= $editing_data['contract_agreed'] ? 'checked' : '' ?>>
                    Контракт ознакомлен
                </label>
                
                <button type="submit">Сохранить изменения</button>
                <a href="admin.php" class="cancel-btn" style="display: inline-block; padding: 8px 16px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Отмена</a>
            </form>
        </div>
    <?php endif; ?>
    
    <p style="margin-top: 20px;"><a href="index.php">← Вернуться к форме</a></p>
</body>
</html>