<?php
/**
 * Напоминания на e-mail за сутки до даты акции (календарный «завтра» в часовом поясе Europe/Minsk).
 *
 * ВАЖНО: этот файл нужно запускать по расписанию ОДИН раз в сутки (например 08:00).
 * От посещений сайта письма не уходят — только когда сработает планировщик или URL-cron.
 *
 * Запуск:
 * - Командная строка: php cron_reminders.php  (из папки проекта)
 * - Windows: Планировщик задач — программа: полный путь к php.exe, аргументы: полный путь к этому файлу
 * - Хостинг с cron (Linux): 0 8 * * * /usr/bin/php /path/to/cron_reminders.php
 * - HTTP (если в config.php задан CRON_REMINDER_SECRET):
 *   https://ваш-домен/volunteer-community/cron_reminders.php?key=ВАШ_СЕКРЕТ
 *   Удобно, если нельзя настроить запуск php, но есть «cron по URL» в панели хостинга.
 *
 * Получатель: Users.Email. Отправитель (From): учётка администратора в БД, если MAIL_FROM_ADDRESS = noreply@localhost.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/event_reminders.php';

if (php_sapi_name() !== 'cli') {
    $secret = defined('CRON_REMINDER_SECRET') ? (string)CRON_REMINDER_SECRET : '';
    if ($secret === '' || !isset($_GET['key']) || !hash_equals($secret, (string)$_GET['key'])) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$sent = runScheduledEventReminders();

if (php_sapi_name() === 'cli') {
    echo "Reminders sent: {$sent}\n";
} elseif (!empty($_GET['key'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OK reminders=' . (int)$sent;
}
