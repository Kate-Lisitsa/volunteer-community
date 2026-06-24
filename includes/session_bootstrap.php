<?php
/**
 * Единый запуск сессии: своё имя cookie, путь приложения, локальное хранилище.
 * Избегает конфликтов с другими PHP-проектами на localhost и потери PHPSESSID.
 */

function detectAppBasePath(): string
{
    if (PHP_SAPI === 'cli') {
        return '/volunteer-community';
    }

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = realpath(APP_ROOT);
    if ($docRoot && $appRoot && strncmp($appRoot, $docRoot, strlen($docRoot)) === 0) {
        $rel = substr($appRoot, strlen($docRoot));
        $rel = '/' . trim(str_replace('\\', '/', $rel), '/');
        return $rel === '/' ? '' : $rel;
    }

    return '/volunteer-community';
}

function detectAppUrl(): string
{
    if (PHP_SAPI === 'cli') {
        return 'http://localhost/volunteer-community';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https'
        : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = detectAppBasePath();

    return $scheme . '://' . $host . $base;
}

function bootstrapAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $basePath = detectAppBasePath();
    $cookiePath = ($basePath === '' ? '/' : rtrim($basePath, '/') . '/');

    $secure = false;
    if (PHP_SAPI !== 'cli') {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    $sessionDir = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
        $sessionDir = '';
    }
    if ($sessionDir !== '' && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    session_name('DOBROHUBSESSID');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $cookiePath, '', $secure, true);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '86400');

    session_start();
}
