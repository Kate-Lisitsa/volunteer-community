<?php
// pages/edit_profile.php - Редактирование профиля
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

$sql = "SELECT * FROM Users WHERE UserID = ?";
$stmt = $db->query($sql, [$userId]);
$user = $db->fetchOne($stmt);

$last_name = '';
$first_name = '';
$patronymic = '';
if ($user) {
    [$last_name, $first_name, $patronymic] = parseFullNameParts($user['FullName'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $patronymic = trim($_POST['patronymic'] ?? '');

    $errLast = validatePersonNamePart($_POST['last_name'] ?? '', true, 'Фамилия');
    $errFirst = validatePersonNamePart($_POST['first_name'] ?? '', true, 'Имя');
    $errMiddle = validatePersonNamePart($_POST['patronymic'] ?? '', false, 'Отчество');

    if ($errLast || $errFirst || $errMiddle) {
        $error = implode(' ', array_filter([$errLast, $errFirst, $errMiddle]));
    } elseif (empty($email)) {
        $error = 'Укажите электронную почту';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный формат email';
    } elseif (!empty($phone) && !preg_match('/^\+375(29|33|44|25)\d{7}$/', $phone)) {
        $error = 'Телефон: формат +375291234567';
    } else {
        $fullname = buildFullNameFromParts($last_name, $first_name, $patronymic);
        $sql = "SELECT 1 FROM Users WHERE Email = ? AND UserID != ?";
        $stmtDup = $db->query($sql, [$email, $userId]);
        if ($db->fetchOne($stmtDup)) {
            $error = 'Этот email уже используется другим пользователем';
        } elseif ($user) {
            $sqlUp = "UPDATE Users SET FullName = ?, Email = ?, Phone = ? WHERE UserID = ?";
            $db->query($sqlUp, [$fullname, $email, $phone, $userId]);

            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_email'] = $email;

            $success = 'Профиль успешно обновлен';

            $user['FullName'] = $fullname;
            $user['Email'] = $email;
            $user['Phone'] = $phone;
        }
    }
}

$pageTitle = 'Редактирование профиля';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <h1 class="page-title">Редактирование профиля</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="surface-form">
            <fieldset class="form-fieldset-fio">
                <legend>ФИО *</legend>
                <div class="form-row-fio">
                    <div class="form-group">
                        <label for="last_name">Фамилия *</label>
                        <input type="text" id="last_name" name="last_name" value="<?= escape($last_name) ?>" required autocomplete="family-name">
                    </div>
                    <div class="form-group">
                        <label for="first_name">Имя *</label>
                        <input type="text" id="first_name" name="first_name" value="<?= escape($first_name) ?>" required autocomplete="given-name">
                    </div>
                    <div class="form-group">
                        <label for="patronymic">Отчество</label>
                        <input type="text" id="patronymic" name="patronymic" value="<?= escape($patronymic) ?>" autocomplete="additional-name" placeholder="Необязательно">
                    </div>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="email">Email *:</label>
                <input type="email" id="email" name="email" value="<?= escape($user['Email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" value="<?= escape($user['Phone'] ?? '') ?>" 
                       placeholder="+375291234567">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Сохранить</button>
                <a href="<?= APP_URL ?>/pages/profile.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
        
        <p style="margin-top:1.5rem"><a class="btn btn-secondary" href="<?= APP_URL ?>/pages/change_password.php">Сменить пароль</a></p>
        
        <?php if (isAdmin()): ?>
            <p style="margin-top:1rem"><a class="btn" href="<?= APP_URL ?>/pages/admin/dashboard.php">Админ-панель</a></p>
        <?php endif; ?>
    </main>
<?php include '../includes/html_foot.php'; ?>