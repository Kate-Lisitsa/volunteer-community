<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAdmin();

$db = Database::getInstance();
ensureNewsCategorySchema($db);
$msg = '';
$err = '';

$editId = isset($_GET['id']) ? $_GET['id'] : null;
$fromEventId = isset($_GET['from_event']) ? (int)$_GET['from_event'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['news_action'] ?? 'save';
    if ($action === 'delete') {
        $nid = (int)($_POST['news_id'] ?? 0);
        if ($nid) {
            $paths = $db->fetchAll($db->query("SELECT FilePath FROM NewsImages WHERE NewsID = ?", [$nid]));
            foreach ($paths as $p) {
                deleteStoredPublicFile($p['FilePath']);
            }
            $db->query("DELETE FROM NewsImages WHERE NewsID = ?", [$nid]);
            $db->query("DELETE FROM News WHERE NewsID = ?", [$nid]);
            $msg = 'Новость удалена.';
        }
        $editId = null;
    } else {
        $title = trim($_POST['title'] ?? '');
        $summaryRaw = trim($_POST['summary'] ?? '');
        $summary = $summaryRaw === '' ? null : mb_substr($summaryRaw, 0, 600, 'UTF-8');
        $body = trim($_POST['body_html'] ?? '');
        $related = (int)($_POST['related_event_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $nid = (int)($_POST['news_id'] ?? 0);

        if ($title === '' || $body === '') {
            $err = 'Заголовок и текст обязательны.';
        } elseif (newsHasCategoryColumn($db) && ($catErr = validateSelectableCategoryId($db, $categoryId, 'Категория новости')) !== null) {
            $err = $catErr;
        } else {
            $rel = $related > 0 ? $related : null;
            $catParam = newsHasCategoryColumn($db) ? $categoryId : null;

            if ($nid) {
                if (newsHasCategoryColumn($db)) {
                    $db->query(
                        'UPDATE News SET Title = ?, Summary = ?, BodyHtml = ?, RelatedEventID = ?, CategoryID = ?, IsPublished = 1, PublishedAt = COALESCE(PublishedAt, GETDATE()) WHERE NewsID = ?',
                        [$title, $summary, $body, $rel, $catParam, $nid]
                    );
                } else {
                    $db->query(
                        'UPDATE News SET Title = ?, Summary = ?, BodyHtml = ?, RelatedEventID = ?, IsPublished = 1, PublishedAt = COALESCE(PublishedAt, GETDATE()) WHERE NewsID = ?',
                        [$title, $summary, $body, $rel, $nid]
                    );
                }

                foreach ($_POST['delete_images'] ?? [] as $imgId) {
                    $imgId = (int)$imgId;
                    if ($imgId <= 0) {
                        continue;
                    }
                    $row = $db->fetchOne($db->query("SELECT FilePath FROM NewsImages WHERE ImageID = ? AND NewsID = ?", [$imgId, $nid]));
                    if ($row) {
                        deleteStoredPublicFile($row['FilePath']);
                        $db->query("DELETE FROM NewsImages WHERE ImageID = ?", [$imgId]);
                    }
                }

                $nextSort = (int)($db->fetchOne($db->query("SELECT ISNULL(MAX(SortOrder), -1) AS m FROM NewsImages WHERE NewsID = ?", [$nid]))['m']) + 1;
                if (!empty($_FILES['news_images']['name']) && is_array($_FILES['news_images']['name'])) {
                    $paths = saveUploadedImagesMultiple($_FILES['news_images'], 'news', 'n' . $nid);
                    foreach ($paths as $i => $path) {
                        $db->query("INSERT INTO NewsImages (NewsID, FilePath, SortOrder) VALUES (?, ?, ?)", [$nid, $path, $nextSort + $i]);
                    }
                }
                $msg = 'Новость обновлена и опубликована.';
            } else {
                if (newsHasCategoryColumn($db)) {
                    $stmt = $db->query(
                        'INSERT INTO News (Title, Summary, BodyHtml, RelatedEventID, CategoryID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
                         OUTPUT INSERTED.NewsID AS NewId
                         VALUES (?, ?, ?, ?, ?, 1, GETDATE(), ?, GETDATE())',
                        [$title, $summary, $body, $rel, $catParam, $_SESSION['user_id']]
                    );
                } else {
                    $stmt = $db->query(
                        'INSERT INTO News (Title, Summary, BodyHtml, RelatedEventID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
                         OUTPUT INSERTED.NewsID AS NewId
                         VALUES (?, ?, ?, ?, 1, GETDATE(), ?, GETDATE())',
                        [$title, $summary, $body, $rel, $_SESSION['user_id']]
                    );
                }
                $insRow = $db->fetchOne($stmt);
                $newId = 0;
                if (is_array($insRow)) {
                    $newId = (int)($insRow['NewId'] ?? $insRow['newid'] ?? 0);
                }
                if ($newId <= 0) {
                    $err = 'Не удалось сохранить новость (ошибка идентификатора). Попробуйте ещё раз.';
                } elseif (!empty($_FILES['news_images']['name']) && is_array($_FILES['news_images']['name'])) {
                    $paths = saveUploadedImagesMultiple($_FILES['news_images'], 'news', 'n' . $newId);
                    foreach ($paths as $i => $path) {
                        $db->query("INSERT INTO NewsImages (NewsID, FilePath, SortOrder) VALUES (?, ?, ?)", [$newId, $path, $i]);
                    }
                    $msg = 'Новость опубликована.';
                } else {
                    $msg = 'Новость опубликована.';
                }
            }
            $editId = null;
        }
    }
}

$newsCategoryOptions = newsHasCategoryColumn($db) ? fetchNewsCategoryOptions($db) : [];

$editing = null;
$newsImages = [];
if ($editId === 'new') {
    $editing = ['NewsID' => 0, 'Title' => '', 'Summary' => '', 'BodyHtml' => '', 'RelatedEventID' => null, 'CategoryID' => null];
    if ($fromEventId > 0) {
        $evRow = $db->fetchOne($db->query('SELECT EventID, Title, CategoryID FROM Events WHERE EventID = ?', [$fromEventId]));
        if ($evRow) {
            $editing['RelatedEventID'] = (int)$evRow['EventID'];
            $editing['Title'] = 'Итоги: ' . $evRow['Title'];
            if (newsHasCategoryColumn($db)) {
                $suggested = suggestNewsCategoryFromEvent($db, (int)$evRow['EventID']);
                if ($suggested > 0) {
                    $editing['CategoryID'] = $suggested;
                }
            }
        }
    }
} elseif (is_numeric($editId)) {
    $editing = $db->fetchOne($db->query("SELECT * FROM News WHERE NewsID = ?", [(int)$editId]));
    if (!$editing) {
        $editing = null;
        $err = 'Запись не найдена.';
    } else {
        $newsImages = $db->fetchAll($db->query(
            "SELECT ImageID, FilePath FROM NewsImages WHERE NewsID = ? ORDER BY SortOrder, ImageID",
            [(int)$editId]
        ));
    }
}

$eventsForSelect = $db->fetchAll($db->query(
    "SELECT TOP 200 EventID, Title, EventDate FROM Events ORDER BY EventDate DESC"
));

$list = [];
$listPath = '/pages/admin/news.php';
$adminToolbar = null;
$totalNews = 0;
$newsPage = 1;
$newsPerPage = 25;
if (!$editing) {
    $search = adminListSearch();
    $pubFilter = adminListFilter('pub', '', ['' => '', '1' => '', '0' => '']);
    $catFilter = newsHasCategoryColumn($db) ? adminListFilter('category', '', ['' => '', '0' => '']) : '';
    $sortOptions = [
        'created_desc' => 'CreatedAt DESC',
        'created_asc' => 'CreatedAt ASC',
        'published_desc' => 'PublishedAt DESC',
        'published_asc' => 'PublishedAt ASC',
        'title_asc' => 'Title ASC',
        'title_desc' => 'Title DESC',
    ];
    [$sort, $orderBy] = adminListSort('created_desc', $sortOptions);
    $newsPage = adminListPage();
    $newsPerPage = adminListPerPage(15);

    $params = [];
    $where = ['1=1'];
    $newsCol = newsHasCategoryColumn($db) ? 'n.' : '';
    adminApplySearch([$newsCol . 'Title', $newsCol . 'Summary'], $search, $where, $params);
    if ($pubFilter === '1') {
        $where[] = $newsCol . 'IsPublished = 1';
    } elseif ($pubFilter === '0') {
        $where[] = $newsCol . 'IsPublished = 0';
    }
    if ($catFilter !== '' && (int)$catFilter > 0) {
        $where[] = $newsCol . 'CategoryID = ?';
        $params[] = (int)$catFilter;
    }
    $whereSql = implode(' AND ', $where);
    $fromSql = newsHasCategoryColumn($db)
        ? "FROM News n LEFT JOIN Categories c ON c.CategoryID = n.CategoryID WHERE {$whereSql}"
        : "FROM News WHERE {$whereSql}";

    $totalNews = (int)($db->fetchOne($db->query("SELECT COUNT(*) AS c {$fromSql}", $params))['c'] ?? 0);
    $offset = ($newsPage - 1) * $newsPerPage;
    $selectCols = newsHasCategoryColumn($db)
        ? 'n.NewsID, n.Title, n.IsPublished, n.PublishedAt, n.CreatedAt, c.CategoryName'
        : 'NewsID, Title, IsPublished, PublishedAt, CreatedAt';
    $orderPrefix = newsHasCategoryColumn($db) ? 'n.' : '';
    $orderByFixed = str_replace(
        ['CreatedAt', 'PublishedAt', 'Title'],
        [$orderPrefix . 'CreatedAt', $orderPrefix . 'PublishedAt', $orderPrefix . 'Title'],
        $orderBy
    );
    $list = $db->fetchAll($db->query(
        "SELECT {$selectCols}
         {$fromSql}
         ORDER BY {$orderByFixed}
         OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
        array_merge($params, [$offset, $newsPerPage])
    ));

    $adminCatFilterOptions = ['' => 'Все категории'];
    if (newsHasCategoryColumn($db)) {
        foreach (fetchNewsCategoryOptions($db) as $catRow) {
            $adminCatFilterOptions[(string)(int)$catRow['CategoryID']] = $catRow['CategoryName'];
        }
    }

    $adminToolbar = [
        'action' => APP_URL . $listPath,
        'reset_url' => APP_URL . $listPath,
        'search' => ['value' => $search, 'placeholder' => 'Заголовок или краткое описание'],
        'filters' => array_values(array_filter([
            [
                'name' => 'pub',
                'label' => 'Статус',
                'value' => $pubFilter,
                'options' => ['' => 'Все', '1' => 'Опубликованные', '0' => 'Черновики'],
            ],
            newsHasCategoryColumn($db) ? [
                'name' => 'category',
                'label' => 'Категория',
                'value' => $catFilter,
                'options' => $adminCatFilterOptions,
            ] : null,
        ])),
        'sort' => [
            'value' => $sort,
            'options' => [
                'created_desc' => 'Сначала новые',
                'created_asc' => 'Сначала старые',
                'published_desc' => 'Дата публикации: новые',
                'published_asc' => 'Дата публикации: старые',
                'title_asc' => 'Заголовок А—Я',
                'title_desc' => 'Заголовок Я—А',
            ],
        ],
        'per' => ['value' => $newsPerPage],
        'count' => $totalNews,
    ];
}

$pageTitle = 'Новости';
$bodyClass = 'admin-area';
$headExtra = '<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">';
$adminCurrent = 'news';
include '../../includes/html_head.php';
include '../../includes/site_header.php';
?>
    <main class="container content-page">
        <?php include __DIR__ . '/../../includes/admin_nav.php'; ?>

        <h1 class="page-title">Новости после акций</h1>
        <?php if ($msg): ?><div class="alert alert-success"><?= escape($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?= escape($err) ?></div><?php endif; ?>

        <?php if ($editing): ?>
            <p><a class="text-link" href="<?= APP_URL ?>/pages/admin/news.php">← К списку</a></p>
            <form method="POST" enctype="multipart/form-data" class="surface-form news-form">
                <input type="hidden" name="news_action" value="save">
                <input type="hidden" name="news_id" value="<?= (int)$editing['NewsID'] ?>">
                <div class="form-group">
                    <label for="title">Заголовок</label>
                    <input type="text" id="title" name="title" required maxlength="255" value="<?= escape($editing['Title']) ?>">
                </div>
                <?php if (newsHasCategoryColumn($db)): ?>
                <div class="form-group">
                    <label for="category_id">Категория *</label>
                    <select id="category_id" name="category_id" required>
                        <?php $editCatId = (int)($editing['CategoryID'] ?? 0); ?>
                        <option value=""<?= $editCatId <= 0 ? ' selected' : '' ?>>— выберите —</option>
                        <?php
                        $formCats = fetchNewsCategoryOptions($db, $editCatId);
                        foreach ($formCats as $catRow):
                        ?>
                            <option value="<?= (int)$catRow['CategoryID'] ?>" <?= $editCatId === (int)$catRow['CategoryID'] ? 'selected' : '' ?>>
                                <?= escape($catRow['CategoryName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="summary">Краткое описание (для списка новостей)</label>
                    <textarea id="summary" name="summary" rows="3" maxlength="600" placeholder="2–3 предложения для карточки новости в списке"><?= escape($editing['Summary'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="body_html">Текст новости</label>
                    <input id="body_html" type="hidden" name="body_html" value="<?= escape($editing['BodyHtml'] ?? '') ?>">
                    <trix-editor input="body_html" class="trix-editor"></trix-editor>
                </div>
                <div class="form-group">
                    <label for="news_images">Фотографии к новости</label>
                    <input type="file" id="news_images" name="news_images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                </div>
                <?php if (!empty($newsImages)): ?>
                    <div class="form-group">
                        <span class="form-label-like">Текущие изображения — отметьте «удалить», чтобы убрать при сохранении</span>
                        <div class="admin-news-thumbs">
                            <?php foreach ($newsImages as $im): ?>
                                <?php $admImg = resolvedPublicFileUrl($im['FilePath'] ?? ''); ?>
                                <label class="checkbox-label" style="flex-direction:column;align-items:flex-start">
                                    <?php if ($admImg !== ''): ?>
                                        <img src="<?= escape($admImg) ?>" alt="">
                                    <?php else: ?>
                                        <span class="muted small">Нет файла на диске (путь в БД: <?= escape($im['FilePath'] ?? '') ?>). Отметьте удаление и сохраните.</span>
                                    <?php endif; ?>
                                    <input type="checkbox" name="delete_images[]" value="<?= (int)$im['ImageID'] ?>"> удалить запись
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="related_event_id">Связанная акция (необязательно)</label>
                    <select id="related_event_id" name="related_event_id">
                        <option value="0">— нет —</option>
                        <?php foreach ($eventsForSelect as $ev): ?>
                            <option value="<?= (int)$ev['EventID'] ?>" <?= (int)($editing['RelatedEventID'] ?? 0) === (int)$ev['EventID'] ? 'selected' : '' ?>>
                                <?= escape($ev['Title']) ?> (<?= formatDate($ev['EventDate']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn"><?= (int)$editing['NewsID'] > 0 ? 'Сохранить и опубликовать' : 'Опубликовать на сайте' ?></button>
            </form>
            <script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
        <?php else: ?>
            <p>
                <a class="btn" href="<?= APP_URL ?>/pages/admin/news.php?id=new">Добавить новость</a>
                <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/admin/outcomes.php">Итоги организаторов</a>
            </p>
            <section class="surface-block">
                <h2 class="section-title">Новости</h2>
                <?php if ($adminToolbar): ?>
                    <?php include __DIR__ . '/../../includes/admin_list_toolbar.php'; ?>
                <?php endif; ?>
                <ul class="admin-list admin-list--in-panel">
                    <?php if (empty($list)): ?>
                        <li class="muted">По запросу новостей не найдено.</li>
                    <?php else: ?>
                        <?php foreach ($list as $n): ?>
                            <li class="admin-list__item">
                                <div>
                                    <strong><?= escape($n['Title']) ?></strong>
                                    <span class="muted small">
                                        <?php if (!empty($n['CategoryName'])): ?>· <?= escape($n['CategoryName']) ?><?php endif; ?>
                                        <?php if ($n['PublishedAt']): ?>· <?= formatDate($n['PublishedAt']) ?><?php endif; ?>
                                    </span>
                                </div>
                                <div class="row-actions">
                                    <a class="btn btn-small btn-secondary" href="<?= APP_URL ?>/pages/news_view.php?id=<?= (int)$n['NewsID'] ?>" target="_blank" rel="noopener">На сайте</a>
                                    <a class="btn btn-small btn-secondary" href="<?= APP_URL ?>/pages/admin/news.php?id=<?= (int)$n['NewsID'] ?>">Правка</a>
                                    <form method="post" action="<?= APP_URL ?>/pages/admin/news.php" class="inline-form news-delete-form" onsubmit="return confirm('Удалить эту новость?');">
                                        <input type="hidden" name="news_action" value="delete">
                                        <input type="hidden" name="news_id" value="<?= (int)$n['NewsID'] ?>">
                                        <button type="submit" class="btn btn-small btn-danger">Удалить</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <?php if ($adminToolbar): ?>
                    <?= adminRenderPagination($totalNews, $newsPage, $newsPerPage, $listPath) ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
<?php include '../../includes/html_foot.php'; ?>
