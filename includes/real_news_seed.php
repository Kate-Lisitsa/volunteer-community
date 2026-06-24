<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/real_news_catalog.php';

function ensureRealNewsCategories($db): array
{
    $map = [];
    $hasIsActive = categoriesHaveIsActiveColumn($db);

    $catSql = $hasIsActive
        ? 'SELECT CategoryID, CategoryName, IsActive FROM Categories'
        : 'SELECT CategoryID, CategoryName FROM Categories';

    foreach ($db->fetchAll($db->query($catSql)) as $row) {
        $map[mb_strtolower((string)$row['CategoryName'])] = [
            'id' => (int)$row['CategoryID'],
            'active' => $hasIsActive ? (int)($row['IsActive'] ?? 1) : 1,
        ];
    }

    foreach (realNewsRequiredCategories() as $name) {
        $key = mb_strtolower($name);
        if (!isset($map[$key])) {
            if ($hasIsActive) {
                $stmt = $db->query(
                    'INSERT INTO Categories (CategoryName, IsActive) OUTPUT INSERTED.CategoryID AS NewId VALUES (?, 1)',
                    [$name]
                );
            } else {
                $stmt = $db->query(
                    'INSERT INTO Categories (CategoryName) OUTPUT INSERTED.CategoryID AS NewId VALUES (?)',
                    [$name]
                );
            }
            $row = $db->fetchOne($stmt);
            $id = (int)($row['NewId'] ?? 0);
            if ($id <= 0) {
                $found = $db->fetchOne($db->query(
                    'SELECT CategoryID FROM Categories WHERE CategoryName = ?',
                    [$name]
                ));
                $id = (int)($found['CategoryID'] ?? 0);
            }
            if ($id <= 0) {
                throw new RuntimeException('Не удалось создать категорию: ' . $name);
            }
            $map[$key] = ['id' => $id, 'active' => 1];
            continue;
        }
        if ($hasIsActive && $map[$key]['active'] !== 1) {
            $db->query('UPDATE Categories SET IsActive = 1 WHERE CategoryID = ?', [$map[$key]['id']]);
            $map[$key]['active'] = 1;
        }
    }

    return $map;
}

function resolveRealNewsCategoryId($db, array $categories, string $categoryName): int
{
    $key = mb_strtolower($categoryName);
    if (isset($categories[$key]['id']) && $categories[$key]['id'] > 0) {
        return (int)$categories[$key]['id'];
    }
    $found = $db->fetchOne($db->query(
        'SELECT CategoryID FROM Categories WHERE CategoryName = ?',
        [$categoryName]
    ));
    $id = (int)($found['CategoryID'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Категория не найдена: ' . $categoryName);
    }
    return $id;
}

function resolveRealNewsEventId($db, ?string $eventTitle): int
{
    if ($eventTitle === null || trim($eventTitle) === '') {
        return 0;
    }
    $row = $db->fetchOne($db->query(
        'SELECT EventID FROM Events WHERE Title = ?',
        [$eventTitle]
    ));

    return (int)($row['EventID'] ?? 0);
}

function realNewsBodyWithSource(string $body, string $sourceUrl, string $sourceLabel): string
{
    $url = htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8');

    return $body . '<p><strong>Источник:</strong> <a href="' . $url . '" rel="noopener noreferrer" target="_blank">' . $label . '</a></p>';
}

function realEnsureNewsImage($db, int $newsId, ?string $imagePath, bool $hasNewsImages): void
{
    if (!$hasNewsImages || $imagePath === null || $imagePath === '') {
        return;
    }

    $existing = $db->fetchOne($db->query(
        'SELECT TOP 1 ImageID, FilePath FROM NewsImages WHERE NewsID = ? ORDER BY SortOrder, ImageID',
        [$newsId]
    ));

    if (!$existing) {
        $db->query(
            'INSERT INTO NewsImages (NewsID, FilePath, SortOrder) VALUES (?, ?, 0)',
            [$newsId, $imagePath]
        );
        return;
    }

    if (($existing['FilePath'] ?? '') !== $imagePath) {
        $db->query(
            'UPDATE NewsImages SET FilePath = ? WHERE ImageID = ?',
            [$imagePath, (int)$existing['ImageID']]
        );
    }
}

function seedRealNews($db, bool $skipExisting = true): array
{
    ensureNewsCategorySchema($db);

    $images = realNewsImageCatalog();
    $categories = ensureRealNewsCategories($db);
    $hasNewsCategory = newsHasCategoryColumn($db);
    $hasNewsImages = !empty($db->fetchOne($db->query(
        "SELECT OBJECT_ID('dbo.NewsImages', 'U') AS tid"
    ))['tid']);

    $admin = $db->fetchOne($db->query(
        "SELECT TOP 1 UserID FROM Users WHERE Role = N'admin' AND IsActive = 1 ORDER BY UserID"
    ));
    if (!$admin) {
        throw new RuntimeException('Не найден активный администратор для создания новостей.');
    }
    $authorId = (int)$admin['UserID'];

    $inserted = 0;
    $skipped = 0;
    $updated = 0;

    foreach (realNewsCatalog() as $item) {
        $categoryId = resolveRealNewsCategoryId($db, $categories, (string)$item['category']);
        $eventId = resolveRealNewsEventId($db, $item['relatedEventTitle'] ?? null);
        if ($hasNewsCategory && $eventId > 0) {
            $fromEvent = suggestNewsCategoryFromEvent($db, $eventId);
            if ($fromEvent > 0) {
                $categoryId = $fromEvent;
            }
        }

        $imagePath = null;
        if (!empty($item['imageKey']) && !empty($images[$item['imageKey']]['file'])) {
            $imagePath = $images[$item['imageKey']]['file'];
        }

        $bodyHtml = realNewsBodyWithSource(
            (string)$item['body'],
            (string)$item['sourceUrl'],
            (string)$item['sourceLabel']
        );

        $existing = $db->fetchOne($db->query(
            'SELECT NewsID, Summary, BodyHtml, CategoryID, PublishedAt FROM News WHERE Title = ?',
            [$item['title']]
        ));

        if ($existing) {
            if ($skipExisting) {
                $needsUpdate = ($existing['Summary'] ?? '') !== $item['summary']
                    || ($existing['BodyHtml'] ?? '') !== $bodyHtml
                    || ($hasNewsCategory && (int)($existing['CategoryID'] ?? 0) !== $categoryId)
                    || ($existing['PublishedAt'] ?? '') < date('Y-m-d');

                if ($needsUpdate) {
                    if ($hasNewsCategory) {
                        $db->query(
                            'UPDATE News SET Summary = ?, BodyHtml = ?, RelatedEventID = ?, CategoryID = ?, IsPublished = 1, PublishedAt = ? WHERE NewsID = ?',
                            [$item['summary'], $bodyHtml, $eventId ?: null, $categoryId, $item['publishedAt'], (int)$existing['NewsID']]
                        );
                    } else {
                        $db->query(
                            'UPDATE News SET Summary = ?, BodyHtml = ?, RelatedEventID = ?, IsPublished = 1, PublishedAt = ? WHERE NewsID = ?',
                            [$item['summary'], $bodyHtml, $eventId ?: null, $item['publishedAt'], (int)$existing['NewsID']]
                        );
                    }
                    realEnsureNewsImage($db, (int)$existing['NewsID'], $imagePath, $hasNewsImages);
                    $updated++;
                } else {
                    realEnsureNewsImage($db, (int)$existing['NewsID'], $imagePath, $hasNewsImages);
                    $skipped++;
                }
                continue;
            }
        }

        if ($hasNewsCategory) {
            $stmt = $db->query(
                'INSERT INTO News (Title, Summary, BodyHtml, RelatedEventID, CategoryID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
                 OUTPUT INSERTED.NewsID AS NewId
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?, GETDATE())',
                [
                    $item['title'],
                    $item['summary'],
                    $bodyHtml,
                    $eventId ?: null,
                    $categoryId,
                    $item['publishedAt'],
                    $authorId,
                ]
            );
        } else {
            $stmt = $db->query(
                'INSERT INTO News (Title, Summary, BodyHtml, RelatedEventID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
                 OUTPUT INSERTED.NewsID AS NewId
                 VALUES (?, ?, ?, ?, 1, ?, ?, GETDATE())',
                [
                    $item['title'],
                    $item['summary'],
                    $bodyHtml,
                    $eventId ?: null,
                    $item['publishedAt'],
                    $authorId,
                ]
            );
        }

        $idRow = $db->fetchOne($stmt);
        $newsId = (int)($idRow['NewId'] ?? 0);
        if ($newsId <= 0) {
            $found = $db->fetchOne($db->query(
                'SELECT NewsID FROM News WHERE Title = ?',
                [$item['title']]
            ));
            $newsId = (int)($found['NewsID'] ?? 0);
        }

        if ($newsId > 0) {
            realEnsureNewsImage($db, $newsId, $imagePath, $hasNewsImages);
            $inserted++;
        }
    }

    return [
        'inserted' => $inserted,
        'skipped' => $skipped,
        'updated' => $updated,
    ];
}
