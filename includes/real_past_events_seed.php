<?php
require_once __DIR__ . '/event_organizers.php';
require_once __DIR__ . '/real_events_seed.php';
require_once __DIR__ . '/real_past_events_catalog.php';

function ensurePastEventCategories($db): array
{
    $map = ensureRealEventCategories($db);
    $hasIsActive = categoriesHaveIsActiveColumn($db);

    foreach (realPastEventsRequiredCategories() as $name) {
        $key = mb_strtolower($name);
        if (isset($map[$key])) {
            continue;
        }

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
    }

    return $map;
}

function seedPastRealEvents($db, bool $skipExisting = true): array
{
    $images = realPastEventsImageCatalog();
    $categories = ensurePastEventCategories($db);

    $hasCoverCol = !empty($db->fetchOne($db->query(
        "SELECT COL_LENGTH('dbo.Events', 'CoverImagePath') AS col"
    ))['col']);

    $inserted = 0;
    $skipped = 0;
    $updated = 0;

    foreach (realPastEventsCatalog() as $item) {
        $categoryId = resolveRealEventCategoryId($db, $categories, (string)$item['category']);
        $createdAt = $item['createdAt'] ?? $item['eventDate'];
        $organizerKey = (string)($item['organizerKey'] ?? resolveEventOrganizerFallbackKey((string)$item['title']));
        $creatorId = ensureEventOrganizerUser($db, $organizerKey);

        $existing = $db->fetchOne($db->query(
            'SELECT EventID, CoverImagePath, EventDate, CreatedAt, CreatorUserID FROM Events WHERE Title = ?',
            [$item['title']]
        ));

        $coverPath = null;
        if ($hasCoverCol && !empty($item['imageKey']) && !empty($images[$item['imageKey']]['file'])) {
            $coverPath = $images[$item['imageKey']]['file'];
        }

        if ($existing) {
            if ($skipExisting) {
                $needsUpdate = ($existing['EventDate'] ?? '') !== $item['eventDate']
                    || ($hasCoverCol && $coverPath && ($existing['CoverImagePath'] ?? '') !== $coverPath)
                    || (int)($existing['CreatorUserID'] ?? 0) !== $creatorId;

                if ($needsUpdate) {
                    if ($hasCoverCol) {
                        $db->query(
                            'UPDATE Events SET EventDate = ?, CoverImagePath = ?, CreatedAt = ?, CreatorUserID = ? WHERE EventID = ?',
                            [$item['eventDate'], $coverPath, $createdAt, $creatorId, (int)$existing['EventID']]
                        );
                    } else {
                        $db->query(
                            'UPDATE Events SET EventDate = ?, CreatedAt = ?, CreatorUserID = ? WHERE EventID = ?',
                            [$item['eventDate'], $createdAt, $creatorId, (int)$existing['EventID']]
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
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, N\'approved\', NULL, ?, ?)',
                [
                    $item['title'],
                    $item['description'],
                    $categoryId,
                    $creatorId,
                    $item['eventDate'],
                    $item['location'],
                    $item['maxParticipants'],
                    $createdAt,
                    (int)$item['isPriority'],
                    $coverPath,
                ]
            );
        } else {
            $stmt = $db->query(
                'INSERT INTO Events (Title, Description, CategoryID, CreatorUserID, EventDate, Location, MaxParticipants, CreatedAt, IsPublished, ModerationStatus, RejectionReason, IsPriority)
                 OUTPUT INSERTED.EventID AS NewId
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, N\'approved\', NULL, ?)',
                [
                    $item['title'],
                    $item['description'],
                    $categoryId,
                    $creatorId,
                    $item['eventDate'],
                    $item['location'],
                    $item['maxParticipants'],
                    $createdAt,
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
