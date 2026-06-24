<?php
// pages/delete_event.php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = Database::getInstance();
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем информацию о событии
$sql = "SELECT * FROM Events WHERE EventID = ?";
$stmt = $db->query($sql, [$eventId]);
$event = $db->fetchOne($stmt);

// Проверяем права
if (!$event || ((int)$event['CreatorUserID'] !== (int)$_SESSION['user_id'] && !isAdmin())) {
    redirect(APP_URL . '/pages/profile.php');
}

if (!empty($event['CoverImagePath'])) {
    deleteStoredPublicFile($event['CoverImagePath']);
}

$oeTbl = $db->fetchOne($db->query("SELECT CAST(OBJECT_ID(N'dbo.EventOutcomeFiles', N'U') AS INT) AS oid"));
if (!empty($oeTbl['oid'])) {
    $outcomeFiles = $db->fetchAll($db->query(
        "SELECT f.FilePath FROM EventOutcomeFiles f
         INNER JOIN EventOutcomes o ON o.OutcomeID = f.OutcomeID
         WHERE o.EventID = ?",
        [$eventId]
    ));
    foreach ($outcomeFiles as $pf) {
        deleteStoredPublicFile($pf['FilePath'] ?? '');
    }
}

$db->query("DELETE FROM ActivityLog WHERE EventID = ?", [$eventId]);
$db->query("DELETE FROM Registrations WHERE EventID = ?", [$eventId]);
$db->query("DELETE FROM Events WHERE EventID = ?", [$eventId]);

// Перенаправляем в зависимости от роли
if (isAdmin()) {
    redirect(APP_URL . '/pages/admin/dashboard.php');
} else {
    redirect(APP_URL . '/pages/profile.php');
}
?>