<?php
/**
 * Перекачивает все обложки акций и новостей из каталогов.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/real_events_seed.php';
require_once __DIR__ . '/../includes/real_news_seed.php';

$db = Database::getInstance();

echo "Акции...\n";
$events = refreshRealEventCovers($db);
echo "  обновлено в БД: {$events['updated']}\n";

echo "Новости...\n";
realNewsImageCatalog([
    'relay_brsm', 'online_camp', 'seven_steps', 'good_route', 'women_club',
    'forest_order', 'wish_tree', 'health_active', 'children_day',
]);
$news = seedRealNews($db, true);
echo "  вставлено: {$news['inserted']}, обновлено: {$news['updated']}, пропущено: {$news['skipped']}\n";
