<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAdmin();

$db = Database::getInstance();
$success = '';
$error = '';

$totalUsers = (int)$db->fetchOne($db->query("SELECT COUNT(*) as c FROM Users WHERE Role = N'user'"))['c'];
$totalAdmins = (int)$db->fetchOne($db->query("SELECT COUNT(*) as c FROM Users WHERE Role = N'admin'"))['c'];
$totalEvents = (int)$db->fetchOne($db->query("SELECT COUNT(*) as c FROM Events"))['c'];
$totalRegistrations = (int)$db->fetchOne($db->query("SELECT COUNT(*) as c FROM Registrations WHERE Status = N'confirmed'"))['c'];

$dash = isset($_GET['dash']) ? preg_replace('/[^a-z_]/', '', $_GET['dash']) : '';
$panelUsers = [];
$panelEvents = [];
$panelRegs = [];
$adminToolbar = null;
$listPath = '/pages/admin/dashboard.php';
$dashTotal = 0;
$dashPage = 1;
$dashPerPage = 25;

if ($dash === 'users') {
    $search = adminListSearch();
    $roleFilter = adminListFilter('role', '', ['' => '', 'user' => '', 'admin' => '']);
    $activeFilter = adminListFilter('active', '', ['' => '', '1' => '', '0' => '']);
    $sortOptions = [
        'registered_desc' => 'u.RegisteredAt DESC',
        'registered_asc' => 'u.RegisteredAt ASC',
        'name_asc' => 'u.FullName ASC',
        'email_asc' => 'u.Email ASC',
    ];
    [$sort, $orderBy] = adminListSort('registered_desc', $sortOptions);
    $dashPage = adminListPage();
    $dashPerPage = adminListPerPage(25);

    $params = [];
    $where = ['1=1'];
    adminApplySearch(['u.FullName', 'u.Email'], $search, $where, $params);
    if ($roleFilter !== '') {
        $where[] = 'u.Role = ?';
        $params[] = $roleFilter;
    }
    if ($activeFilter === '1') {
        $where[] = 'u.IsActive = 1';
    } elseif ($activeFilter === '0') {
        $where[] = 'u.IsActive = 0';
    }
    $whereSql = implode(' AND ', $where);
    $dashTotal = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c FROM Users u WHERE {$whereSql}", $params))['c'] ?? 0);
    $offset = ($dashPage - 1) * $dashPerPage;
    $panelUsers = $db->fetchAll($db->query(
        "SELECT u.UserID, u.Email, u.FullName, u.Role, u.IsActive, u.RegisteredAt
         FROM Users u WHERE {$whereSql}
         ORDER BY {$orderBy}
         OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
        array_merge($params, [$offset, $dashPerPage])
    ));
    $adminToolbar = [
        'action' => APP_URL . $listPath,
        'reset_url' => APP_URL . $listPath . '?dash=users',
        'hidden' => ['dash' => 'users'],
        'search' => ['value' => $search, 'placeholder' => 'ФИО или email'],
        'filters' => [
            ['name' => 'role', 'label' => 'Роль', 'value' => $roleFilter, 'options' => ['' => 'Все', 'user' => 'Участник', 'admin' => 'Админ']],
            ['name' => 'active', 'label' => 'Активность', 'value' => $activeFilter, 'options' => ['' => 'Все', '1' => 'Активные', '0' => 'Заблокированные']],
        ],
        'sort' => ['value' => $sort, 'options' => [
            'registered_desc' => 'Регистрация: новые',
            'registered_asc' => 'Регистрация: старые',
            'name_asc' => 'ФИО А—Я',
            'email_asc' => 'Email А—Я',
        ]],
        'per' => ['value' => $dashPerPage],
        'count' => $dashTotal,
    ];
} elseif ($dash === 'events') {
    $search = adminListSearch();
    $modFilter = adminListFilter('mod', '', ['' => '', 'approved' => '', 'pending' => '', 'rejected' => '']);
    $sortOptions = [
        'created_desc' => 'e.CreatedAt DESC',
        'date_desc' => 'e.EventDate DESC',
        'date_asc' => 'e.EventDate ASC',
        'title_asc' => 'e.Title ASC',
    ];
    [$sort, $orderBy] = adminListSort('created_desc', $sortOptions);
    $dashPage = adminListPage();
    $dashPerPage = adminListPerPage(25);

    $params = [];
    $where = ['1=1'];
    adminApplySearch(['e.Title', 'e.Location', 'u.FullName'], $search, $where, $params);
    if ($modFilter !== '') {
        $where[] = 'e.ModerationStatus = ?';
        $params[] = $modFilter;
    }
    $whereSql = implode(' AND ', $where);
    $fromSql = "FROM Events e LEFT JOIN Users u ON e.CreatorUserID = u.UserID WHERE {$whereSql}";
    $dashTotal = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c {$fromSql}", $params))['c'] ?? 0);
    $offset = ($dashPage - 1) * $dashPerPage;
    $panelEvents = $db->fetchAll($db->query(
        "SELECT e.EventID, e.Title, e.EventDate, e.ModerationStatus, e.IsPublished, u.FullName as CreatorName
         {$fromSql}
         ORDER BY {$orderBy}
         OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
        array_merge($params, [$offset, $dashPerPage])
    ));
    $adminToolbar = [
        'action' => APP_URL . $listPath,
        'reset_url' => APP_URL . $listPath . '?dash=events',
        'hidden' => ['dash' => 'events'],
        'search' => ['value' => $search, 'placeholder' => 'Название, место, организатор'],
        'filters' => [[
            'name' => 'mod', 'label' => 'Модерация', 'value' => $modFilter,
            'options' => ['' => 'Все', 'approved' => 'Опубликовано', 'pending' => 'На модерации', 'rejected' => 'Отклонено'],
        ]],
        'sort' => ['value' => $sort, 'options' => [
            'created_desc' => 'Сначала новые',
            'date_desc' => 'Дата: поздние',
            'date_asc' => 'Дата: ближайшие',
            'title_asc' => 'Название А—Я',
        ]],
        'per' => ['value' => $dashPerPage],
        'count' => $dashTotal,
    ];
} elseif ($dash === 'registrations') {
    $search = adminListSearch();
    $sortOptions = [
        'registered_desc' => 'r.RegisteredAt DESC',
        'registered_asc' => 'r.RegisteredAt ASC',
        'event_desc' => 'e.EventDate DESC',
        'user_asc' => 'u.FullName ASC',
        'event_asc' => 'e.Title ASC',
    ];
    [$sort, $orderBy] = adminListSort('registered_desc', $sortOptions);
    $dashPage = adminListPage();
    $dashPerPage = adminListPerPage(25);

    $params = [];
    $where = ["r.Status = N'confirmed'"];
    adminApplySearch(['u.FullName', 'u.Email', 'e.Title'], $search, $where, $params);
    $whereSql = implode(' AND ', $where);
    $fromSql = "FROM Registrations r
         INNER JOIN Users u ON r.UserID = u.UserID
         INNER JOIN Events e ON r.EventID = e.EventID
         WHERE {$whereSql}";
    $dashTotal = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c {$fromSql}", $params))['c'] ?? 0);
    $offset = ($dashPage - 1) * $dashPerPage;
    $panelRegs = $db->fetchAll($db->query(
        "SELECT r.RegistrationID, r.RegisteredAt, r.Status, u.FullName, u.Email, e.Title as EventTitle, e.EventID
         {$fromSql}
         ORDER BY {$orderBy}
         OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
        array_merge($params, [$offset, $dashPerPage])
    ));
    $adminToolbar = [
        'action' => APP_URL . $listPath,
        'reset_url' => APP_URL . $listPath . '?dash=registrations',
        'hidden' => ['dash' => 'registrations'],
        'search' => ['value' => $search, 'placeholder' => 'Участник, email, акция'],
        'sort' => ['value' => $sort, 'options' => [
            'registered_desc' => 'Сначала новые записи',
            'registered_asc' => 'Сначала старые записи',
            'event_desc' => 'Дата акции: поздние',
            'user_asc' => 'Участник А—Я',
            'event_asc' => 'Акция А—Я',
        ]],
        'per' => ['value' => $dashPerPage],
        'count' => $dashTotal,
    ];
}

$pageTitle = 'Админ-панель';
$bodyClass = 'admin-area';
$adminCurrent = 'dashboard';
include '../../includes/html_head.php';
include '../../includes/site_header.php';
$dashBase = APP_URL . '/pages/admin/dashboard.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Сводка</h1>

        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

        <div class="stats-grid">
            <a class="stat-card stat-card--link<?= $dash === 'users' ? ' is-selected' : '' ?>" href="<?= escape($dashBase . '?dash=users') ?>">
                <h3><?= $totalUsers + $totalAdmins ?></h3>
                <p>Пользователей</p>
                <small class="muted">админов: <?= $totalAdmins ?></small>
            </a>
            <a class="stat-card stat-card--link<?= $dash === 'events' ? ' is-selected' : '' ?>" href="<?= escape($dashBase . '?dash=events') ?>">
                <h3><?= $totalEvents ?></h3>
                <p>Акций в базе</p>
            </a>
            <a class="stat-card stat-card--link<?= $dash === 'registrations' ? ' is-selected' : '' ?>" href="<?= escape($dashBase . '?dash=registrations') ?>">
                <h3><?= $totalRegistrations ?></h3>
                <p>Подтверждённых записей</p>
            </a>
        </div>

        <?php if ($dash === 'users'): ?>
            <section class="surface-block dash-panel">
                <h2 class="section-title">Пользователи</h2>
                <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
                <?php if (empty($panelUsers)): ?>
                    <p class="muted">По запросу ничего не найдено.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ФИО</th>
                                    <th>Email</th>
                                    <th>Роль</th>
                                    <th>Активен</th>
                                    <th>Регистрация</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($panelUsers as $u): ?>
                                    <tr>
                                        <td><?= escape($u['FullName']) ?></td>
                                        <td><?= escape($u['Email']) ?></td>
                                        <td><?= escape($u['Role']) ?></td>
                                        <td><?= !empty($u['IsActive']) ? 'да' : 'нет' ?></td>
                                        <td><?= formatDate($u['RegisteredAt']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= adminRenderPagination($dashTotal, $dashPage, $dashPerPage, $listPath, ['dash' => 'users']) ?>
                <?php endif; ?>
            </section>
        <?php elseif ($dash === 'events'): ?>
            <section class="surface-block dash-panel">
                <h2 class="section-title">Акции</h2>
                <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
                <?php if (empty($panelEvents)): ?>
                    <p class="muted">По запросу ничего не найдено.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Дата</th>
                                    <th>Организатор</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($panelEvents as $pe): ?>
                                    <tr>
                                        <td><a href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$pe['EventID'] ?>"><?= escape($pe['Title']) ?></a></td>
                                        <td><?= formatDate($pe['EventDate']) ?></td>
                                        <td><?= escape($pe['CreatorName'] ?? '') ?></td>
                                        <td><?= escape(moderationLabel($pe['ModerationStatus'] ?? 'approved')) ?><?= !empty($pe['IsPublished']) ? ', в каталоге' : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= adminRenderPagination($dashTotal, $dashPage, $dashPerPage, $listPath, ['dash' => 'events']) ?>
                <?php endif; ?>
            </section>
        <?php elseif ($dash === 'registrations'): ?>
            <section class="surface-block dash-panel">
                <h2 class="section-title">Подтверждённые записи</h2>
                <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
                <?php if (empty($panelRegs)): ?>
                    <p class="muted">По запросу ничего не найдено.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Участник</th>
                                    <th>Email</th>
                                    <th>Акция</th>
                                    <th>Записан</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($panelRegs as $pr): ?>
                                    <tr>
                                        <td><?= escape($pr['FullName']) ?></td>
                                        <td><?= escape($pr['Email']) ?></td>
                                        <td><a href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$pr['EventID'] ?>"><?= escape($pr['EventTitle']) ?></a></td>
                                        <td><?= formatDate($pr['RegisteredAt']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= adminRenderPagination($dashTotal, $dashPage, $dashPerPage, $listPath, ['dash' => 'registrations']) ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
<?php include '../../includes/html_foot.php'; ?>
