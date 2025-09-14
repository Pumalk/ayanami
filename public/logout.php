<?php
// logout.php
session_start();
// Уничтожаем все данные сессии
$_SESSION = array();
session_destroy();
// Перенаправляем пользователя на главную страницу или страницу входа
header('Location: index.php');
exit();
?>