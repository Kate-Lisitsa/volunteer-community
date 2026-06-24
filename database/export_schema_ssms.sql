/*
  DobroHub — список таблиц, полей и типов из живой БД (SSMS).
  Выполнить для базы VolunteerCommunity.
*/
USE [VolunteerCommunity];
GO

SELECT
    t.TABLE_NAME AS [Таблица],
    c.COLUMN_NAME AS [Поле],
    CASE
        WHEN c.DATA_TYPE IN ('nvarchar', 'varchar', 'nchar', 'char')
            THEN c.DATA_TYPE + '(' +
                CASE WHEN c.CHARACTER_MAXIMUM_LENGTH = -1 THEN 'max'
                     ELSE CAST(c.CHARACTER_MAXIMUM_LENGTH AS VARCHAR(10)) END + ')'
        WHEN c.DATA_TYPE IN ('decimal', 'numeric')
            THEN c.DATA_TYPE + '(' + CAST(c.NUMERIC_PRECISION AS VARCHAR(10))
                + ',' + CAST(c.NUMERIC_SCALE AS VARCHAR(10)) + ')'
        ELSE c.DATA_TYPE
    END AS [Тип],
  CASE c.IS_NULLABLE WHEN 'YES' THEN 'NULL' ELSE 'NOT NULL' END AS [Null],
  CASE WHEN pk.COLUMN_NAME IS NOT NULL THEN 'PK' ELSE '' END AS [Ключ]
FROM INFORMATION_SCHEMA.TABLES t
INNER JOIN INFORMATION_SCHEMA.COLUMNS c
    ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
LEFT JOIN (
    SELECT ku.TABLE_NAME, ku.COLUMN_NAME
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
    INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE ku
        ON tc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
       AND tc.TABLE_SCHEMA = ku.TABLE_SCHEMA
    WHERE tc.TABLE_SCHEMA = N'dbo' AND tc.CONSTRAINT_TYPE = N'PRIMARY KEY'
) pk ON pk.TABLE_NAME = c.TABLE_NAME AND pk.COLUMN_NAME = c.COLUMN_NAME
WHERE t.TABLE_SCHEMA = N'dbo' AND t.TABLE_TYPE = N'BASE TABLE'
ORDER BY t.TABLE_NAME, c.ORDINAL_POSITION;
GO

SELECT
    OBJECT_NAME(fk.parent_object_id) AS [От таблицы],
    COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS [Поле],
    OBJECT_NAME(fk.referenced_object_id) AS [К таблице],
    COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS [Поле родителя],
    fk.name AS [FK]
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
WHERE fk.parent_object_id IN (
    SELECT object_id FROM sys.tables WHERE schema_id = SCHEMA_ID(N'dbo')
)
ORDER BY [От таблицы], fk.name;
GO
