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

// Обработка действий с товарами
$action = $_GET['action'] ?? '';
$product_id = $_GET['id'] ?? 0;
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
            $filePath = __DIR__ . '/' . $imagePath;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $message = 'Товар успешно удален!';
    } else {
        $message = 'Ошибка при удалении товара!';
    }
}

// Обработка формы ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ товара
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $category = trim($_POST['category']);
    $id = $_POST['id'] ?? 0; // 0 для новой записи

    // Валидация
    if (empty($name) || $price <= 0) {
        $message = 'Название и цена обязательны для заполнения!';
    } else {
        // Обработка загрузки изображения
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileInfo = pathinfo($_FILES['image']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($extension, $allowedExtensions)) {
                // Удаляем старое изображение при редактировании
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldImage = $stmt->fetchColumn();
                    if ($oldImage) {
                        $oldImagePath = __DIR__ . '/' . $oldImage;
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                }

                // Генерируем уникальное имя для файла
                $newFileName = 'product_' . ($id > 0 ? $id : time()) . '.' . $extension;
                $newFilePath = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $newFilePath)) {
                    $imagePath = 'uploads/products/' . $newFileName;
                }
            }
        } elseif ($id > 0) {
            // Если новое изображение не загрузили, но редактируем существующий товар - оставляем старый путь
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $imagePath = $stmt->fetchColumn();
        }

        // Сохранение в БД
        if ($id > 0) {
            // РЕДАКТИРОВАНИЕ существующего товара
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, image = COALESCE(?, image) WHERE id = ?");
            $success = $stmt->execute([$name, $description, $price, $category, $imagePath, $id]);
            $message = $success ? 'Товар успешно обновлен!' : 'Ошибка при обновлении товара!';
        } else {
            // ДОБАВЛЕНИЕ нового товара
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, image) VALUES (?, ?, ?, ?, ?)");
            $success = $stmt->execute([$name, $description, $price, $category, $imagePath]);
            $message = $success ? 'Товар успешно добавлен!' : 'Ошибка при добавлении товара!';
        }
    }
}

// Получаем список всех товаров
$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Получаем список всех пользователей
$users = $pdo->query("SELECT id, username, email, first_name, last_name, is_admin, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Получаем данные товара для редактирования
$editProduct = null;
if ($action === 'edit_product' && $product_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
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
            <a href="#" onclick="showTab('add-product')"><?= $editProduct ? 'Редактировать товар' : 'Добавить товар' ?></a>
        </div>

        <!-- ВКЛАДКА: ДОБАВЛЕНИЕ/РЕДАКТИРОВАНИЕ ТОВАРА -->
        <div id="add-product" class="tab-content <?= ($action === 'edit_product' || $action === 'add_product') ? 'active' : '' ?>">
            <div class="admin-section">
                <h2><?= $editProduct ? 'Редактировать товар' : 'Добавить новый товар' ?></h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Название товара:</label>
                        <input type="text" id="name" name="name" value="<?= $editProduct['name'] ?? '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Описание:</label>
                        <textarea id="description" name="description" rows="4"><?= $editProduct['description'] ?? '' ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Цена (руб.):</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?= $editProduct['price'] ?? '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Категория:</label>
                        <input type="text" id="category" name="category" value="<?= $editProduct['category'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="image">Изображение товара:</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <?php if ($editProduct && $editProduct['image']): ?>
                            <div class="image-preview">
                                <img src="../<?= $editProduct['image'] ?>" alt="Текущее изображение">
                                <p>Текущее изображение</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-add"><?= $editProduct ? 'Обновить товар' : 'Добавить товар' ?></button>
                        <?php if ($editProduct): ?>
                            <a href="admin.php" class="btn btn-cancel">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ВКЛАДКА: ВСЕ ТОВАРЫ -->
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
                                    <img src="../<?= $product['image'] ?>" alt="<?= $product['name'] ?>" class="table-image">
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

        // Показать первую вкладку по умолчанию
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.querySelector('.tab-content.active')) {
                showTab('products');
            }
        });
    </script>
</body>
</html>