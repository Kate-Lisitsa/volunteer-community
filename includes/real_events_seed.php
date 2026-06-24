<?php
require_once __DIR__ . '/event_organizers.php';
require_once __DIR__ . '/real_events_catalog.php';

function ensureRealEventCategories($db): array
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

    foreach (realEventsRequiredCategories() as $name) {
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

function resolveRealEventCategoryId($db, array $categories, string $categoryName): int
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

function seedRealEvents($db, bool $skipExisting = true): array
{
    $images = realEventsImageCatalog();
    $categories = ensureRealEventCategories($db);

    $hasCoverCol = !empty($db->fetchOne($db->query(
        "SELECT COL_LENGTH('dbo.Events', 'CoverImagePath') AS col"
    ))['col']);

    $inserted = 0;
    $skipped = 0;
    $updated = 0;

    foreach (realEventsCatalog() as $item) {
        $categoryId = resolveRealEventCategoryId($db, $categories, (string)$item['category']);
        $organizerKey = (string)($item['organizerKey'] ?? resolveEventOrganizerFallbackKey((string)$item['title']));
        $creatorId = ensureEventOrganizerUser($db, $organizerKey);

        $existing = $db->fetchOne($db->query(
            'SELECT EventID, CoverImagePath, CreatorUserID FROM Events WHERE Title = ?',
            [$item['title']]
        ));

        $coverPath = null;
        if ($hasCoverCol && !empty($item['imageKey']) && !empty($images[$item['imageKey']]['file'])) {
            $coverPath = $images[$item['imageKey']]['file'];
        }

        if ($existing) {
            if ($skipExisting) {
                $needsCover = $hasCoverCol && $coverPath && empty($existing['CoverImagePath']);
                $needsOrganizer = (int)($existing['CreatorUserID'] ?? 0) !== $creatorId;
                if ($needsCover || $needsOrganizer) {
                    if ($needsCover && $needsOrganizer) {
                        $db->query(
                            'UPDATE Events SET CoverImagePath = ?, CreatorUserID = ? WHERE EventID = ?',
                            [$coverPath, $creatorId, (int)$existing['EventID']]
                        );
                    } elseif ($needsCover) {
                        $db->query(
                            'UPDATE Events SET CoverImagePath = ? WHERE EventID = ?',
                            [$coverPath, (int)$existing['EventID']]
                        );
                    } else {
                        $db->query(
                            'UPDATE Events SET CreatorUserID = ? WHERE EventID = ?',
                            [$creatorId, (int)$existing['EventID']]
                        );
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }
        }

        if ($hasCoverCol) {
            $stmt = $db->query(
                'INSERT INTO Events (Title, Description, CategoryID, CreatorUserID, EventDate, Location, MaxParticipants, CreatedAt, IsPublished, ModerationStatus, RejectionReason, IsPriority, CoverImagePath)
                 OUTPUT INSERTED.EventID AS NewId
                 VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), 1, N\'approved\', NULL, ?, ?)',
                [
                    $item['title'],
                    $item['description'],
                    $categoryId,
                    $creatorId,
                    $item['eventDate'],
                    $item['location'],
                    $item['maxParticipants'],
                    (int)$item['isPriority'],
                    $coverPath,
                ]
            );
        } else {
            $stmt = $db->query(
                'INSERT INTO Events (Title, Description, CategoryID, CreatorUserID, EventDate, Location, MaxParticipants, CreatedAt, IsPublished, ModerationStatus, RejectionReason, IsPriority)
                 OUTPUT INSERTED.EventID AS NewId
                 VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), 1, N\'approved\', NULL, ?)',
                [
                    $item['title'],
                    $item['description'],
                    $categoryId,
                    $creatorId,
                    $item['eventDate'],
                    $item['location'],
                    $item['maxParticipants'],
                    (int)$item['isPriority'],
                ]
            );
        }

        $idRow = $db->fetchOne($stmt);
        $eventId = (int)($idRow['NewId'] ?? 0);
        if ($eventId > 0) {
            $db->query(
                'INSERT INTO ActivityLog (UserID, EventID, ActionType) VALUES (?, ?, N\'create_event\')',
                [$creatorId, $eventId]
            );
            $inserted++;
        }
    }

    return [
        'inserted' => $inserted,
        'skipped' => $skipped,
        'updated' => $updated,
    ];
}

/**
 * Перекачивает обложки и обновляет CoverImagePath у существующих акций из каталога.
 */
function refreshRealEventCovers($db, array $forceKeys = []): array
{
    if ($forceKeys === []) {
        $forceKeys = [
            'snow_dispatch', 'christmas_miracles', 'from_the_heart', 'mercy_animals',
            'christmas_everyone', 'relay_of_kindness', 'elderly_help', 'orthodox_belarus',
            'pioneer_quest', 'defense_ready', 'ironstar', 'citizens_belarus',
            'colors_of_life', 'shrines_restore',
        ];
    }

    $images = realEventsImageCatalog($forceKeys);
    $hasCoverCol = !empty($db->fetchOne($db->query(
        "SELECT COL_LENGTH('dbo.Events', 'CoverImagePath') AS col"
    ))['col']);

    if (!$hasCoverCol) {
        return ['updated' => 0, 'missing' => 0];
    }

    $updated = 0;
    $missing = 0;

    foreach (realEventsCatalog() as $item) {
        $coverPath = null;
        if (!empty($item['imageKey']) && !empty($images[$item['imageKey']]['file'])) {
            $coverPath = $images[$item['imageKey']]['file'];
        }
        if ($coverPath === null) {
            continue;
        }

        $existing = $db->fetchOne($db->query(
            'SELECT EventID, CoverImagePath FROM Events WHERE Title = ?',
            [$item['title']]
        ));
        if (!$existing) {
            $missing++;
            continue;
        }
        if (($existing['CoverImagePath'] ?? '') === $coverPath) {
            continue;
        }
        $db->query(
            'UPDATE Events SET CoverImagePath = ? WHERE EventID = ?',
            [$coverPath, (int)$existing['EventID']]
        );
        $updated++;
    }

    return ['updated' => $updated, 'missing' => $missing];
}
