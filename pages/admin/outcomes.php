<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAdmin();

$db = Database::getInstance();

$search = adminListSearch();
$contentFilter = adminListFilter('content', '', ['' => '', 'text' => '', 'files' => '', 'both' => '']);
$sortOptions = [
    'submitted_asc' => 'o.SubmittedAt ASC',
    'submitted_desc' => 'o.SubmittedAt DESC',
    'updated_desc' => 'o.UpdatedAt DESC',
    'event_desc' => 'e.EventDate DESC',
    'title_asc' => 'e.Title ASC',
    'organizer_asc' => 'u.FullName ASC',
];
[$sort, $orderBy] = adminListSort('submitted_asc', $sortOptions);
$page = adminListPage();
$perPage = adminListPerPage(15);

$params = [];
$where = ['1=1'];
adminApplySearch(['e.Title', 'u.FullName', 'u.Email', 'o.BodyText'], $search, $where, $params);
if ($contentFilter === 'text') {
    $where[] = "NULLIF(LTRIM(RTRIM(ISNULL(o.BodyText, N''))), N'') IS NOT NULL";
} elseif ($contentFilter === 'files') {
    $where[] = 'EXISTS (SELECT 1 FROM EventOutcomeFiles f WHERE f.OutcomeID = o.OutcomeID)';
} elseif ($contentFilter === 'both') {
    $where[] = "NULLIF(LTRIM(RTRIM(ISNULL(o.BodyText, N''))), N'') IS NOT NULL AND EXISTS (SELECT 1 FROM EventOutcomeFiles f WHERE f.OutcomeID = o.OutcomeID)";
}
$whereSql = implode(' AND ', $where);
$fromSql = "FROM EventOutcomes o
     INNER JOIN Events e ON e.EventID = o.EventID
     INNER JOIN Users u ON u.UserID = e.CreatorUserID
     WHERE {$whereSql}";

$totalRows = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c {$fromSql}", $params))['c'] ?? 0);
$offset = ($page - 1) * $perPage;
$rows = $db->fetchAll($db->query(
    "SELECT o.OutcomeID, o.EventID, o.BodyText, o.SubmittedAt, o.UpdatedAt,
            e.Title AS EventTitle, e.EventDate,
            u.FullName AS OrganizerName, u.Email AS OrganizerEmail
     {$fromSql}
     ORDER BY {$orderBy}
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    array_merge($params, [$offset, $perPage])
));

$filesByOutcome = [];
if (!empty($rows)) {
    $ids = array_map(static function ($r) {
        return (int)$r['OutcomeID'];
    }, $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->query(
        "SELECT * FROM EventOutcomeFiles WHERE OutcomeID IN ($placeholders) ORDER BY CreatedAt ASC",
        $ids
    );
    foreach ($db->fetchAll($st) as $f) {
        $oid = (int)$f['OutcomeID'];
        if (!isset($filesByOutcome[$oid])) {
            $filesByOutcome[$oid] = [];
        }
        $filesByOutcome[$oid][] = $f;
    }
}

$listPath = '/pages/admin/outcomes.php';
$pageTitle = 'Итоги организаторов';
$adminToolbar = [
    'action' => APP_URL . $listPath,
    'reset_url' => APP_URL . $listPath,
    'search' => ['value' => $search, 'placeholder' => 'Акция, организатор, текст итогов'],
    'filters' => [[
        'name' => 'content',
        'label' => 'Содержимое',
        'value' => $contentFilter,
        'options' => ['' => 'Все', 'text' => 'С текстом', 'files' => 'С файлами', 'both' => 'Текст и файлы'],
    ]],
    'sort' => [
        'value' => $sort,
        'options' => [
            'submitted_asc' => 'Сначала старые',
            'submitted_desc' => 'Сначала новые',
            'updated_desc' => 'Недавно обновлённые',
            'event_desc' => 'Дата акции: поздние',
            'title_asc' => 'Акция А—Я',
            'organizer_asc' => 'Организатор А—Я',
        ],
    ],
    'per' => ['value' => $perPage],
    'count' => $totalRows,
];

$bodyClass = 'admin-area';
$adminCurrent = 'outcomes';
include '../../includes/html_head.php';
include '../../includes/site_header.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Итоги акций</h1>
        <p class="page-lead muted">Организаторы передают текст и материалы после завершения акции. Ниже очередь по дате отправки: скачайте файлы и по тексту подготовьте новость.</p>

        <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>

        <?php if (empty($rows)): ?>
            <p class="muted">По запросу итогов не найдено.</p>
        <?php else: ?>
            <ul class="admin-materials-list admin-outcomes-list">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $oid = (int)$r['OutcomeID'];
                    $flist = $filesByOutcome[$oid] ?? [];
                    $newsUrl = APP_URL . '/pages/admin/news.php?id=new&from_event=' . (int)$r['EventID'];
                    $hasBody = trim((string)($r['BodyText'] ?? '')) !== '';
                    ?>
                    <li class="surface-block admin-materials-item">
                        <div class="admin-materials-head">
                            <strong><?= escape($r['EventTitle']) ?></strong>
                            <span class="muted">Акция: <?= formatDate($r['EventDate']) ?></span>
                        </div>
                        <p class="muted small">
                            Организатор: <?= escape($r['OrganizerName']) ?> &lt;<?= escape($r['OrganizerEmail']) ?>&gt;
                            · отправлено: <?= formatDate($r['SubmittedAt']) ?>
                            <?php if (!empty($r['UpdatedAt'])): ?>
                                · обновлено: <?= formatDate($r['UpdatedAt']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($hasBody): ?>
                            <blockquote class="material-quote"><?= nl2br(escape($r['BodyText'])) ?></blockquote>
                        <?php else: ?>
                            <p class="muted small">Текст не указан.</p>
                        <?php endif; ?>
                        <?php if (!empty($flist)): ?>
                            <ul class="admin-outcome-files">
                                <?php foreach ($flist as $f): ?>
                                    <?php $fu = resolvedPublicFileUrl($f['FilePath'] ?? ''); ?>
                                    <li>
                                        <a href="<?= escape($fu) ?>" target="_blank" rel="noopener" download><?= escape($f['OriginalName'] ?? 'Файл') ?></a>
                                        <span class="muted"> · <?= escape($f['MaterialType'] ?? '') ?></span>
                                        <?php if ($fu !== '' && ($f['MaterialType'] ?? '') === 'image'): ?>
                                            <div class="material-thumb-wrap">
                                                <a href="<?= escape($fu) ?>" target="_blank" rel="noopener">
                                                    <img class="material-thumb" src="<?= escape($fu) ?>" alt="" loading="lazy" width="200" height="120">
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="row-actions">
                            <a class="btn btn-small btn-secondary" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$r['EventID'] ?>">Страница акции</a>
                            <a class="btn btn-small" href="<?= escape($newsUrl) ?>">Черновик новости</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= adminRenderPagination($totalRows, $page, $perPage, $listPath) ?>
        <?php endif; ?>
    </main>
<?php include '../../includes/html_foot.php'; ?>
