<?php
/**
 * Полный откат упрощения схемы БД.
 * D:\PHP\php\php.exe database/run_schema_rollback.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';

$db = Database::getInstance();

function step($msg) {
    echo $msg . "\n";
}

function tableExists($db, $table) {
    $row = $db->fetchOne($db->query("SELECT OBJECT_ID(?, 'U') AS oid", ['dbo.' . $table]));
    return !empty($row['oid']);
}

function columnExists($db, $table, $column) {
    $row = $db->fetchOne($db->query("SELECT COL_LENGTH(?, ?) AS col", ['dbo.' . $table, $column]));
    return !empty($row['col']);
}

step('=== Откат схемы VolunteerCommunity ===');

$sqlFile = __DIR__ . '/schema_simplify_diagram_rollback.sql';
$batch = preg_split('/^\s*GO\s*$/mi', file_get_contents($sqlFile));
foreach ($batch as $chunk) {
    $chunk = trim($chunk);
    if ($chunk === '' || stripos($chunk, 'USE [') === 0 || preg_match('/^\s*PRINT\b/i', $chunk)) {
        continue;
    }
    $stmt = sqlsrv_query($db->getConnection(), $chunk);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $fatal = array_filter($errors ?: [], static function ($e) {
            return ($e['SQLSTATE'] ?? '') !== '01000';
        });
        if ($fatal) {
            die('Ошибка SQL: ' . print_r($fatal, true));
        }
    }
}
step('Таблицы и FK восстановлены (структура).');

if (columnExists($db, 'News', 'CoverImagePath') || columnExists($db, 'News', 'GalleryJson')) {
    step('News → NewsImages...');
    $newsRows = $db->fetchAll($db->query('SELECT NewsID, CoverImagePath, GalleryJson FROM News'));
    foreach ($newsRows as $n) {
        $nid = (int)$n['NewsID'];
        $exists = $db->fetchOne($db->query('SELECT TOP 1 ImageID FROM NewsImages WHERE NewsID = ?', [$nid]));
        if ($exists) {
            continue;
        }
        $paths = [];
        if (!empty($n['CoverImagePath'])) {
            $paths[] = (string)$n['CoverImagePath'];
        }
        if (!empty($n['GalleryJson'])) {
            $extra = json_decode((string)$n['GalleryJson'], true);
            if (is_array($extra)) {
                foreach ($extra as $p) {
                    if (is_string($p) && $p !== '') {
                        $paths[] = $p;
                    }
                }
            }
        }
        $paths = array_values(array_unique($paths));
        foreach ($paths as $i => $path) {
            $db->query(
                'INSERT INTO NewsImages (NewsID, FilePath, SortOrder) VALUES (?, ?, ?)',
                [$nid, $path, $i]
            );
        }
    }
}

if (columnExists($db, 'EventOutcomes', 'FilesJson')) {
    step('EventOutcomes → EventOutcomeFiles...');
    $outcomes = $db->fetchAll($db->query('SELECT OutcomeID, FilesJson FROM EventOutcomes WHERE FilesJson IS NOT NULL'));
    foreach ($outcomes as $o) {
        $oid = (int)$o['OutcomeID'];
        $exists = $db->fetchOne($db->query('SELECT TOP 1 FileID FROM EventOutcomeFiles WHERE OutcomeID = ?', [$oid]));
        if ($exists) {
            continue;
        }
        $decoded = json_decode((string)$o['FilesJson'], true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['path'])) {
                continue;
            }
            $created = $item['createdAt'] ?? null;
            if ($created instanceof DateTimeInterface) {
                $createdSql = $created->format('Y-m-d H:i:s');
            } elseif (is_string($created) && $created !== '') {
                $ts = strtotime($created);
                $createdSql = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
            } else {
                $createdSql = date('Y-m-d H:i:s');
            }
            $db->query(
                'INSERT INTO EventOutcomeFiles (OutcomeID, MaterialType, FilePath, OriginalName, CreatedAt) VALUES (?, ?, ?, ?, ?)',
                [$oid, $item['type'] ?? 'file', $item['path'], $item['name'] ?? 'Файл', $createdSql]
            );
        }
    }
}

if (tableExists($db, 'ActivityLog')) {
    $cnt = (int)($db->fetchOne($db->query('SELECT COUNT(*) AS c FROM ActivityLog'))['c'] ?? 0);
    if ($cnt === 0) {
        step('Восстановление ActivityLog...');
        $db->query(
            "INSERT INTO ActivityLog (UserID, EventID, ActionType, ActionDate)
             SELECT e.CreatorUserID, e.EventID, N'create_event', e.CreatedAt FROM Events e"
        );
        $db->query(
            "INSERT INTO ActivityLog (UserID, EventID, ActionType, ActionDate)
             SELECT r.UserID, r.EventID, N'register', r.RegisteredAt
             FROM Registrations r WHERE r.Status = N'confirmed' AND r.RegisteredAt IS NOT NULL"
        );
        if (columnExists($db, 'Registrations', 'CancelledAt')) {
            $db->query(
                "INSERT INTO ActivityLog (UserID, EventID, ActionType, ActionDate)
                 SELECT r.UserID, r.EventID, N'cancel_registration', r.CancelledAt
                 FROM Registrations r WHERE r.CancelledAt IS NOT NULL"
            );
        }
    }
}

if (columnExists($db, 'News', 'CoverImagePath')) {
    $db->query('ALTER TABLE dbo.News DROP COLUMN CoverImagePath');
    step('- News.CoverImagePath удалён');
}
if (columnExists($db, 'News', 'GalleryJson')) {
    $db->query('ALTER TABLE dbo.News DROP COLUMN GalleryJson');
    step('- News.GalleryJson удалён');
}
if (columnExists($db, 'EventOutcomes', 'FilesJson')) {
    $db->query('ALTER TABLE dbo.EventOutcomes DROP COLUMN FilesJson');
    step('- EventOutcomes.FilesJson удалён');
}
if (columnExists($db, 'Registrations', 'CancelledAt')) {
    $db->query('ALTER TABLE dbo.Registrations DROP COLUMN CancelledAt');
    step('- Registrations.CancelledAt удалён');
}

step('Готово. Таблицы:');
require __DIR__ . '/_list_tables.php';
