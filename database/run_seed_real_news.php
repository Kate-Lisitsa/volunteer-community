<?php
/**
 * Наполнение раздела новостей реальными материалами (с обложками).
 * Запуск: D:\PHP\php\php.exe database/run_seed_real_news.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/real_news_seed.php';

$db = Database::getInstance();

echo "Загрузка изображений новостей...\n";
realNewsImageCatalog();

echo "Создание новостей...\n";
$result = seedRealNews($db, true);

echo "Добавлено: {$result['inserted']}\n";
echo "Пропущено (уже есть): {$result['skipped']}\n";
echo "Обновлено: {$result['updated']}\n";

$published = (int)($db->fetchOne($db->query(
    'SELECT COUNT(*) AS c FROM News WHERE IsPublished = 1'
))['c'] ?? 0);

echo "Опубликованных новостей: {$published}\n";
