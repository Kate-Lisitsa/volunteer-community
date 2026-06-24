<?php
/**
 * Прошедшие акции (архив): апрель–июнь, не в публичном каталоге.
 * Запуск: D:\PHP\php\php.exe database/run_seed_past_events.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/real_past_events_seed.php';

$db = Database::getInstance();

echo "Загрузка изображений...\n";
realPastEventsImageCatalog();

echo "Создание прошедших акций...\n";
$result = seedPastRealEvents($db, true);

echo "Добавлено: {$result['inserted']}\n";
echo "Пропущено (уже есть): {$result['skipped']}\n";
echo "Обновлено: {$result['updated']}\n";

$past = (int)($db->fetchOne($db->query(
    "SELECT COUNT(*) AS c FROM Events
     WHERE IsPublished = 1 AND ModerationStatus = N'approved' AND EventDate < GETDATE()"
))['c'] ?? 0);

$public = (int)($db->fetchOne($db->query(
    "SELECT COUNT(*) AS c FROM Events e
     INNER JOIN Categories c ON e.CategoryID = c.CategoryID
     WHERE " . sqlEventInPublicCatalog('e', 'c')
))['c'] ?? 0);

echo "Прошедших в архиве: {$past}\n";
echo "В публичном каталоге: {$public}\n";
