<?php
/**
 * Удаление тестовых новостей и обновление обложек акций.
 * D:\PHP\php\php.exe database/run_fix_demo_content.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/demo_events_seed.php';
require_once __DIR__ . '/../includes/demo_news_seed.php';

$db = Database::getInstance();

echo "Удаление тестовых новостей...\n";
demoCleanupTestNews($db);

echo "Обновление изображений и акций...\n";
seedDemoEventsIfNeeded($db, 12);
seedDemoNewsIfNeeded($db, 12);

$news = $db->fetchAll($db->query('SELECT NewsID, Title FROM News ORDER BY NewsID'));
echo "Новости (" . count($news) . "):\n";
foreach ($news as $row) {
    echo '  ' . $row['NewsID'] . ' | ' . $row['Title'] . "\n";
}

$titles = [
    'Кухня добрых дел: обед для нуждающихся',
    'Сортировка вещей в благотворительном магазине',
    'Посадка деревьев в Лошицком парке',
    'Эко-урок в школе: раздельный сбор отходов',
    'День донора в Минске',
    'Курс первой помощи для волонтёров',
];

echo "\nОбложки акций:\n";
foreach ($titles as $title) {
    $ev = $db->fetchOne($db->query('SELECT CoverImagePath FROM Events WHERE Title = ?', [$title]));
    echo '  ' . $title . ' => ' . ($ev['CoverImagePath'] ?? '—') . "\n";
}

echo "\nГотово.\n";
