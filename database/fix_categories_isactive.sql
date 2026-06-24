/*
  DobroHub — добавить столбец Categories.IsActive (если его ещё нет).
  В SSMS: выберите базу VolunteerCommunity в списке сверху, откройте этот файл и нажмите «Выполнить».
*/
IF COL_LENGTH('dbo.Categories', 'IsActive') IS NULL
BEGIN
    ALTER TABLE dbo.Categories ADD IsActive BIT NOT NULL
        CONSTRAINT DF_Categories_IsActive DEFAULT (1);
END
GO

UPDATE dbo.Categories SET IsActive = 1 WHERE IsActive IS NULL;
GO

SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = N'dbo' AND TABLE_NAME = N'Categories'
  AND COLUMN_NAME = N'IsActive';
