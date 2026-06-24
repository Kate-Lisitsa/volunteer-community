<?php
/**
 * News.CategoryID — те же категории, что у акций (Categories). Поле необязательное.
 *
 * Запуск: php database/run_schema_news_category.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

function schemaStep(string $msg): void {
    echo $msg . "\n";
}

function schemaColumnExists($db, string $table, string $column): bool {
    $row = $db->fetchOne($db->query(
        'SELECT COL_LENGTH(?, ?) AS col',
        ['dbo.' . $table, $column]
    ));
    return !empty($row['col']);
}

schemaStep('=== DobroHub: категории новостей (общие с акциями) ===');

if (!schemaColumnExists($db, 'News', 'CategoryID')) {
    $db->query('ALTER TABLE dbo.News ADD CategoryID INT NULL');
    schemaStep('+ News.CategoryID');
} else {
    schemaStep('= News.CategoryID уже есть');
}

$fk = $db->fetchOne($db->query(
    "SELECT 1 AS ok FROM sys.foreign_keys
     WHERE name = N'FK_News_Category' AND parent_object_id = OBJECT_ID(N'dbo.News')"
));
if (empty($fk['ok'])) {
    $db->query(
        'ALTER TABLE dbo.News ADD CONSTRAINT FK_News_Category
         FOREIGN KEY (CategoryID) REFERENCES dbo.Categories (CategoryID)'
    );
    schemaStep('+ FK_News_Category');
}

$db->query(
    'UPDATE n SET n.CategoryID = e.CategoryID
     FROM News n
     INNER JOIN Events e ON e.EventID = n.RelatedEventID
     WHERE e.CategoryID IS NOT NULL
       AND (n.CategoryID IS NULL OR n.CategoryID IN (
           SELECT CategoryID FROM Categories
           WHERE CategoryName IN (N\'Отчёты с акций\', N\'Анонсы и объявления\')
       ))'
);

$db->query(
    'UPDATE n SET n.CategoryID = NULL
     FROM News n
     INNER JOIN Categories c ON c.CategoryID = n.CategoryID
     WHERE c.CategoryName IN (N\'Отчёты с акций\', N\'Анонсы и объявления\')
       AND n.RelatedEventID IS NULL'
);
schemaStep('= категории из связанных акций; лишние «редакционные» сняты');

$without = (int)($db->fetchOne($db->query('SELECT COUNT(*) AS c FROM News WHERE CategoryID IS NULL'))['c'] ?? 0);
$with = (int)($db->fetchOne($db->query('SELECT COUNT(*) AS c FROM News WHERE CategoryID IS NOT NULL'))['c'] ?? 0);
schemaStep("Готово. Новостей с категорией: {$with}, без категории: {$without}");
