<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db_connect.php';
$db = Database::getInstance();
echo "=== EVENTS ===\n";
foreach ($db->fetchAll($db->query('SELECT e.EventID, e.Title, e.ModerationStatus, e.IsPublished, u.Email FROM Events e LEFT JOIN Users u ON e.CreatorUserID=u.UserID ORDER BY e.EventID')) as $r) {
    echo $r['EventID'].' | '.$r['ModerationStatus'].' | '.$r['Email'].' | '.$r['Title']."\n";
}
echo "\n=== ORPHAN REGISTRATIONS ===\n";
$o = $db->fetchOne($db->query('SELECT COUNT(*) c FROM Registrations r WHERE NOT EXISTS (SELECT 1 FROM Events e WHERE e.EventID=r.EventID)'));
echo $o['c']."\n";
echo "\n=== ORPHAN ACTIVITY ===\n";
$o2 = $db->fetchOne($db->query('SELECT COUNT(*) c FROM ActivityLog a WHERE a.EventID IS NOT NULL AND NOT EXISTS (SELECT 1 FROM Events e WHERE e.EventID=a.EventID)'));
echo $o2['c']."\n";
echo "\n=== NEWS ===\n";
foreach ($db->fetchAll($db->query('SELECT NewsID, Title FROM News ORDER BY NewsID')) as $r) {
    echo $r['NewsID'].' | '.$r['Title']."\n";
}
