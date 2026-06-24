<?php

/**
 * Профили организаторов акций (отдельные учётные записи с реалистичными ФИО).
 */
function eventOrganizerProfiles(): array
{
    return [
        'minsk_social' => [
            'fullName' => 'Тихонова Марина Викторовна',
            'email' => 'organizer.snezhny-deskant@dobrohub.by',
            'phone' => '+375172943323',
        ],
        'brsm_secretary' => [
            'fullName' => 'Качановский Андрей Владимирович',
            'email' => 'organizer.brsm@dobrohub.by',
            'phone' => '+375291112233',
        ],
        'brsm_gomel' => [
            'fullName' => 'Дубовицкая Наталья Владимировна',
            'email' => 'organizer.brsm-gomel@dobrohub.by',
            'phone' => '+375232456789',
        ],
        'brsm_dobroe_serdce' => [
            'fullName' => 'Лукашевич Артём Игоревич',
            'email' => 'organizer.dobroe-serdce@dobrohub.by',
            'phone' => '+375294445566',
        ],
        'brsm_bobruisk' => [
            'fullName' => 'Левченко Игорь Николаевич',
            'email' => 'organizer.brsm-bobruisk@dobrohub.by',
            'phone' => '+375225334455',
        ],
        'brpo_pioneer' => [
            'fullName' => 'Жук Анастасия Владимировна',
            'email' => 'organizer.brpo@dobrohub.by',
            'phone' => '+375333221100',
        ],
        'brpo_patriot' => [
            'fullName' => 'Козловский Денис Александрович',
            'email' => 'organizer.patriot@dobrohub.by',
            'phone' => '+375445566778',
        ],
        'red_cross' => [
            'fullName' => 'Савицкая Татьяна Игоревна',
            'email' => 'organizer.redcross@dobrohub.by',
            'phone' => '+375172345678',
        ],
        'animal_shelter' => [
            'fullName' => 'Кравченко Алина Сергеевна',
            'email' => 'organizer.priut-volkovysk@dobrohub.by',
            'phone' => '+375152987654',
        ],
        'cathedral_charity' => [
            'fullName' => 'Кузьмич Ольга Петровна',
            'email' => 'organizer.sobor-minsk@dobrohub.by',
            'phone' => '+375291876543',
        ],
        'orthodox_project' => [
            'fullName' => 'Нестерова Светлана Александровна',
            'email' => 'organizer.pravoslavie-by@dobrohub.by',
            'phone' => '+375293851609',
        ],
        'ironstar' => [
            'fullName' => 'Романова Виктория Олеговна',
            'email' => 'organizer.ironstar@dobrohub.by',
            'phone' => '+375291234000',
        ],
        'citizens_youth' => [
            'fullName' => 'Петрова Юлия Андреевна',
            'email' => 'organizer.grazhdane-by@dobrohub.by',
            'phone' => '+375336677889',
        ],
        'shrines_restore' => [
            'fullName' => 'Остриков Павел Сергеевич',
            'email' => 'organizer.svyatyni@dobrohub.by',
            'phone' => '+375232112233',
        ],
        'forestry_rechitsa' => [
            'fullName' => 'Журавлёв Пётр Васильевич',
            'email' => 'organizer.lesnichestvo-rechitsa@dobrohub.by',
            'phone' => '+375234556677',
        ],
        'med_union_vitebsk' => [
            'fullName' => 'Казючиц Ирина Владимировна',
            'email' => 'organizer.vitprofmed@dobrohub.by',
            'phone' => '+375212334455',
        ],
        'gomel_volunteers' => [
            'fullName' => 'Новикова Светлана Николаевна',
            'email' => 'organizer.gomel-volunteers@dobrohub.by',
            'phone' => '+375232778899',
        ],
        'fallback_1' => [
            'fullName' => 'Мельникова Екатерина Павловна',
            'email' => 'organizer.volunteer-01@dobrohub.by',
            'phone' => '+375291001122',
        ],
        'fallback_2' => [
            'fullName' => 'Шевченко Дмитрий Викторович',
            'email' => 'organizer.volunteer-02@dobrohub.by',
            'phone' => '+375292223344',
        ],
        'fallback_3' => [
            'fullName' => 'Борисова Анна Геннадьевна',
            'email' => 'organizer.volunteer-03@dobrohub.by',
            'phone' => '+375293334455',
        ],
        'fallback_4' => [
            'fullName' => 'Григорьев Максим Сергеевич',
            'email' => 'organizer.volunteer-04@dobrohub.by',
            'phone' => '+375294445566',
        ],
        'fallback_5' => [
            'fullName' => 'Яковлева Вероника Ильинична',
            'email' => 'organizer.volunteer-05@dobrohub.by',
            'phone' => '+375295556677',
        ],
    ];
}

function eventOrganizerFallbackKeys(): array
{
    return ['fallback_1', 'fallback_2', 'fallback_3', 'fallback_4', 'fallback_5'];
}

function resolveEventOrganizerFallbackKey(string $seed): string
{
    $keys = eventOrganizerFallbackKeys();
    $idx = abs(crc32($seed)) % count($keys);

    return $keys[$idx];
}

function ensureEventOrganizerUser($db, string $organizerKey): int
{
    $profiles = eventOrganizerProfiles();
    if (!isset($profiles[$organizerKey])) {
        $organizerKey = resolveEventOrganizerFallbackKey($organizerKey);
    }

    $profile = $profiles[$organizerKey];
    $email = (string)$profile['email'];
    $fullName = (string)$profile['fullName'];
    $phone = $profile['phone'] ?? null;

    $row = $db->fetchOne($db->query(
        'SELECT UserID, FullName, Phone FROM Users WHERE Email = ?',
        [$email]
    ));

    if ($row) {
        $userId = (int)$row['UserID'];
        if (($row['FullName'] ?? '') !== $fullName || ($row['Phone'] ?? null) !== $phone) {
            $db->query(
                'UPDATE Users SET FullName = ?, Phone = ? WHERE UserID = ?',
                [$fullName, $phone, $userId]
            );
        }
        return $userId;
    }

    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $hasIsActive = !empty($db->fetchOne($db->query(
        "SELECT COL_LENGTH('dbo.Users', 'IsActive') AS col"
    ))['col']);

    if ($hasIsActive) {
        $stmt = $db->query(
            'INSERT INTO Users (Email, PasswordHash, FullName, Phone, Role, RegisteredAt, IsActive)
             OUTPUT INSERTED.UserID AS NewId
             VALUES (?, ?, ?, ?, N\'user\', GETDATE(), 1)',
            [$email, $hash, $fullName, $phone]
        );
    } else {
        $stmt = $db->query(
            'INSERT INTO Users (Email, PasswordHash, FullName, Phone, Role, RegisteredAt)
             OUTPUT INSERTED.UserID AS NewId
             VALUES (?, ?, ?, ?, N\'user\', GETDATE())',
            [$email, $hash, $fullName, $phone]
        );
    }

    $idRow = $db->fetchOne($stmt);
    $userId = (int)($idRow['NewId'] ?? 0);
    if ($userId <= 0) {
        $found = $db->fetchOne($db->query('SELECT UserID FROM Users WHERE Email = ?', [$email]));
        $userId = (int)($found['UserID'] ?? 0);
    }
    if ($userId <= 0) {
        throw new RuntimeException('Не удалось создать организатора: ' . $fullName);
    }

    return $userId;
}

/**
 * Карта «название акции» → ключ организатора (все каталоги акций).
 */
function eventOrganizerKeyByTitle(): array
{
    require_once __DIR__ . '/real_events_catalog.php';
    require_once __DIR__ . '/real_past_events_catalog.php';

    $map = [];
    foreach (array_merge(realEventsCatalog(), realPastEventsCatalog()) as $item) {
        if (!empty($item['title']) && !empty($item['organizerKey'])) {
            $map[(string)$item['title']] = (string)$item['organizerKey'];
        }
    }

    return $map;
}

/**
 * Назначает организаторов всем акциям в БД.
 */
function assignAllEventOrganizers($db): array
{
    $titleMap = eventOrganizerKeyByTitle();
    $updated = 0;
    $skipped = 0;

    $rows = $db->fetchAll($db->query('SELECT EventID, Title, CreatorUserID FROM Events'));
    foreach ($rows as $row) {
        $title = (string)$row['Title'];
        $organizerKey = $titleMap[$title] ?? resolveEventOrganizerFallbackKey($title);
        $userId = ensureEventOrganizerUser($db, $organizerKey);

        if ((int)$row['CreatorUserID'] === $userId) {
            $skipped++;
            continue;
        }

        $db->query(
            'UPDATE Events SET CreatorUserID = ? WHERE EventID = ?',
            [$userId, (int)$row['EventID']]
        );
        $updated++;
    }

    return ['updated' => $updated, 'skipped' => $skipped, 'total' => count($rows)];
}
