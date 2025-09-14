<?php
session_start();
require_once '../config.php';

// Запрос к базе данных для получения всех товаров
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="catalog.css">
    <title>Ayanami - Каталог</title>
    <script>
        // Функция добавления в корзину
        function addToCart(productId) {
            <?php if (isset($_SESSION['user_id'])): ?>
                fetch('../cart_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=add&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Товар добавлен в корзину!');
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Произошла ошибка!');
                });
            <?php else: ?>
                alert('Для добавления в корзину необходимо авторизоваться!');
                window.location.href = '../auth/auth.php';
            <?php endif; ?>
        }
    </script>
</head>
<body>
    <header>
        <ul class="row1">
            <li><a href="../index.php">Главная</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="../lk/lk.php">Личный кабинет</a></li>
                <li><a href="../lk/lk.php#cart">Корзина</a></li>
            <?php else: ?>
                <li><a href="../auth/auth.php">Вход</a></li>
            <?php endif; ?>
        </ul>
    </header>
    
    <main>
        <section>
            <h2>Каталог</h2>
            <div class="portfolio-grid">
                <?php if (empty($products)): ?>
                    <p class="no-products">Товаров пока нет.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="card">
                            <div>
                                <img src="../<?= !empty($product['image']) ? htmlspecialchars($product['image']) : 'img/placeholder.jpg' ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            </div>
                            <h1><?= htmlspecialchars($product['name']) ?></h1>
                            <p><?= htmlspecialchars($product['description']) ?></p>
                            <div class="price-card">
                                <p class="price"><?= number_format($product['price'], 2, '.', ' ') ?> руб.</p>
                            </div>
                            <button class="add-to-cart" onclick="addToCart(<?= $product['id'] ?>)">
                                В корзину
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <footer>
        <div class="f1">+7 912 345-67-89</div>
        <div class="f2">ayanami.ru</div>
        <div class="f3">admin@ayanami.ru</div>
        <div class="f4">© 2024-2025 Ayanami. Права не защищены.</div>
    </footer>
</body>
</html>