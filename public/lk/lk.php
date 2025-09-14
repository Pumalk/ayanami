<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/auth.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Получаем данные пользователя
$stmt = $pdo->prepare("SELECT first_name, last_name, email, birth_date, username, avatar, is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: ../auth/auth.php');
    exit();
}

// Получаем корзину пользователя
$cart_stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
$cart_stmt->execute([$user_id]);
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем историю заказов
$orders_stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$orders_stmt->execute([$user_id]);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="lk.css">
    <title>Ayanami - Личный кабинет</title>
    <script>
        function uploadAvatar(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            fetch('../upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('avatar-message');
                messageDiv.textContent = data.message;
                messageDiv.style.color = data.success ? 'green' : 'red';

                if (data.success) {
                    const avatarImg = document.querySelector('.avatar-preview img');
                    if (avatarImg) {
                        avatarImg.src = '../' + data.path + '?t=' + new Date().getTime();
                        avatarImg.style.display = 'block';
                        document.querySelector('.no-avatar').style.display = 'none';
                    }
                    event.target.reset();
                }
            })
            .catch(error => {
                document.getElementById('avatar-message').textContent = 'Произошла ошибка!';
            });
        }

        function updateCartQuantity(productId, change) {
            const quantityInput = document.getElementById('quantity-' + productId);
            let newQuantity = parseInt(quantityInput.value) + change;
            if (newQuantity < 1) newQuantity = 1;
            
            fetch('../cart_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=update&product_id=' + productId + '&quantity=' + newQuantity
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    quantityInput.value = newQuantity;
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.message);
                }
            });
        }

        function removeFromCart(productId) {
            if (confirm('Удалить товар из корзины?')) {
                fetch('../cart_actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=remove&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                });
            }
        }

        function submitOrder(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            fetch('../create_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Заказ успешно оформлен!');
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.message);
                }
            });
        }
    </script>
</head>
<body>
    <header>
        <ul class="row1">
            <li><a href="../index.php">Главная</a></li>
            <li><a href="../catalog/catalog.php">Каталог</a></li>
        </ul>
        <ul class="row2">
            <li><a href="../logout.php">Выйти из аккаунта</a></li>
        </ul>
    </header>
    
    <main>
        <section class="profile-section">
            <h2>Личный кабинет</h2>
            <?php if ($user['is_admin'] == 1): ?>
                <p><strong>Статус:</strong> Администратор</p>
                <p><a href="../admin/admin.php" style="color: red;">Перейти в админ-панель</a></p>
            <?php endif; ?>
            
            <div class="avatar-section">
                <div class="avatar-preview">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="../<?= htmlspecialchars($user['avatar']) ?>" alt="Аватар пользователя">
                    <?php else: ?>
                        <div class="no-avatar">Нет аватара</div>
                    <?php endif; ?>
                </div>
                <div class="zagruzka">
                    <form id="avatar-upload-form" method="post" enctype="multipart/form-data" onsubmit="uploadAvatar(event)">
                        <input type="file" name="attachment" accept="image/jpeg, image/png, image/gif" required>
                        <button type="submit">Загрузить аватар</button>
                    </form>
                    <div id="avatar-message"></div>
                </div>
            </div>
        
            <details class="profile-details">
                <summary>Показать/Скрыть данные</summary>
                <div class="profile-info">
                    <p><strong>Имя:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Логин:</strong> <?= htmlspecialchars($user['username']) ?></p>
                    <p><strong>Дата рождения:</strong> <?= htmlspecialchars($user['birth_date']) ?></p>
                </div>
            </details> 
        </section>

        <!-- История заказов -->
        <section class="orders-section">
            <h2>История заказов</h2>
            <?php if (empty($orders)): ?>
                <p>У вас пока нет заказов</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>№ Заказа</th>
                            <th>Дата</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Телефон</th>
                            <th>Адрес</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                            <td><?= number_format($order['total_amount'], 2, '.', ' ') ?> руб.</td>
                            <td>
                                <?php
                                $status_labels = [
                                    'pending' => 'Ожидание',
                                    'processing' => 'В обработке',
                                    'completed' => 'Завершен',
                                    'cancelled' => 'Отменен'
                                ];
                                echo $status_labels[$order['status']];
                                ?>
                            </td>
                            <td><?= htmlspecialchars($order['phone']) ?></td>
                            <td><?= htmlspecialchars($order['address']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    
        <section class="cart-section" id="cart">
            <h2>Корзина</h2>
            <?php if (empty($cart_items)): ?>
                <p>Корзина пуста</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Количество</th>
                            <th>Цена</th>
                            <th>Итого</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td>
                                <img src="../<?= $item['image'] ?>" alt="<?= $item['name'] ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                <?= htmlspecialchars($item['name']) ?>
                            </td>
                            <td>
                                <button onclick="updateCartQuantity(<?= $item['product_id'] ?>, -1)">-</button>
                                <input type="number" id="quantity-<?= $item['product_id'] ?>" value="<?= $item['quantity'] ?>" min="1" style="width: 50px; text-align: center;" readonly>
                                <button onclick="updateCartQuantity(<?= $item['product_id'] ?>, 1)">+</button>
                            </td>
                            <td><?= number_format($item['price'], 2, '.', ' ') ?> руб.</td>
                            <td><?= number_format($item['price'] * $item['quantity'], 2, '.', ' ') ?> руб.</td>
                            <td>
                                <button onclick="removeFromCart(<?= $item['product_id'] ?>)" style="background: #ff4444; color: white; border: none; padding: 5px 10px; cursor: pointer;">
                                    Удалить
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
    
                <div class="total-price">
                    Общая сумма: <strong><?= number_format($total_price, 2, '.', ' ') ?> ₽</strong>
                </div>
    
                <h2>Оформление заказа</h2>
                <form class="order-form" onsubmit="submitOrder(event)">
                    <label>
                        Телефон:
                        <input type="tel" name="phone" required pattern="[\+]?[0-9\s\-\(\)]+">
                    </label>
                    <label>
                        Адрес доставки:
                        <textarea name="address" rows="3" required></textarea>
                    </label>
                    <button type="submit">Оформить заказ</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
    
    <footer>
        <div class="f1">+7 983 539-26-40</div>
        <div class="f2">Ayanami.ru</div>
        <div class="f3">Ayanami@shop.ru</div>
        <div class="f4">© 2024-2025 Магазин одежды "Аянами"</div>
    </footer>
</body>
</html>