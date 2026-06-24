<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/mail.php';
require_once '../includes/event_reminders.php';

$db = Database::getInstance();
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

$sql = "SELECT e.*, c.CategoryName, " . sqlCategoryIsActiveSelect('c') . ", u.FullName as CreatorName, u.UserID as CreatorID,
        (SELECT COUNT(*) FROM Registrations WHERE EventID = e.EventID AND Status = N'confirmed') as ParticipantsCount
        FROM Events e
        LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
        LEFT JOIN Users u ON e.CreatorUserID = u.UserID
        WHERE e.EventID = ?";
$stmt = $db->query($sql, [$eventId]);
$event = $db->fetchOne($stmt);

if (!$event) {
    http_response_code(404);
    exit('Акция не найдена');
}

$modStatus = $event['ModerationStatus'] ?? 'approved';
$isPublicOk = eventIsInPublicCatalog($event);
$isOwner = isLoggedIn() && (int)$event['CreatorUserID'] === (int)$_SESSION['user_id'];
$mayView = $isPublicOk || (isLoggedIn() && (isAdmin() || $isOwner));
$catalogNotes = eventCatalogVisibilityNotes($event);

if (!$mayView) {
    http_response_code(404);
    exit('Акция не найдена или ещё не опубликована');
}

$isRegistered = false;
if (isLoggedIn()) {
    $st = $db->query("SELECT * FROM Registrations WHERE EventID = ? AND UserID = ? AND Status = N'confirmed'", [$eventId, $_SESSION['user_id']]);
    $isRegistered = (bool)$db->fetchOne($st);
}

$eventDt = eventDateTime($event['EventDate']);
$now = new DateTime('now', new DateTimeZone('Europe/Minsk'));
$isPastEvent = $eventDt < $now;
$canEditOrganizerOutcome = isLoggedIn() && $isPastEvent && $isOwner && eventAllowsOrganizerOutcomes($event);

$outcomeUploadDir = APP_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'event_outcomes';
$outcomeMaxFileBytes = 40 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_mod_approve' && isAdmin()) {
        $chk = $db->fetchOne($db->query("SELECT ModerationStatus FROM Events WHERE EventID = ?", [$eventId]));
        if ($chk && ($chk['ModerationStatus'] ?? '') === 'pending') {
            if (!eventHasPublishableCategory($db, $eventId)) {
                $error = 'Нельзя опубликовать: у акции нет активной категории. Верните категорию или назначьте другую.';
            } else {
                $db->query(
                    "UPDATE Events SET IsPublished = 1, ModerationStatus = N'approved', RejectionReason = NULL WHERE EventID = ?",
                    [$eventId]
                );
                redirect(APP_URL . '/pages/event_details.php?id=' . $eventId);
            }
        }
    } elseif ($action === 'admin_mod_reject' && isAdmin()) {
        $reason = trim($_POST['reject_reason'] ?? '');
        $chk = $db->fetchOne($db->query("SELECT ModerationStatus FROM Events WHERE EventID = ?", [$eventId]));
        if ($chk && ($chk['ModerationStatus'] ?? '') === 'pending') {
            if ($reason === '') {
                $error = 'Укажите причину отклонения.';
            } else {
                $db->query(
                    "UPDATE Events SET IsPublished = 0, ModerationStatus = N'rejected', RejectionReason = ? WHERE EventID = ?",
                    [$reason, $eventId]
                );
                redirect(APP_URL . '/pages/event_details.php?id=' . $eventId);
            }
        }
    } elseif ($action === 'register') {
        if ($isPastEvent) {
            $error = 'Запись на прошедшую акцию недоступна.';
        } else {
        $st = $db->query("SELECT * FROM Registrations WHERE EventID = ? AND UserID = ?", [$eventId, $_SESSION['user_id']]);
        $existing = $db->fetchOne($st);
        if ($existing) {
            $db->query("UPDATE Registrations SET Status = N'confirmed', RegisteredAt = GETDATE(), ReminderSent = 0 WHERE EventID = ? AND UserID = ?", [$eventId, $_SESSION['user_id']]);
        } else {
            $db->query("INSERT INTO Registrations (EventID, UserID, Status) VALUES (?, ?, N'confirmed')", [$eventId, $_SESSION['user_id']]);
        }
        $db->query("INSERT INTO ActivityLog (UserID, EventID, ActionType) VALUES (?, ?, N'register')", [$_SESSION['user_id'], $eventId]);

        $userRow = $db->fetchOne($db->query(
            "SELECT Email, FullName FROM Users WHERE UserID = ? AND IsActive = 1",
            [$_SESSION['user_id']]
        ));
        $mailSent = false;
        if ($userRow && !empty($userRow['Email'])) {
            $cat = trim((string)($event['CategoryName'] ?? ''));
            $mailSent = sendEventRegistrationEmail(
                $userRow['Email'],
                $userRow['FullName'] ?? 'участник',
                $event['Title'],
                formatDate($event['EventDate']),
                $event['Location'],
                $eventId,
                $cat,
                $event['EventDate']
            );
            if ($mailSent) {
                sendMissedReminderOnRegistrationIfNeeded($db, $eventId, (int)$_SESSION['user_id']);
            }
        }

        if ($mailSent) {
            $success = 'Вы записаны на акцию. Подтверждение отправлено на ваш e-mail.';
            $reminderNote = registrationReminderNoticeText($event['EventDate']);
            if ($reminderNote !== null) {
                $success .= ' ' . $reminderNote;
            }
        } else {
            $success = 'Вы записаны на акцию. Не удалось отправить письмо на e-mail — проверьте адрес в профиле или настройки почты на сервере.';
        }
        $isRegistered = true;
        }
    } elseif ($action === 'cancel') {
        $db->query("UPDATE Registrations SET Status = N'cancelled' WHERE EventID = ? AND UserID = ?", [$eventId, $_SESSION['user_id']]);
        $db->query("INSERT INTO ActivityLog (UserID, EventID, ActionType) VALUES (?, ?, N'cancel_registration')", [$_SESSION['user_id'], $eventId]);
        $success = 'Запись отменена.';
        $isRegistered = false;
    } elseif ($action === 'submit_outcome' && $canEditOrganizerOutcome) {
        $text = trim($_POST['outcome_text'] ?? '');
        if (mb_strlen($text, 'UTF-8') > 12000) {
            $text = mb_substr($text, 0, 12000, 'UTF-8');
        }
        $textParam = $text === '' ? null : $text;
        $hasFile = !empty($_FILES['outcome_file']['name'])
            && ($_FILES['outcome_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($textParam === null && !$hasFile) {
            $error = 'Напишите текст итогов и/или выберите файл, затем нажмите «Отправить».';
        } else {
            $fileMeta = null;
            $fileError = null;
            if ($hasFile) {
                $checked = validateEventOutcomeFile($_FILES['outcome_file'], $outcomeMaxFileBytes);
                if ($checked['error'] !== null) {
                    $fileError = $checked['error'];
                    if ($textParam === null) {
                        $error = $fileError;
                    }
                } else {
                    $fileMeta = $checked;
                }
            }

            if ($error === '' && ($textParam !== null || $fileMeta !== null)) {
                $outcomeId = 0;
                $ex = $db->fetchOne($db->query('SELECT OutcomeID FROM EventOutcomes WHERE EventID = ?', [$eventId]));
                if ($ex) {
                    $outcomeId = (int)$ex['OutcomeID'];
                    if ($textParam !== null) {
                        $db->query('UPDATE EventOutcomes SET BodyText = ?, UpdatedAt = GETDATE() WHERE EventID = ?', [$textParam, $eventId]);
                    } elseif ($hasFile) {
                        $db->query('UPDATE EventOutcomes SET UpdatedAt = GETDATE() WHERE EventID = ?', [$eventId]);
                    }
                } else {
                    $inserted = $db->fetchOne($db->query(
                        'INSERT INTO EventOutcomes (EventID, BodyText) OUTPUT INSERTED.OutcomeID AS OutcomeID VALUES (?, ?)',
                        [$eventId, $textParam]
                    ));
                    $outcomeId = (int)($inserted['OutcomeID'] ?? 0);
                    if ($outcomeId <= 0) {
                        $fallback = $db->fetchOne($db->query('SELECT OutcomeID FROM EventOutcomes WHERE EventID = ?', [$eventId]));
                        $outcomeId = (int)($fallback['OutcomeID'] ?? 0);
                    }
                }

                if ($outcomeId <= 0) {
                    $error = 'Не удалось сохранить итоги. Попробуйте ещё раз или обратитесь к администратору.';
                } elseif ($fileMeta !== null) {
                    if (!is_dir($outcomeUploadDir)) {
                        mkdir($outcomeUploadDir, 0755, true);
                    }
                    $f = $_FILES['outcome_file'];
                    $safe = 'out_ev' . $eventId . '_' . bin2hex(random_bytes(8)) . '.' . $fileMeta['ext'];
                    $dest = $outcomeUploadDir . DIRECTORY_SEPARATOR . $safe;
                    if (!move_uploaded_file($f['tmp_name'], $dest)) {
                        $error = 'Не удалось сохранить файл на сервере.';
                    } else {
                        $rel = 'assets/uploads/event_outcomes/' . $safe;
                        $fileStmt = $db->tryQuery(
                            'INSERT INTO EventOutcomeFiles (OutcomeID, MaterialType, FilePath, OriginalName) VALUES (?, ?, ?, ?)',
                            [$outcomeId, $fileMeta['type'], $rel, $f['name']]
                        );
                        if ($fileStmt === false) {
                            deleteStoredPublicFile($rel);
                            $error = 'Не удалось прикрепить файл к итогам. Попробуйте ещё раз.';
                        } else {
                            $db->query('UPDATE EventOutcomes SET UpdatedAt = GETDATE() WHERE OutcomeID = ?', [$outcomeId]);
                        }
                    }
                }
            }

            if ($error === '') {
                if ($fileError !== null) {
                    $success = 'Текст итогов сохранён. Файл не прикреплён: ' . $fileError;
                } else {
                    $success = 'Итоги отправлены. Администратор увидит их в разделе «Итоги».';
                }
            }
        }
    } elseif ($action === 'delete_outcome_file' && $canEditOrganizerOutcome) {
        $fid = (int)($_POST['outcome_file_id'] ?? 0);
        $st = $db->query(
            "SELECT f.FileID, f.FilePath FROM EventOutcomeFiles f
             INNER JOIN EventOutcomes o ON o.OutcomeID = f.OutcomeID
             WHERE f.FileID = ? AND o.EventID = ?",
            [$fid, $eventId]
        );
        $row = $db->fetchOne($st);
        if ($row) {
            deleteStoredPublicFile($row['FilePath']);
            $db->query("DELETE FROM EventOutcomeFiles WHERE FileID = ?", [$fid]);
            $db->query(
                "UPDATE EventOutcomes SET UpdatedAt = GETDATE() WHERE EventID = ?",
                [$eventId]
            );
            $success = 'Файл удалён.';
        }
    }

    $st = $db->query("SELECT COUNT(*) as c FROM Registrations WHERE EventID = ? AND Status = N'confirmed'", [$eventId]);
    $event['ParticipantsCount'] = $db->fetchOne($st)['c'];
    $st = $db->query("SELECT * FROM Registrations WHERE EventID = ? AND UserID = ? AND Status = N'confirmed'", [$eventId, $_SESSION['user_id']]);
    $isRegistered = (bool)$db->fetchOne($st);
}

$outcomeRow = $db->fetchOne($db->query("SELECT * FROM EventOutcomes WHERE EventID = ?", [$eventId]));
$outcomeFiles = [];
if ($outcomeRow && !empty($outcomeRow['OutcomeID'])) {
    $outcomeFiles = $db->fetchAll($db->query(
        "SELECT * FROM EventOutcomeFiles WHERE OutcomeID = ? ORDER BY CreatedAt ASC",
        [(int)$outcomeRow['OutcomeID']]
    ));
}
$showOutcomePublic = $outcomeRow && (
    trim((string)($outcomeRow['BodyText'] ?? '')) !== ''
    || !empty($outcomeFiles)
);

$participants = [];
if (isAdmin() || $isOwner) {
    $participants = $db->fetchAll($db->query(
        "SELECT u.FullName, u.Email, r.RegisteredAt FROM Registrations r JOIN Users u ON r.UserID = u.UserID
         WHERE r.EventID = ? AND r.Status = N'confirmed' ORDER BY r.RegisteredAt ASC",
        [$eventId]
    ));
}

$participantsSectionHtml = '';
if (!empty($participants)) {
    ob_start();
    ?>
                <section class="surface-block">
                    <h2 class="section-title">Записались</h2>
                    <ul class="participant-list">
                        <?php foreach ($participants as $p): ?>
                            <li>
                                <span class="p-name"><?= escape($p['FullName']) ?></span>
                                <?php if (isAdmin()): ?>
                                    <span class="p-email"><?= escape($p['Email']) ?></span>
                                <?php endif; ?>
                                <span class="p-when"><?= formatDate($p['RegisteredAt']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
    <?php
    $participantsSectionHtml = ob_get_clean();
}

$pageTitle = $event['Title'];
$eventCoverUrl = resolvedPublicFileUrl($event['CoverImagePath'] ?? '');
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <article class="event-article">
            <header class="event-article__head">
                <h1 class="page-title"><?= escape($event['Title']) ?></h1>
                <?php if (!empty($catalogNotes) && (isAdmin() || $isOwner)): ?>
                    <div class="alert alert-warn">
                        <ul class="list-plain">
                            <?php foreach ($catalogNotes as $note): ?>
                                <li><?= escape($note) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (isAdmin()): ?>
                            <p class="small muted">
                                <a href="<?= APP_URL ?>/pages/admin/events.php?scope=hidden">Все скрытые из каталога</a>
                                ·
                                <a href="<?= APP_URL ?>/pages/admin/categories.php">Категории</a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($eventCoverUrl !== ''): ?>
                <div class="event-cover">
                    <img src="<?= escape($eventCoverUrl) ?>" alt="">
                </div>
            <?php endif; ?>

            <?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

            <div class="event-meta event-meta--card">
                <p><span class="meta-k">Категория</span>
                    <?php if (empty($event['CategoryID'])): ?>
                        <span class="muted">не указана (удалена ранее)</span>
                    <?php elseif (isset($event['CategoryIsActive']) && (int)$event['CategoryIsActive'] === 0): ?>
                        <?= escape($event['CategoryName'] ?? '—') ?>
                        <span class="status-pill status-pill--warn">категория снята с публикации</span>
                    <?php else: ?>
                        <?= escape($event['CategoryName'] ?? '—') ?>
                    <?php endif; ?>
                </p>
                <p><span class="meta-k">Когда</span> <?= formatDate($event['EventDate']) ?></p>
                <p><span class="meta-k">Место</span> <?= escape($event['Location']) ?></p>
                <p><span class="meta-k">Организатор</span> <?= escape($event['CreatorName']) ?></p>
                <p><span class="meta-k">Участников</span> <?= (int)$event['ParticipantsCount'] ?><?= !empty($event['MaxParticipants']) ? ' / ' . (int)$event['MaxParticipants'] : '' ?></p>
            </div>

            <?php if ($participantsSectionHtml !== '' && !isAdmin() && $isOwner): ?>
                <?= $participantsSectionHtml ?>
            <?php endif; ?>

            <section class="surface-block event-description">
                <h2 class="section-title">Описание</h2>
                <div class="prose"><?= nl2br(escape($event['Description'])) ?></div>
            </section>

            <?php if ($showOutcomePublic): ?>
                <section class="surface-block event-outcomes-public">
                    <h2 class="section-title">Итоги</h2>
                    <?php if (trim((string)($outcomeRow['BodyText'] ?? '')) !== ''): ?>
                        <div class="prose"><?= nl2br(escape($outcomeRow['BodyText'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($outcomeFiles)): ?>
                        <div class="materials-grid materials-grid--outcome">
                            <?php foreach ($outcomeFiles as $of): ?>
                                <?php $ou = resolvedPublicFileUrl($of['FilePath'] ?? ''); ?>
                                <div class="material-card">
                                    <?php if (($of['MaterialType'] ?? '') === 'image' && $ou !== ''): ?>
                                        <a href="<?= escape($ou) ?>" target="_blank" rel="noopener">
                                            <img src="<?= escape($ou) ?>" alt="" class="material-thumb">
                                        </a>
                                    <?php elseif (($of['MaterialType'] ?? '') === 'video' && $ou !== ''): ?>
                                        <video class="material-video" controls src="<?= escape($ou) ?>"></video>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($canEditOrganizerOutcome): ?>
                <section class="surface-block event-outcomes-edit">
                    <h2 class="section-title">Итоги для администратора</h2>
                    <p class="muted">Кратко опишите итог или приложите фото/видео (MP4) — одной отправкой. Администратор увидит материалы в разделе «Итоги» и подготовит новость.</p>
                    <form method="POST" enctype="multipart/form-data" class="stack-form">
                        <input type="hidden" name="action" value="submit_outcome">
                        <div class="form-group">
                            <label for="outcome_text">Текст итогов</label>
                            <textarea id="outcome_text" name="outcome_text" rows="6" placeholder="Что сделали, сколько человек участвовало — что важно для новости"><?= escape($outcomeRow['BodyText'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="outcome_file">Файл (по желанию)</label>
                            <input type="file" id="outcome_file" name="outcome_file" accept="image/*,video/mp4">
                        </div>
                        <button type="submit" class="btn">Отправить</button>
                    </form>
                    <?php if (!empty($outcomeFiles)): ?>
                        <ul class="outcome-files-edit list-plain">
                            <?php foreach ($outcomeFiles as $of): ?>
                                <?php $ou = resolvedPublicFileUrl($of['FilePath'] ?? ''); ?>
                                <li class="outcome-files-edit__row">
                                    <a href="<?= escape($ou) ?>" target="_blank" rel="noopener"><?= escape($of['OriginalName'] ?? 'Файл') ?></a>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Удалить файл?');">
                                        <input type="hidden" name="action" value="delete_outcome_file">
                                        <input type="hidden" name="outcome_file_id" value="<?= (int)$of['FileID'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-small">Удалить</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="event-actions-bar">
                <?php if (isLoggedIn() && $isPublicOk): ?>
                    <?php if ($isRegistered): ?>
                        <form method="POST" onsubmit="return confirm('Отменить запись?');">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger">Отменить запись</button>
                        </form>
                    <?php else: ?>
                        <?php if (!$event['MaxParticipants'] || (int)$event['ParticipantsCount'] < (int)$event['MaxParticipants']): ?>
                            <?php if (!$isPastEvent): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="register">
                                    <button type="submit" class="btn">Записаться</button>
                                </form>
                            <?php else: ?>
                                <p class="muted">Запись закрыта: акция уже прошла.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="alert alert-warn">Достигнут лимит участников.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php elseif (!$isPublicOk): ?>
                    <p class="muted">Запись доступна после публикации акции.</p>
                <?php else: ?>
                    <p><a href="<?= APP_URL ?>/pages/login.php">Войдите</a> или <a href="<?= APP_URL ?>/pages/register.php">зарегистрируйтесь</a>, чтобы записаться.</p>
                <?php endif; ?>

                <?php if (isLoggedIn() && (isAdmin() || $isOwner)): ?>
                    <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/edit_event.php?id=<?= $eventId ?>">Редактировать</a>
                    <a class="btn btn-danger" href="<?= APP_URL ?>/pages/delete_event.php?id=<?= $eventId ?>" onclick="return confirm('Удалить акцию?');">Удалить</a>
                <?php endif; ?>
            </section>

            <?php if ($participantsSectionHtml !== '' && isAdmin()): ?>
                <?= $participantsSectionHtml ?>
            <?php endif; ?>

            <?php if (isAdmin() && $modStatus === 'pending'): ?>
                <section class="surface-block event-admin-mod" aria-label="Модерация заявки">
                    <h2 class="section-title">Модерация</h2>
                    <p class="muted small">Опубликуйте акцию в каталоге или отклоните заявку с причиной — её увидит организатор на этой странице и в личном кабинете.</p>
                    <div class="mod-actions mod-actions--on-card">
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="admin_mod_approve">
                            <button type="submit" class="btn">Одобрить</button>
                        </form>
                        <form method="POST" class="mod-reject-form mod-reject-form--on-card">
                            <input type="hidden" name="action" value="admin_mod_reject">
                            <label class="sr-only" for="reject_reason_card">Причина отклонения</label>
                            <input type="text" id="reject_reason_card" name="reject_reason" placeholder="Причина отклонения" autocomplete="off" required>
                            <button type="submit" class="btn btn-danger">Отклонить</button>
                        </form>
                    </div>
                    <p class="muted small event-admin-mod__back"><a href="<?= APP_URL ?>/pages/admin/moderation.php">Все заявки в разделе «Модерация»</a></p>
                </section>
            <?php endif; ?>

            <p class="back-link"><a href="<?= APP_URL ?>/index.php">← Все акции</a></p>
        </article>
    </main>
<?php include '../includes/html_foot.php'; ?>
