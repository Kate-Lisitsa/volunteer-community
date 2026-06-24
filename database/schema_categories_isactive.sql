/*
  DobroHub — мягкое удаление категорий (IsActive).
  Категория не удаляется из БД: акции сохраняют CategoryID и название для админа.

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
