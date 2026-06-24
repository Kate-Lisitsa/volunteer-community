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
    $uid = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['user_action'] ?? '';
    if ($uid === (int)$_SESSION['user_id']) {
        $err = 'Нельзя изменить собственную учётную запись здесь.';
    } elseif ($action === 'toggle_active') {
        $db->query("UPDATE Users SET IsActive = CASE WHEN IsActive = 1 THEN 0 ELSE 1 END WHERE UserID = ?", [$uid]);
        $msg = 'Статус активности обновлён.';
    }
}

$search = adminListSearch();
$roleFilter = adminListFilter('role', '', ['' => '', 'user' => '', 'admin' => '']);
$activeFilter = adminListFilter('active', '', ['' => '', '1' => '', '0' => '']);
$sortOptions = [
    'registered_desc' => 'u.RegisteredAt DESC',
    'registered_asc' => 'u.RegisteredAt ASC',
    'name_asc' => 'u.FullName ASC',
    'name_desc' => 'u.FullName DESC',
    'email_asc' => 'u.Email ASC',
    'email_desc' => 'u.Email DESC',
];
[$sort, $orderBy] = adminListSort('registered_desc', $sortOptions);
$page = adminListPage();
$perPage = adminListPerPage(25);

$where = ['1=1'];
$params = [];
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

$totalUsers = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c FROM Users u WHERE {$whereSql}", $params))['c'] ?? 0);
$offset = ($page - 1) * $perPage;
$users = $db->fetchAll($db->query(
    "SELECT u.UserID, u.Email, u.FullName, u.Role, u.IsActive, u.RegisteredAt
     FROM Users u
     WHERE {$whereSql}
     ORDER BY {$orderBy}
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    array_merge($params, [$offset, $perPage])
));

$listPath = '/pages/admin/users.php';
$adminToolbar = [
    'action' => APP_URL . $listPath,
    'reset_url' => APP_URL . $listPath,
    'search' => ['value' => $search, 'placeholder' => 'ФИО или email'],
    'filters' => [
        [
            'name' => 'role',
            'label' => 'Роль',
            'value' => $roleFilter,
            'options' => ['' => 'Все роли', 'user' => 'Участник', 'admin' => 'Администратор'],
        ],
        [
            'name' => 'active',
            'label' => 'Активность',
            'value' => $activeFilter,
            'options' => ['' => 'Все', '1' => 'Активные', '0' => 'Заблокированные'],
        ],
    ],
    'sort' => [
        'value' => $sort,
        'options' => [
            'registered_desc' => 'Регистрация: новые',
            'registered_asc' => 'Регистрация: старые',
            'name_asc' => 'ФИО А—Я',
            'name_desc' => 'ФИО Я—А',
            'email_asc' => 'Email А—Я',
            'email_desc' => 'Email Я—А',
        ],
    ],
    'per' => ['value' => $perPage],
    'count' => $totalUsers,
];

$pageTitle = 'Пользователи';
$bodyClass = 'admin-area';
$adminCurrent = 'users';
include '../../includes/html_head.php';
include '../../includes/site_header.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Пользователи и организаторы</h1>
        <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?= escape($err) ?></div><?php endif; ?>

        <section class="surface-block">
            <h2 class="section-title">Пользователи</h2>
            <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
            <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Активен</th>
                        <th>Регистрация</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="muted">По запросу ничего не найдено.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= escape($u['FullName']) ?></td>
                                <td><?= escape($u['Email']) ?></td>
                                <td><?= escape($u['Role']) ?></td>
                                <td><?= !empty($u['IsActive']) ? 'да' : 'нет' ?></td>
                                <td><?= formatDate($u['RegisteredAt']) ?></td>
                                <td>
                                    <?php if ((int)$u['UserID'] !== (int)$_SESSION['user_id']): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['UserID'] ?>">
                                            <input type="hidden" name="user_action" value="toggle_active">
                                            <button type="submit" class="btn btn-small btn-secondary"><?= !empty($u['IsActive']) ? 'Заблокировать' : 'Разблокировать' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
            <?= adminRenderPagination($totalUsers, $page, $perPage, $listPath) ?>
        </section>
    </main>
<?php include '../../includes/html_foot.php'; ?>
