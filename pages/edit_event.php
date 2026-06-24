<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/nominatim.php';

requireLogin();

$db = Database::getInstance();
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->query("SELECT * FROM Events WHERE EventID = ?", [$eventId]);
$event = $db->fetchOne($stmt);

if (!$event || ((int)$event['CreatorUserID'] !== (int)$_SESSION['user_id'] && !isAdmin())) {
    redirect(APP_URL . '/pages/profile.php');
}

$categories = fetchSelectableCategories($db, (int)$event['CategoryID']);

$error = '';
$success = '';

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
    $isPriority = (isAdmin() && !empty($_POST['is_priority'])) ? 1 : 0;
    $osmType = strtolower(trim($_POST['location_osm_type'] ?? ''));
    $osmId = (int)($_POST['location_osm_id'] ?? 0);

    $coverPath = $event['CoverImagePath'] ?? null;
    if (!empty($_FILES['cover_image']['tmp_name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $up = saveUploadedImage($_FILES['cover_image'], 'event_covers', 'ev' . $eventId);
        if ($up) {
            deleteStoredPublicFile($event['CoverImagePath'] ?? null);
            $coverPath = $up;
        } else {
            $error = 'Обложка: допустимы только изображения JPEG, PNG, WebP или GIF.';
        }
    }

    if ($error === '') {
        if (empty($title) || empty($description) || empty($eventDate) || $location === '') {
            $error = 'Заполните все обязательные поля';
        } elseif (($catErr = validateSelectableCategoryId($db, $categoryId, 'Категория')) !== null) {
            $error = $catErr;
        } else {
            $prevLoc = trim((string)($event['Location'] ?? ''));
            if ($location !== '' && $location === $prevLoc && $osmId < 1 && $prevLoc !== '') {
                /* место не меняли — повторный выбор из карты не обязателен */
            } else {
                $normalized = null;
                $mapErr = nominatimValidateAndNormalizeLocation($osmType, $osmId, $normalized);
                if ($mapErr !== '') {
                    $error = $mapErr;
                } else {
                    $location = $normalized;
                }
            }
        }
    }

    if ($error === '') {
            $prevReminderNorm = eventDateTime($event['EventDate'])->format('Y-m-d H:i:s');
            $prevReminderLocation = trim((string)($event['Location'] ?? ''));

            if (isAdmin()) {
                $sql = "UPDATE Events SET Title = ?, Description = ?, CategoryID = ?, EventDate = ?, Location = ?, MaxParticipants = ?, IsPriority = ?, CoverImagePath = ?
                        WHERE EventID = ?";
                $params = [$title, $description, $categoryId, $eventDate, $location, $maxParticipants, $isPriority, $coverPath, $eventId];
            } else {
                $sql = "UPDATE Events SET Title = ?, Description = ?, CategoryID = ?, EventDate = ?, Location = ?, MaxParticipants = ?, CoverImagePath = ?,
                        ModerationStatus = N'pending', IsPublished = 0, RejectionReason = NULL
                        WHERE EventID = ?";
                $params = [$title, $description, $categoryId, $eventDate, $location, $maxParticipants, $coverPath, $eventId];
            }
            $db->query($sql, $params);
            syncEventPublishedForCatalog($db, $eventId);

            $newNorm = eventDateTime($eventDate)->format('Y-m-d H:i:s');
            if ($prevReminderNorm !== $newNorm || $prevReminderLocation !== trim($location)) {
                $db->query(
                    "UPDATE Registrations SET ReminderSent = 0 WHERE EventID = ? AND Status = N'confirmed'",
                    [$eventId]
                );
            }

            $success = isAdmin() ? 'Изменения сохранены.' : 'Изменения сохранены. Акция снова отправлена на модерацию.';
            $stmt = $db->query("SELECT * FROM Events WHERE EventID = ?", [$eventId]);
            $event = $db->fetchOne($stmt);
    }
}

$eventDateFormatted = '';
if (!empty($event['EventDate'])) {
    $ed = $event['EventDate'];
    if ($ed instanceof DateTime) {
        $eventDateFormatted = $ed->format('Y-m-d\TH:i');
    } elseif (is_object($ed) && method_exists($ed, 'format')) {
        $eventDateFormatted = $ed->format('Y-m-d\TH:i');
    } else {
        $eventDateFormatted = date('Y-m-d\TH:i', strtotime((string)$ed));
    }
}

$pageTitle = 'Редактирование акции';
include '../includes/html_head.php';
include '../includes/site_header.php';
?>
    <main class="container content-page">
        <header class="page-head">
            <h1 class="page-title">Редактировать акцию</h1>
        </header>

        <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="surface-form event-form">
            <div class="form-group">
                <label for="cover_image">Обложка (фото акции)</label>
                <?php
                $editCoverPreview = resolvedPublicFileUrl($event['CoverImagePath'] ?? '');
                $hasCoverInDb = !empty($event['CoverImagePath']);
                if ($editCoverPreview !== ''):
                ?>
                    <p><img src="<?= escape($editCoverPreview) ?>" alt="" style="max-width:280px;border-radius:8px;border:1px solid var(--line)"></p>
                <?php elseif ($hasCoverInDb): ?>
                    <p class="alert alert-warn">В базе указана обложка, но файл на сервере не найден. Загрузите новое изображение, чтобы заменить ссылку.</p>
                <?php endif; ?>
                <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif">
                <p class="muted small">Загрузите новый файл, чтобы заменить обложку.</p>
            </div>
            <div class="form-group">
                <label for="title">Название *</label>
                <input type="text" id="title" name="title" value="<?= escape($event['Title']) ?>" required maxlength="200">
            </div>
            <div class="form-group">
                <label for="category_id">Категория *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">— выберите —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['CategoryID'] ?>" <?= (int)$cat['CategoryID'] === (int)$event['CategoryID'] ? 'selected' : '' ?>>
                            <?= escape($cat['CategoryName']) ?><?= empty($cat['IsActive']) ? ' (снята с публикации)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="event_date">Дата и время начала *</label>
                <input type="datetime-local" id="event_date" name="event_date" value="<?= escape($eventDateFormatted) ?>" required>
            </div>
            <div class="form-group location-field-wrap" data-suggest-url="<?= escape(APP_URL . '/pages/api/location_suggest.php') ?>">
                <label for="location">Место *</label>
                <input type="text" id="location" name="location" value="<?= escape($event['Location']) ?>" required maxlength="300" autocomplete="street-address">
                <input type="hidden" name="location_osm_type" id="location_osm_type" value="">
                <input type="hidden" name="location_osm_id" id="location_osm_id" value="">
                <p class="muted small">Введите адрес и <strong>выберите вариант из подсказок</strong> (OpenStreetMap). При смене места наберите заново и снова выберите из списка.</p>
            </div>
            <div class="form-group">
                <label for="max_participants">Лимит участников</label>
                <input type="number" id="max_participants" name="max_participants" value="<?= escape((string)($event['MaxParticipants'] ?? '')) ?>" min="1">
            </div>
            <?php if (isAdmin()): ?>
                <div class="form-group form-check">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_priority" value="1" <?= !empty($event['IsPriority']) ? 'checked' : '' ?>>
                        Показывать в блоке «Приоритетные акции» на главной
                    </label>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="description">Описание *</label>
                <textarea id="description" name="description" rows="10" required><?= escape($event['Description']) ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Сохранить</button>
                <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/event_details.php?id=<?= $eventId ?>">К акции</a>
            </div>
        </form>
    </main>
<?php
$footExtra = '<script src="' . escape(APP_URL . '/assets/js/location_autocomplete.js') . '" defer></script>';
include '../includes/html_foot.php';
?>
