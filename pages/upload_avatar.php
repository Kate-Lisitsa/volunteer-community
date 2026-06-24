<?php
// pages/upload_avatar.php - Загрузка аватарки
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Создаем папку для аватарок, если её нет
$uploadDir = APP_ROOT . '/assets/uploads/avatars/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    
    // Проверяем, был ли загружен файл
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Выберите файл для загрузки';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка при загрузке файла';
    } else {
        // Проверяем тип файла
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $file['tmp_name']);
        finfo_close($fileInfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $error = 'Разрешены только JPG, PNG и GIF файлы';
        } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB
            $error = 'Файл не должен превышать 2MB';
        } else {
            // Генерируем уникальное имя файла
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Удаляем старую аватарку, если она есть
                $sql = "SELECT AvatarPath FROM Users WHERE UserID = ?";
                $stmt = $db->query($sql, [$userId]);
                $oldAvatar = $db->fetchOne($stmt)['AvatarPath'];
                
                if ($oldAvatar && file_exists($uploadDir . $oldAvatar)) {
                    unlink($uploadDir . $oldAvatar);
                }
                
                // Сохраняем путь в БД
                $sql = "UPDATE Users SET AvatarPath = ? WHERE UserID = ?";
                $db->query($sql, [$filename, $userId]);
                
                $success = 'Аватар успешно загружен';
            } else {
                $error = 'Ошибка при сохранении файла';
            }
        }
    }
}

// Получаем текущую аватарку
$sql = "SELECT AvatarPath FROM Users WHERE UserID = ?";
$stmt = $db->query($sql, [$userId]);
$avatar = $db->fetchOne($stmt)['AvatarPath'];

$pageTitle = 'Аватар';
$headExtra = '<style>.avatar-preview{max-width:200px;max-height:200px;border-radius:50%;margin:1rem 0;border:var(--border-width) solid var(--line)}.current-avatar{text-align:center;margin-bottom:1.5rem}</style>';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <h1 class="page-title">Аватар</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <?php if ($avatar && file_exists($uploadDir . $avatar)): ?>
            <div class="current-avatar">
                <h3 class="section-title">Сейчас</h3>
                <img src="<?= APP_URL ?>/assets/uploads/avatars/<?= escape($avatar) ?>" alt="" class="avatar-preview">
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="surface-form">
            <div class="form-group">
                <label for="avatar">Выберите изображение (JPG, PNG, GIF, до 2MB):</label>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Загрузить</button>
                <a href="<?= APP_URL ?>/pages/profile.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </main>
<?php include '../includes/html_foot.php'; ?>