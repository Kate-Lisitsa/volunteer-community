<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$actPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$actPer = 15;
$actOffset = ($actPage - 1) * $actPer;

$actCountRow = $db->fetchOne($db->query(
    "SELECT COUNT(*) as c FROM ActivityLog WHERE UserID = ?",
    [$userId]
));
$actTotal = (int)($actCountRow['c'] ?? 0);
$actTotalPages = $actTotal > 0 ? (int)ceil($actTotal / $actPer) : 1;
if ($actPage > $actTotalPages && $actTotalPages > 0) {
    $actPage = $actTotalPages;
    $actOffset = ($actPage - 1) * $actPer;
}

$activity = $db->fetchAll($db->query(
    "SELECT a.*, e.Title
     FROM ActivityLog a
     LEFT JOIN Events e ON a.EventID = e.EventID
     WHERE a.UserID = ?
     ORDER BY a.ActionDate DESC
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    [$userId, $actOffset, $actPer]
));

$pageTitle = 'История действий';
include '../includes/html_head.php';
include '../includes/site_header.php';
$historyBase = APP_URL . '/pages/activity_history.php?page=';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">История действий</h1>
            <p class="muted"><a href="<?= APP_URL ?>/pages/profile.php">← Личный кабинет</a></p>
        </header>

        <section class="surface-block profile-activity">
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
                <?php if ($actTotalPages > 1): ?>
                    <nav class="pagination pagination--tight" aria-label="Страницы истории">
                        <?php if ($actPage > 1): ?>
                            <a href="<?= escape($historyBase . ($actPage - 1)) ?>" class="pagination__arrow" aria-label="Предыдущая страница">←</a>
                        <?php else: ?>
                            <span class="disabled pagination__arrow" aria-hidden="true">←</span>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $actTotalPages; $i++): ?>
                            <?php if ($i === $actPage): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= escape($historyBase . $i) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($actPage < $actTotalPages): ?>
                            <a href="<?= escape($historyBase . ($actPage + 1)) ?>" class="pagination__arrow" aria-label="Следующая страница">→</a>
                        <?php else: ?>
                            <span class="disabled pagination__arrow" aria-hidden="true">→</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
<?php include '../includes/html_foot.php'; ?>
