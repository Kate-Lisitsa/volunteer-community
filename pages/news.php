<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
$db = Database::getInstance();
ensureNewsCategorySchema($db);

$firstImg = newsSqlFirstImagePath('n');
$hasNewsCat = newsHasCategoryColumn($db);

$perPage = 6;
$listPath = '/pages/news.php';

$sort = $_GET['sort'] ?? 'date_desc';
$sortOptions = [
    'date_desc' => ['ORDER BY n.PublishedAt DESC', 'Сначала новые'],
    'date_asc' => ['ORDER BY n.PublishedAt ASC', 'Сначала старые'],
    'title_asc' => ['ORDER BY n.Title ASC', 'Заголовок А—Я'],
    'title_desc' => ['ORDER BY n.Title DESC', 'Заголовок Я—А'],
];
$currentSort = $sortOptions[$sort] ?? $sortOptions['date_desc'];
$orderBy = $currentSort[0];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = $hasNewsCat ? (int)($_GET['category'] ?? 0) : 0;

$params = [];
$where = ['n.IsPublished = 1'];

if ($search !== '') {
    $where[] = '(n.Title LIKE ? OR n.Summary LIKE ? OR n.BodyHtml LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($hasNewsCat && $category > 0) {
    $where[] = 'n.CategoryID = ?';
    $params[] = $category;
}

$whereSql = implode(' AND ', $where);
$joinCat = $hasNewsCat ? 'LEFT JOIN Categories c ON c.CategoryID = n.CategoryID' : '';

$totalRow = $db->fetchOne($db->query(
    "SELECT COUNT(*) AS c FROM News n {$joinCat} WHERE {$whereSql}",
    $params
));
$totalNews = (int)($totalRow['c'] ?? 0);
$totalPages = $totalNews > 0 ? (int)ceil($totalNews / $perPage) : 1;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$catSelect = $hasNewsCat ? ', c.CategoryName' : '';

$items = [];
if ($totalNews > 0) {
    $items = $db->fetchAll($db->query(
        "SELECT n.NewsID, n.Title, n.Summary, n.BodyHtml, n.PublishedAt, {$firstImg} AS FirstImagePath{$catSelect}
         FROM News n
         {$joinCat}
         WHERE {$whereSql}
         {$orderBy}
         OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
        array_merge($params, [$offset, $perPage])
    ));
}

$categories = $db->fetchAll($db->query('SELECT CategoryID, CategoryName FROM Categories ORDER BY CategoryName'));

$pageTitle = 'Новости';
$bodyClass = 'page-news';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">Новости</h1>
        </header>

        <form method="GET" class="filters-bar" data-auto-submit-filters>
            <div class="filters-bar__item filters-bar__grow">
                <label class="sr-only" for="search">Поиск</label>
                <input type="search" id="search" name="search" placeholder="Заголовок или текст" value="<?= escape($search) ?>">
            </div>
            <div class="filters-bar__item">
                <label class="sr-only" for="news_category">Категория</label>
                <select id="news_category" name="category">
                    <option value="0">Все категории</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['CategoryID'] ?>" <?= $category === (int)$cat['CategoryID'] ? 'selected' : '' ?>>
                            <?= escape($cat['CategoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters-bar__item">
                <label class="sr-only" for="sort">Сортировка</label>
                <select id="sort" name="sort">
                    <?php foreach ($sortOptions as $key => $opt): ?>
                        <option value="<?= escape($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= escape($opt[1]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Применить</button>
            <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/news.php">Сбросить</a>
        </form>

        <p class="results-count">Найдено: <?= $totalNews ?></p>

        <?php if ($totalNews === 0): ?>
            <p class="muted">По запросу ничего не найдено.</p>
        <?php else: ?>
            <ul class="news-grid">
                <?php foreach ($items as $n): ?>
                    <?php
                    $newsUrl = APP_URL . '/pages/news_view.php?id=' . (int)$n['NewsID'];
                    $thumbUrl = newsImagePublicUrl($n['FirstImagePath'] ?? '');
                    $sum = trim((string)($n['Summary'] ?? ''));
                    if ($sum === '') {
                        $sum = excerptFromHtml($n['BodyHtml'] ?? '', 140);
                    }
                    ?>
                    <li class="news-tile">
                        <a class="news-tile__link" href="<?= escape($newsUrl) ?>">
                            <span class="news-tile__media">
                                <?php if ($thumbUrl !== ''): ?>
                                    <img src="<?= escape($thumbUrl) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="news-tile__media-placeholder" aria-hidden="true"></span>
                                <?php endif; ?>
                            </span>
                            <span class="news-tile__body">
                                <?php if (!empty($n['CategoryName'])): ?>
                                    <span class="news-tile__cat"><?= escape($n['CategoryName']) ?></span>
                                <?php endif; ?>
                                <span class="news-tile__title"><?= escape($n['Title']) ?></span>
                                <?php if ($sum !== ''): ?>
                                    <span class="news-tile__sum"><?= escape($sum) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($n['PublishedAt'])): ?>
                                    <?php $pubDt = toDateTime($n['PublishedAt']); ?>
                                    <?php if ($pubDt): ?>
                                        <time class="news-tile__date" datetime="<?= escape($pubDt->format('Y-m-d')) ?>">
                                            <?= escape(formatDateOnly($n['PublishedAt'])) ?>
                                        </time>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php
            $paginationPreserve = ['sort' => $sort];
            if ($search !== '') {
                $paginationPreserve['search'] = $search;
            }
            if ($category > 0) {
                $paginationPreserve['category'] = $category;
            }
            echo adminRenderPagination($totalNews, $page, $perPage, $listPath, $paginationPreserve);
            ?>
        <?php endif; ?>
    </main>
<?php include '../includes/html_foot.php'; ?>
