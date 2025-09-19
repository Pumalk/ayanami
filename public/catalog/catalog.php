<?php
session_start();
require_once '../config.php';

// Получаем категорию из GET-параметра
$category = $_GET['category'] ?? 'all';

// Формируем SQL запрос в зависимости от выбранной категории
if ($category === 'all') {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? ORDER BY created_at DESC");
    $stmt->execute([$category]);
}

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
        function addToCart(productId) {
            <?php if (isset($_SESSION['user_id'])): ?>
                fetch('../cart_actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
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
                    alert('Произошла ошибка!');
                });
            <?php else: ?>
                alert('Для добавления в корзину необходимо авторизоваться!');
                window.location.href = '../auth/auth.php';
            <?php endif; ?>
        }

        function filterCategory(category) {
            window.location.href = 'catalog.php?category=' + category;
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
            
            <!-- Фильтр по категориям -->
            <div class="category-filter">
                <button onclick="filterCategory('all')" class="<?= $category === 'all' ? 'active' : '' ?>">Все товары</button>
                <button onclick="filterCategory('лонгслив')" class="<?= $category === 'лонгслив' ? 'active' : '' ?>">Лонгсливы</button>
                <button onclick="filterCategory('зип-худи')" class="<?= $category === 'зип-худи' ? 'active' : '' ?>">Зип-худи</button>
                <button onclick="filterCategory('куртка')" class="<?= $category === 'куртка' ? 'active' : '' ?>">Куртки</button>
            </div>

            <div class="portfolio-grid">
                <?php if (empty($products)): ?>
                    <p class="no-products">Товаров пока нет.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="card">
                            <div>
                                <?php $img = !empty($product['image']) ? htmlspecialchars($product['image']) : 'img/placeholder.jpg'; ?>
                                <a href="../<?= $img ?>" class="lightbox-trigger">
                                    <img src="../<?= $img ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="clickable-image">
                                </a>
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
    <script src="../js/lightbox.js"></script>
</body>
</html>