<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
$db = Database::getInstance();
$pub = sqlPublishedEvents('e');

$perPage = 12;

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc';
$sortOptions = [
    'date_asc' => ['ORDER BY e.EventDate ASC', 'Сначала ближайшие'],
    'date_desc' => ['ORDER BY e.EventDate DESC', 'Сначала поздние'],
    'title_asc' => ['ORDER BY e.Title ASC', 'Название А—Я'],
    'title_desc' => ['ORDER BY e.Title DESC', 'Название Я—А'],
    'popular' => ['ORDER BY ParticipantsCount DESC', 'По числу записей'],
    'location_asc' => ['ORDER BY e.Location ASC', 'По месту'],
];
$currentSort = $sortOptions[$sort] ?? $sortOptions['date_asc'];
$orderBy = $currentSort[0];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$whereConditions = [$pub, sqlUpcomingEvents('e'), 'e.CategoryID IS NOT NULL', sqlActiveCategory('c')];
$params = [];

if ($search !== '') {
    $whereConditions[] = "(e.Title LIKE ? OR e.Description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($category > 0) {
    $whereConditions[] = "e.CategoryID = ?";
    $params[] = $category;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
$fromSql = "FROM Events e
        LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
        LEFT JOIN Users u ON e.CreatorUserID = u.UserID";

$countSql = "SELECT COUNT(*) as total $fromSql $whereClause";
$totalEvents = (int)$db->fetchOne($db->query($countSql, $params))['total'];
$totalPages = $totalEvents > 0 ? (int)ceil($totalEvents / $perPage) : 1;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT e.*, c.CategoryName, u.FullName as CreatorName,
        (SELECT COUNT(*) FROM Registrations WHERE EventID = e.EventID AND Status = N'confirmed') as ParticipantsCount
        $fromSql
        $whereClause
        $orderBy
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$events = $db->fetchAll($db->query($sql, array_merge($params, [$offset, $perPage])));

$categories = $db->fetchAll($db->query("SELECT * FROM Categories ORDER BY CategoryName"));

$pageTitle = 'Каталог акций';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">Каталог акций</h1>
        </header>

        <form method="GET" class="filters-bar" data-auto-submit-filters>
            <div class="filters-bar__item filters-bar__grow">
                <label class="sr-only" for="search">Поиск</label>
                <input type="search" id="search" name="search" placeholder="Название или описание" value="<?= escape($search) ?>">
            </div>
            <div class="filters-bar__item">
                <select id="category" name="category">
                    <option value="0">Все категории</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['CategoryID'] ?>" <?= $category === (int)$cat['CategoryID'] ? 'selected' : '' ?>>
                            <?= escape($cat['CategoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters-bar__item">
                <select id="sort" name="sort">
                    <?php foreach ($sortOptions as $key => $opt): ?>
                        <option value="<?= escape($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= escape($opt[1]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Применить</button>
            <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/events.php">Сбросить</a>
        </form>

        <p class="results-count">Найдено: <?= $totalEvents ?></p>

        <div class="events-grid">
            <?php if (empty($events)): ?>
                <p class="empty-state">По запросу ничего нет.</p>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php $listCover = resolvedPublicFileUrl($event['CoverImagePath'] ?? ''); ?>
                    <article class="event-card" data-category="<?= (int)($event['CategoryID'] ?? 0) ?>">
                        <div class="event-card__media">
                            <?php if ($listCover !== ''): ?>
                                <a href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$event['EventID'] ?>">
                                    <img src="<?= escape($listCover) ?>" alt="" loading="lazy">
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="event-card__body">
                        <h3><?= escape($event['Title']) ?></h3>
                        <p class="event-card__cat"><?= escape($event['CategoryName'] ?? 'Без категории') ?></p>
                        <p class="event-card__meta"><?= formatDate($event['EventDate']) ?></p>
                        <p class="event-card__meta"><?= escape($event['Location']) ?></p>
                        <p class="event-card__meta"><?= escape($event['CreatorName'] ?? '') ?></p>
                        <p class="event-card__pop">Участников: <?= (int)$event['ParticipantsCount'] ?></p>
                        <a class="btn btn-small" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$event['EventID'] ?>">Подробнее</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Страницы">
                <?php
                $pq = ['sort' => $sort];
                if ($search !== '') {
                    $pq['search'] = $search;
                }
                if ($category > 0) {
                    $pq['category'] = $category;
                }
                $pbase = '?' . http_build_query($pq) . '&page=';
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?= escape($pbase . ($page - 1)) ?>" class="pagination__arrow" aria-label="Предыдущая страница">←</a>
                <?php else: ?>
                    <span class="disabled pagination__arrow" aria-hidden="true">←</span>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= escape($pbase . $i) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= escape($pbase . ($page + 1)) ?>" class="pagination__arrow" aria-label="Следующая страница">→</a>
                <?php else: ?>
                    <span class="disabled pagination__arrow" aria-hidden="true">→</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
<?php include '../includes/html_foot.php'; ?>
