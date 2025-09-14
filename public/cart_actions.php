<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Необходима авторизация']));
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$product_id = $_POST['product_id'] ?? 0;

header('Content-Type: application/json');

switch ($action) {
    case 'add':
        // Проверяем существование товара
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Товар не найден']);
            exit;
        }

        // Проверяем, есть ли уже товар в корзине
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Увеличиваем количество
            $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?");
            $success = $stmt->execute([$existing['id']]);
        } else {
            // Добавляем новый товар
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $success = $stmt->execute([$user_id, $product_id]);
        }

        echo json_encode(['success' => $success, 'message' => $success ? 'Товар добавлен в корзину' : 'Ошибка добавления']);
        break;

    case 'remove':
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $success = $stmt->execute([$user_id, $product_id]);
        echo json_encode(['success' => $success, 'message' => $success ? 'Товар удален из корзины' : 'Ошибка удаления']);
        break;

    case 'update':
        $quantity = $_POST['quantity'] ?? 1;
        if ($quantity <= 0) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $success = $stmt->execute([$user_id, $product_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $success = $stmt->execute([$quantity, $user_id, $product_id]);
        }
        echo json_encode(['success' => $success, 'message' => $success ? 'Количество обновлено' : 'Ошибка обновления']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
}
?>