<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $db = Database::getInstance();
    $row = $db->fetchOne($db->query("SELECT IsActive FROM Users WHERE Email = ?", [$email]));
    if ($row && empty($row['IsActive'])) {
        $error = 'Учётная запись заблокирована администратором.';
    } elseif (loginUser($email, $password)) {
        redirect(APP_URL . '/index.php');
    } else {
        $error = 'Неверный email или пароль';
    }
}

$pageTitle = 'Вход';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page narrow">
        <div class="auth-panel surface-block">
            <h1 class="page-title">Вход</h1>
            <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>
            <form method="POST" class="stack-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-lg">Войти</button>
            </form>
            <p class="auth-extra">Нет аккаунта? <a href="<?= APP_URL ?>/pages/register.php">Регистрация</a></p>
        </div>
    </main>
<?php include '../includes/html_foot.php'; ?>
