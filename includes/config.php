<?php
// includes/config.php
define('DB_HOST', '(localdb)\\mssqllocaldb');
define('DB_NAME', 'VolunteerCommunity');
define('DB_USER', '');
define('DB_PASS', '');

define('APP_NAME', 'DobroHub');
define('APP_ROOT', dirname(__DIR__));

require_once __DIR__ . '/session_bootstrap.php';

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', detectAppBasePath());
}
if (!defined('APP_URL')) {
    define('APP_URL', detectAppUrl());
}

/**
 * От кого уходят письма — ящик Gmail для SMTP и поля From.
 * Пароль приложения Google: https://myaccount.google.com/apppasswords (нужна двухэтапная аутентификация).
 */
define('MAIL_FROM_ADDRESS', 'katelisitsa5@gmail.com');
define('MAIL_FROM_NAME', 'DobroHub');

define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 465);
define('MAIL_SMTP_SECURE', 'ssl');
define('MAIL_SMTP_USER', 'katelisitsa5@gmail.com');
/** Пароль приложения Google (16 символов), не обычный пароль от аккаунта */
define('MAIL_SMTP_PASS', 'kmrdywunwntfabqn');

/**
 * Секрет для запуска cron_reminders.php по HTTP (?key=...). Пустая строка — только CLI (рекомендуется).
 */
define('CRON_REMINDER_SECRET', '');

/**
 * User-Agent для запросов к Nominatim (в правилах OSM нужен реальный контакт).
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
define('NOMINATIM_USER_AGENT', 'DobroHub/1.0 (volunteer app; contact: ' . MAIL_FROM_ADDRESS . ')');

bootstrapAppSession();

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Europe/Minsk');
?>