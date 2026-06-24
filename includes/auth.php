<?php
// includes/auth.php
require_once 'db_connect.php';

function loginUser($email, $password) {
    $db = Database::getInstance();
    $sql = "SELECT * FROM Users WHERE Email = ? AND IsActive = 1";
    $stmt = $db->query($sql, [$email]);
    $user = $db->fetchOne($stmt);
    
    if ($user) {
        // Проверяем пароль с помощью password_verify()
        if (password_verify($password, $user['PasswordHash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['UserID'];
            $_SESSION['user_email'] = $user['Email'];
            $_SESSION['user_name'] = $user['FullName'];
            $_SESSION['user_role'] = $user['Role'];
            return true;
        }
    }
    return false;
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }
    session_destroy();
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/pages/login.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        redirect(APP_URL . '/index.php');
    }
}
?>