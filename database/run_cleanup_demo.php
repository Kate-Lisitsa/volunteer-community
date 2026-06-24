<?php
/**
 * Удаление демо-акций и демо-новостей из БД.
 * Запуск: php database/run_cleanup_demo.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/demo_cleanup.php';

$db = Database::getInstance();
$result = deleteAllDemoAndTestContent($db);

echo "Демо-акций удалено: {$result['demo_events']}\n";
echo "Демо-новостей удалено: {$result['demo_news']}\n";
echo "Тестовых акций удалено: {$result['test_events']}\n";
echo "Тестовых новостей удалено: {$result['test_news']}\n";
echo "Сироты: регистрации {$result['orphans']['registrations']}, активность {$result['orphans']['activity']}, новости {$result['orphans']['news']}\n";

$eventsLeft = (int)($db->fetchOne($db->query('SELECT COUNT(*) AS c FROM Events'))['c'] ?? 0);
$newsLeft = (int)($db->fetchOne($db->query('SELECT COUNT(*) AS c FROM News'))['c'] ?? 0);

echo "Осталось акций: {$eventsLeft}\n";
echo "Осталось новостей: {$newsLeft}\n";

if ($eventsLeft > 0) {
    echo "\nОставшиеся акции:\n";
    foreach ($db->fetchAll($db->query(
        "SELECT e.EventID, e.Title, e.ModerationStatus, u.Email
         FROM Events e LEFT JOIN Users u ON e.CreatorUserID = u.UserID
         ORDER BY e.EventID"
    )) as $row) {
        echo "  #{$row['EventID']} [{$row['ModerationStatus']}] {$row['Email']}: {$row['Title']}\n";
    }
}
