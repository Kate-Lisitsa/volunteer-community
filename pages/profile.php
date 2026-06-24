<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$user = $db->fetchOne($db->query("SELECT * FROM Users WHERE UserID = ?", [$userId]));

$cardPreviewLimit = 3;

$orgCountRow = $db->fetchOne($db->query(
    "SELECT COUNT(*) as c FROM Events WHERE CreatorUserID = ?",
    [$userId]
));
$orgTotal = (int)($orgCountRow['c'] ?? 0);

$myEvents = $db->fetchAll($db->query(
    "SELECT e.*, c.CategoryName,
        (SELECT COUNT(*) FROM Registrations WHERE EventID = e.EventID AND Status = N'confirmed') as ParticipantsCount
     FROM Events e
     LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
     WHERE e.CreatorUserID = ?
     ORDER BY CASE WHEN ISNULL(e.ModerationStatus, N'') = N'pending' THEN 0 ELSE 1 END,
              e.CreatedAt DESC,
              e.EventDate DESC
     OFFSET 0 ROWS FETCH NEXT ? ROWS ONLY",
    [$userId, $cardPreviewLimit]
));

$regCountRow = $db->fetchOne($db->query(
    "SELECT COUNT(*) as c FROM Registrations WHERE UserID = ? AND Status = N'confirmed'",
    [$userId]
));
$regTotal = (int)($regCountRow['c'] ?? 0);

$myRegistrations = $db->fetchAll($db->query(
    "SELECT e.*, c.CategoryName, r.RegisteredAt, r.Status
     FROM Registrations r
     JOIN Events e ON r.EventID = e.EventID
     LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
     WHERE r.UserID = ? AND r.Status = N'confirmed'
     ORDER BY e.EventDate ASC
     OFFSET 0 ROWS FETCH NEXT ? ROWS ONLY",
    [$userId, $cardPreviewLimit]
));

$actCountRow = $db->fetchOne($db->query(
    "SELECT COUNT(*) as c FROM ActivityLog WHERE UserID = ?",
    [$userId]
));
$actTotal = (int)($actCountRow['c'] ?? 0);

$activity = $db->fetchAll($db->query(
    "SELECT a.*, e.Title
     FROM ActivityLog a
     LEFT JOIN Events e ON a.EventID = e.EventID
     WHERE a.UserID = ?
     ORDER BY a.ActionDate DESC
     OFFSET 0 ROWS FETCH NEXT ? ROWS ONLY",
    [$userId, $cardPreviewLimit]
));

$pageTitle = 'Личный кабинет';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">Личный кабинет</h1>
        </header>

        <div class="profile-header surface-block">
            <?php if (!empty($user['AvatarPath']) && file_exists(APP_ROOT . '/assets/uploads/avatars/' . $user['AvatarPath'])): ?>
                <img src="<?= APP_URL ?>/assets/uploads/avatars/<?= escape($user['AvatarPath']) ?>" alt="" class="profile-avatar">
            <?php else: ?>
                <div class="profile-avatar-placeholder" aria-hidden="true"><?= escape(mb_substr($user['FullName'], 0, 1, 'UTF-8')) ?></div>
            <?php endif; ?>
            <div class="profile-info">
                <h2 class="profile-name"><?= escape($user['FullName']) ?></h2>
                <p class="muted"><?= escape($user['Email']) ?></p>
                <p class="muted">Телефон: <?= escape($user['Phone'] ?? 'не указан') ?></p>
                <p class="profile-role"><?= $user['Role'] === 'admin' ? 'Администратор' : 'Волонтёр' ?></p>
                <p class="muted">Регистрация: <?= formatDate($user['RegisteredAt']) ?></p>
                <div class="profile-actions">
                    <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/edit_profile.php">Профиль</a>
                    <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/upload_avatar.php">Аватар</a>
                    <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/change_password.php">Пароль</a>
                    <?php if (!isAdmin()): ?>
                        <a class="btn" href="<?= APP_URL ?>/pages/create_event.php">Новая акция</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-columns">
            <section class="surface-block profile-cards-section">
                <h2 class="section-title">Мои акции (организатор)</h2>
                <?php if (empty($myEvents)): ?>
                    <p class="muted">Вы ещё не создавали акций.</p>
                    <a class="btn" href="<?= APP_URL ?>/pages/create_event.php">Создать</a>
                <?php else: ?>
                    <ul class="user-event-cards">
                        <?php foreach ($myEvents as $event): ?>
                            <?php $ms = $event['ModerationStatus'] ?? 'approved'; ?>
                            <li class="user-event-card">
                                <div class="user-event-card__head">
                                    <span class="status-pill status-pill--<?= escape($ms) ?>"><?= escape(moderationLabel($ms)) ?></span>
                                </div>
                                <h3 class="user-event-card__title"><?= escape($event['Title']) ?></h3>
                                <?php if ($ms === 'rejected' && !empty($event['RejectionReason'])): ?>
                                    <p class="muted small">Причина: <?= escape($event['RejectionReason']) ?></p>
                                <?php endif; ?>
                                <p class="muted small"><?= formatDate($event['EventDate']) ?> · <?= escape($event['Location']) ?></p>
                                <p class="muted small">Записей: <?= (int)$event['ParticipantsCount'] ?></p>
                                <div class="user-event-card__links">
                                    <a class="btn btn-small" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$event['EventID'] ?>">Карточка</a>
                                    <a class="btn btn-small btn-secondary" href="<?= APP_URL ?>/pages/edit_event.php?id=<?= (int)$event['EventID'] ?>">Правка</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($orgTotal > $cardPreviewLimit): ?>
                        <p class="profile-section-more">
                            <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/organized_events.php">Все мои акции (<?= (int)$orgTotal ?>)</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="surface-block profile-cards-section">
                <h2 class="section-title">Участие в акциях</h2>
                <?php if (empty($myRegistrations)): ?>
                    <p class="muted">Нет активных записей.</p>
                    <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/events.php">Каталог</a>
                <?php else: ?>
                    <ul class="user-event-cards">
                        <?php foreach ($myRegistrations as $reg): ?>
                            <li class="user-event-card">
                                <div class="user-event-card__head">
                                    <span class="status-pill status-pill--approved">Участвую</span>
                                </div>
                                <h3 class="user-event-card__title"><?= escape($reg['Title']) ?></h3>
                                <p class="muted small"><?= escape($reg['CategoryName'] ?? 'Без категории') ?></p>
                                <p class="muted small"><?= formatDate($reg['EventDate']) ?> · <?= escape($reg['Location']) ?></p>
                                <p class="muted small">Запись оформлена: <?= formatDateOnly($reg['RegisteredAt']) ?></p>
                                <div class="user-event-card__links">
                                    <a class="btn btn-small" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$reg['EventID'] ?>">Подробнее</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($regTotal > $cardPreviewLimit): ?>
                        <p class="profile-section-more">
                            <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/my_participation.php">Все акции с участием (<?= (int)$regTotal ?>)</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <section class="surface-block profile-activity">
            <h2 class="section-title">История действий</h2>
            <?php if (empty($activity)): ?>
                <p class="muted">Пока пусто.</p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($activity as $act): ?>
                        <li>
                            <strong><?= escape(activityActionTypeLabel($act['ActionType'])) ?></strong>
                            <?php if (!empty($act['Title'])): ?> — «<?= escape($act['Title']) ?>»<?php endif; ?>
                            <div class="activity-date"><?= formatDate($act['ActionDate']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($actTotal > $cardPreviewLimit): ?>
                    <p class="profile-activity-more">
                        <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/activity_history.php">Вся история<?php if ($actTotal > 0): ?> (<?= (int)$actTotal ?>)<?php endif; ?></a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
<?php include '../includes/html_foot.php'; ?>
