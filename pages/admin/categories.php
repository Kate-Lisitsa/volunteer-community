<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAdmin();

$db = Database::getInstance();
$msg = '';
$err = '';
$catSoftDelete = categoriesHaveIsActiveColumn($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['cat_action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $err = 'Введите название категории.';
        } elseif ($catSoftDelete) {
            $db->query("INSERT INTO Categories (CategoryName, IsActive) VALUES (?, 1)", [$name]);
            $msg = 'Категория добавлена.';
        } else {
            $db->query("INSERT INTO Categories (CategoryName) VALUES (?)", [$name]);
            $msg = 'Категория добавлена.';
        }
    } elseif ($action === 'rename') {
        $id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name !== '') {
            $db->query("UPDATE Categories SET CategoryName = ? WHERE CategoryID = ?", [$name, $id]);
            $msg = 'Сохранено.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['category_id'] ?? 0);
        if ($id) {
            $cntRow = $db->fetchOne($db->query(
                "SELECT COUNT(*) AS c FROM Events WHERE CategoryID = ?",
                [$id]
            ));
            $hiddenCount = (int)($cntRow['c'] ?? 0);
            $db->query("UPDATE Events SET IsPublished = 0 WHERE CategoryID = ?", [$id]);
            if ($catSoftDelete) {
                $db->query("UPDATE Categories SET IsActive = 0 WHERE CategoryID = ?", [$id]);
                if ($hiddenCount > 0) {
                    $msg = 'Категория снята с публикации. Акции (' . $hiddenCount . ') скрыты из каталога — их можно найти в разделе «Акции → Скрытые из каталога».';
                } else {
                    $msg = 'Категория снята с публикации.';
                }
            } else {
                $db->query("UPDATE Events SET CategoryID = NULL WHERE CategoryID = ?", [$id]);
                $db->query("DELETE FROM Categories WHERE CategoryID = ?", [$id]);
                if ($hiddenCount > 0) {
                    $msg = 'Категория удалена. Акции (' . $hiddenCount . ') скрыты из каталога — их можно найти в разделе «Акции → Скрытые из каталога».';
                } else {
                    $msg = 'Категория удалена.';
                }
            }
        }
    } elseif ($action === 'restore' && $catSoftDelete) {
        $id = (int)($_POST['category_id'] ?? 0);
        if ($id) {
            $db->query("UPDATE Categories SET IsActive = 1 WHERE CategoryID = ?", [$id]);
            $republished = republishApprovedEventsForCategory($db, $id);
            if ($republished > 0) {
                $msg = 'Категория возвращена. В каталог снова опубликовано акций: ' . $republished . '.';
            } else {
                $msg = 'Категория снова доступна для новых акций.';
            }
        }
    }
}

$search = adminListSearch();
$sectionFilter = adminListFilter('section', 'active', ['active' => '', 'hidden' => '', 'all' => '']);
$sortOptions = [
    'name_asc' => 'c.CategoryName ASC',
    'name_desc' => 'c.CategoryName DESC',
    'events_desc' => '(SELECT COUNT(*) FROM Events e WHERE e.CategoryID = c.CategoryID) DESC',
    'events_asc' => '(SELECT COUNT(*) FROM Events e WHERE e.CategoryID = c.CategoryID) ASC',
];
[$sort, $orderBy] = adminListSort('name_asc', $sortOptions);
$page = adminListPage();
$perPage = adminListPerPage(25);

$params = [];
$where = ['1=1'];
adminApplySearch(['c.CategoryName'], $search, $where, $params);
if ($catSoftDelete) {
    if ($sectionFilter === 'active') {
        $where[] = 'c.IsActive = 1';
    } elseif ($sectionFilter === 'hidden') {
        $where[] = 'c.IsActive = 0';
    }
} elseif ($sectionFilter === 'hidden') {
    $where[] = '1=0';
}
$whereSql = implode(' AND ', $where);
$fromSql = "FROM Categories c
     WHERE {$whereSql}";

$totalCategories = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c {$fromSql}", $params))['c'] ?? 0);
$offset = ($page - 1) * $perPage;
$categoriesList = $db->fetchAll($db->query(
    "SELECT c.*, (SELECT COUNT(*) FROM Events e WHERE e.CategoryID = c.CategoryID) AS EventsCount
     {$fromSql}
     ORDER BY {$orderBy}
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    array_merge($params, [$offset, $perPage])
));

$listPath = '/pages/admin/categories.php';
$adminToolbar = [
    'action' => APP_URL . $listPath,
    'reset_url' => APP_URL . $listPath,
    'search' => ['value' => $search, 'placeholder' => 'Название категории'],
    'sort' => [
        'value' => $sort,
        'options' => [
            'name_asc' => 'Название А—Я',
            'name_desc' => 'Название Я—А',
            'events_desc' => 'Больше акций',
            'events_asc' => 'Меньше акций',
        ],
    ],
    'per' => ['value' => $perPage],
    'count' => $totalCategories,
];
if ($catSoftDelete) {
    $adminToolbar['filters'] = [[
        'name' => 'section',
        'label' => 'Раздел',
        'value' => $sectionFilter,
        'options' => ['active' => 'Активные', 'hidden' => 'Снятые', 'all' => 'Все'],
    ]];
}

$pageTitle = 'Категории';
$bodyClass = 'admin-area';
$adminCurrent = 'categories';
$deleteConfirm = $catSoftDelete
    ? 'Снять категорию с публикации? Связанные акции будут скрыты из каталога, но останутся в разделе «Акции → Скрытые из каталога».'
    : 'Удалить категорию? Связанные акции будут скрыты из каталога.';
include '../../includes/html_head.php';
include '../../includes/site_header.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Категории помощи</h1>
        <?php if ($msg): ?><div class="alert alert-success"><?= escape($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?= escape($err) ?></div><?php endif; ?>

        <?php if ($catSoftDelete): ?>
            <p class="muted admin-events-note">
                При снятии категории связанные акции остаются в базе, но исчезают из каталога. Название категории сохраняется — в админ-разделе «Акции» они отмечены подписью «категория снята».
            </p>
        <?php endif; ?>

        <form method="POST" class="surface-form row-form surface-block">
            <input type="hidden" name="cat_action" value="add">
            <div class="form-group grow">
                <label for="newcat">Новая категория</label>
                <input type="text" id="newcat" name="name" maxlength="120" placeholder="Например: Экология">
            </div>
            <button type="submit" class="btn">Добавить</button>
        </form>

        <section class="surface-block">
            <h2 class="section-title">Категории</h2>
            <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
            <ul class="admin-list admin-list--in-panel">
            <?php if (empty($categoriesList)): ?>
                <li class="muted">По запросу категорий не найдено.</li>
            <?php else: ?>
                <?php foreach ($categoriesList as $cat): ?>
                    <?php $isHiddenCat = $catSoftDelete && empty($cat['IsActive']); ?>
                    <li class="admin-list__item">
                        <?php if ($isHiddenCat): ?>
                            <span><?= escape($cat['CategoryName']) ?></span>
                            <span class="status-pill status-pill--warn">снята с публикации</span>
                        <?php else: ?>
                            <form method="POST" class="row-form">
                                <input type="hidden" name="cat_action" value="rename">
                                <input type="hidden" name="category_id" value="<?= (int)$cat['CategoryID'] ?>">
                                <input type="text" name="name" value="<?= escape($cat['CategoryName']) ?>" maxlength="120">
                                <button type="submit" class="btn btn-secondary">Сохранить</button>
                            </form>
                        <?php endif; ?>
                        <span class="muted small">акций: <?= (int)$cat['EventsCount'] ?></span>
                        <?php if ($isHiddenCat): ?>
                            <a class="btn btn-small btn-secondary" href="<?= APP_URL ?>/pages/admin/events.php?scope=hidden&amp;category=<?= (int)$cat['CategoryID'] ?>">Показать акции</a>
                            <form method="POST">
                                <input type="hidden" name="cat_action" value="restore">
                                <input type="hidden" name="category_id" value="<?= (int)$cat['CategoryID'] ?>">
                                <button type="submit" class="btn btn-ghost">Вернуть категорию</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm(<?= json_encode($deleteConfirm, JSON_UNESCAPED_UNICODE) ?>);">
                                <input type="hidden" name="cat_action" value="delete">
                                <input type="hidden" name="category_id" value="<?= (int)$cat['CategoryID'] ?>">
                                <button type="submit" class="btn btn-ghost btn-danger"><?= $catSoftDelete ? 'Снять с публикации' : 'Удалить' ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
            </ul>
            <?= adminRenderPagination($totalCategories, $page, $perPage, $listPath) ?>
        </section>
    </main>
<?php include '../../includes/html_foot.php'; ?>
