<?php
/**
 * Выгрузка актуальной схемы БД (типы полей, PK, FK) из живой базы.
 *
 * Запуск из корня проекта:
 *   php database/run_export_schema.php
 *
 * Создаёт в database/:
 *   schema_export.dbml   — для https://dbdiagram.io (Импорт / вставить код)
 *   schema_export.txt  — текстовая схема для пояснительной записки
 *   schema_export.sql  — фрагмент CREATE TABLE (документация)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';

$db = Database::getInstance();

$ruNames = [
    'ActivityLog' => 'Журнал действий',
    'Users' => 'Пользователи',
    'Categories' => 'Категории',
    'Events' => 'Акции',
    'Registrations' => 'Регистрации',
    'News' => 'Новости',
    'NewsImages' => 'Изображения новостей',
    'EventMaterials' => 'Материалы акции',
    'EventOutcomes' => 'Итоги акции',
    'EventOutcomeFiles' => 'Файлы итогов акции',
];

function formatColumnType(array $col): string
{
    $type = strtolower((string)$col['DATA_TYPE']);
    $len = $col['CHARACTER_MAXIMUM_LENGTH'];
    $prec = $col['NUMERIC_PRECISION'];
    $scale = $col['NUMERIC_SCALE'];

    if (in_array($type, ['nvarchar', 'varchar', 'nchar', 'char'], true)) {
        if ($len === null) {
            return $type;
        }
        if ((int)$len === -1) {
            return $type . '(max)';
        }
        return $type . '(' . (int)$len . ')';
    }
    if ($type === 'decimal' || $type === 'numeric') {
        return $type . '(' . (int)$prec . ',' . (int)$scale . ')';
    }
    if ($type === 'datetime2' && $scale !== null) {
        return 'datetime2(' . (int)$scale . ')';
    }
    return $type;
}

function dbmlType(string $sqlType): string
{
    // dbdiagram.io: varchar/int/datetime/boolean
    if (preg_match('/^(?:nvarchar|nchar|varchar|char)\((\d+|max)\)$/i', $sqlType, $m)) {
        return 'varchar' . ($m[1] === 'max' ? '' : '(' . $m[1] . ')');
    }
    if (stripos($sqlType, 'nvarchar') === 0 || stripos($sqlType, 'nchar') === 0) {
        return 'varchar';
    }
    if ($sqlType === 'bit') {
        return 'boolean';
    }
    if (strpos($sqlType, 'datetime') !== false) {
        return 'datetime';
    }
    if ($sqlType === 'int' || $sqlType === 'bigint' || $sqlType === 'smallint') {
        return 'integer';
    }
    return $sqlType;
}

$tables = $db->fetchAll($db->query(
    "SELECT TABLE_NAME
     FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = N'dbo' AND TABLE_TYPE = N'BASE TABLE'
     ORDER BY TABLE_NAME"
));

$columns = $db->fetchAll($db->query(
    "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH,
            NUMERIC_PRECISION, NUMERIC_SCALE, IS_NULLABLE, COLUMN_DEFAULT
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = N'dbo'
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
));

$pkRows = $db->fetchAll($db->query(
    "SELECT tc.TABLE_NAME, kcu.COLUMN_NAME
     FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
     INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
        ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
       AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
     WHERE tc.TABLE_SCHEMA = N'dbo' AND tc.CONSTRAINT_TYPE = N'PRIMARY KEY'
     ORDER BY tc.TABLE_NAME, kcu.ORDINAL_POSITION"
));

$pks = [];
foreach ($pkRows as $row) {
    $pks[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
}

$fkRows = $db->fetchAll($db->query(
    "SELECT
        OBJECT_NAME(fk.parent_object_id) AS child_table,
        COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS child_column,
        OBJECT_NAME(fk.referenced_object_id) AS parent_table,
        COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS parent_column,
        fk.name AS fk_name
     FROM sys.foreign_keys fk
     INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
     WHERE fk.parent_object_id IN (SELECT object_id FROM sys.tables WHERE schema_id = SCHEMA_ID(N'dbo'))
     ORDER BY child_table, fk.name, fkc.constraint_column_id"
));

$colsByTable = [];
foreach ($columns as $col) {
    $colsByTable[$col['TABLE_NAME']][] = $col;
}

$outDir = __DIR__;
$dbml = "// DobroHub — схема из базы " . DB_NAME . ' (' . date('Y-m-d H:i') . ")\n";
$dbml .= "// Сайт для диаграммы: https://dbdiagram.io → New diagram → Import → DBML\n\n";

$txt = "Схема БД " . DB_NAME . "\n";
$txt .= 'Дата выгрузки: ' . date('Y-m-d H:i') . "\n";
$txt .= str_repeat('=', 72) . "\n\n";

$sqlDoc = "-- DobroHub — документация схемы из базы " . DB_NAME . "\n";
$sqlDoc .= '-- ' . date('Y-m-d H:i') . "\n\n";

foreach ($tables as $t) {
    $tableName = $t['TABLE_NAME'];
    $ru = $ruNames[$tableName] ?? $tableName;
    $tableCols = $colsByTable[$tableName] ?? [];

    $dbml .= "Table {$tableName} [note: '{$ru}'] {\n";
    $txt .= "Таблица: {$tableName} ({$ru})\n";
    $txt .= str_repeat('-', 72) . "\n";
    $sqlDoc .= "/* {$ru} */\nCREATE TABLE dbo.{$tableName} (\n";

    $sqlLines = [];
    foreach ($tableCols as $col) {
        $name = $col['COLUMN_NAME'];
        $sqlType = formatColumnType($col);
        $nullable = ($col['IS_NULLABLE'] === 'YES');
        $isPk = in_array($name, $pks[$tableName] ?? [], true);

        $attrs = [];
        if ($isPk) {
            $attrs[] = 'pk';
        }
        if (!$nullable && !$isPk) {
            $attrs[] = 'not null';
        }
        $attrStr = empty($attrs) ? '' : ' [' . implode(', ', $attrs) . ']';

        $dbml .= "  {$name} " . dbmlType($sqlType) . $attrStr . "\n";

        $nullRu = $nullable ? 'NULL' : 'NOT NULL';
        $pkMark = $isPk ? ' PK' : '';
        $txt .= sprintf("  %-22s %-18s %s%s\n", $name, $sqlType, $nullRu, $pkMark);

        $sqlLines[] = '    ' . $name . ' ' . strtoupper($sqlType) . ($nullable ? ' NULL' : ' NOT NULL');
    }

    $dbml .= "}\n\n";
    $sqlDoc .= implode(",\n", $sqlLines) . "\n);\n\n";
    $txt .= "\n";
}

$dbml .= "\n";
foreach ($fkRows as $fk) {
    $child = $fk['child_table'];
    $parent = $fk['parent_table'];
    $dbml .= "Ref: {$child}.{$fk['child_column']} > {$parent}.{$fk['parent_column']}\n";
}

$txt .= "Связи (внешние ключи)\n";
$txt .= str_repeat('-', 72) . "\n";
foreach ($fkRows as $fk) {
    $txt .= sprintf(
        "  %s.%s → %s.%s (%s)\n",
        $fk['child_table'],
        $fk['child_column'],
        $fk['parent_table'],
        $fk['parent_column'],
        $fk['fk_name']
    );
}

$files = [
    'schema_export.dbml' => $dbml,
    'schema_export.txt' => $txt,
    'schema_export.sql' => $sqlDoc,
];

foreach ($files as $name => $content) {
    $path = $outDir . DIRECTORY_SEPARATOR . $name;
    file_put_contents($path, $content);
    echo "OK: {$path}\n";
}

echo "\nДиаграмма онлайн:\n";
echo "  1) Откройте https://dbdiagram.io\n";
echo "  2) New diagram → Import → DBML (или вставьте файл schema_export.dbml)\n";
echo "\nЛокально (ваш draw.io):\n";
echo "  https://app.diagrams.net → открыть database/erd_dobrohub.drawio\n";
echo "\nSSMS:\n";
echo "  ПКМ на базу → Database Diagrams → обновить диаграмму после ALTER COLUMN\n";
