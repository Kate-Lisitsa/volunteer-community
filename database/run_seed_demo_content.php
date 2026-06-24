<?php
/**
 * Загрузка демо-изображений и наполнение акций/новостей.
 * Запуск из корня: D:\PHP\php\php.exe database/run_seed_demo_content.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/demo_events_seed.php';
require_once __DIR__ . '/../includes/demo_news_seed.php';

$db = Database::getInstance();

require_once __DIR__ . '/../includes/demo_content_data.php';

echo "Загрузка изображений...\n";
demoImageCatalog(demoForceRefreshImageKeys());

echo "Акции...\n";
seedDemoEventsIfNeeded($db, 12, true);

echo "Новости...\n";
seedDemoNewsIfNeeded($db, 14, true);

$events = (int)($db->fetchOne($db->query(
    "SELECT COUNT(*) AS c FROM Events WHERE IsPublished = 1 AND ModerationStatus = N'approved' AND EventDate >= GETDATE()"
))['c'] ?? 0);

$withCovers = (int)($db->fetchOne($db->query(
    "SELECT COUNT(*) AS c FROM Events WHERE IsPublished = 1 AND CoverImagePath IS NOT NULL AND CoverImagePath <> '' AND CoverImagePath NOT LIKE N'%dobrohub-mark%'"
))['c'] ?? 0);

$news = (int)($db->fetchOne($db->query(
    'SELECT COUNT(*) AS c FROM News WHERE IsPublished = 1'
))['c'] ?? 0);

$newsWithImg = (int)($db->fetchOne($db->query(
    'SELECT COUNT(DISTINCT NewsID) AS c FROM NewsImages'
))['c'] ?? 0);

echo "Готово.\n";
echo "Предстоящих акций: {$events}\n";
echo "Акций с обложками: {$withCovers}\n";
echo "Опубликованных новостей: {$news}\n";
echo "Новостей с фото: {$newsWithImg}\n";
