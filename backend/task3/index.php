<?php
// Выводим сообщения, переданные через GET
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 3 – Анкета</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f2f5;
        }
        .pic {
            width: 200px;
            height: 200px;
            object-fit: cover;
        }
        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 20px;
        }
        nav ul li a {
            color: white;
            text-decoration: none;
        }
        /* Контент */
        main {
            max-width: 960px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group label {
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
        .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .radio-group label {
            font-weight: normal;
            display: flex;
            align-items: center;
            gap: 5px;
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
        .error {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .success {
            color: #155724;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
        }
        /* Таблица (оставляем как есть) */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        /* Подвал */
        footer {
            background: #4A90E2;
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            nav ul {
                flex-direction: column;
                gap: 10px;
            }
            .radio-group {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

<main>
    <h1>Анкета разработчика</h1>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="save.php" method="post">
        <div class="form-group">
            <label>ФИО:</label>
            <input type="text" name="full_name" required placeholder="Иванов Иван Иванович">
        </div>

        <div class="form-group">
            <label>Телефон:</label>
            <input type="tel" name="phone" required placeholder="+7 (123) 456-78-90">
        </div>

        <div class="form-group">
            <label>E-mail:</label>
            <input type="email" name="email" required placeholder="ivan@example.com">
        </div>

        <div class="form-group">
            <label>Дата рождения:</label>
            <input type="date" name="birth_date" required>
        </div>

        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="female" required> Женский</label>
                <label><input type="radio" name="gender" value="male"> Мужской</label>
            </div>
        </div>

        <div class="form-group">
            <label>Любимые языки программирования (выберите несколько):</label>
            <select name="languages[]" multiple size="6">
                <option value="Pascal">Pascal</option>
                <option value="C">C</option>
                <option value="C++">C++</option>
                <option value="JavaScript">JavaScript</option>
                <option value="PHP">PHP</option>
                <option value="Python">Python</option>
                <option value="Java">Java</option>
                <option value="Haskel">Haskel</option>
                <option value="Clojure">Clojure</option>
                <option value="Prolog">Prolog</option>
                <option value="Scala">Scala</option>
                <option value="Go">Go</option>
            </select>
            <small>Зажмите Ctrl (Cmd) для выбора нескольких</small>
        </div>

        <div class="form-group">
            <label>Биография:</label>
            <textarea name="biography" rows="5" placeholder="Расскажите о себе..."></textarea>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="contract" value="1" required>
                Я ознакомлен(а) с условиями контракта
            </label>
        </div>

        <button type="submit">Сохранить</button>
    </form>
</main>

</body>
</html>