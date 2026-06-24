<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$pageNum = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset = ($pageNum - 1) * $perPage;

$countRow = $db->fetchOne($db->query(
    "SELECT COUNT(*) as c FROM Events WHERE CreatorUserID = ?",
    [$userId]
));
$total = (int)($countRow['c'] ?? 0);
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($pageNum > $totalPages && $totalPages > 0) {
    $pageNum = $totalPages;
    $offset = ($pageNum - 1) * $perPage;
}

$events = $db->fetchAll($db->query(
    "SELECT e.*, c.CategoryName,
        (SELECT COUNT(*) FROM Registrations WHERE EventID = e.EventID AND Status = N'confirmed') as ParticipantsCount
     FROM Events e
     LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
     WHERE e.CreatorUserID = ?
     ORDER BY CASE WHEN ISNULL(e.ModerationStatus, N'') = N'pending' THEN 0 ELSE 1 END,
              e.CreatedAt DESC,
              e.EventDate DESC
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    [$userId, $offset, $perPage]
));

$pageTitle = 'Мои акции (организатор)';
include '../includes/html_head.php';
include '../includes/site_header.php';
$listBase = APP_URL . '/pages/organized_events.php?page=';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">Мои акции (организатор)</h1>
            <p class="muted"><a href="<?= APP_URL ?>/pages/profile.php">← Личный кабинет</a></p>
        </header>

        <section class="surface-block profile-list-page">
            <?php if (empty($events)): ?>
                <p class="muted">Вы ещё не создавали акций.</p>
                <a class="btn" href="<?= APP_URL ?>/pages/create_event.php">Создать акцию</a>
            <?php else: ?>
                <ul class="user-event-cards">
                    <?php foreach ($events as $event): ?>
                        <?php $ms = $event['ModerationStatus'] ?? 'approved'; ?>
                        <li class="user-event-card">
                            <div class="user-event-card__head">
                                <span class="status-pill status-pill--<?= escape($ms) ?>"><?= escape(moderationLabel($ms)) ?></span>
                            </div>
                            <h2 class="user-event-card__title"><?= escape($event['Title']) ?></h2>
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
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination pagination--tight profile-list-page__pagination" aria-label="Страницы списка акций">
                        <?php if ($pageNum > 1): ?>
                            <a href="<?= escape($listBase . ($pageNum - 1)) ?>" class="pagination__arrow" aria-label="Предыдущая страница">←</a>
                        <?php else: ?>
                            <span class="disabled pagination__arrow" aria-hidden="true">←</span>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === $pageNum): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= escape($listBase . $i) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($pageNum < $totalPages): ?>
                            <a href="<?= escape($listBase . ($pageNum + 1)) ?>" class="pagination__arrow" aria-label="Следующая страница">→</a>
                        <?php else: ?>
                            <span class="disabled pagination__arrow" aria-hidden="true">→</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
<?php include '../includes/html_foot.php'; ?>
