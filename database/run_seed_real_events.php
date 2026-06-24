<?php
/**
 * Наполнение каталога реальными волонтёрскими акциями (с обложками).
 * Запуск: D:\PHP\php\php.exe database/run_seed_real_events.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/real_events_seed.php';

$db = Database::getInstance();

echo "Загрузка изображений...\n";
realEventsImageCatalog();

echo "Создание акций...\n";
$result = seedRealEvents($db, true);

echo "Добавлено: {$result['inserted']}\n";
echo "Пропущено (уже есть): {$result['skipped']}\n";
echo "Обновлено обложек: {$result['updated']}\n";

$upcoming = (int)($db->fetchOne($db->query(
    "SELECT COUNT(*) AS c FROM Events
     WHERE IsPublished = 1 AND ModerationStatus = N'approved' AND EventDate >= GETDATE()"
))['c'] ?? 0);

echo "Предстоящих в каталоге: {$upcoming}\n";
