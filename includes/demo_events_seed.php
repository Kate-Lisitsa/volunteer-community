<?php
require_once __DIR__ . '/demo_image_loader.php';
require_once __DIR__ . '/demo_content_data.php';

/**
 * Добавляет и обновляет демо-акции с обложками и реалистичными описаниями.
 */
function seedDemoEventsIfNeeded($db, int $minCatalog = 12, bool $force = false): void
{
    static $done = false;
    if ($done && !$force) {
        return;
    }
    $done = true;

    $pub = "IsPublished = 1 AND ModerationStatus = N'approved' AND EventDate >= GETDATE()";
    $catalogCount = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c FROM Events WHERE {$pub}"))['c'] ?? 0);
    if (!$force && $catalogCount >= $minCatalog) {
        return;
    }

    $images = demoImageCatalog();
    $events = demoEventsCatalog();

    $usersByEmail = [];
    foreach ($db->fetchAll($db->query('SELECT UserID, Email FROM Users')) as $u) {
        $usersByEmail[mb_strtolower((string)$u['Email'])] = (int)$u['UserID'];
    }
    $defaultCreator = (int)($usersByEmail['user@test.by'] ?? 0);
    if ($defaultCreator <= 0) {
        $admin = $db->fetchOne($db->query("SELECT TOP 1 UserID FROM Users WHERE Role = N'admin' ORDER BY UserID"));
        $defaultCreator = (int)($admin['UserID'] ?? 0);
    }
    if ($defaultCreator <= 0) {
        return;
    }

    $categoriesByName = [];
    foreach ($db->fetchAll($db->query('SELECT CategoryID, CategoryName FROM Categories')) as $c) {
        $categoriesByName[mb_strtolower((string)$c['CategoryName'])] = (int)$c['CategoryID'];
    }

    $hasCoverCol = !empty($db->fetchOne($db->query(
        "SELECT COL_LENGTH('dbo.Events', 'CoverImagePath') AS col"
    ))['col']);

    $inserted = 0;
    $updated = 0;

    foreach ($events as $item) {
        $categoryId = $categoriesByName[mb_strtolower((string)$item['category'])] ?? null;
        if ($categoryId === null) {
            continue;
        }

        $creatorId = $usersByEmail[mb_strtolower((string)$item['creatorEmail'])] ?? $defaultCreator;
        $coverPath = null;
        if ($hasCoverCol && !empty($item['imageKey']) && !empty($images[$item['imageKey']]['file'])) {
            $coverPath = $images[$item['imageKey']]['file'];
        }

        $existing = demoFindEventByTitle($db, $item);

        if ($existing) {
            $eventId = (int)$existing['EventID'];
            $currentCover = (string)($existing['CoverImagePath'] ?? '');
            $needsCover = $coverPath !== null && (
                !empty($item['refreshCover'])
                || $currentCover === ''
                || str_contains($currentCover, 'dobrohub-mark.svg')
                || !demoImageFileExists($currentCover)
            );

            if ($needsCover || ($existing['Description'] ?? '') !== $item['description']) {
                if ($hasCoverCol && $needsCover) {
                    $db->query(
                        'UPDATE Events SET Description = ?, CoverImagePath = ?, CategoryID = ?, EventDate = ?, Location = ?, MaxParticipants = ?, IsPriority = ?, IsPublished = 1, ModerationStatus = N\'approved\' WHERE EventID = ?',
                        [$item['description'], $coverPath, $categoryId, $item['eventDate'], $item['location'], $item['maxParticipants'], (int)$item['isPriority'], $eventId]
                    );
                } else {
                    $db->query(
                        'UPDATE Events SET Description = ?, CategoryID = ?, EventDate = ?, Location = ?, MaxParticipants = ?, IsPriority = ?, IsPublished = 1, ModerationStatus = N\'approved\' WHERE EventID = ?',
                        [$item['description'], $categoryId, $item['eventDate'], $item['location'], $item['maxParticipants'], (int)$item['isPriority'], $eventId]
                    );
                }
                $updated++;
            }
            continue;
        }

        if ($hasCoverCol) {
            $db->query(
                'INSERT INTO Events (Title, Description, CategoryID, CreatorUserID, EventDate, Location, MaxParticipants, CreatedAt, IsPublished, ModerationStatus, RejectionReason, IsPriority, CoverImagePath)
                 VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), 1, N\'approved\', NULL, ?, ?)',
                [$item['title'], $item['description'], $categoryId, $creatorId, $item['eventDate'], $item['location'], $item['maxParticipants'], (int)$item['isPriority'], $coverPath]
            );
        } else {
            $db->query(
                'INSERT INTO Events (Title, Description, CategoryID, CreatorUserID, EventDate, Location, MaxParticipants, CreatedAt, IsPublished, ModerationStatus, RejectionReason, IsPriority)
                 VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), 1, N\'approved\', NULL, ?)',
                [$item['title'], $item['description'], $categoryId, $creatorId, $item['eventDate'], $item['location'], $item['maxParticipants'], (int)$item['isPriority']]
            );
        }

        $eventId = (int)$db->lastInsertId();
        if ($eventId > 0) {
            $db->query(
                'INSERT INTO ActivityLog (UserID, EventID, ActionType) VALUES (?, ?, N\'create_event\')',
                [$creatorId, $eventId]
            );
            demoSeedEventRegistrations($db, $eventId, $creatorId);
            $inserted++;
        }
    }

    demoEnsureMinimumPublishedEvents($db, $minCatalog, $defaultCreator, $usersByEmail);
}

function demoSeedEventRegistrations($db, int $eventId, int $creatorId): void
{
    $volunteers = [];
    foreach ($db->fetchAll($db->query(
        "SELECT UserID FROM Users WHERE Role = N'user' AND IsActive = 1 AND UserID <> ? ORDER BY UserID",
        [$creatorId]
    )) as $row) {
        $volunteers[] = (int)$row['UserID'];
    }

    $take = min(count($volunteers), random_int(2, 4));
    if ($take <= 0) {
        return;
    }

    shuffle($volunteers);
    foreach (array_slice($volunteers, 0, $take) as $userId) {
        $exists = $db->fetchOne($db->query(
            'SELECT RegistrationID FROM Registrations WHERE EventID = ? AND UserID = ?',
            [$eventId, $userId]
        ));
        if ($exists) {
            continue;
        }
        $db->query(
            'INSERT INTO Registrations (EventID, UserID, Status, ReminderSent) VALUES (?, ?, N\'confirmed\', 0)',
            [$eventId, $userId]
        );
    }
}

function demoEnsureMinimumPublishedEvents($db, int $minCatalog, int $defaultCreator, array $usersByEmail): void
{
    $pub = "IsPublished = 1 AND ModerationStatus = N'approved' AND EventDate >= GETDATE()";
    $count = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c FROM Events WHERE {$pub}"))['c'] ?? 0);
    if ($count >= $minCatalog) {
        return;
    }

    // Дополнительно обновим обложки у уже существующих акций без картинок.
    $missing = $db->fetchAll($db->query(
        "SELECT EventID, Title FROM Events WHERE {$pub} AND (CoverImagePath IS NULL OR CoverImagePath = '' OR CoverImagePath LIKE N'%dobrohub-mark%')"
    ));
    $images = demoImageCatalog();
    $fallbackKeys = array_keys($images);
    $i = 0;
    foreach ($missing as $row) {
        $key = $fallbackKeys[$i % count($fallbackKeys)];
        $i++;
        if (empty($images[$key]['file'])) {
            continue;
        }
        $db->query(
            'UPDATE Events SET CoverImagePath = ? WHERE EventID = ?',
            [$images[$key]['file'], (int)$row['EventID']]
        );
    }
}

function demoFindEventByTitle($db, array $item): ?array
{
    $titles = [$item['title']];
    if (!empty($item['legacyTitles']) && is_array($item['legacyTitles'])) {
        $titles = array_merge($titles, $item['legacyTitles']);
    }

    foreach ($titles as $title) {
        $row = $db->fetchOne($db->query(
            'SELECT EventID, CoverImagePath, Description, Title FROM Events WHERE Title = ?',
            [$title]
        ));
        if ($row) {
            return $row;
        }
    }

    return null;
}

function demoResolveEventIdByTitle($db, ?string $title): ?int
{
    if ($title === null || $title === '') {
        return null;
    }
    $row = $db->fetchOne($db->query('SELECT EventID FROM Events WHERE Title = ?', [$title]));
    return $row ? (int)$row['EventID'] : null;
}
