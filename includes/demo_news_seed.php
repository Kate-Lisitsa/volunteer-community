<?php
require_once __DIR__ . '/demo_image_loader.php';
require_once __DIR__ . '/demo_content_data.php';
require_once __DIR__ . '/demo_events_seed.php';

/**
 * Удаляет тестовые новости (еноты, модерация, черновики).
 */
function demoCleanupTestNews($db): void
{
    static $cleaned = false;
    if ($cleaned) {
        return;
    }
    $cleaned = true;

    require_once __DIR__ . '/functions.php';

    $rows = $db->fetchAll($db->query(
        "SELECT NewsID, Title FROM News
         WHERE Title IN (N'тестиее', N'Итоги: тест на модерацию', N'Итоги: енотики', N'Хвосты довольны')
            OR Title LIKE N'%тест на модера%'
            OR Title LIKE N'%енотик%'
            OR Title LIKE N'Итоги: енот%'
            OR Title LIKE N'тестие%'"
    ));

    $hasNewsImages = !empty($db->fetchOne($db->query(
        "SELECT OBJECT_ID('dbo.NewsImages', 'U') AS tid"
    ))['tid']);

    foreach ($rows as $row) {
        $newsId = (int)$row['NewsID'];
        if ($hasNewsImages) {
            $imgs = $db->fetchAll($db->query(
                'SELECT FilePath FROM NewsImages WHERE NewsID = ?',
                [$newsId]
            ));
            foreach ($imgs as $img) {
                deleteStoredPublicFile($img['FilePath'] ?? null);
            }
            $db->query('DELETE FROM NewsImages WHERE NewsID = ?', [$newsId]);
        }
        $db->query('DELETE FROM News WHERE NewsID = ?', [$newsId]);
    }
}

/**
 * Добавляет и обновляет демо-новости с фото и связями с акциями.
 */
function seedDemoNewsIfNeeded($db, int $minPublished = 12, bool $force = false): void
{
    static $done = false;
    if ($done && !$force) {
        return;
    }
    $done = true;

    ensureNewsCategorySchema($db);

    $publishedCount = (int)($db->fetchOne($db->query(
        'SELECT COUNT(*) AS c FROM News WHERE IsPublished = 1'
    ))['c'] ?? 0);
    if (!$force && $publishedCount >= $minPublished) {
        return;
    }

    demoCleanupTestNews($db);
    seedDemoEventsIfNeeded($db, 12);

    $admin = $db->fetchOne($db->query(
        "SELECT TOP 1 UserID FROM Users WHERE Role = N'admin' ORDER BY UserID"
    ));
    if (!$admin) {
        return;
    }
    $adminId = (int)$admin['UserID'];

    $images = demoImageCatalog();
    $hasNewsImages = !empty($db->fetchOne($db->query(
        "SELECT OBJECT_ID('dbo.NewsImages', 'U') AS tid"
    ))['tid']);

    $newsItems = demoNewsCatalog();
    $hasNewsCategory = newsHasCategoryColumn($db);

    foreach ($newsItems as $item) {
        $eventId = demoResolveEventIdByTitle($db, $item['relatedEventTitle'] ?? null);
        $categoryId = ($hasNewsCategory && $eventId > 0)
            ? suggestNewsCategoryFromEvent($db, $eventId)
            : 0;
        $imagePath = null;
        if (!empty($item['imageKey']) && !empty($images[$item['imageKey']]['file'])) {
            $imagePath = $images[$item['imageKey']]['file'];
        }

        $existing = demoFindNewsByTitle($db, $item);

        if ($existing) {
            $newsId = (int)$existing['NewsID'];
            $needsCategory = $hasNewsCategory && $categoryId > 0 && empty($existing['CategoryID']);
            $needsUpdate = ($existing['Summary'] ?? '') !== $item['summary']
                || ($existing['BodyHtml'] ?? '') !== $item['body']
                || $needsCategory;
            if ($needsUpdate) {
                if ($hasNewsCategory) {
                    $catParam = $categoryId > 0 ? $categoryId : ($existing['CategoryID'] ?? null);
                    $db->query(
                        'UPDATE News SET Summary = ?, BodyHtml = ?, RelatedEventID = ?, CategoryID = ?, IsPublished = 1, PublishedAt = COALESCE(PublishedAt, DATEADD(DAY, ?, GETDATE())) WHERE NewsID = ?',
                        [$item['summary'], $item['body'], $eventId, $catParam, -1 * (int)$item['daysAgo'], $newsId]
                    );
                } else {
                    $db->query(
                        'UPDATE News SET Summary = ?, BodyHtml = ?, RelatedEventID = ?, IsPublished = 1, PublishedAt = COALESCE(PublishedAt, DATEADD(DAY, ?, GETDATE())) WHERE NewsID = ?',
                        [$item['summary'], $item['body'], $eventId, -1 * (int)$item['daysAgo'], $newsId]
                    );
                }
            }
            demoEnsureNewsImage($db, $newsId, $imagePath, $hasNewsImages);
            continue;
        }

        if ($hasNewsCategory) {
            $catParam = $categoryId > 0 ? $categoryId : null;
            $db->query(
                'INSERT INTO News (Title, Summary, BodyHtml, RelatedEventID, CategoryID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
                 VALUES (?, ?, ?, ?, ?, 1, DATEADD(DAY, ?, GETDATE()), ?, GETDATE())',
                [
                    $item['title'],
                    $item['summary'],
                    $item['body'],
                    $eventId,
                    $catParam,
                    -1 * (int)$item['daysAgo'],
                    $adminId,
                ]
            );
        } else {
            $db->query(
                'INSERT INTO News (Title, Summary, BodyHtml, RelatedEventID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
                 VALUES (?, ?, ?, ?, 1, DATEADD(DAY, ?, GETDATE()), ?, GETDATE())',
                [
                    $item['title'],
                    $item['summary'],
                    $item['body'],
                    $eventId,
                    -1 * (int)$item['daysAgo'],
                    $adminId,
                ]
            );
        }

        $newId = (int)$db->lastInsertId();
        if ($newId > 0) {
            demoEnsureNewsImage($db, $newId, $imagePath, $hasNewsImages);
        }
    }

    demoBackfillNewsImages($db, $hasNewsImages, $images);
}

function demoFindNewsByTitle($db, array $item): ?array
{
    $titles = [$item['title']];
    if (!empty($item['legacyTitles']) && is_array($item['legacyTitles'])) {
        $titles = array_merge($titles, $item['legacyTitles']);
    }

    foreach ($titles as $title) {
        $row = $db->fetchOne($db->query(
            'SELECT NewsID, Summary, BodyHtml FROM News WHERE Title = ?',
            [$title]
        ));
        if ($row) {
            return $row;
        }
    }

    return null;
}

function demoEnsureNewsImage($db, int $newsId, ?string $imagePath, bool $hasNewsImages): void
{
    if (!$hasNewsImages || $imagePath === null || $imagePath === '') {
        return;
    }

    $current = $db->fetchOne($db->query(
        'SELECT TOP 1 ImageID, FilePath FROM NewsImages WHERE NewsID = ? ORDER BY SortOrder, ImageID',
        [$newsId]
    ));

    if (!$current) {
        $db->query(
            'INSERT INTO NewsImages (NewsID, FilePath, SortOrder) VALUES (?, ?, 0)',
            [$newsId, $imagePath]
        );
        return;
    }

    $currentPath = (string)($current['FilePath'] ?? '');
    $needsUpdate = $currentPath === ''
        || str_contains($currentPath, 'dobrohub-mark')
        || !demoImageFileExists($currentPath);

    if ($needsUpdate) {
        $db->query(
            'UPDATE NewsImages SET FilePath = ? WHERE ImageID = ?',
            [$imagePath, (int)$current['ImageID']]
        );
    }
}

function demoBackfillNewsImages($db, bool $hasNewsImages, array $images): void
{
    if (!$hasNewsImages) {
        return;
    }

    $rows = $db->fetchAll($db->query(
        'SELECT n.NewsID, n.Title FROM News n
         WHERE n.IsPublished = 1
           AND NOT EXISTS (SELECT 1 FROM NewsImages ni WHERE ni.NewsID = n.NewsID)
         ORDER BY n.NewsID'
    ));

    $keys = array_keys($images);
    $i = 0;
    foreach ($rows as $row) {
        $key = $keys[$i % count($keys)];
        $i++;
        if (empty($images[$key]['file'])) {
            continue;
        }
        $db->query(
            'INSERT INTO NewsImages (NewsID, FilePath, SortOrder) VALUES (?, ?, 0)',
            [(int)$row['NewsID'], $images[$key]['file']]
        );
    }
}
