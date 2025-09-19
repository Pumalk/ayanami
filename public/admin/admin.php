<?php
session_start();
require_once '../config.php';

// Проверка прав администратора
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/auth.php');
    exit();
}

$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();

if (!$userData || $userData['is_admin'] != 1) {
    die('Доступ запрещен. Недостаточно прав.');
}

// Обработка действий
$action = $_GET['action'] ?? '';
$product_id = $_GET['id'] ?? 0;
$order_id = $_GET['order_id'] ?? 0;
$message = '';

// УДАЛЕНИЕ ТОВАРА
if ($action === 'delete_product' && $product_id > 0) {
    // Сначала получаем путь к изображению, чтобы удалить файл
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $imagePath = $stmt->fetchColumn();
    
    // Удаляем запись из БД
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$product_id])) {
        // Если удаление из БД прошло успешно, удаляем файл изображения
        if (!empty($imagePath)) {
            $filePath = __DIR__ . '/../' . $imagePath;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $message = 'Товар успешно удален!';
    } else {
        $message = 'Ошибка при удалении товара!';
    }
}

// ОБНОВЛЕНИЕ СТАТУСА ЗАКАЗА
if ($action === 'update_order_status' && $order_id > 0) {
    $status = $_POST['status'] ?? '';
    $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    
    if (in_array($status, $allowed_statuses)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $order_id])) {
            $message = 'Статус заказа успешно обновлен!';
        } else {
            $message = 'Ошибка при обновлении статуса заказа!';
        }
    } else {
        $message = 'Неверный статус заказа!';
    }
}

// Обработка формы ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['name']) || isset($_FILES['image']))) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $id = $_POST['id'] ?? 0; // 0 для новой записи

    // Валидация
    if (empty($name) || $price <= 0) {
        $message = 'Название и цена обязательны для заполнения!';
    } else {
        // Обработка загрузки изображения
        $imagePath = null;
        $uploadError = false;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/products/';
            
            // Создаем директорию, если её нет
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    $message = 'Ошибка создания директории для загрузки!';
                    $uploadError = true;
                }
            }

            if (!$uploadError) {
                $fileInfo = pathinfo($_FILES['image']['name']);
                $extension = strtolower($fileInfo['extension'] ?? '');
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($extension, $allowedExtensions)) {
                    // Удаляем старое изображение при редактировании
                    if ($id > 0) {
                        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                        $stmt->execute([$id]);
                        $oldImage = $stmt->fetchColumn();
                        if ($oldImage && file_exists(__DIR__ . '/../' . $oldImage)) {
                            unlink(__DIR__ . '/../' . $oldImage);
                        }
                    }

                    // Генерируем уникальное имя для файла
                    $newFileName = 'product_' . ($id > 0 ? $id : time()) . '.' . $extension;
                    $newFilePath = $uploadDir . $newFileName;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $newFilePath)) {
                        $imagePath = 'uploads/products/' . $newFileName;
                    } else {
                        $message = 'Ошибка при загрузке изображения! Проверьте права на запись.';
                        $uploadError = true;
                    }
                } else {
                    $message = 'Недопустимый формат изображения! Разрешены: JPG, PNG, GIF, WEBP';
                    $uploadError = true;
                }
            }
        } elseif ($id > 0 && empty($_FILES['image']['name'])) {
            // Если новое изображение не загрузили, но редактируем существующий товар - оставляем старый путь
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $imagePath = $stmt->fetchColumn();
        }

        // Если нет ошибок с изображением, сохраняем в БД
        if (!$uploadError) {
            try {
                // Сохранение в БД
                if ($id > 0) {
                    // РЕДАКТИРОВАНИЕ существующего товара
                    if ($imagePath !== null) {
                        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, image = ? WHERE id = ?");
                        $success = $stmt->execute([$name, $description, $price, $category, $imagePath, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ? WHERE id = ?");
                        $success = $stmt->execute([$name, $description, $price, $category, $id]);
                    }
                    $message = $success ? 'Товар успешно обновлен!' : 'Ошибка при обновлении товара!';
                } else {
                    // ДОБАВЛЕНИЕ нового товара
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, image) VALUES (?, ?, ?, ?, ?)");
                    $success = $stmt->execute([$name, $description, $price, $category, $imagePath]);
                    $message = $success ? 'Товар успешно добавлен!' : 'Ошибка при добавлении товара!';
                    
                    // Если это добавление нового товара, перенаправляем на список товаров
                    if ($success) {
                        header("Location: admin.php?message=" . urlencode('Товар успешно добавлен!'));
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $message = 'Ошибка базы данных: ' . $e->getMessage();
            }
        }
    }
}

// Получаем список всех товаров
$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Получаем список всех пользователей
$users = $pdo->query("SELECT id, username, email, first_name, last_name, is_admin, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Получаем список всех заказов с информацией о пользователях
$orders = $pdo->query("
    SELECT o.*, u.username, u.first_name, u.last_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Получаем данные товара для редактирования
$editProduct = null;
if ($action === 'edit_product' && $product_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Получаем детали заказа если нужно
$orderDetails = null;
if ($action === 'view_order' && $order_id > 0) {
    // Информация о заказе
    $stmt = $pdo->prepare("
        SELECT o.*, u.username, u.first_name, u.last_name, u.email
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Товары в заказе
    if ($orderDetails) {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name, p.image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $orderDetails['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Обработка сообщений из GET-параметра
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
    <title>Админ-панель - Ayanami</title>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Админ-панель Ayanami</h1>
            <div class="admin-header-links">
                <a href="../index.php">На сайт</a>
                <a href="../logout.php">Выйти</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?= strpos($message, 'Ошибка') !== false ? 'error' : 'success' ?>"><?= $message ?></div>
        <?php endif; ?>

        <div class="admin-nav">
            <a href="#" onclick="showTab('products')">Товары</a>
            <a href="#" onclick="showTab('users')">Пользователи</a>
            <a href="#" onclick="showTab('orders')">Заказы</a>
            <a href="#" onclick="showTab('add-product')"><?= $editProduct ? 'Редактировать товар' : 'Добавить товар' ?></a>
        </div>

        <!-- ВКЛАДКА: ДОБАВЛЕНИЕ/РЕДАКТИРОВАНИЕ ТОВАРА -->
        <div id="add-product" class="tab-content <?= ($action === 'edit_product' || $action === 'add_product') ? 'active' : '' ?>">
            <div class="admin-section">
                <h2><?= $editProduct ? 'Редактировать товар' : 'Добавить новый товар' ?></h2>
                
                <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Название товара:</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Описание:</label>
                        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Цена (руб.):</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?= $editProduct['price'] ?? '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Категория:</label>
                        <select id="category" name="category">
                            <option value="зип-худи" <?= (isset($editProduct['category']) && $editProduct['category'] === 'зип-худи') ? 'selected' : '' ?>>Зип-худи</option>
                            <option value="куртка" <?= (isset($editProduct['category']) && $editProduct['category'] === 'куртка') ? 'selected' : '' ?>>Куртка</option>
                            <option value="лонгслив" <?= (isset($editProduct['category']) && $editProduct['category'] === 'лонгслив') ? 'selected' : '' ?>>Лонгслив</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="image">Изображение товара:</label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small>Разрешены: JPG, PNG, GIF, WEBP. Макс. размер: 8MB</small>
                        
                        <?php if ($editProduct && $editProduct['image']): ?>
                            <div class="image-preview">
                                <?php $editImg = htmlspecialchars($editProduct['image']); ?>
                                <a href="../<?= $editImg ?>" class="lightbox-trigger">
                                    <img src="../<?= $editImg ?>" alt="Текущее изображение" style="max-width: 200px; margin-top: 10px;" class="clickable-image">
                                </a>
                                <p>Текущее изображение</p>
                                <label>
                                    <p><input type="checkbox" name="remove_image" value="1" width="10px" style="text-align: left;">Удалить текущее изображение</p> 
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-add"><?= $editProduct ? 'Обновить товар' : 'Добавить товар' ?></button>
                        <a href="admin.php" class="btn btn-cancel">Отмена</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ВКЛАДКА: ВСЕ ТОВАРы -->
        <div id="products" class="tab-content <?= (!$action || $action === 'delete_product') ? 'active' : '' ?>">
            <div class="admin-section">
                <h2>Управление товарами</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Изображение</th>
                            <th>Название</th>
                            <th>Цена</th>
                            <th>Категория</th>
                            <th>Дата добавления</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= $product['id'] ?></td>
                            <td>
                                <?php if ($product['image']): ?>
                                    <?php $pimg = htmlspecialchars($product['image']); ?>
                                    <a href="../<?= $pimg ?>" class="lightbox-trigger">
                                        <img src="../<?= $pimg ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="table-image clickable-image">
                                    </a>
                                <?php else: ?>
                                    <span>Нет изображения</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= number_format($product['price'], 2, '.', ' ') ?> руб.</td>
                            <td><?= $product['category'] ? htmlspecialchars($product['category']) : '-' ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($product['created_at'])) ?></td>
                            <td class="action-buttons">
                                <a href="admin.php?action=edit_product&id=<?= $product['id'] ?>" class="btn btn-edit">Редактировать</a>
                                <a href="admin.php?action=delete_product&id=<?= $product['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить этот товар?')">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ВКЛАДКА: ЗАКАЗЫ -->
        <div id="orders" class="tab-content <?= ($action === 'view_order') ? 'active' : '' ?>">
            <?php if ($action === 'view_order' && $orderDetails): ?>
                <!-- Детали заказа -->
                <div class="admin-section">
                    <h2>Детали заказа #<?= $orderDetails['id'] ?></h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                        <div>
                            <h3>Информация о заказе</h3>
                            <p><strong>Статус:</strong> 
                                <form method="POST" action="admin.php?action=update_order_status&order_id=<?= $orderDetails['id'] ?>" style="display: inline-block;">
                                    <select name="status" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px;">
                                        <option value="pending" <?= $orderDetails['status'] === 'pending' ? 'selected' : '' ?>>Ожидание</option>
                                        <option value="processing" <?= $orderDetails['status'] === 'processing' ? 'selected' : '' ?>>В обработке</option>
                                        <option value="completed" <?= $orderDetails['status'] === 'completed' ? 'selected' : '' ?>>Завершен</option>
                                        <option value="cancelled" <?= $orderDetails['status'] === 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                                    </select>
                                </form>
                            </p>
                            <p><strong>Общая сумма:</strong> <?= number_format($orderDetails['total_amount'], 2, '.', ' ') ?> руб.</p>
                            <p><strong>Дата заказа:</strong> <?= date('d.m.Y H:i', strtotime($orderDetails['created_at'])) ?></p>
                        </div>
                        
                        <div>
                            <h3>Информация о клиенте</h3>
                            <p><strong>Клиент:</strong> <?= htmlspecialchars($orderDetails['first_name'] . ' ' . $orderDetails['last_name']) ?></p>
                            <p><strong>Логин:</strong> <?= htmlspecialchars($orderDetails['username']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($orderDetails['email']) ?></p>
                            <p><strong>Телефон:</strong> <?= htmlspecialchars($orderDetails['phone']) ?></p>
                            <p><strong>Адрес доставки:</strong> <?= htmlspecialchars($orderDetails['address']) ?></p>
                        </div>
                    </div>

                    <h3>Товары в заказе</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>Количество</th>
                                <th>Цена</th>
                                <th>Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderDetails['items'] as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item['image']): ?>
                                        <?php $iimg = htmlspecialchars($item['image']); ?>
                                        <a href="../<?= $iimg ?>" class="lightbox-trigger">
                                            <img src="../<?= $iimg ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="table-image clickable-image" style="width: 40px; height: 40px;">
                                        </a>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($item['name']) ?>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['price'], 2, '.', ' ') ?> руб.</td>
                                <td><?= number_format($item['price'] * $item['quantity'], 2, '.', ' ') ?> руб.</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="admin.php?action=orders" class="btn btn-cancel">← Назад к списку заказов</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Список заказов -->
                <div class="admin-section">
                    <h2>Управление заказами</h2>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Клиент</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Телефон</th>
                                <th>Дата заказа</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                <td><?= number_format($order['total_amount'], 2, '.', ' ') ?> руб.</td>
                                <td>
                                    <?php 
                                    $status_labels = [
                                        'pending' => 'Ожидание',
                                        'processing' => 'В обработке',
                                        'completed' => 'Завершен',
                                        'cancelled' => 'Отменен'
                                    ];
                                    $status_class = [
                                        'pending' => 'status-pending',
                                        'processing' => 'status-processing',
                                        'completed' => 'status-completed',
                                        'cancelled' => 'status-cancelled'
                                    ];
                                    ?>
                                    <span class="<?= $status_class[$order['status']] ?>">
                                        <?= $status_labels[$order['status']] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($order['phone']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                <td class="action-buttons">
                                    <a href="admin.php?action=view_order&order_id=<?= $order['id'] ?>" class="btn btn-edit">Просмотр</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ВКЛАДКА: ПОЛЬЗОВАТЕЛИ -->
        <div id="users" class="tab-content">
            <div class="admin-section">
                <h2>Управление пользователями</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>Имя Фамилия</th>
                            <th>Статус</th>
                            <th>Дата регистрации</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                            <td class="<?= $user['is_admin'] ? 'status-admin' : 'status-user' ?>"><?= $user['is_admin'] ? 'Администратор' : 'Пользователь' ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // Скрыть все вкладки
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Показать выбранную вкладку
            document.getElementById(tabId).classList.add('active');
            
            // Если показываем форму добавления, сбросить режим редактирования
            if (tabId === 'add-product' && new URLSearchParams(window.location.search).get('action') === 'edit_product') {
                window.history.replaceState({}, document.title, 'admin.php');
            }
        }

        function validateForm() {
            const price = document.getElementById('price').value;
            if (price <= 0) {
                alert('Цена должна быть больше 0!');
                return false;
            }
            return true;
        }

        // Показать первую вкладку по умолчанию
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.querySelector('.tab-content.active')) {
                showTab('products');
            }
        });
    </script>
        <script src="../js/lightbox.js"></script>
</body>
</html>