<?php
// pages/change_password.php - Смена пароля
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Получаем текущий хеш пароля
    $sql = "SELECT PasswordHash FROM Users WHERE UserID = ?";
    $stmt = $db->query($sql, [$userId]);
    $user = $db->fetchOne($stmt);
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Заполните все поля';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Новый пароль и подтверждение не совпадают';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } elseif (!password_verify($currentPassword, $user['PasswordHash'])) {
        $error = 'Текущий пароль неверен';
    } else {
        // Хешируем новый пароль
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Обновляем пароль
        $sql = "UPDATE Users SET PasswordHash = ? WHERE UserID = ?";
        $db->query($sql, [$newHash, $userId]);
        
        $success = 'Пароль успешно изменен';
    }
}

$pageTitle = 'Смена пароля';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page narrow">
        <h1 class="page-title">Смена пароля</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
            <p><a href="<?= APP_URL ?>/pages/profile.php" class="btn">В профиль</a></p>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="surface-form">
                <div class="form-group">
                    <label for="current_password">Текущий пароль:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Новый пароль (мин. 6 символов):</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Подтвердите новый пароль:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Сохранить</button>
                    <a href="<?= APP_URL ?>/pages/edit_profile.php" class="btn btn-secondary">Назад</a>
                </div>
            </form>
        <?php endif; ?>
    </main>
<?php include '../includes/html_foot.php'; ?>