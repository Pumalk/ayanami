<?php
session_start(); // Запускаем сессию для хранения данных пользователя
require_once '../config.php'; // Подключаем конфиг с БД

$error = ''; // Переменная для сообщений об ошибках

// ОБРАБОТКА РЕГИСТРАЦИИ
if (isset($_POST['register_submit'])) {
    // Получаем и очищаем данные из формы
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $birth_date = $_POST['birth_date'];
    $username = trim($_POST['reg_username']);
    $password = $_POST['reg_password'];
    $password_confirm = $_POST['reg_password_confirm'] ?? ''; // Добавьте это поле в форму!

    // Простейшая валидация
    if (empty($first_name) || empty($last_name) || empty($email) || empty($birth_date) || empty($username) || empty($password)) {
        $error = 'Все поля обязательны для заполнения!';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают!';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов!';
    } else {
        // Проверяем, нет ли уже такого пользователя
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким email или логином уже существует!';
        } else {
            // Хэшируем пароль
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            // Сохраняем пользователя в БД
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, birth_date, username, password) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$first_name, $last_name, $email, $birth_date, $username, $password_hash])) {
                // Автоматически авторизуем пользователя после регистрации
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                header('Location: ../lk/lk.php'); // Перенаправляем в ЛК
                exit();
            } else {
                $error = 'Ошибка при регистрации. Попробуйте позже.';
            }
        }
    }
}

// ОБРАБОТКА АВТОРИЗАЦИИ
if (isset($_POST['login_submit'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Введите логин и пароль!';
    } else {
        // Ищем пользователя в БД
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Проверяем пароль
        if ($user && password_verify($password, $user['password'])) {
            // Успешный вход
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: ../lk/lk.php');
            exit();
        } else {
            $error = 'Неверный логин или пароль!';
        }
    }
}
?>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="auth.css">
    <title>Ayanami - Вход</title>
</head>
<body>
    <header>
        <a href="../index.html">На главную</a>
    </header>
    <main>
    <div class="forms-container">
        <input type="radio" name="slider" id="login" checked>
        <input type="radio" name="slider" id="register">
        
        <div class="form-tabs">
            <label for="login" class="login-tab">Вход</label>
            <label for="register" class="register-tab">Регистрация</label>
        </div>

        <?php if (!empty($error)): ?>
            <div style="color: red; padding: 10px; margin: 10px 0; border: 1px solid red;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <div class="forms-wrapper">
            <div class="login-form form-box">
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Имя пользователя:</label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Пароль:</label>
                        <input type="password" id="password" name="password" required>
                    </div>


                    <button type="submit" name="login_submit">Войти</button>
                </form>
            </div>

                <?php if (!empty($error)): ?>
                    <div style="color: red; padding: 10px; margin: 10px 0; border: 1px solid red;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
            <div class="register-form form-box">
                <form method="POST">
                    <div class="form-group">
                        <label for="first_name">Имя:</label>
                        <input type="text" id="first_name" name="first_name" value="" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Фамилия:</label>
                        <input type="text" id="last_name" name="last_name" value="" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="" required>

                    </div>

                    <div class="form-group">
                        <label for="birth_date">Дата рождения:</label>
                        <input type="date" id="birth_date" name="birth_date" value="" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_username">Имя пользователя:</label>
                        <input type="text" id="reg_username" name="reg_username" value="" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_password">Пароль:</label>
                        <input type="password" id="reg_password" name="reg_password" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_password_confirm">Подтвердите пароль:</label>
                        <input type="password" id="reg_password_confirm" name="reg_password_confirm" required>
                    </div>

                    <button type="submit" name="register_submit">Зарегистрироваться</button>
                </form>
            </div>
        </div>
    </div>
</main>
    <footer>
        <div class="f1">+7 983 539-26-40</div>
        <div class="f2">Ayanami.ru</div>
        <div class="f3">Ayanami@shop.ru</div>
        <div class="f4">© 2024-2025 Магазин одежды "Аянами"</div>
    </footer>
</body>
</html>