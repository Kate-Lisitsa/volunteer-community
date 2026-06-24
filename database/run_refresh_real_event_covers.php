<?php
/**
 * Перекачивает обложки реальных акций и обновляет пути в БД.
 * Запуск: D:\PHP\php\php.exe database/run_refresh_real_event_covers.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/real_events_seed.php';

$db = Database::getInstance();

echo "Перекачивание обложек акций...\n";
$result = refreshRealEventCovers($db);

echo "Обновлено записей в БД: {$result['updated']}\n";
echo "Акций не найдено в каталоге: {$result['missing']}\n";
