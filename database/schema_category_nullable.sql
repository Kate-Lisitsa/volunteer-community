/*
  DobroHub — разрешить NULL в Events.CategoryID (удаление категории без удаления акций).

  Выполнение: откройте этот файл в SSMS и нажмите «Выполнить» целиком.
  Не вставляйте второй SELECT внутрь IF NOT EXISTS — в скобках должен быть ОДИН подзапрос.

  Шаги:
    1) Снять внешний ключ с колонки CategoryID (имя ограничения ищется автоматически).
    2) Разрешить NULL.
    3) Вернуть ограничение FK_Events_Categories (если его ещё нет).
*/
USE [VolunteerCommunity];
GO

DECLARE @fkName sysname;

SELECT @fkName = fk.name
FROM sys.foreign_keys AS fk
INNER JOIN sys.foreign_key_columns AS fc
    ON fk.object_id = fc.constraint_object_id
INNER JOIN sys.columns AS c
    ON fc.parent_object_id = c.object_id AND fc.parent_column_id = c.column_id
WHERE fk.parent_object_id = OBJECT_ID(N'dbo.Events')
  AND c.name = N'CategoryID';

DECLARE @sql nvarchar(max);

IF @fkName IS NOT NULL
BEGIN
    SET @sql = N'ALTER TABLE dbo.Events DROP CONSTRAINT ' + QUOTENAME(@fkName) + N';';
    EXEC sys.sp_executesql @sql;
END
GO

/* Одна команда ALTER — не дублируйте */
ALTER TABLE dbo.Events ALTER COLUMN CategoryID INT NULL;
GO

/* Внутри IF NOT EXISTS — только один SELECT ... FROM ... WHERE ... */
IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID(N'dbo.Events')
      AND name = N'FK_Events_Categories'
)
BEGIN
    ALTER TABLE dbo.Events
    ADD CONSTRAINT FK_Events_Categories
        FOREIGN KEY (CategoryID) REFERENCES dbo.Categories (CategoryID);
END
GO
