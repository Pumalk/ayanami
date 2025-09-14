<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Необходима авторизация']));
}

$user_id = $_SESSION['user_id'];
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';

// Валидация
if (empty($phone) || empty($address)) {
    die(json_encode(['success' => false, 'message' => 'Заполните все поля']));
}

// Получаем корзину пользователя
$stmt = $pdo->prepare("
    SELECT c.*, p.price 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    die(json_encode(['success' => false, 'message' => 'Корзина пуста']));
}

// Рассчитываем общую сумму
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

// Начинаем транзакцию
$pdo->beginTransaction();

try {
    // Создаем заказ
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, phone, address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $total_amount, $phone, $address]);
    $order_id = $pdo->lastInsertId();

    // Добавляем товары в заказ
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($cart_items as $item) {
        $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
    }

    // Очищаем корзину
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Заказ успешно создан', 'order_id' => $order_id]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Ошибка при создании заказа: ' . $e->getMessage()]);
}
?>