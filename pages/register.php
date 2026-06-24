<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$error = '';
$success = '';
$last_name = trim($_POST['last_name'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$patronymic = trim($_POST['patronymic'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errLast = validatePersonNamePart($_POST['last_name'] ?? '', true, 'Фамилия');
    $errFirst = validatePersonNamePart($_POST['first_name'] ?? '', true, 'Имя');
    $errMiddle = validatePersonNamePart($_POST['patronymic'] ?? '', false, 'Отчество');

    if ($errLast || $errFirst || $errMiddle) {
        $error = implode(' ', array_filter([$errLast, $errFirst, $errMiddle]));
    } elseif (empty($email) || empty($password)) {
        $error = 'Заполните все обязательные поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email';
    } elseif ($password !== $confirm) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль не короче 6 символов';
    } elseif (!empty($phone) && !preg_match('/^\+375(29|33|44|25)\d{7}$/', $phone)) {
        $error = 'Телефон: формат +375291234567';
    } else {
        $fullname = buildFullNameFromParts($last_name, $first_name, $patronymic);
        $db = Database::getInstance();
        $stmt = $db->query("SELECT 1 FROM Users WHERE Email = ?", [$email]);
        if ($db->fetchOne($stmt)) {
            $error = 'Этот email уже занят';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->query(
                "INSERT INTO Users (Email, PasswordHash, FullName, Phone, Role, RegisteredAt) VALUES (?, ?, ?, ?, N'user', GETDATE())",
                [$email, $hash, $fullname, $phone]
            );
            $success = 'Готово. Теперь можно войти.';
        }
    }
}

$pageTitle = 'Регистрация';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page narrow">
        <div class="auth-panel surface-block">
            <h1 class="page-title">Регистрация</h1>
            <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
                <p><a class="btn" href="<?= APP_URL ?>/pages/login.php">Перейти ко входу</a></p>
            <?php else: ?>
                <form method="POST" class="stack-form">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" value="<?= escape($email ?? '') ?>" required>
                    </div>
                    <fieldset class="form-fieldset-fio">
                        <legend>ФИО *</legend>
                        <div class="form-row-fio">
                            <div class="form-group">
                                <label for="last_name">Фамилия *</label>
                                <input type="text" id="last_name" name="last_name" value="<?= escape($last_name ?? '') ?>" required autocomplete="family-name">
                            </div>
                            <div class="form-group">
                                <label for="first_name">Имя *</label>
                                <input type="text" id="first_name" name="first_name" value="<?= escape($first_name ?? '') ?>" required autocomplete="given-name">
                            </div>
                            <div class="form-group">
                                <label for="patronymic">Отчество</label>
                                <input type="text" id="patronymic" name="patronymic" value="<?= escape($patronymic ?? '') ?>" autocomplete="additional-name" placeholder="Необязательно">
                            </div>
                        </div>
                    </fieldset>
                    <div class="form-group">
                        <label for="phone">Телефон</label>
                        <input type="tel" id="phone" name="phone" value="<?= escape($phone ?? '') ?>" placeholder="+375291234567">
                    </div>
                    <div class="form-group">
                        <label for="password">Пароль *</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Пароль ещё раз *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-lg">Зарегистрироваться</button>
                </form>
                <p class="auth-extra">Уже есть аккаунт? <a href="<?= APP_URL ?>/pages/login.php">Вход</a></p>
            <?php endif; ?>
        </div>
    </main>
<?php include '../includes/html_foot.php'; ?>
