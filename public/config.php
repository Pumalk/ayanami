<?php
session_start();
// config.php

$host = 'MySQL-8.0'; // Сервер БД (обычно localhost на OpenServer)
$dbname = 'ayanami'; // Имя вашей базы данных
$username = 'root'; // Имя пользователя БД (по умолчанию в OpenServer - root)
$password = ''; // Пароль БД (по умолчанию в OpenServer пустой)

// Создаем подключение с помощью PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Устанавливаем режим ошибок PDO на исключения (для удобства отладки)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // В случае ошибки подключения выводим сообщение и прекращаем выполнение скрипта
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>