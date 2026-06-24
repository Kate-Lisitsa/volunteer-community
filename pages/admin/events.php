<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAdmin();

$db = Database::getInstance();
$success = '';
$error = '';

$scope = isset($_GET['scope']) ? preg_replace('/[^a-z_]/', '', $_GET['scope']) : 'upcoming';
if (!in_array($scope, ['upcoming', 'past', 'hidden'], true)) {
    $scope = 'upcoming';
}
$filterCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;

if (isset($_POST['delete_event'])) {
    $eventId = (int)$_POST['event_id'];
    $stmt = $db->query("SELECT Title, CoverImagePath, EventDate FROM Events WHERE EventID = ?", [$eventId]);
    $event = $db->fetchOne($stmt);
    if ($event) {
        if (!empty($event['CoverImagePath'])) {
            deleteStoredPublicFile($event['CoverImagePath']);
        }
        $db->query("DELETE FROM ActivityLog WHERE EventID = ?", [$eventId]);
        $db->query("DELETE FROM Registrations WHERE EventID = ?", [$eventId]);
        $db->query("DELETE FROM Events WHERE EventID = ?", [$eventId]);
        $success = 'Акция «' . escape($event['Title']) . '» удалена безвозвратно.';
    } else {
        $error = 'Акция не найдена';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mod_action'])) {
    $eid = (int)($_POST['event_id'] ?? 0);
    $action = $_POST['mod_action'] ?? '';
    $st = $db->query("SELECT EventID, Title FROM Events WHERE EventID = ?", [$eid]);
    $ev = $db->fetchOne($st);
    if (!$ev) {
        $error = 'Акция не найдена.';
    } elseif ($action === 'approve') {
        if (!eventHasPublishableCategory($db, $eid)) {
            $error = 'Нельзя опубликовать: у акции нет активной категории. Верните категорию или назначьте другую.';
        } else {
            $db->query(
                "UPDATE Events SET IsPublished = 1, ModerationStatus = N'approved', RejectionReason = NULL WHERE EventID = ?",
                [$eid]
            );
            $success = 'Акция «' . escape($ev['Title']) . '» одобрена и опубликована.';
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reject_reason'] ?? '');
        if ($reason === '') {
            $error = 'Укажите причину отклонения.';
        } else {
            $db->query(
                "UPDATE Events SET IsPublished = 0, ModerationStatus = N'rejected', RejectionReason = ? WHERE EventID = ?",
                [$reason, $eid]
            );
            $success = 'Акция «' . escape($ev['Title']) . '» отклонена.';
        }
    }
}

if ($scope === 'past') {
    $dateFilter = sqlPastEvents('e');
} elseif ($scope === 'hidden') {
    $dateFilter = sqlAdminHiddenFromCatalog('e', 'c');
} else {
    $dateFilter = sqlAdminUpcomingInCatalog('e', 'c');
}

$scopeCountFrom = 'FROM Events e LEFT JOIN Categories c ON e.CategoryID = c.CategoryID';
$scopeCounts = [
    'upcoming' => (int)($db->fetchOne($db->query(
        'SELECT COUNT(*) AS c ' . $scopeCountFrom . ' WHERE ' . sqlAdminUpcomingInCatalog('e', 'c')
    ))['c'] ?? 0),
    'hidden' => (int)($db->fetchOne($db->query(
        'SELECT COUNT(*) AS c ' . $scopeCountFrom . ' WHERE ' . sqlAdminHiddenFromCatalog('e', 'c')
    ))['c'] ?? 0),
    'past' => (int)($db->fetchOne($db->query(
        'SELECT COUNT(*) AS c ' . $scopeCountFrom . ' WHERE ' . sqlPastEvents('e')
    ))['c'] ?? 0),
];

$search = adminListSearch();
$modFilter = adminListFilter('mod', '', ['' => '', 'approved' => '', 'pending' => '', 'rejected' => '']);
$pubFilter = adminListFilter('pub', '', ['' => '', '1' => '', '0' => '']);
$sortOptions = [
    'date_asc' => 'e.EventDate ASC',
    'date_desc' => 'e.EventDate DESC',
    'title_asc' => 'e.Title ASC',
    'title_desc' => 'e.Title DESC',
    'created_desc' => 'e.CreatedAt DESC',
    'location_asc' => 'e.Location ASC',
];
[$sort, $orderBy] = adminListSort($scope === 'past' || $scope === 'hidden' ? 'date_desc' : 'date_asc', $sortOptions);
$page = adminListPage();
$perPage = adminListPerPage(25);

$params = [];
$where = [$dateFilter];
if ($filterCategory > 0) {
    $where[] = 'e.CategoryID = ?';
    $params[] = $filterCategory;
}
adminApplySearch(['e.Title', 'e.Location', 'e.Description', 'u.FullName', 'c.CategoryName'], $search, $where, $params);
if ($modFilter !== '') {
    $where[] = 'e.ModerationStatus = ?';
    $params[] = $modFilter;
}
if ($scope === 'past') {
    if ($pubFilter === '1') {
        $where[] = 'e.IsPublished = 1';
    } elseif ($pubFilter === '0') {
        $where[] = 'e.IsPublished = 0';
    }
}
$whereSql = implode(' AND ', $where);
$fromSql = "FROM Events e
     LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
     LEFT JOIN Users u ON e.CreatorUserID = u.UserID
     WHERE {$whereSql}";

$totalEvents = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c {$fromSql}", $params))['c'] ?? 0);
$offset = ($page - 1) * $perPage;
$eventsList = $db->fetchAll($db->query(
    "SELECT e.*, c.CategoryName, " . sqlCategoryIsActiveSelect('c') . ", u.FullName as CreatorName
     {$fromSql}
     ORDER BY {$orderBy}
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    array_merge($params, [$offset, $perPage])
));

$allCategories = $db->fetchAll($db->query("SELECT CategoryID, CategoryName FROM Categories ORDER BY CategoryName"));
$categoryFilterOptions = ['' => 'Все категории'];
foreach ($allCategories as $catRow) {
    $categoryFilterOptions[(string)$catRow['CategoryID']] = $catRow['CategoryName'];
}
$listPath = '/pages/admin/events.php';
$adminToolbar = [
    'action' => APP_URL . $listPath,
    'reset_url' => adminEventsScopeUrl($scope),
    'hidden' => ['scope' => $scope],
    'search' => ['value' => $search, 'placeholder' => 'Название, место, организатор, категория'],
    'filters' => array_values(array_filter([
        [
            'name' => 'category',
            'label' => 'Категория',
            'value' => $filterCategory > 0 ? (string)$filterCategory : '',
            'options' => $categoryFilterOptions,
        ],
        [
            'name' => 'mod',
            'label' => 'Модерация',
            'value' => $modFilter,
            'options' => ['' => 'Любой статус', 'approved' => 'Одобрено', 'pending' => 'На модерации', 'rejected' => 'Отклонено'],
        ],
        $scope === 'past' ? [
            'name' => 'pub',
            'label' => 'Публикация',
            'value' => $pubFilter,
            'options' => ['' => 'Все', '1' => 'Опубликованные', '0' => 'Снятые'],
        ] : null,
    ])),
    'sort' => [
        'value' => $sort,
        'options' => [
            'date_asc' => 'Дата: ближайшие',
            'date_desc' => 'Дата: поздние',
            'title_asc' => 'Название А—Я',
            'title_desc' => 'Название Я—А',
            'created_desc' => 'Сначала новые',
            'location_asc' => 'Место А—Я',
        ],
    ],
    'per' => ['value' => $perPage],
    'count' => $totalEvents,
];

$filterCategoryName = '';
if ($filterCategory > 0) {
    $catRow = $db->fetchOne($db->query("SELECT CategoryName FROM Categories WHERE CategoryID = ?", [$filterCategory]));
    $filterCategoryName = trim((string)($catRow['CategoryName'] ?? ''));
}

$scopeTitles = [
    'upcoming' => 'Предстоящие акции',
    'past' => 'Прошедшие акции',
    'hidden' => 'Скрытые из каталога',
];
$scopeEmpty = [
    'upcoming' => 'Нет предстоящих акций.',
    'past' => 'Прошедших акций пока нет.',
    'hidden' => 'Нет акций вне каталога. Сбросьте фильтры над списком.',
    'upcoming' => 'Нет предстоящих акций в каталоге.',
];
$pageTitle = 'Акции';
$bodyClass = 'admin-area';
$adminCurrent = 'events';

include '../../includes/html_head.php';
include '../../includes/site_header.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Управление акциями</h1>

        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

        <p class="muted admin-events-note">
            <?php if ($scope === 'hidden'): ?>
                Акции вне публичного каталога: на модерации, отклонённые, без категории, снятые с публикации и другие предстоящие, не попадающие в каталог. Отклонённые и без категории показываются здесь независимо от даты.
            <?php elseif ($scope === 'upcoming'): ?>
                Предстоящие акции, которые сейчас видны гостям в каталоге.
            <?php else: ?>
                Прошедшие акции остаются в базе для отчётов и истории. Из публичного каталога они автоматически скрываются после даты проведения.
            <?php endif; ?>
        </p>

        <nav class="admin-subtabs" aria-label="Разделы управления акциями">
            <a class="<?= $scope === 'upcoming' ? 'is-active' : '' ?>" href="<?= escape(adminEventsScopeUrl('upcoming')) ?>">Предстоящие <span class="muted">(<?= $scopeCounts['upcoming'] ?>)</span></a>
            <a class="<?= $scope === 'past' ? 'is-active' : '' ?>" href="<?= escape(adminEventsScopeUrl('past')) ?>">Прошедшие <span class="muted">(<?= $scopeCounts['past'] ?>)</span></a>
            <a class="<?= $scope === 'hidden' ? 'is-active' : '' ?>" href="<?= escape(adminEventsScopeUrl('hidden')) ?>">Скрытые из каталога <span class="muted">(<?= $scopeCounts['hidden'] ?>)</span></a>
        </nav>

        <section class="surface-block">
            <h2 class="section-title"><?= escape($scopeTitles[$scope] ?? 'Акции') ?></h2>
            <?php if ($filterCategory > 0): ?>
                <p class="muted admin-events-filter">
                    Фильтр: категория «<?= escape($filterCategoryName !== '' ? $filterCategoryName : 'ID ' . $filterCategory) ?>».
                    <a href="<?= escape(adminEventsScopeUrl($scope)) ?>">Показать все</a>
                </p>
            <?php endif; ?>
            <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
            <div class="event-list-admin">
                <?php if (empty($eventsList)): ?>
                    <p class="muted"><?= escape($scopeEmpty[$scope] ?? 'Акций нет.') ?></p>
                <?php else: ?>
                    <?php foreach ($eventsList as $event): ?>
                        <?php $past = isEventPast($event['EventDate']); ?>
                        <div class="event-item-admin">
                            <div class="event-info">
                                <strong><?= escape($event['Title']) ?></strong>
                                <div class="event-meta muted">
                                    <?= formatDate($event['EventDate']) ?> · <?= escape($event['Location']) ?> · <?= escape($event['CreatorName'] ?? '') ?>
                                    <?php foreach (eventAdminStatusPills($event) as $pill): ?>
                                        <span class="status-pill <?= escape($pill['class']) ?>"><?= escape($pill['text']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="event-actions-admin">
                                <a class="btn btn-small btn-secondary" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$event['EventID'] ?>">Открыть</a>
                                <?php if (!$past && strtolower((string)($event['ModerationStatus'] ?? '')) === 'pending'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="event_id" value="<?= (int)$event['EventID'] ?>">
                                        <input type="hidden" name="mod_action" value="approve">
                                        <button type="submit" class="btn btn-small">Одобрить</button>
                                    </form>
                                    <form method="POST" class="mod-reject-form dashboard-mod-reject">
                                        <input type="hidden" name="event_id" value="<?= (int)$event['EventID'] ?>">
                                        <input type="hidden" name="mod_action" value="reject">
                                        <label class="sr-only" for="rej<?= (int)$event['EventID'] ?>">Причина отказа</label>
                                        <input type="text" id="rej<?= (int)$event['EventID'] ?>" name="reject_reason" placeholder="Причина отказа" required>
                                        <button type="submit" class="btn btn-small btn-danger">Отклонить</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm(<?= $past ? "'Прошедшая акция учитывается в отчётах. Удалить безвозвратно?'" : "'Удалить эту акцию безвозвратно?'" ?>);">
                                    <input type="hidden" name="event_id" value="<?= (int)$event['EventID'] ?>">
                                    <button type="submit" name="delete_event" class="btn btn-small btn-danger">Удалить</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?= adminRenderPagination($totalEvents, $page, $perPage, $listPath, ['scope' => $scope]) ?>
        </section>
    </main>
<?php include '../../includes/html_foot.php'; ?>
