<?php
/**
 * Назначает реалистичных организаторов всем акциям.
 * Запуск: D:\PHP\php\php.exe database/run_assign_event_organizers.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/event_organizers.php';

$db = Database::getInstance();

echo "Назначение организаторов...\n";
$result = assignAllEventOrganizers($db);

echo "Всего акций: {$result['total']}\n";
echo "Обновлено: {$result['updated']}\n";
echo "Без изменений: {$result['skipped']}\n";

echo "\nПримеры:\n";
foreach ($db->fetchAll($db->query(
    'SELECT TOP 8 e.Title, u.FullName AS Organizer
     FROM Events e
     INNER JOIN Users u ON u.UserID = e.CreatorUserID
     ORDER BY e.EventDate DESC'
)) as $row) {
    echo "- {$row['Title']} → {$row['Organizer']}\n";
}
