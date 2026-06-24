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
    "SELECT COUNT(*) as c FROM Registrations WHERE UserID = ? AND Status = N'confirmed'",
    [$userId]
));
$total = (int)($countRow['c'] ?? 0);
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($pageNum > $totalPages && $totalPages > 0) {
    $pageNum = $totalPages;
    $offset = ($pageNum - 1) * $perPage;
}

$registrations = $db->fetchAll($db->query(
    "SELECT e.*, c.CategoryName, r.RegisteredAt, r.Status
     FROM Registrations r
     JOIN Events e ON r.EventID = e.EventID
     LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
     WHERE r.UserID = ? AND r.Status = N'confirmed'
     ORDER BY e.EventDate ASC
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    [$userId, $offset, $perPage]
));

$pageTitle = 'Участие в акциях';
include '../includes/html_head.php';
include '../includes/site_header.php';
$listBase = APP_URL . '/pages/my_participation.php?page=';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">Участие в акциях</h1>
            <p class="muted"><a href="<?= APP_URL ?>/pages/profile.php">← Личный кабинет</a></p>
        </header>

        <section class="surface-block profile-list-page">
            <?php if (empty($registrations)): ?>
                <p class="muted">Нет подтверждённых записей.</p>
                <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/events.php">Каталог акций</a>
            <?php else: ?>
                <ul class="user-event-cards">
                    <?php foreach ($registrations as $reg): ?>
                        <li class="user-event-card">
                            <div class="user-event-card__head">
                                <span class="status-pill status-pill--approved">Участвую</span>
                            </div>
                            <h2 class="user-event-card__title"><?= escape($reg['Title']) ?></h2>
                            <p class="muted small"><?= escape($reg['CategoryName'] ?? 'Без категории') ?></p>
                            <p class="muted small"><?= formatDate($reg['EventDate']) ?> · <?= escape($reg['Location']) ?></p>
                            <p class="muted small">Запись оформлена: <?= formatDateOnly($reg['RegisteredAt']) ?></p>
                            <div class="user-event-card__links">
                                <a class="btn btn-small" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$reg['EventID'] ?>">Подробнее</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination pagination--tight profile-list-page__pagination" aria-label="Страницы списка участия">
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
