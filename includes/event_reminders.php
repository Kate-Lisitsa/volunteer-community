<?php
/**
 * Напоминания на e-mail за сутки до даты акции (Europe/Minsk).
 * Адрес участника — Users.Email; отправитель — как у администратора (см. resolveMailFromIdentity в mail.php).
 */

/** Час запуска cron (Europe/Minsk), после которого «дневное» напоминание уже должно было уйти */
function reminderCronHour(): int {
    return 8;
}

function eventReminderTimezone(): DateTimeZone {
    return new DateTimeZone('Europe/Minsk');
}

/** Календарный день акции (00:00) в Europe/Minsk */
function eventCalendarDayMinsk($eventDate): DateTime {
    $tz = eventReminderTimezone();
    $event = eventDateTime($eventDate);
    $event->setTimezone($tz);
    return (new DateTime($event->format('Y-m-d'), $tz));
}

/**
 * Участнику имеет смысл обещать письмо «за день до»:
 * акция не сегодня и не в прошлом (cron шлёт напоминания накануне календарного дня акции).
 */
function eventWillGetDayBeforeReminder($eventDate): bool {
    $tz = eventReminderTimezone();
    $today = new DateTime('today', $tz);
    return eventCalendarDayMinsk($eventDate) > $today;
}

/** Календарный день акции — завтра (Europe/Minsk) */
function eventIsTomorrowMinsk($eventDate): bool {
    $tz = eventReminderTimezone();
    $today = new DateTime('today', $tz);
    $tomorrow = (clone $today)->modify('+1 day');
    return eventCalendarDayMinsk($eventDate)->format('Y-m-d') === $tomorrow->format('Y-m-d');
}

/** Текст для письма и страницы после записи */
function registrationReminderNoticeText($eventDate): ?string {
    if (!eventWillGetDayBeforeReminder($eventDate)) {
        return null;
    }
    if (eventIsTomorrowMinsk($eventDate)) {
        return 'Отдельным письмом придёт напоминание о завтрашней акции.';
    }
    return 'Накануне акции (утром) придёт напоминание на этот же e-mail.';
}

/**
 * Напоминание при записи: если акция завтра — сразу; иначе ждёт cron накануне.
 */
function sendMissedReminderOnRegistrationIfNeeded($db, int $eventId, int $userId): void {
    require_once __DIR__ . '/mail.php';

    $row = $db->fetchOne($db->query(
        "SELECT r.RegistrationID, u.Email, u.FullName, e.Title, e.EventDate, e.Location,
                ISNULL(c.CategoryName, N'') AS CategoryName
         FROM Registrations r
         INNER JOIN Users u ON r.UserID = u.UserID AND u.IsActive = 1
         INNER JOIN Events e ON r.EventID = e.EventID
         LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
         WHERE r.EventID = ? AND r.UserID = ? AND r.Status = N'confirmed'
           AND (r.ReminderSent = 0 OR r.ReminderSent IS NULL)
           AND e.IsPublished = 1 AND e.ModerationStatus = N'approved'",
        [$eventId, $userId]
    ));
    if (!$row) {
        return;
    }

    $tz = eventReminderTimezone();
    $now = new DateTime('now', $tz);
    $event = eventDateTime($row['EventDate']);
    $event->setTimezone($tz);
    if ($event <= $now) {
        return;
    }

    $today = new DateTime('today', $tz);
    $tomorrow = (clone $today)->modify('+1 day');
    $eventDay = eventCalendarDayMinsk($row['EventDate']);
    if ($eventDay->format('Y-m-d') !== $tomorrow->format('Y-m-d')) {
        return;
    }

    $df = $row['EventDate'] instanceof DateTimeInterface
        ? $row['EventDate']->format('d.m.Y H:i')
        : formatDate($row['EventDate']);
    $cat = trim((string)($row['CategoryName'] ?? ''));
    if (sendEventReminderEmail(
        $row['Email'],
        $row['FullName'],
        $row['Title'],
        $df,
        $row['Location'],
        $eventId,
        $cat
    )) {
        $db->query('UPDATE Registrations SET ReminderSent = 1 WHERE RegistrationID = ?', [$row['RegistrationID']]);
    }
}

/**
 * Отправить напоминания всем подходящим записям. Вызывается только из cron_reminders.php (по расписанию ОС или хостинга).
 *
 * @return int количество успешно отправленных писем
 */
function runScheduledEventReminders() {
    require_once __DIR__ . '/mail.php';

    $db = Database::getInstance();

    $tz = eventReminderTimezone();
    $tomorrow = new DateTime('tomorrow', $tz);
    $tomorrowStart = $tomorrow->format('Y-m-d 00:00:00');
    $tomorrowEnd = (clone $tomorrow)->modify('+1 day')->format('Y-m-d 00:00:00');

    $sql = "
        SELECT r.RegistrationID, r.UserID, u.Email, u.FullName, e.EventID, e.Title, e.EventDate, e.Location,
               ISNULL(c.CategoryName, N'') AS CategoryName
        FROM Registrations r
        INNER JOIN Users u ON r.UserID = u.UserID AND u.IsActive = 1
        INNER JOIN Events e ON r.EventID = e.EventID
        LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
        WHERE r.Status = N'confirmed'
          AND (r.ReminderSent = 0 OR r.ReminderSent IS NULL)
          AND e.EventDate >= ?
          AND e.EventDate < ?
          AND e.IsPublished = 1 AND e.ModerationStatus = N'approved'
    ";

    $stmt = $db->query($sql, [$tomorrowStart, $tomorrowEnd]);
    $rows = $db->fetchAll($stmt);
    $sent = 0;

    foreach ($rows as $row) {
        $ed = $row['EventDate'];
        $df = $ed instanceof DateTime ? $ed->format('d.m.Y H:i') : formatDate($ed);
        $cat = trim((string)($row['CategoryName'] ?? ''));
        if (sendEventReminderEmail(
            $row['Email'],
            $row['FullName'],
            $row['Title'],
            $df,
            $row['Location'],
            (int)$row['EventID'],
            $cat
        )) {
            $db->query('UPDATE Registrations SET ReminderSent = 1 WHERE RegistrationID = ?', [$row['RegistrationID']]);
            $sent++;
        }
    }

    return $sent;
}
