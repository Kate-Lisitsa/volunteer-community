<?php
// includes/functions.php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function getUserRole() {
    if (!isLoggedIn()) return 'guest';
    if (isAdmin()) return 'admin';
    return 'user';
}

function getUserRoleName() {
    $role = getUserRole();
    switch ($role) {
        case 'admin': return 'Администратор';
        case 'user': return 'Авторизованный пользователь';
        default: return 'Гость';
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Проверка части ФИО. Возвращает текст ошибки или null.
 */
function validatePersonNamePart(string $value, bool $required, string $fieldLabel): ?string {
    $value = trim($value);
    if ($value === '') {
        return $required ? "{$fieldLabel}: укажите значение." : null;
    }
    $len = mb_strlen($value, 'UTF-8');
    if ($len < 2) {
        return "{$fieldLabel}: не короче 2 символов.";
    }
    if ($len > 60) {
        return "{$fieldLabel}: не длиннее 60 символов.";
    }
    if (!preg_match('/^[\p{L}\-\s]+$/u', $value)) {
        return "{$fieldLabel}: допускаются буквы, пробел и дефис.";
    }
    return null;
}

/** Сборка одной строки FullName для БД из частей ФИО. */
function buildFullNameFromParts(?string $lastName, ?string $firstName, ?string $patronymic): string {
    $parts = [];
    foreach ([trim((string)$lastName), trim((string)$firstName), trim((string)$patronymic)] as $t) {
        if ($t !== '') {
            $parts[] = $t;
        }
    }
    return implode(' ', $parts);
}

/**
 * Разбор сохранённого FullName для формы (первое слово — фамилия, второе — имя, остальное — отчество).
 */
function parseFullNameParts(string $storedFullName): array {
    $storedFullName = trim(preg_replace('/\s+/u', ' ', $storedFullName));
    if ($storedFullName === '') {
        return ['', '', ''];
    }
    $tokens = preg_split('/\s+/u', $storedFullName, -1, PREG_SPLIT_NO_EMPTY);
    $last = $tokens[0] ?? '';
    $first = $tokens[1] ?? '';
    $middle = count($tokens) > 2 ? implode(' ', array_slice($tokens, 2)) : '';
    return [$last, $first, $middle];
}

/**
 * Приведение значения из БД или формы к DateTime.
 */
function toDateTime($value): ?DateTime {
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof DateTime) {
        return $value;
    }
    if ($value instanceof DateTimeInterface) {
        return new DateTime($value->format('Y-m-d H:i:s'));
    }
    if (is_object($value) && method_exists($value, 'format')) {
        return new DateTime($value->format('Y-m-d H:i:s'));
    }
    $s = trim((string)$value);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return new DateTime($s . ' 00:00:00');
    }
    $dt = date_create($s);
    return $dt instanceof DateTime ? $dt : null;
}

function formatDate($date) {
    $dt = toDateTime($date);
    return $dt ? $dt->format('d.m.Y H:i') : '';
}

/** Только дата (для списков новостей и т.п.) */
function formatDateOnly($date) {
    $dt = toDateTime($date);
    return $dt ? $dt->format('d.m.Y') : '';
}

/** Только время (для экспорта отчётов и т.п.) */
function formatTimeOnly($date) {
    $dt = toDateTime($date);
    return $dt ? $dt->format('H:i') : '';
}

/** Значение поля type="date" (Y-m-d) → д.м.г для подписей и экспорта */
function formatCalendarDateRu(string $isoDate): string {
    return formatDateOnly($isoDate);
}

/** Период отчёта в русском формате: 01.05.2026 — 28.05.2026 */
function formatReportPeriodRu(string $startIso, string $endIso): string {
    return formatCalendarDateRu($startIso) . ' — ' . formatCalendarDateRu($endIso);
}

/** Краткое описание из HTML (для превью в списке новостей) */
function excerptFromHtml($html, $maxLen = 200) {
    if ($html === null || $html === '') {
        return '';
    }
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$html)));
    if (mb_strlen($plain, 'UTF-8') <= $maxLen) {
        return $plain;
    }
    return rtrim(mb_substr($plain, 0, $maxLen, 'UTF-8'), " \t\n\r\0\x0B,.;") . '…';
}

/**
 * Проверка поля «Место» при создании/редактировании акции.
 * Без внешнего геокодера — эвристика «узнаваемое место», как для модерации живых акций.
 * Возвращает строку ошибки или пустую строку, если ок.
 */
function validateEventLocation($location) {
    $s = trim((string)$location);
    if ($s === '') {
        return 'Укажите место проведения.';
    }

    $len = mb_strlen($s, 'UTF-8');
    if ($len < 12) {
        return 'Опишите место подробнее: не короче 12 символов — город и ориентир (улица, дом, организация, метро…).';
    }
    if ($len > 300) {
        return 'Поле «Место» не длиннее 300 символов.';
    }

    $compact = preg_replace('/\s+/u', '', $s);
    if (!preg_match('/\p{L}{4,}/u', $compact)) {
        return 'Добавьте буквенное название города или площадки (нельзя только цифры и знаки).';
    }

    $alnumCount = preg_match_all('/[\p{L}\d]/u', $s);
    if ($alnumCount < 8) {
        return 'Адрес слишком короткий по смыслу — укажите населённый пункт и как добраться.';
    }

    $lower = mb_strtolower($s, 'UTF-8');
    $lazy = ['адрес', 'здесь', 'тут', 'место', 'потом', 'напишу позже', 'нет адреса', 'укажу потом', 'test', 'тест', 'xxx', 'asdf'];
    foreach ($lazy as $w) {
        if ($lower === $w || preg_match('/^' . preg_quote($w, '/') . '[\s.!]*$/u', $lower)) {
            return 'Укажите реальный ориентир вместо общих слов («адрес», «тут» и т.п.).';
        }
    }

    $parts = preg_split('/[\s,;]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    $sigWords = 0;
    foreach ($parts as $p) {
        if (mb_strlen($p, 'UTF-8') >= 2 && preg_match('/\p{L}/u', $p)) {
            $sigWords++;
        }
    }
    if ($sigWords < 2) {
        return 'Добавьте минимум два ориентира (например: город и улица, или район и название парка).';
    }

    $hasDigit = (bool)preg_match('/\d/u', $s);
    $streetLike = (bool)preg_match(
        '/(ул\.?\s|улица|просп\.?|проспект|пр-т\b|пер\.?|переулок|бульвар|б-р\b|наб\.?|набережн|шоссе|тракт|площадь|пл\.?\b|микрорайон|м-н\b|д\.?\s*\d|дом\s*\d|здание|корп\.?|стр\.?|каб\.?|оф\.?\s*\d|ТЦ\b|тц\s|«[^»]{2,}»|№\s*\d)/iu',
        $s
    );
    $landmark = (bool)preg_match(
        '/(метро|ст\.?\s*м\.?|ж\/д|жд\b|вокзал|остановк|ост\.|платформ|сквер|парк\b|набережн|ДК\b|СШ\b|школа\s*№|гимназ|универ|областн|район|г\.|город|обл\.|р-н\b|деревн|агрогородок|посёлок|поселок|с\/с\b)/iu',
        $s
    );

    if (!($hasDigit || $streetLike || $landmark)) {
        return 'Добавьте номер дома, «ул./просп.», станцию метро, парк, остановку или другое узнаваемое место.';
    }

    if (preg_match('/^\d+[.,\s]*\d*$/u', $s)) {
        return 'Только цифры не подходят — напишите адрес словами (город, улица…).';
    }

    return '';
}

/** Условие для публичного каталога: опубликовано и прошла модерацию */
function sqlPublishedEvents($alias = 'e') {
    return "({$alias}.IsPublished = 1 AND {$alias}.ModerationStatus = N'approved')";
}

/** Акция ещё не началась / идёт (дата проведения не в прошлом) */
function sqlUpcomingEvents($alias = 'e') {
    return "({$alias}.EventDate >= GETDATE())";
}

/** Акция уже прошла по дате проведения */
function sqlPastEvents($alias = 'e') {
    return "({$alias}.EventDate < GETDATE())";
}

/** Категория акции доступна для публичного каталога */
function categoriesHaveIsActiveColumn($db = null): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if ($db === null) {
        $db = Database::getInstance();
    }
    $row = $db->fetchOne($db->query("SELECT COL_LENGTH('dbo.Categories', 'IsActive') AS colLen"));
    $cached = !empty($row['colLen']);
    return $cached;
}

function sqlActiveCategory($catAlias = 'c') {
    if (!categoriesHaveIsActiveColumn()) {
        return '1=1';
    }
    return "({$catAlias}.IsActive = 1)";
}

/** В SELECT: активность категории (если миграции нет — всегда активна) */
function sqlCategoryIsActiveSelect($catAlias = 'c', $alias = 'CategoryIsActive') {
    if (!categoriesHaveIsActiveColumn()) {
        return "CAST(1 AS BIT) AS {$alias}";
    }
    return "{$catAlias}.IsActive AS {$alias}";
}

/** Список категорий для форм создания/редактирования акции */
function fetchSelectableCategories($db, int $includeCategoryId = 0): array {
    if (categoriesHaveIsActiveColumn($db)) {
        if ($includeCategoryId > 0) {
            return $db->fetchAll($db->query(
                "SELECT CategoryID, CategoryName, IsActive FROM Categories
                 WHERE IsActive = 1 OR CategoryID = ? ORDER BY CategoryName",
                [$includeCategoryId]
            ));
        }
        return $db->fetchAll($db->query(
            "SELECT CategoryID, CategoryName, IsActive FROM Categories WHERE IsActive = 1 ORDER BY CategoryName"
        ));
    }
    return $db->fetchAll($db->query(
        "SELECT CategoryID, CategoryName, CAST(1 AS BIT) AS IsActive FROM Categories ORDER BY CategoryName"
    ));
}

function newsHasCategoryColumn($db = null, bool $refresh = false): bool {
    static $cached = null;
    if ($refresh) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    if ($db === null) {
        $db = Database::getInstance();
    }
    $row = $db->fetchOne($db->query("SELECT COL_LENGTH('dbo.News', 'CategoryID') AS colLen"));
    $cached = !empty($row['colLen']);
    return $cached;
}

/**
 * Добавляет News.CategoryID при первом обращении (если миграция ещё не запускалась).
 */
function ensureNewsCategorySchema($db = null): bool {
    static $done = false;
    if ($done) {
        return newsHasCategoryColumn($db);
    }
    if ($db === null) {
        $db = Database::getInstance();
    }
    if (newsHasCategoryColumn($db)) {
        $done = true;
        return true;
    }

    if ($db->tryQuery('ALTER TABLE dbo.News ADD CategoryID INT NULL') === false) {
        newsHasCategoryColumn($db, true);
        $done = newsHasCategoryColumn($db);
        return $done;
    }

    $fk = $db->fetchOne($db->query(
        "SELECT 1 AS ok FROM sys.foreign_keys
         WHERE name = N'FK_News_Category' AND parent_object_id = OBJECT_ID(N'dbo.News')"
    ));
    if (empty($fk['ok'])) {
        $db->tryQuery(
            'ALTER TABLE dbo.News ADD CONSTRAINT FK_News_Category
             FOREIGN KEY (CategoryID) REFERENCES dbo.Categories (CategoryID)'
        );
    }

    $db->tryQuery(
        'UPDATE n SET n.CategoryID = e.CategoryID
         FROM News n
         INNER JOIN Events e ON e.EventID = n.RelatedEventID
         WHERE n.CategoryID IS NULL AND e.CategoryID IS NOT NULL'
    );

    newsHasCategoryColumn($db, true);
    $done = newsHasCategoryColumn($db);
    return $done;
}

/** Категории для формы новости (активные + текущая при правке) */
function fetchNewsCategoryOptions($db, int $includeCategoryId = 0): array {
    return fetchSelectableCategories($db, $includeCategoryId);
}

/** Категории, в которых есть опубликованные новости (для фильтра на сайте) */
function fetchNewsFilterCategories($db): array {
    if (!newsHasCategoryColumn($db)) {
        return [];
    }
    return $db->fetchAll($db->query(
        'SELECT DISTINCT c.CategoryID, c.CategoryName
         FROM Categories c
         INNER JOIN News n ON n.CategoryID = c.CategoryID AND n.IsPublished = 1
         ORDER BY c.CategoryName'
    ));
}

/** Подсказка категории из связанной акции (0 — не подставлять) */
function suggestNewsCategoryFromEvent($db, int $relatedEventId): int {
    if ($relatedEventId <= 0) {
        return 0;
    }
    $row = $db->fetchOne($db->query('SELECT CategoryID FROM Events WHERE EventID = ?', [$relatedEventId]));
    return (int)($row['CategoryID'] ?? 0);
}

/** SQL: готова к показу в каталоге (без проверки даты; нужен JOIN Categories AS c) */
function sqlEventCatalogEligibility(string $e = 'e', string $c = 'c'): string {
    $parts = [
        sqlPublishedEvents($e),
        "({$e}.CategoryID IS NOT NULL AND {$e}.CategoryID > 0)",
        "{$c}.CategoryID IS NOT NULL",
    ];
    if (categoriesHaveIsActiveColumn()) {
        $parts[] = "ISNULL({$c}.IsActive, 0) = 1";
    }
    return '(' . implode(' AND ', $parts) . ')';
}

/** SQL: акция видна в публичном каталоге (нужен JOIN Categories AS c) */
function sqlEventInPublicCatalog(string $e = 'e', string $c = 'c'): string {
    return '(' . sqlEventCatalogEligibility($e, $c) . ' AND ' . sqlUpcomingEvents($e) . ')';
}

/** SQL: вне публичного каталога для админки «Скрытые» */
function sqlAdminHiddenFromCatalog(string $e = 'e', string $c = 'c'): string {
    $moderationHidden = "{$e}.ModerationStatus IN (N'pending', N'rejected')";
    $noCategory = "({$e}.CategoryID IS NULL OR {$e}.CategoryID = 0)";
    $invalidCategory = "({$e}.CategoryID IS NOT NULL AND {$e}.CategoryID > 0 AND {$c}.CategoryID IS NULL)";
    if (categoriesHaveIsActiveColumn()) {
        $invalidCategory = "({$e}.CategoryID IS NOT NULL AND {$e}.CategoryID > 0 AND ({$c}.CategoryID IS NULL OR ISNULL({$c}.IsActive, 0) <> 1))";
    }
    $categoryHidden = "({$noCategory} OR {$invalidCategory})";
    $upcomingNotInCatalog = '(' . sqlUpcomingEvents($e) . ' AND NOT ' . sqlEventCatalogEligibility($e, $c) . ')';

    return "({$moderationHidden}) OR ({$categoryHidden}) OR ({$upcomingNotInCatalog})";
}

/** SQL: предстоящие в публичном каталоге */
function sqlAdminUpcomingInCatalog(string $e = 'e', string $c = 'c'): string {
    return sqlEventInPublicCatalog($e, $c);
}

/** Ссылка на вкладку списка акций в админке (без «залипших» фильтров) */
function adminEventsScopeUrl(string $scope): string {
    return APP_URL . '/pages/admin/events.php?scope=' . rawurlencode($scope);
}

/** Проверка выбранной категории для форм акции и новости */
function validateSelectableCategoryId($db, $categoryId, string $fieldLabel = 'Категория'): ?string {
    $id = (int)$categoryId;
    if ($id <= 0) {
        return "{$fieldLabel}: укажите значение.";
    }
    foreach (fetchSelectableCategories($db, $id) as $row) {
        if ((int)$row['CategoryID'] === $id) {
            return null;
        }
    }
    return "{$fieldLabel}: выберите действующую категорию из списка.";
}

/** Акция отображается в публичном каталоге */
function eventIsInPublicCatalog(array $event): bool {
    if (empty($event['IsPublished']) || ($event['ModerationStatus'] ?? 'approved') !== 'approved') {
        return false;
    }
    if (isEventPast($event['EventDate'] ?? null)) {
        return false;
    }
    if (empty($event['CategoryID'])) {
        return false;
    }
    if (isset($event['CategoryIsActive']) && (int)$event['CategoryIsActive'] !== 1) {
        return false;
    }
    return true;
}

/** Пояснения для организатора и админа: почему акции нет в каталоге */
function eventCatalogVisibilityNotes(array $event): array {
    $notes = [];
    $mod = $event['ModerationStatus'] ?? 'approved';

    if ($mod === 'pending') {
        $notes[] = 'Акция на модерации и не видна гостям.';
    } elseif ($mod === 'rejected') {
        $reason = trim((string)($event['RejectionReason'] ?? ''));
        $notes[] = 'Модерация отклонена' . ($reason !== '' ? ': ' . $reason : '.');
    }

    if (empty($event['CategoryID'])) {
        $notes[] = 'Категория была удалена ранее — укажите новую, чтобы вернуть акцию в каталог.';
    } elseif (isset($event['CategoryIsActive']) && (int)$event['CategoryIsActive'] === 0) {
        $name = trim((string)($event['CategoryName'] ?? ''));
        $notes[] = 'Категория «' . ($name !== '' ? $name : 'без названия') . '» больше не публикуется. Верните её в разделе «Категории» или смените категорию акции.';
    }

    if (empty($event['IsPublished']) && $mod === 'approved') {
        $catProblem = empty($event['CategoryID'])
            || (isset($event['CategoryIsActive']) && (int)$event['CategoryIsActive'] === 0);
        if (!$catProblem) {
            $notes[] = 'Акция скрыта из каталога после снятия категории. Сохраните акцию ещё раз — она вернётся в каталог автоматически.';
        }
    }

    if (isEventPast($event['EventDate'] ?? null) && $mod === 'approved') {
        $notes[] = 'Дата проведения прошла — акция в архиве и не показывается в каталоге.';
    }

    return $notes;
}

/** Организатор может отправлять итоги администратору (только одобренные акции). */
function eventAllowsOrganizerOutcomes(array $event): bool {
    return ($event['ModerationStatus'] ?? 'approved') === 'approved';
}

/** Бейджи статуса акции в админ-списке */
function eventAdminStatusPills(array $event): array {
    $pills = [];
    $mod = $event['ModerationStatus'] ?? 'approved';

    $pills[] = ['class' => 'status-pill--' . $mod, 'text' => moderationLabel($mod)];

    if (isEventPast($event['EventDate'] ?? null)) {
        $pills[] = ['class' => '', 'text' => 'архив'];
    }
    if (eventIsInPublicCatalog($event)) {
        $pills[] = ['class' => 'status-pill--ok', 'text' => 'в каталоге'];
    } elseif (!isEventPast($event['EventDate'] ?? null)) {
        $pills[] = ['class' => 'status-pill--warn', 'text' => 'не в каталоге'];
    }
    if (empty($event['CategoryID'])) {
        $pills[] = ['class' => 'status-pill--warn', 'text' => 'нет категории'];
    } elseif (isset($event['CategoryIsActive']) && (int)$event['CategoryIsActive'] === 0) {
        $name = trim((string)($event['CategoryName'] ?? ''));
        $pills[] = ['class' => 'status-pill--warn', 'text' => 'категория снята' . ($name !== '' ? ': ' . $name : '')];
    }

    return $pills;
}

/** Можно ли опубликовать акцию (есть активная категория) */
function eventHasPublishableCategory($db, int $eventId): bool {
    if (!categoriesHaveIsActiveColumn($db)) {
        $row = $db->fetchOne($db->query("SELECT CategoryID FROM Events WHERE EventID = ?", [$eventId]));
        return $row && !empty($row['CategoryID']);
    }
    $row = $db->fetchOne($db->query(
        "SELECT e.CategoryID, c.IsActive AS CategoryIsActive
         FROM Events e LEFT JOIN Categories c ON e.CategoryID = c.CategoryID
         WHERE e.EventID = ?",
        [$eventId]
    ));
    return $row && !empty($row['CategoryID']) && (int)($row['CategoryIsActive'] ?? 0) === 1;
}

/**
 * Возвращает одобренную акцию в каталог, если назначена активная категория.
 * Используется после смены категории (при снятии категории IsPublished сбрасывается в 0).
 */
function syncEventPublishedForCatalog($db, int $eventId): bool {
    if ($eventId <= 0) {
        return false;
    }
    $row = $db->fetchOne($db->query(
        'SELECT ModerationStatus, IsPublished FROM Events WHERE EventID = ?',
        [$eventId]
    ));
    if (!$row || ($row['ModerationStatus'] ?? '') !== 'approved') {
        return !empty($row['IsPublished']);
    }
    if (!eventHasPublishableCategory($db, $eventId)) {
        return !empty($row['IsPublished']);
    }
    if (empty($row['IsPublished'])) {
        $db->query('UPDATE Events SET IsPublished = 1 WHERE EventID = ?', [$eventId]);
        return true;
    }
    return true;
}

/** Одобренные акции с активной категорией — снова в каталоге (после возврата категории). */
function republishApprovedEventsForCategory($db, int $categoryId): int {
    if ($categoryId <= 0) {
        return 0;
    }
    $cnt = (int)($db->fetchOne($db->query(
        'SELECT COUNT(*) AS c FROM Events
         WHERE CategoryID = ? AND ModerationStatus = N\'approved\' AND IsPublished = 0',
        [$categoryId]
    ))['c'] ?? 0);
    if ($cnt > 0) {
        $db->query(
            'UPDATE Events SET IsPublished = 1
             WHERE CategoryID = ? AND ModerationStatus = N\'approved\' AND IsPublished = 0',
            [$categoryId]
        );
    }
    return $cnt;
}

function isEventPast($eventDate): bool {
    return eventDateTime($eventDate) < new DateTime('now');
}

function moderationLabel($status) {
    switch ($status) {
        case 'pending': return 'На модерации';
        case 'rejected': return 'Отклонено';
        case 'approved': return 'Опубликовано';
        default: return (string)$status;
    }
}

/** Подпись типа записи в журнале ActivityLog для личного кабинета */
function activityActionTypeLabel($actionType) {
    switch ($actionType) {
        case 'create_event':
            return 'Создание акции';
        case 'register':
            return 'Запись на акцию';
        case 'cancel_registration':
            return 'Отмена записи';
        default:
            return (string)$actionType;
    }
}

/** Дата/время события как DateTime для сравнения */
function eventDateTime($eventDate) {
    $dt = toDateTime($eventDate);
    if ($dt) {
        return $dt;
    }
    return new DateTime((string)$eventDate);
}

/** URL для файла из assets/uploads/... */
function publicAssetUrl($relativePath) {
    if (empty($relativePath)) {
        return '';
    }
    return APP_URL . '/' . str_replace('\\', '/', ltrim($relativePath, '/'));
}

/**
 * Подзапрос: путь к первой картинке галереи новости (для списков и главной).
 * $alias — алиас таблицы News в запросе, например «n».
 */
function newsSqlFirstImagePath($alias = 'n') {
    $a = preg_replace('/[^a-z0-9_]/i', '', (string)$alias);
    if ($a === '') {
        $a = 'n';
    }
    return "(SELECT TOP 1 ni.FilePath FROM NewsImages ni WHERE ni.NewsID = {$a}.NewsID ORDER BY ni.SortOrder, ni.ImageID)";
}

/**
 * URL к файлу из проекта (обложка, фото новости и т.д.) — только если файл есть на диске.
 */
function resolvedPublicFileUrl($relativePath) {
    if ($relativePath === null || $relativePath === '') {
        return '';
    }
    $norm = str_replace('\\', '/', ltrim(trim((string)$relativePath), '/'));
    if ($norm === '' || strpos($norm, '..') !== false) {
        return '';
    }
    $full = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
    if (!is_file($full)) {
        return '';
    }
    return publicAssetUrl($norm);
}

/** Изображения новостей: то же, что resolvedPublicFileUrl */
function newsImagePublicUrl($relativePath) {
    return resolvedPublicFileUrl($relativePath);
}

/**
 * Минимальная очистка HTML из редактора новостей (без полноценного HTML Purifier).
 */
function newsBodyHtmlSafe($html) {
    if ($html === null || $html === '') {
        return '';
    }
    $s = (string)$html;
    $s = preg_replace('#<\s*script\b[^>]*>.*?</\s*script\s*>#is', '', $s);
    $s = preg_replace('#<\s*iframe\b[^>]*>.*?</\s*iframe\s*>#is', '', $s);
    $s = preg_replace('#\s(?:on\w+|javascript:)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $s);
    return $s;
}

/** Сохранить загруженное изображение (JPEG/PNG/WebP/GIF), вернуть относительный путь или null */
function saveUploadedImage(array $file, $subfolder, $namePrefix) {
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }
    $safeFolder = preg_replace('/[^a-z0-9_-]/i', '', (string)$subfolder);
    if ($safeFolder === '') {
        return null;
    }
    $base = APP_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $safeFolder;
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }
    $name = preg_replace('/[^a-z0-9_-]/i', '', (string)$namePrefix) . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $dest = $base . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return 'assets/uploads/' . $safeFolder . '/' . $name;
}

/** Сообщение об ошибке загрузки файла (PHP upload error code). */
function uploadErrorMessage(int $code, int $maxBytes = 41943040): string {
    $maxMb = (int)round($maxBytes / (1024 * 1024));
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Файл слишком большой (макс. {$maxMb} МБ).";
        case UPLOAD_ERR_PARTIAL:
            return 'Файл загружен не полностью. Повторите отправку.';
        case UPLOAD_ERR_NO_FILE:
            return 'Файл не выбран.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'Сервер не смог принять файл. Обратитесь к администратору.';
        default:
            return 'Ошибка загрузки файла.';
    }
}

/**
 * Проверка вложения для итогов акции (фото или видео).
 *
 * @return array{error: ?string, ext?: string, type?: string}
 */
function validateEventOutcomeFile(array $file, int $maxBytes = 41943040): array {
    $allowedImg = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $allowedVid = ['video/mp4' => 'mp4'];

    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['error' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['error' => uploadErrorMessage((int)$file['error'], $maxBytes)];
    }
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['error' => 'Некорректная загрузка файла.'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        $maxMb = (int)round($maxBytes / (1024 * 1024));
        return ['error' => "Файл слишком большой (макс. {$maxMb} МБ)."];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if (isset($allowedImg[$mime])) {
        return ['error' => null, 'ext' => $allowedImg[$mime], 'type' => 'image'];
    }
    if (isset($allowedVid[$mime])) {
        return ['error' => null, 'ext' => $allowedVid[$mime], 'type' => 'video'];
    }

    return ['error' => 'Допустимы фото (JPEG, PNG, WebP, GIF) или видео MP4.'];
}

function deleteStoredPublicFile($relativePath) {
    if (empty($relativePath)) {
        return;
    }
    $norm = str_replace('\\', '/', ltrim($relativePath, '/'));
    if (stripos($norm, 'assets/uploads/') !== 0) {
        return;
    }
    $full = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
    if (is_file($full)) {
        @unlink($full);
    }
}

/** Несколько файлов: input name="field[]" multiple */
function saveUploadedImagesMultiple($filesField, $subfolder, $namePrefix) {
    $out = [];
    if (empty($filesField['name']) || !is_array($filesField['name'])) {
        return $out;
    }
    $n = count($filesField['name']);
    for ($i = 0; $i < $n; $i++) {
        if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $one = [
            'name' => $filesField['name'][$i],
            'type' => $filesField['type'][$i],
            'tmp_name' => $filesField['tmp_name'][$i],
            'error' => $filesField['error'][$i],
            'size' => $filesField['size'][$i],
        ];
        $path = saveUploadedImage($one, $subfolder, $namePrefix . '_' . $i);
        if ($path) {
            $out[] = $path;
        }
    }
    return $out;
}

require_once __DIR__ . '/admin_list.php';
?>