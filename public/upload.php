<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['success' => false, 'message' => 'Необходима авторизация.']));
}

$user_id = $_SESSION['user_id'];
$result = ['success' => false, 'message' => ''];

// Определяем тип загрузки (аватар или товар)
$is_avatar = true; // По умолчанию для обратной совместимости

$uploadDir = __DIR__ . '/uploads/avatars/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Размер файла превышает значение upload_max_filesize в php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает значение MAX_FILE_SIZE в HTML-форме.',
        UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично.',
        UPLOAD_ERR_NO_FILE => 'Файл не был загружен.',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
        UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла.'
    ];
    
    $errorCode = $_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE;
    $result['message'] = $errorMessages[$errorCode] ?? 'Неизвестная ошибка загрузки файла.';
    echo json_encode($result);
    exit;
}

$file = $_FILES['attachment'];

// Проверки ТОЛЬКО для аватаров
if ($is_avatar) {
    // 1. Проверка размера файла (< 8MB)
    $maxSize = 8 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $result['message'] = 'Файл слишком большой. Максимальный размер: 8MB.';
        echo json_encode($result);
        exit;
    }

    // 2. Проверка типа файла
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension'] ?? '');

    if (!in_array($extension, $allowedExtensions)) {
        $result['message'] = 'Можно загружать только изображения (JPG, PNG, GIF, WEBP).';
        echo json_encode($result);
        exit;
    }

    // 3. Проверка MIME-типа
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileMimeType = mime_content_type($file['tmp_name']);

    if (!in_array($fileMimeType, $allowedMimeTypes)) {
        $result['message'] = 'Недопустимый тип файла.';
        echo json_encode($result);
        exit;
    }

    // 4. Проверка: является ли файл изображением
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        $result['message'] = 'Загружаемый файл не является изображением.';
        echo json_encode($result);
        exit;
    }

    // 5. Проверка размеров изображения
    list($width, $height) = $imageInfo;
    $maxWidth = 1280;
    $maxHeight = 720;

    if ($width > $maxWidth || $height > $maxHeight) {
        $result['message'] = "Размер изображения не должен превышать {$maxWidth}x{$maxHeight}px.";
        echo json_encode($result);
        exit;
    }
}

// Удаляем старый аватар
try {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $oldAvatar = $stmt->fetchColumn();
    
    if ($oldAvatar) {
        $cleanPath = str_replace('../', '', $oldAvatar);
        $oldAvatarPath = __DIR__ . '/' . $cleanPath;
        if (file_exists($oldAvatarPath)) {
            unlink($oldAvatarPath);
        }
    }
} catch (PDOException $e) {
    error_log('Ошибка при удалении старого аватара: ' . $e->getMessage());
}

// Сохраняем новый файл
$fileInfo = pathinfo($file['name']);
$extension = strtolower($fileInfo['extension']);
$newFileName = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
$newFilePath = $uploadDir . $newFileName;

if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
    $relativePath = 'uploads/avatars/' . $newFileName;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt->execute([$relativePath, $user_id])) {
            $result['success'] = true;
            $result['message'] = 'Аватар успешно обновлен!';
            $result['path'] = $relativePath;
            $_SESSION['user_avatar'] = $relativePath;
        } else {
            $result['message'] = 'Ошибка при сохранении в базу данных.';
            if (file_exists($newFilePath)) {
                unlink($newFilePath);
            }
        }
    } catch (PDOException $e) {
        $result['message'] = 'Ошибка базы данных: ' . $e->getMessage();
        if (file_exists($newFilePath)) {
            unlink($newFilePath);
        }
    }
} else {
    $result['message'] = 'Ошибка при загрузке файла на сервер.';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>