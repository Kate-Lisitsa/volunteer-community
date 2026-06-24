<?php
/**
 * Упрощение схемы БД для ER-диаграммы (меньше таблиц и «ромбов»).
 *
 * Было: Users, Events, Categories, Registrations, News, NewsImages,
 *       ActivityLog, EventMaterials, EventOutcomes, EventOutcomeFiles.
 * Стало: Users, Events, Categories, Registrations, News, EventOutcomes
 *       + сняты лишние FK (Events→Users, News→Users/Events).
 *
 * Откат: database/schema_simplify_diagram_rollback.sql (структура; данные — из бэкапа БД).
 *
 * Запуск: D:\PHP\php\php.exe database/run_schema_simplify.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';

$db = Database::getInstance();

function step($msg) {
    echo $msg . "\n";
}

function tableExists($db, $table) {
    $row = $db->fetchOne($db->query(
        "SELECT OBJECT_ID(?, 'U') AS oid",
        ['dbo.' . $table]
    ));
    return !empty($row['oid']);
}

function columnExists($db, $table, $column) {
    $row = $db->fetchOne($db->query(
        "SELECT COL_LENGTH(?, ?) AS col",
        ['dbo.' . $table, $column]
    ));
    return !empty($row['col']);
}

function dropFkOnColumn($db, $table, $column) {
    $rows = $db->fetchAll($db->query(
        "SELECT fk.name AS fk_name
         FROM sys.foreign_keys fk
         INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
         INNER JOIN sys.columns cp ON fkc.parent_object_id = cp.object_id AND fkc.parent_column_id = cp.column_id
         INNER JOIN sys.tables tp ON tp.object_id = fk.parent_object_id
         WHERE tp.name = ? AND cp.name = ?",
        [$table, $column]
    ));
    foreach ($rows as $r) {
        $db->query('ALTER TABLE dbo.' . $table . ' DROP CONSTRAINT [' . $r['fk_name'] . ']');
        step('  FK удалён: ' . $r['fk_name']);
    }
}

step('=== DobroHub: упрощение схемы ===');

if (!columnExists($db, 'News', 'CoverImagePath')) {
    $db->query('ALTER TABLE dbo.News ADD CoverImagePath NVARCHAR(500) NULL');
    step('+ News.CoverImagePath');
}
if (!columnExists($db, 'News', 'GalleryJson')) {
    $db->query('ALTER TABLE dbo.News ADD GalleryJson NVARCHAR(MAX) NULL');
    step('+ News.GalleryJson');
}
if (!columnExists($db, 'EventOutcomes', 'FilesJson')) {
    $db->query('ALTER TABLE dbo.EventOutcomes ADD FilesJson NVARCHAR(MAX) NULL');
    step('+ EventOutcomes.FilesJson');
}
if (!columnExists($db, 'Registrations', 'CancelledAt')) {
    $db->query('ALTER TABLE dbo.Registrations ADD CancelledAt DATETIME NULL');
    step('+ Registrations.CancelledAt');
}

if (tableExists($db, 'NewsImages')) {
    step('Миграция NewsImages → News...');
    $newsRows = $db->fetchAll($db->query('SELECT NewsID FROM News'));
    foreach ($newsRows as $nr) {
        $nid = (int)$nr['NewsID'];
        $imgs = $db->fetchAll($db->query(
            'SELECT FilePath FROM NewsImages WHERE NewsID = ? ORDER BY SortOrder, ImageID',
            [$nid]
        ));
        if ($imgs === []) {
            continue;
        }
        $paths = array_values(array_filter(array_map(static function ($r) {
            return (string)($r['FilePath'] ?? '');
        }, $imgs)));
        if ($paths === []) {
            continue;
        }
        $cover = $paths[0];
        $extra = array_slice($paths, 1);
        $galleryJson = $extra === [] ? null : json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $db->query(
            'UPDATE News SET CoverImagePath = ?, GalleryJson = ? WHERE NewsID = ?',
            [$cover, $galleryJson, $nid]
        );
    }
    dropFkOnColumn($db, 'NewsImages', 'NewsID');
    $db->query('DROP TABLE dbo.NewsImages');
    step('- таблица NewsImages удалена');
}

if (tableExists($db, 'EventOutcomeFiles')) {
    step('Миграция EventOutcomeFiles → EventOutcomes.FilesJson...');
    $outcomes = $db->fetchAll($db->query('SELECT OutcomeID FROM EventOutcomes'));
    foreach ($outcomes as $o) {
        $oid = (int)$o['OutcomeID'];
        $files = $db->fetchAll($db->query(
            'SELECT FileID, MaterialType, FilePath, OriginalName, CreatedAt FROM EventOutcomeFiles WHERE OutcomeID = ? ORDER BY CreatedAt ASC',
            [$oid]
        ));
        if ($files === []) {
            continue;
        }
        $payload = [];
        foreach ($files as $f) {
            $payload[] = [
                'id' => (string)(int)$f['FileID'],
                'type' => (string)($f['MaterialType'] ?? 'file'),
                'path' => (string)($f['FilePath'] ?? ''),
                'name' => (string)($f['OriginalName'] ?? 'Файл'),
                'createdAt' => $f['CreatedAt'] instanceof DateTimeInterface
                    ? $f['CreatedAt']->format('c')
                    : (string)($f['CreatedAt'] ?? ''),
            ];
        }
        $db->query(
            'UPDATE EventOutcomes SET FilesJson = ? WHERE OutcomeID = ?',
            [json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $oid]
        );
    }
    dropFkOnColumn($db, 'EventOutcomeFiles', 'OutcomeID');
    $db->query('DROP TABLE dbo.EventOutcomeFiles');
    step('- таблица EventOutcomeFiles удалена');
}

if (tableExists($db, 'ActivityLog')) {
    step('Миграция отмен записей ActivityLog → Registrations.CancelledAt...');
    $cancels = $db->fetchAll($db->query(
        "SELECT UserID, EventID, ActionDate FROM ActivityLog WHERE ActionType = N'cancel_registration'"
    ));
    foreach ($cancels as $c) {
        $db->query(
            'UPDATE Registrations SET CancelledAt = ?, Status = N\'cancelled\'
             WHERE UserID = ? AND EventID = ? AND CancelledAt IS NULL',
            [$c['ActionDate'], (int)$c['UserID'], (int)$c['EventID']]
        );
    }
    dropFkOnColumn($db, 'ActivityLog', 'UserID');
    dropFkOnColumn($db, 'ActivityLog', 'EventID');
    $db->query('DROP TABLE dbo.ActivityLog');
    step('- таблица ActivityLog удалена');
}

if (tableExists($db, 'EventMaterials')) {
    dropFkOnColumn($db, 'EventMaterials', 'EventID');
    dropFkOnColumn($db, 'EventMaterials', 'UserID');
    $db->query('DROP TABLE dbo.EventMaterials');
    step('- таблица EventMaterials удалена (не использовалась приложением)');
}

step('Снятие лишних FK для упрощения диаграммы (столбцы сохраняются)...');
dropFkOnColumn($db, 'Events', 'CreatorUserID');
dropFkOnColumn($db, 'News', 'AuthorUserID');
dropFkOnColumn($db, 'News', 'RelatedEventID');

step('Готово. Таблицы:');
require __DIR__ . '/_list_tables.php';
