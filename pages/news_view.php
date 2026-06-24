<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$db = Database::getInstance();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$newsCatJoin = newsHasCategoryColumn($db) ? 'LEFT JOIN Categories c ON c.CategoryID = n.CategoryID' : '';
$newsCatSelect = newsHasCategoryColumn($db) ? ', c.CategoryName' : '';
$row = $db->fetchOne($db->query(
    "SELECT n.*, e.Title as EventTitle, e.EventID{$newsCatSelect}
     FROM News n
     LEFT JOIN Events e ON n.RelatedEventID = e.EventID
     {$newsCatJoin}
     WHERE n.NewsID = ? AND n.IsPublished = 1",
    [$id]
));

if (!$row) {
    http_response_code(404);
    exit('Новость не найдена');
}

$galleryRows = $db->fetchAll($db->query(
    "SELECT FilePath FROM NewsImages WHERE NewsID = ? ORDER BY SortOrder, ImageID",
    [$id]
));
$galleryUrls = [];
foreach ($galleryRows as $gr) {
    $u = newsImagePublicUrl($gr['FilePath'] ?? '');
    if ($u !== '') {
        $galleryUrls[] = $u;
    }
}

$pageTitle = $row['Title'];
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <article class="news-article surface-block">
            <header class="news-article__head">
                <h1 class="page-title"><?= escape($row['Title']) ?></h1>
                <p class="muted">
                    <?php if (!empty($row['CategoryName'])): ?>
                        <span class="news-tile__cat"><?= escape($row['CategoryName']) ?></span>
                        <?php if ($row['PublishedAt']): ?> · <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($row['PublishedAt']): ?>
                        <?= formatDateOnly($row['PublishedAt']) ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($row['EventTitle'])): ?>
                    <p>Связанная акция: <a href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$row['EventID'] ?>"><?= escape($row['EventTitle']) ?></a></p>
                <?php endif; ?>
            </header>
            <?php if (!empty($galleryUrls)): ?>
                <div class="news-gallery">
                    <?php foreach ($galleryUrls as $u): ?>
                        <a href="<?= escape($u) ?>" target="_blank" rel="noopener">
                            <img src="<?= escape($u) ?>" alt="" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="news-body prose-html">
                <?= newsBodyHtmlSafe($row['BodyHtml'] ?? '') ?>
            </div>
        </article>
        <p class="back-link"><a href="<?= APP_URL ?>/pages/news.php">← Все новости</a></p>
    </main>
<?php include '../includes/html_foot.php'; ?>
