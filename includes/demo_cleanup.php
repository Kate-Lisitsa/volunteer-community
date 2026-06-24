<?php
require_once __DIR__ . '/demo_content_data.php';

/** Все заголовки демо-акций (включая legacy). */
function demoCollectEventTitles(): array
{
    $titles = [];
    foreach (demoEventsCatalog() as $item) {
        $titles[] = $item['title'];
        if (!empty($item['legacyTitles']) && is_array($item['legacyTitles'])) {
            foreach ($item['legacyTitles'] as $legacy) {
                $titles[] = $legacy;
            }
        }
    }

    return array_values(array_unique($titles));
}

/** Все заголовки демо-новостей (включая legacy). */
function demoCollectNewsTitles(): array
{
    $titles = [];
    foreach (demoNewsCatalog() as $item) {
        $titles[] = $item['title'];
        if (!empty($item['legacyTitles']) && is_array($item['legacyTitles'])) {
            foreach ($item['legacyTitles'] as $legacy) {
                $titles[] = $legacy;
            }
        }
    }

    return array_values(array_unique($titles));
}

function deleteEventCompletely($db, int $eventId): void
{
    if ($eventId <= 0) {
        return;
    }

    $event = $db->fetchOne($db->query('SELECT CoverImagePath FROM Events WHERE EventID = ?', [$eventId]));
    if (!$event) {
        return;
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

    $matTbl = $db->fetchOne($db->query("SELECT CAST(OBJECT_ID(N'dbo.EventMaterials', N'U') AS INT) AS oid"));
    if (!empty($matTbl['oid'])) {
        $materials = $db->fetchAll($db->query(
            'SELECT FilePath FROM EventMaterials WHERE EventID = ?',
            [$eventId]
        ));
        foreach ($materials as $row) {
            deleteStoredPublicFile($row['FilePath'] ?? '');
        }
    }

    $db->query('DELETE FROM ActivityLog WHERE EventID = ?', [$eventId]);
    $db->query('DELETE FROM Registrations WHERE EventID = ?', [$eventId]);
    $db->query('DELETE FROM Events WHERE EventID = ?', [$eventId]);
}

function deleteNewsCompletely($db, int $newsId): void
{
    if ($newsId <= 0) {
        return;
    }

    $hasNewsImages = !empty($db->fetchOne($db->query(
        "SELECT OBJECT_ID('dbo.NewsImages', 'U') AS tid"
    ))['tid']);

    if ($hasNewsImages) {
        $images = $db->fetchAll($db->query('SELECT FilePath FROM NewsImages WHERE NewsID = ?', [$newsId]));
        foreach ($images as $img) {
            deleteStoredPublicFile($img['FilePath'] ?? '');
        }
        $db->query('DELETE FROM NewsImages WHERE NewsID = ?', [$newsId]);
    }

    $db->query('DELETE FROM News WHERE NewsID = ?', [$newsId]);
}

/**
 * Удаляет демо-акции и демо-новости из каталога seed-данных.
 *
 * @return array{events:int,news:int}
 */
function deleteDemoEventsAndNews($db): array
{
    $eventTitles = demoCollectEventTitles();
    $newsTitles = demoCollectNewsTitles();
    $deletedEvents = 0;
    $deletedNewsIds = [];

    $eventIds = [];
    foreach ($eventTitles as $title) {
        $rows = $db->fetchAll($db->query('SELECT EventID FROM Events WHERE Title = ?', [$title]));
        foreach ($rows as $row) {
            $eventIds[(int)$row['EventID']] = true;
        }
    }

    $queueNewsDelete = function (int $newsId) use (&$deletedNewsIds, $db): void {
        if ($newsId <= 0 || isset($deletedNewsIds[$newsId])) {
            return;
        }
        deleteNewsCompletely($db, $newsId);
        $deletedNewsIds[$newsId] = true;
    };

    foreach ($newsTitles as $title) {
        $rows = $db->fetchAll($db->query('SELECT NewsID FROM News WHERE Title = ?', [$title]));
        foreach ($rows as $row) {
            $queueNewsDelete((int)$row['NewsID']);
        }
    }

    if ($eventIds !== []) {
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $relatedNews = $db->fetchAll($db->query(
            "SELECT NewsID FROM News WHERE RelatedEventID IN ({$placeholders})",
            array_keys($eventIds)
        ));
        foreach ($relatedNews as $row) {
            $queueNewsDelete((int)$row['NewsID']);
        }
    }

    foreach (array_keys($eventIds) as $eventId) {
        deleteEventCompletely($db, (int)$eventId);
        $deletedEvents++;
    }

    return ['events' => $deletedEvents, 'news' => count($deletedNewsIds)];
}

/** Удаляет «хвосты» после удалённых акций. */
function purgeOrphanedEventData($db): array
{
    $regs = (int)($db->fetchOne($db->query(
        'SELECT COUNT(*) AS c FROM Registrations WHERE NOT EXISTS (SELECT 1 FROM Events e WHERE e.EventID = Registrations.EventID)'
    ))['c'] ?? 0);
    if ($regs > 0) {
        $db->query('DELETE FROM Registrations WHERE NOT EXISTS (SELECT 1 FROM Events e WHERE e.EventID = Registrations.EventID)');
    }

    $activity = (int)($db->fetchOne($db->query(
        "SELECT COUNT(*) AS c FROM ActivityLog WHERE EventID IS NOT NULL
         AND NOT EXISTS (SELECT 1 FROM Events e WHERE e.EventID = ActivityLog.EventID)"
    ))['c'] ?? 0);
    if ($activity > 0) {
        $db->query(
            "DELETE FROM ActivityLog WHERE EventID IS NOT NULL
             AND NOT EXISTS (SELECT 1 FROM Events e WHERE e.EventID = ActivityLog.EventID)"
        );
    }

    $newsOrphans = 0;
    $orphanNews = $db->fetchAll($db->query(
        'SELECT NewsID FROM News WHERE RelatedEventID IS NOT NULL
         AND NOT EXISTS (SELECT 1 FROM Events e WHERE e.EventID = News.RelatedEventID)'
    ));
    foreach ($orphanNews as $row) {
        deleteNewsCompletely($db, (int)$row['NewsID']);
        $newsOrphans++;
    }

    return ['registrations' => $regs, 'activity' => $activity, 'news' => $newsOrphans];
}

/**
 * Удаляет тестовые/демо-акции и новости, которые остались вне каталога seed.
 *
 * @return array{events:int,news:int,orphans:array}
 */
function deleteResidualTestData($db): array
{
    $deletedEvents = 0;
    $deletedNews = 0;

    $testPatterns = [
        'енот',
        'тест',
        'тестие',
        'бебе',
        'мощеник',
        'напоминан',
        'проверка для',
        'демо',
        'mock',
        'hero',
        'героем',
    ];

    $eventIds = [];

    $testCreators = $db->fetchAll($db->query(
        "SELECT EventID FROM Events e
         INNER JOIN Users u ON u.UserID = e.CreatorUserID
         WHERE u.Email LIKE N'%@test.by'"
    ));
    foreach ($testCreators as $row) {
        $eventIds[(int)$row['EventID']] = true;
    }

    $rejected = $db->fetchAll($db->query(
        "SELECT EventID FROM Events WHERE ModerationStatus IN (N'rejected', N'pending')"
    ));
    foreach ($rejected as $row) {
        $eventIds[(int)$row['EventID']] = true;
    }

    $titleConds = [];
    $titleParams = [];
    foreach ($testPatterns as $pattern) {
        $titleConds[] = 'LOWER(Title) LIKE LOWER(?)';
        $titleParams[] = '%' . $pattern . '%';
    }
    if ($titleConds !== []) {
        $byTitle = $db->fetchAll($db->query(
            'SELECT EventID FROM Events WHERE ' . implode(' OR ', $titleConds),
            $titleParams
        ));
        foreach ($byTitle as $row) {
            $eventIds[(int)$row['EventID']] = true;
        }
    }

    $newsPatterns = array_merge($testPatterns, ['могилев', 'медицинской сестры', 'знатоков']);
    $newsConds = [];
    $newsParams = [];
    foreach ($newsPatterns as $pattern) {
        $newsConds[] = 'LOWER(Title) LIKE LOWER(?)';
        $newsParams[] = '%' . $pattern . '%';
    }
    if ($newsConds !== []) {
        foreach ($db->fetchAll($db->query(
            'SELECT NewsID FROM News WHERE ' . implode(' OR ', $newsConds),
            $newsParams
        )) as $row) {
            deleteNewsCompletely($db, (int)$row['NewsID']);
            $deletedNews++;
        }
    }

    foreach (array_keys($eventIds) as $eventId) {
        $related = $db->fetchAll($db->query(
            'SELECT NewsID FROM News WHERE RelatedEventID = ?',
            [$eventId]
        ));
        foreach ($related as $row) {
            deleteNewsCompletely($db, (int)$row['NewsID']);
            $deletedNews++;
        }
        deleteEventCompletely($db, (int)$eventId);
        $deletedEvents++;
    }

    $orphans = purgeOrphanedEventData($db);

    return [
        'events' => $deletedEvents,
        'news' => $deletedNews,
        'orphans' => $orphans,
    ];
}

/** Полная очистка демо и тестовых данных. */
function deleteAllDemoAndTestContent($db): array
{
    $demo = deleteDemoEventsAndNews($db);
    $residual = deleteResidualTestData($db);

    return [
        'demo_events' => $demo['events'],
        'demo_news' => $demo['news'],
        'test_events' => $residual['events'],
        'test_news' => $residual['news'],
        'orphans' => $residual['orphans'],
    ];
}
