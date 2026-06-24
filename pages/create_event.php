<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/nominatim.php';

requireLogin();

$db = Database::getInstance();
$error = '';
$success = '';

$categories = fetchSelectableCategories($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $eventDate = $_POST['event_date'] ?? '';
    if (!empty($eventDate)) {
        $eventDate = str_replace('T', ' ', $eventDate) . ':00';
    }
    $location = trim($_POST['location'] ?? '');
    $maxParticipants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $osmType = strtolower(trim($_POST['location_osm_type'] ?? ''));
    $osmId = (int)($_POST['location_osm_id'] ?? 0);

    if (empty($title) || empty($description) || empty($eventDate) || $location === '') {
        $error = 'Заполните все обязательные поля';
    } elseif (($catErr = validateSelectableCategoryId($db, $categoryId, 'Категория помощи')) !== null) {
        $error = $catErr;
    }

    if ($error === '' && !isAdmin()) {
        $cntRow = $db->fetchOne($db->query(
            "SELECT COUNT(*) AS c FROM Events WHERE CreatorUserID = ? AND CreatedAt >= CAST(GETDATE() AS DATE)",
            [$_SESSION['user_id']]
        ));
        if ((int)($cntRow['c'] ?? 0) >= 1) {
            $error = 'Не более одной новой акции в сутки. Попробуйте завтра или отредактируйте уже созданную заявку.';
        }
    }

    if ($error === '') {
        $normalized = null;
        $mapErr = nominatimValidateAndNormalizeLocation($osmType, $osmId, $normalized);
        if ($mapErr !== '') {
            $error = $mapErr;
        } else {
            $location = $normalized;
        }
    }

    if ($error === '') {
        if (isAdmin()) {
            $isPublished = 1;
            $modStatus = 'approved';
        } else {
            $isPublished = 0;
            $modStatus = 'pending';
        }

        $coverPath = null;
        if (!empty($_FILES['cover_image']['tmp_name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $coverPath = saveUploadedImage($_FILES['cover_image'], 'event_covers', 'evnew');
            if (!$coverPath) {
                $error = 'Обложка: допустимы только изображения JPEG, PNG, WebP или GIF.';
            }
        }

        if ($error === '') {
            $sql = "INSERT INTO Events (Title, Description, CategoryID, CreatorUserID, EventDate, Location, MaxParticipants, CreatedAt, IsPublished, ModerationStatus, RejectionReason, CoverImagePath)
                    VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?, NULL, ?)";
            $params = [$title, $description, $categoryId, $_SESSION['user_id'], $eventDate, $location, $maxParticipants, $isPublished, $modStatus, $coverPath];
            $db->query($sql, $params);
            $eventId = $db->lastInsertId();

            $db->query("INSERT INTO ActivityLog (UserID, EventID, ActionType) VALUES (?, ?, N'create_event')", [$_SESSION['user_id'], $eventId]);

            if (isAdmin()) {
                $success = 'Акция создана и сразу опубликована в каталоге.';
            } else {
                $success = 'Акция отправлена на модерацию. После проверки администратором она появится на главной странице.';
            }
        }
    }
}

$pageTitle = 'Новая акция';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">Создать акцию</h1>
            <p class="page-lead">Заполните форму: для обычных пользователей акция проходит проверку перед публикацией. Обложка по желанию — её увидят в каталоге и на странице акции.</p>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
            <p><a class="btn" href="<?= APP_URL ?>/pages/profile.php">Личный кабинет</a>
               <a class="btn btn-secondary" href="<?= APP_URL ?>/index.php">На главную</a></p>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="surface-form event-form">
                <div class="form-group">
                    <label for="cover_image">Обложка (фото акции)</label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif">
                    <p class="muted small">Необязательно. Рекомендуемый формат — горизонтальное фото.</p>
                </div>
                <div class="form-group">
                    <label for="title">Название *</label>
                    <input type="text" id="title" name="title" required maxlength="200">
                </div>
                <div class="form-group">
                    <label for="category_id">Категория помощи *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">— выберите —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['CategoryID'] ?>"><?= escape($cat['CategoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="event_date">Дата и время начала *</label>
                    <input type="datetime-local" id="event_date" name="event_date" required>
                </div>
                <div class="form-group location-field-wrap" data-suggest-url="<?= escape(APP_URL . '/pages/api/location_suggest.php') ?>">
                    <label for="location">Место *</label>
                    <input type="text" id="location" name="location" required maxlength="300" autocomplete="street-address">
                    <input type="hidden" name="location_osm_type" id="location_osm_type" value="">
                    <input type="hidden" name="location_osm_id" id="location_osm_id" value="">
                    <p class="muted small">Начните вводить адрес или название места — появятся подсказки с <strong>OpenStreetMap</strong>. <strong>Обязательно выберите один вариант из списка</strong> (иначе форма не отправится). После сохранения в карточке будет официальная подпись из справочника.</p>
                </div>
                <div class="form-group">
                    <label for="max_participants">Лимит участников</label>
                    <input type="number" id="max_participants" name="max_participants" min="1" placeholder="без ограничения">
                </div>
                <div class="form-group">
                    <label for="description">Описание *</label>
                    <textarea id="description" name="description" rows="10" required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Отправить</button>
                    <a class="btn btn-secondary" href="<?= APP_URL ?>/index.php">Отмена</a>
                </div>
            </form>
        <?php endif; ?>
    </main>
<?php
$footExtra = '<script src="' . escape(APP_URL . '/assets/js/location_autocomplete.js') . '" defer></script>';
include '../includes/html_foot.php';
?>
