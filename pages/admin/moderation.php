<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAdmin();

$db = Database::getInstance();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eid = (int)($_POST['event_id'] ?? 0);
    $action = $_POST['mod_action'] ?? '';
    $st = $db->query("SELECT EventID, Title FROM Events WHERE EventID = ?", [$eid]);
    $ev = $db->fetchOne($st);
    if (!$ev) {
        $err = 'Акция не найдена.';
    } elseif ($action === 'approve') {
        if (!eventHasPublishableCategory($db, $eid)) {
            $err = 'Нельзя опубликовать: у акции нет активной категории. Верните категорию или назначьте другую.';
        } else {
            $db->query(
                "UPDATE Events SET IsPublished = 1, ModerationStatus = N'approved', RejectionReason = NULL WHERE EventID = ?",
                [$eid]
            );
            $msg = 'Акция «' . escape($ev['Title']) . '» опубликована.';
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reject_reason'] ?? '');
        if ($reason === '') {
            $err = 'Укажите причину отклонения.';
        } else {
            $db->query(
                "UPDATE Events SET IsPublished = 0, ModerationStatus = N'rejected', RejectionReason = ? WHERE EventID = ?",
                [$reason, $eid]
            );
            $msg = 'Акция отклонена, автор увидит причину в карточке.';
        }
    }
}

$search = adminListSearch();
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sortOptions = [
    'created_asc' => 'e.CreatedAt ASC',
    'created_desc' => 'e.CreatedAt DESC',
    'date_asc' => 'e.EventDate ASC',
    'date_desc' => 'e.EventDate DESC',
    'title_asc' => 'e.Title ASC',
];
[$sort, $orderBy] = adminListSort('created_asc', $sortOptions);
$page = adminListPage();
$perPage = adminListPerPage(10);

$params = [];
$where = ["e.ModerationStatus = N'pending'"];
adminApplySearch(['e.Title', 'e.Description', 'e.Location', 'u.FullName', 'u.Email', 'c.CategoryName'], $search, $where, $params);
if ($categoryFilter > 0) {
    $where[] = 'e.CategoryID = ?';
    $params[] = $categoryFilter;
}
$whereSql = implode(' AND ', $where);
$fromSql = "FROM Events e
     LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
     LEFT JOIN Users u ON e.CreatorUserID = u.UserID
     WHERE {$whereSql}";

$totalPending = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c {$fromSql}", $params))['c'] ?? 0);
$offset = ($page - 1) * $perPage;
$pending = $db->fetchAll($db->query(
    "SELECT e.*, c.CategoryName, u.FullName as CreatorName, u.Email
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

$listPath = '/pages/admin/moderation.php';
$adminToolbar = [
    'action' => APP_URL . $listPath,
    'reset_url' => APP_URL . $listPath,
    'search' => ['value' => $search, 'placeholder' => 'Название, место, автор, email'],
    'filters' => [[
        'name' => 'category',
        'label' => 'Категория',
        'value' => $categoryFilter > 0 ? (string)$categoryFilter : '',
        'options' => $categoryFilterOptions,
    ]],
    'sort' => [
        'value' => $sort,
        'options' => [
            'created_asc' => 'Сначала старые заявки',
            'created_desc' => 'Сначала новые заявки',
            'date_asc' => 'Дата акции: ближайшие',
            'date_desc' => 'Дата акции: поздние',
            'title_asc' => 'Название А—Я',
        ],
    ],
    'per' => ['value' => $perPage],
    'count' => $totalPending,
];

$pageTitle = 'Модерация акций';
$bodyClass = 'admin-area';
$headExtra = '';
$adminCurrent = 'moderation';
include '../../includes/html_head.php';
include '../../includes/site_header.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Модерация предложенных акций</h1>

        <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?= escape($err) ?></div><?php endif; ?>

        <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>

        <?php if (empty($pending)): ?>
            <p class="muted">По запросу заявок нет — очередь пуста.</p>
        <?php else: ?>
            <div class="mod-list">
                <?php foreach ($pending as $row): ?>
                    <article class="mod-card surface-block">
                        <h2><?= escape($row['Title']) ?></h2>
                        <p class="muted"><?= escape($row['CategoryName'] ?? '') ?> · <?= formatDate($row['EventDate']) ?> · <?= escape($row['Location']) ?></p>
                        <p class="mod-author">Автор: <?= escape($row['CreatorName']) ?> &lt;<?= escape($row['Email']) ?>&gt;</p>
                        <div class="prose"><?= nl2br(escape($row['Description'])) ?></div>
                        <div class="mod-actions">
                            <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$row['EventID'] ?>">Карточка акции</a>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="event_id" value="<?= (int)$row['EventID'] ?>">
                                <input type="hidden" name="mod_action" value="approve">
                                <button type="submit" class="btn">Одобрить</button>
                            </form>
                            <form method="POST" class="mod-reject-form">
                                <input type="hidden" name="event_id" value="<?= (int)$row['EventID'] ?>">
                                <input type="hidden" name="mod_action" value="reject">
                                <label class="sr-only" for="reason<?= (int)$row['EventID'] ?>">Причина отказа</label>
                                <input type="text" id="reason<?= (int)$row['EventID'] ?>" name="reject_reason" placeholder="Причина отказа" required>
                                <button type="submit" class="btn btn-danger">Отклонить</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?= adminRenderPagination($totalPending, $page, $perPage, $listPath) ?>
        <?php endif; ?>
    </main>
<?php include '../../includes/html_foot.php'; ?>
