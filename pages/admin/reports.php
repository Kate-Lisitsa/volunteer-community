<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAdmin();

$db = Database::getInstance();
$error = '';

function normalizeReportDateInput(string $value): ?string {
    $value = trim($value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
}

/** Параметры периода для sqlsrv (DateTime, включительно по календарным дням). */
function reportDateRangeParams(string $startDate, string $endDate): array {
    $from = DateTime::createFromFormat('Y-m-d H:i:s', $startDate . ' 00:00:00');
    $to = DateTime::createFromFormat('Y-m-d H:i:s', $endDate . ' 23:59:59');
    if (!$from || !$to) {
        $from = new DateTime($startDate . ' 00:00:00');
        $to = new DateTime($endDate . ' 23:59:59');
    }
    return [$from, $to];
}

function reportSqlDatetimeBetween(string $column): string {
    return "({$column} >= ? AND {$column} <= ?)";
}

function loadReportEvents($db, string $startDate, string $endDate, string $search = '', string $orderBy = 'e.EventDate DESC', ?int $offset = null, ?int $limit = null): array {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $params = [$from, $to];
    $where = [reportSqlDatetimeBetween('e.EventDate')];
    adminApplySearch(['e.Title', 'e.Location', 'c.CategoryName', 'u.FullName'], $search, $where, $params);
    $whereSql = implode(' AND ', $where);
    $sql = "SELECT e.Title, e.EventDate, e.CreatedAt, e.Location, c.CategoryName, u.FullName as CreatorName,
            COUNT(r.RegistrationID) as ParticipantsCount
            FROM Events e
            LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
            LEFT JOIN Users u ON e.CreatorUserID = u.UserID
            LEFT JOIN Registrations r ON e.EventID = r.EventID AND r.Status = N'confirmed'
            WHERE {$whereSql}
            GROUP BY e.EventID, e.Title, e.EventDate, e.CreatedAt, e.Location, c.CategoryName, u.FullName
            ORDER BY {$orderBy}";
    if ($offset !== null && $limit !== null) {
        $sql .= ' OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
        $params[] = $offset;
        $params[] = $limit;
    }
    return $db->fetchAll($db->query($sql, $params));
}

function countReportEvents($db, string $startDate, string $endDate, string $search = ''): int {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $params = [$from, $to];
    $where = [reportSqlDatetimeBetween('e.EventDate')];
    adminApplySearch(['e.Title', 'e.Location', 'c.CategoryName', 'u.FullName'], $search, $where, $params);
    $whereSql = implode(' AND ', $where);
    $row = $db->fetchOne($db->query(
        "SELECT COUNT(DISTINCT e.EventID) AS c
         FROM Events e
         LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
         LEFT JOIN Users u ON e.CreatorUserID = u.UserID
         WHERE {$whereSql}",
        $params
    ));
    return (int)($row['c'] ?? 0);
}

function loadReportRegistrations($db, string $startDate, string $endDate, string $search = '', string $orderBy = 'r.RegisteredAt DESC', ?int $offset = null, ?int $limit = null): array {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $params = [$from, $to];
    $where = ["r.Status = N'confirmed'", reportSqlDatetimeBetween('r.RegisteredAt')];
    adminApplySearch(['u.FullName', 'u.Email', 'e.Title'], $search, $where, $params);
    $whereSql = implode(' AND ', $where);
    $sql = "SELECT u.FullName, u.Email, e.Title as EventTitle, e.EventDate, r.RegisteredAt
            FROM Registrations r
            INNER JOIN Users u ON r.UserID = u.UserID
            INNER JOIN Events e ON r.EventID = e.EventID
            WHERE {$whereSql}
            ORDER BY {$orderBy}";
    if ($offset !== null && $limit !== null) {
        $sql .= ' OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
        $params[] = $offset;
        $params[] = $limit;
    }
    return $db->fetchAll($db->query($sql, $params));
}

function countReportRegistrations($db, string $startDate, string $endDate, string $search = ''): int {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $params = [$from, $to];
    $where = ["r.Status = N'confirmed'", reportSqlDatetimeBetween('r.RegisteredAt')];
    adminApplySearch(['u.FullName', 'u.Email', 'e.Title'], $search, $where, $params);
    $whereSql = implode(' AND ', $where);
    $row = $db->fetchOne($db->query(
        "SELECT COUNT(*) AS c
         FROM Registrations r
         INNER JOIN Users u ON r.UserID = u.UserID
         INNER JOIN Events e ON r.EventID = e.EventID
         WHERE {$whereSql}",
        $params
    ));
    return (int)($row['c'] ?? 0);
}

function loadReportStats($db, string $startDate, string $endDate): array {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $eventBetween = reportSqlDatetimeBetween('e.EventDate');
    $regBetween = reportSqlDatetimeBetween('r.RegisteredAt');
    $sql = "SELECT
            (SELECT COUNT(*) FROM Events e WHERE {$eventBetween}) AS TotalEvents,
            (SELECT COUNT(*) FROM Registrations r
             WHERE r.Status = N'confirmed' AND {$regBetween}) AS TotalRegistrations";
    return $db->fetchOne($db->query($sql, [$from, $to, $from, $to])) ?: ['TotalEvents' => 0, 'TotalRegistrations' => 0];
}

function loadReportChartByCategory($db, string $startDate, string $endDate): array {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $sql = "SELECT c.CategoryName, COUNT(*) as cnt
            FROM Events e
            INNER JOIN Categories c ON e.CategoryID = c.CategoryID
            WHERE " . reportSqlDatetimeBetween('e.EventDate') . " AND e.ModerationStatus = N'approved'
            GROUP BY c.CategoryName
            ORDER BY cnt DESC";
    return $db->fetchAll($db->query($sql, [$from, $to]));
}

/** Подтверждённые записи участников по категориям акций за период (по дате записи) */
function loadReportChartRegistrationsByCategory($db, string $startDate, string $endDate): array {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $sql = "SELECT c.CategoryName, COUNT(r.RegistrationID) as cnt
            FROM Registrations r
            INNER JOIN Events e ON r.EventID = e.EventID
            INNER JOIN Categories c ON e.CategoryID = c.CategoryID
            WHERE r.Status = N'confirmed' AND " . reportSqlDatetimeBetween('r.RegisteredAt') . "
            GROUP BY c.CategoryName
            ORDER BY cnt DESC";
    return $db->fetchAll($db->query($sql, [$from, $to]));
}

function countReportActiveVolunteers($db, string $startDate, string $endDate): int {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $row = $db->fetchOne($db->query(
        "SELECT COUNT(DISTINCT r.UserID) AS c
         FROM Registrations r
         INNER JOIN Users u ON u.UserID = r.UserID
         WHERE r.Status = N'confirmed'
           AND u.Role = N'user'
           AND u.IsActive = 1
           AND " . reportSqlDatetimeBetween('r.RegisteredAt'),
        [$from, $to]
    ));
    return (int)($row['c'] ?? 0);
}

function countReportVolunteersList($db, string $startDate, string $endDate, string $search = ''): int {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $params = [$from, $to];
    $where = [
        "r.Status = N'confirmed'",
        "u.Role = N'user'",
        "u.IsActive = 1",
        reportSqlDatetimeBetween('r.RegisteredAt'),
    ];
    adminApplySearch(['u.FullName', 'u.Email'], $search, $where, $params);
    $whereSql = implode(' AND ', $where);
    $row = $db->fetchOne($db->query(
        "SELECT COUNT(DISTINCT u.UserID) AS c
         FROM Registrations r
         INNER JOIN Users u ON u.UserID = r.UserID
         WHERE {$whereSql}",
        $params
    ));
    return (int)($row['c'] ?? 0);
}

/** Участники с подтверждёнными записями на акции за период (по дате записи) */
function loadReportVolunteersList($db, string $startDate, string $endDate, string $search = '', string $orderBy = 'Participations DESC, u.FullName ASC', ?int $offset = null, ?int $limit = null): array {
    [$from, $to] = reportDateRangeParams($startDate, $endDate);
    $params = [$from, $to];
    $where = [
        "r.Status = N'confirmed'",
        "u.Role = N'user'",
        "u.IsActive = 1",
        reportSqlDatetimeBetween('r.RegisteredAt'),
    ];
    adminApplySearch(['u.FullName', 'u.Email'], $search, $where, $params);
    $whereSql = implode(' AND ', $where);
    $sql = "SELECT u.FullName, u.Email, COUNT(r.RegistrationID) AS Participations,
                   MAX(r.RegisteredAt) AS LastRegisteredAt
            FROM Registrations r
            INNER JOIN Users u ON u.UserID = r.UserID
            WHERE {$whereSql}
            GROUP BY u.UserID, u.FullName, u.Email
            ORDER BY {$orderBy}";
    if ($offset !== null && $limit !== null) {
        $sql .= ' OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
        $params[] = $offset;
        $params[] = $limit;
    }
    return $db->fetchAll($db->query($sql, $params));
}

function sendReportXls(string $filename, string $title, array $headerRow, array $rows): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<meta charset="UTF-8"><table border="1">';
    echo '<tr><th colspan="' . count($headerRow) . '">' . escape($title) . '</th></tr>';
    echo '<tr>';
    foreach ($headerRow as $h) {
        echo '<th>' . escape($h) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . escape((string)$cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_section'])) {
    $startDate = normalizeReportDateInput(trim($_POST['start_date'] ?? ''));
    $endDate = normalizeReportDateInput(trim($_POST['end_date'] ?? ''));
    $section = preg_replace('/[^a-z_]/', '', $_POST['export_section'] ?? '');

    if ($startDate === null || $endDate === null) {
        $error = 'Выберите корректный период для отчёта';
    } elseif ($startDate > $endDate) {
        $error = 'Дата «С» не может быть позже даты «По».';
    } elseif ($section === 'events') {
        $events = loadReportEvents($db, $startDate, $endDate);
        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                $event['Title'],
                formatDateOnly($event['EventDate']),
                formatTimeOnly($event['EventDate']),
                $event['Location'],
                $event['CategoryName'] ?? '-',
                $event['CreatorName'] ?? '',
                (int)$event['ParticipantsCount'],
            ];
        }
        sendReportXls(
            'dobrohub_events_' . date('Y-m-d') . '.xls',
            'DobroHub — акции за ' . formatReportPeriodRu($startDate, $endDate),
            ['Название', 'Дата акции', 'Время акции', 'Место', 'Категория', 'Организатор', 'Участников'],
            $rows
        );
    } elseif ($section === 'registrations') {
        $regs = loadReportRegistrations($db, $startDate, $endDate);
        $rows = [];
        foreach ($regs as $r) {
            $rows[] = [
                $r['FullName'],
                $r['Email'],
                $r['EventTitle'],
                formatDateOnly($r['EventDate']),
                formatTimeOnly($r['EventDate']),
                formatDateOnly($r['RegisteredAt']),
                formatTimeOnly($r['RegisteredAt']),
            ];
        }
        sendReportXls(
            'dobrohub_registrations_' . date('Y-m-d') . '.xls',
            'DobroHub — записи участников за ' . formatReportPeriodRu($startDate, $endDate),
            ['ФИО', 'Email', 'Акция', 'Дата акции', 'Время акции', 'Дата записи', 'Время записи'],
            $rows
        );
    } elseif ($section === 'categories') {
        $chartByCat = loadReportChartByCategory($db, $startDate, $endDate);
        $rows = [];
        foreach ($chartByCat as $row) {
            $rows[] = [$row['CategoryName'], (int)$row['cnt']];
        }
        sendReportXls(
            'dobrohub_categories_' . date('Y-m-d') . '.xls',
            'DobroHub — одобренные акции по категориям за ' . formatReportPeriodRu($startDate, $endDate),
            ['Категория', 'Количество акций'],
            $rows
        );
    } elseif ($section === 'registrations_by_category') {
        $chartRegs = loadReportChartRegistrationsByCategory($db, $startDate, $endDate);
        $rows = [];
        foreach ($chartRegs as $row) {
            $rows[] = [$row['CategoryName'], (int)$row['cnt']];
        }
        sendReportXls(
            'dobrohub_registrations_by_category_' . date('Y-m-d') . '.xls',
            'DobroHub — записи участников по категориям за ' . formatReportPeriodRu($startDate, $endDate),
            ['Категория', 'Количество записей'],
            $rows
        );
    } elseif ($section === 'active_volunteers') {
        $volunteers = loadReportVolunteersList($db, $startDate, $endDate);
        $rows = [];
        foreach ($volunteers as $row) {
            $rows[] = [
                $row['FullName'],
                $row['Email'],
                (int)$row['Participations'],
                formatDateOnly($row['LastRegisteredAt']),
                formatTimeOnly($row['LastRegisteredAt']),
            ];
        }
        sendReportXls(
            'dobrohub_active_volunteers_' . date('Y-m-d') . '.xls',
            'DobroHub — активные волонтёры за ' . formatReportPeriodRu($startDate, $endDate),
            ['ФИО', 'Email', 'Записей на акции', 'Дата последней записи', 'Время последней записи'],
            $rows
        );
    }
}

$startDate = normalizeReportDateInput($_GET['start_date'] ?? '') ?? date('Y-m-01');
$endDate = normalizeReportDateInput($_GET['end_date'] ?? '') ?? date('Y-m-d');
$reportPeriod = isset($_GET['show_report']) && $startDate !== '' && $endDate !== '';
if ($reportPeriod && $startDate > $endDate) {
    $error = 'Дата «С» не может быть позже даты «По».';
    $reportPeriod = false;
}
$view = $reportPeriod ? preg_replace('/[^a-z_]/', '', $_GET['view'] ?? '') : '';
if (!in_array($view, ['events', 'registrations', 'volunteers', ''], true)) {
    $view = '';
}

$stats = ['TotalEvents' => 0, 'TotalRegistrations' => 0];
$reportEvents = [];
$reportRegistrations = [];
$reportVolunteers = [];
$chartByCat = [];
$chartRegsByCat = [];
$totalActiveVolunteers = 0;
$adminToolbar = null;
$listPath = '/pages/admin/reports.php';
$reportListTotal = 0;
$reportListPage = 1;
$reportListPerPage = 25;

if ($reportPeriod) {
    $stats = loadReportStats($db, $startDate, $endDate);
    $chartByCat = loadReportChartByCategory($db, $startDate, $endDate);
    $chartRegsByCat = loadReportChartRegistrationsByCategory($db, $startDate, $endDate);
    $totalActiveVolunteers = countReportActiveVolunteers($db, $startDate, $endDate);
    if ($view === 'events') {
        $search = adminListSearch();
        $sortOptions = [
            'date_desc' => 'e.EventDate DESC',
            'date_asc' => 'e.EventDate ASC',
            'created_desc' => 'e.CreatedAt DESC',
            'created_asc' => 'e.CreatedAt ASC',
            'title_asc' => 'e.Title ASC',
            'participants_desc' => 'COUNT(r.RegistrationID) DESC',
            'location_asc' => 'e.Location ASC',
        ];
        [$sort, $orderBy] = adminListSort('date_desc', $sortOptions);
        $reportListPage = adminListPage();
        $reportListPerPage = adminListPerPage(25);
        $reportListTotal = countReportEvents($db, $startDate, $endDate, $search);
        $offset = ($reportListPage - 1) * $reportListPerPage;
        $reportEvents = loadReportEvents($db, $startDate, $endDate, $search, $orderBy, $offset, $reportListPerPage);
        $adminToolbar = [
            'action' => APP_URL . $listPath,
            'reset_url' => APP_URL . $listPath . '?show_report=1&start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate) . '&view=events',
            'hidden' => ['show_report' => '1', 'start_date' => $startDate, 'end_date' => $endDate, 'view' => 'events'],
            'search' => ['value' => $search, 'placeholder' => 'Название, место, категория, организатор'],
            'sort' => ['value' => $sort, 'options' => [
                'date_desc' => 'Проведение: поздние',
                'date_asc' => 'Проведение: ближайшие',
                'created_desc' => 'Создание: новые',
                'created_asc' => 'Создание: старые',
                'title_asc' => 'Название А—Я',
                'participants_desc' => 'Больше участников',
                'location_asc' => 'Место А—Я',
            ]],
            'per' => ['value' => $reportListPerPage],
            'count' => $reportListTotal,
        ];
    } elseif ($view === 'registrations') {
        $search = adminListSearch();
        $sortOptions = [
            'registered_desc' => 'r.RegisteredAt DESC',
            'registered_asc' => 'r.RegisteredAt ASC',
            'event_desc' => 'e.EventDate DESC',
            'user_asc' => 'u.FullName ASC',
            'event_asc' => 'e.Title ASC',
        ];
        [$sort, $orderBy] = adminListSort('registered_desc', $sortOptions);
        $reportListPage = adminListPage();
        $reportListPerPage = adminListPerPage(25);
        $reportListTotal = countReportRegistrations($db, $startDate, $endDate, $search);
        $offset = ($reportListPage - 1) * $reportListPerPage;
        $reportRegistrations = loadReportRegistrations($db, $startDate, $endDate, $search, $orderBy, $offset, $reportListPerPage);
        $adminToolbar = [
            'action' => APP_URL . $listPath,
            'reset_url' => APP_URL . $listPath . '?show_report=1&start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate) . '&view=registrations',
            'hidden' => ['show_report' => '1', 'start_date' => $startDate, 'end_date' => $endDate, 'view' => 'registrations'],
            'search' => ['value' => $search, 'placeholder' => 'Участник, email, акция'],
            'sort' => ['value' => $sort, 'options' => [
                'registered_desc' => 'Сначала новые записи',
                'registered_asc' => 'Сначала старые записи',
                'event_desc' => 'Дата акции: поздние',
                'user_asc' => 'Участник А—Я',
                'event_asc' => 'Акция А—Я',
            ]],
            'per' => ['value' => $reportListPerPage],
            'count' => $reportListTotal,
        ];
    } elseif ($view === 'volunteers') {
        $search = adminListSearch();
        $sortOptions = [
            'participations_desc' => 'Participations DESC, u.FullName ASC',
            'participations_asc' => 'Participations ASC, u.FullName ASC',
            'name_asc' => 'u.FullName ASC',
            'last_desc' => 'LastRegisteredAt DESC',
        ];
        [$sort, $orderBy] = adminListSort('participations_desc', $sortOptions);
        $reportListPage = adminListPage();
        $reportListPerPage = adminListPerPage(25);
        $reportListTotal = countReportVolunteersList($db, $startDate, $endDate, $search);
        $offset = ($reportListPage - 1) * $reportListPerPage;
        $reportVolunteers = loadReportVolunteersList($db, $startDate, $endDate, $search, $orderBy, $offset, $reportListPerPage);
        $adminToolbar = [
            'action' => APP_URL . $listPath,
            'reset_url' => APP_URL . $listPath . '?show_report=1&start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate) . '&view=volunteers',
            'hidden' => ['show_report' => '1', 'start_date' => $startDate, 'end_date' => $endDate, 'view' => 'volunteers'],
            'search' => ['value' => $search, 'placeholder' => 'ФИО или email'],
            'sort' => ['value' => $sort, 'options' => [
                'participations_desc' => 'Больше записей',
                'participations_asc' => 'Меньше записей',
                'name_asc' => 'ФИО А—Я',
                'last_desc' => 'Недавно записывались',
            ]],
            'per' => ['value' => $reportListPerPage],
            'count' => $reportListTotal,
        ];
    }
}

$pageTitle = 'Отчёты';
$bodyClass = 'admin-area';
$chartLabels = array_map(function ($r) { return $r['CategoryName']; }, $chartByCat);
$chartValues = array_map(function ($r) { return (int)$r['cnt']; }, $chartByCat);
$chartRegsLabels = array_map(function ($r) { return $r['CategoryName']; }, $chartRegsByCat);
$chartRegsValues = array_map(function ($r) { return (int)$r['cnt']; }, $chartRegsByCat);
$hasAnyChart = !empty($chartByCat) || !empty($chartRegsByCat);
$adminCurrent = 'reports';
$periodLabelRu = formatReportPeriodRu($startDate, $endDate);
$reportBase = APP_URL . '/pages/admin/reports.php?show_report=1&start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate);

include '../../includes/html_head.php';
include '../../includes/site_header.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Отчёты и диаграммы</h1>
        <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

        <section class="surface-block report-block">
            <h2 class="section-title">Период</h2>
            <form method="GET" class="report-form">
                <input type="hidden" name="show_report" value="1">
                <div class="report-form__field">
                    <label for="start_date">С</label>
                    <input type="date" id="start_date" name="start_date" value="<?= escape($startDate) ?>" required>
                </div>
                <div class="report-form__field">
                    <label for="end_date">По (включительно)</label>
                    <input type="date" id="end_date" name="end_date" value="<?= escape($endDate) ?>" required>
                </div>
                <button type="submit" class="btn report-form__submit">Показать</button>
            </form>
        </section>

        <?php if ($reportPeriod): ?>
            <section class="surface-block report-block">
                <h2 class="section-title">Период <?= escape($periodLabelRu) ?></h2>
                <div class="stats-grid stats-grid--compact">
                    <a class="stat-card stat-card--link<?= $view === 'events' ? ' is-selected' : '' ?>"
                       href="<?= escape($reportBase . '&view=events') ?>">
                        <h3><?= (int)$stats['TotalEvents'] ?></h3>
                        <p>Акции (дата проведения)</p>
                    </a>
                    <a class="stat-card stat-card--link<?= $view === 'registrations' ? ' is-selected' : '' ?>"
                       href="<?= escape($reportBase . '&view=registrations') ?>">
                        <h3><?= (int)$stats['TotalRegistrations'] ?></h3>
                        <p>Записей участников</p>
                    </a>
                    <a class="stat-card stat-card--link<?= $view === 'volunteers' ? ' is-selected' : '' ?>"
                       href="<?= escape($reportBase . '&view=volunteers') ?>">
                        <h3><?= (int)$totalActiveVolunteers ?></h3>
                        <p>Активных волонтёров</p>
                    </a>
                </div>
            </section>

            <?php if ($view === 'events'): ?>
                <section class="surface-block dash-panel">
                    <h2 class="section-title">Акции с датой проведения <?= escape($periodLabelRu) ?></h2>
                    <?php if ($adminToolbar): ?>
                        <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
                    <?php endif; ?>
                    <div class="report-list-export">
                        <form method="POST" class="export-row">
                            <input type="hidden" name="start_date" value="<?= escape($startDate) ?>">
                            <input type="hidden" name="end_date" value="<?= escape($endDate) ?>">
                            <input type="hidden" name="export_section" value="events">
                            <button type="submit" class="btn btn-secondary btn-small">Экспорт .xls</button>
                        </form>
                    </div>
                    <?php if (empty($reportEvents)): ?>
                        <p class="muted">По запросу акций за период не найдено.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Название</th><th>Дата проведения</th><th>Место</th><th>Категория</th><th>Организатор</th><th>Участников</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportEvents as $event): ?>
                                        <tr>
                                            <td><?= escape($event['Title']) ?></td>
                                            <td><?= formatDate($event['EventDate']) ?></td>
                                            <td><?= escape($event['Location']) ?></td>
                                            <td><?= escape($event['CategoryName'] ?? '-') ?></td>
                                            <td><?= escape($event['CreatorName'] ?? '') ?></td>
                                            <td><?= (int)$event['ParticipantsCount'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?= adminRenderPagination($reportListTotal, $reportListPage, $reportListPerPage, $listPath, [
                            'show_report' => '1',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'view' => 'events',
                        ]) ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($view === 'registrations'): ?>
                <section class="surface-block dash-panel">
                    <h2 class="section-title">Записи участников <?= escape($periodLabelRu) ?></h2>
                    <?php if ($adminToolbar): ?>
                        <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
                    <?php endif; ?>
                    <div class="report-list-export">
                        <form method="POST" class="export-row">
                            <input type="hidden" name="start_date" value="<?= escape($startDate) ?>">
                            <input type="hidden" name="end_date" value="<?= escape($endDate) ?>">
                            <input type="hidden" name="export_section" value="registrations">
                            <button type="submit" class="btn btn-secondary btn-small">Экспорт .xls</button>
                        </form>
                    </div>
                    <?php if (empty($reportRegistrations)): ?>
                        <p class="muted">По запросу записей за период не найдено.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr><th>ФИО</th><th>Email</th><th>Акция</th><th>Дата акции</th><th>Записан</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportRegistrations as $row): ?>
                                        <tr>
                                            <td><?= escape($row['FullName']) ?></td>
                                            <td><?= escape($row['Email']) ?></td>
                                            <td><?= escape($row['EventTitle']) ?></td>
                                            <td><?= formatDate($row['EventDate']) ?></td>
                                            <td><?= formatDate($row['RegisteredAt']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?= adminRenderPagination($reportListTotal, $reportListPage, $reportListPerPage, $listPath, [
                            'show_report' => '1',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'view' => 'registrations',
                        ]) ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($view === 'volunteers'): ?>
                <section class="surface-block dash-panel">
                    <h2 class="section-title">Активные волонтёры за период</h2>
                    <p class="muted report-volunteer-lead">
                        Участники, которые <strong>записались на акцию</strong> в период <?= escape($periodLabelRu) ?>.
                        В колонке «Записей на акции» — сколько раз человек записался (каждая акция отдельно).
                    </p>
                    <?php if ($adminToolbar): ?>
                        <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
                    <?php endif; ?>
                    <div class="report-list-export">
                        <form method="POST" class="export-row">
                            <input type="hidden" name="start_date" value="<?= escape($startDate) ?>">
                            <input type="hidden" name="end_date" value="<?= escape($endDate) ?>">
                            <input type="hidden" name="export_section" value="active_volunteers">
                            <button type="submit" class="btn btn-secondary btn-small">Экспорт .xls</button>
                        </form>
                    </div>
                    <?php if (empty($reportVolunteers)): ?>
                        <p class="muted">За этот период никто не записывался на акции.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ФИО</th>
                                        <th>Email</th>
                                        <th>Записей на акции</th>
                                        <th>Последняя запись</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportVolunteers as $row): ?>
                                        <tr>
                                            <td><?= escape($row['FullName']) ?></td>
                                            <td><?= escape($row['Email']) ?></td>
                                            <td><?= (int)$row['Participations'] ?></td>
                                            <td><?= formatDate($row['LastRegisteredAt']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?= adminRenderPagination($reportListTotal, $reportListPage, $reportListPerPage, $listPath, [
                            'show_report' => '1',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'view' => 'volunteers',
                        ]) ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="surface-block report-chart-block" id="report-chart-events">
                <div class="dash-panel__head">
                    <h2 class="section-title">Акции по категориям (одобренные, дата проведения <?= escape($periodLabelRu) ?>)</h2>
                    <form method="POST" class="export-row">
                        <input type="hidden" name="start_date" value="<?= escape($startDate) ?>">
                        <input type="hidden" name="end_date" value="<?= escape($endDate) ?>">
                        <input type="hidden" name="export_section" value="categories">
                        <button type="submit" class="btn btn-secondary btn-small">Экспорт .xls</button>
                    </form>
                </div>
                <?php if (empty($chartByCat)): ?>
                    <p class="muted">Нет одобренных акций за период для построения диаграммы.</p>
                <?php else: ?>
                    <div class="charts-row">
                        <div class="chart-box chart-box--wide">
                            <canvas id="chartCategories" height="220"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="surface-block report-chart-block" id="report-chart-registrations">
                <div class="dash-panel__head">
                    <h2 class="section-title">Записи участников по категориям акций</h2>
                    <form method="POST" class="export-row">
                        <input type="hidden" name="start_date" value="<?= escape($startDate) ?>">
                        <input type="hidden" name="end_date" value="<?= escape($endDate) ?>">
                        <input type="hidden" name="export_section" value="registrations_by_category">
                        <button type="submit" class="btn btn-secondary btn-small">Экспорт .xls</button>
                    </form>
                </div>
                <?php if (empty($chartRegsByCat)): ?>
                    <p class="muted">Нет подтверждённых записей участников за выбранный период.</p>
                <?php else: ?>
                    <div class="charts-row">
                        <div class="chart-box chart-box--wide">
                            <canvas id="chartRegistrations" height="220"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($hasAnyChart): ?>
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof Chart === 'undefined') return;
                    var c1 = document.getElementById('chartCategories');
                    if (c1) {
                        new Chart(c1, {
                            type: 'bar',
                            data: {
                                labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
                                datasets: [{ label: 'Акции', data: <?= json_encode($chartValues) ?>, backgroundColor: 'rgba(59, 66, 159, 0.72)' }]
                            },
                            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                        });
                    }
                    var c2 = document.getElementById('chartRegistrations');
                    if (c2) {
                        new Chart(c2, {
                            type: 'bar',
                            data: {
                                labels: <?= json_encode($chartRegsLabels, JSON_UNESCAPED_UNICODE) ?>,
                                datasets: [{ label: 'Записи', data: <?= json_encode($chartRegsValues) ?>, backgroundColor: 'rgba(168, 87, 126, 0.75)' }]
                            },
                            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                        });
                    }
                });
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </main>
<?php include '../../includes/html_foot.php'; ?>
