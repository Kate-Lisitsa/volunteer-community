/*
  DobroHub — донастройка БД под новый код (SQL Server, SSMS).
  
  ВАЖНО:
  1) Имя базы должно совпадать с DB_NAME в includes/config.php (сейчас: VolunteerCommunity).
  2) Выполняйте в SSMS, выбрав нужную базу в выпадающем списке ИЛИ раскомментировав USE ниже.
  3) Если какой-то шаг пишет "столбец уже существует" — этот шаг можно пропустить.
*/

-- Раскомментируйте и подставьте своё имя базы, если не выбрали её вручную в SSMS:
-- USE [VolunteerCommunity];
-- GO

/* --- 1. Таблица Events --- */
IF COL_LENGTH('dbo.Events', 'ModerationStatus') IS NULL
BEGIN
    ALTER TABLE dbo.Events ADD ModerationStatus NVARCHAR(20) NOT NULL
        CONSTRAINT DF_Events_ModerationStatus DEFAULT (N'approved');
END
GO

IF COL_LENGTH('dbo.Events', 'RejectionReason') IS NULL
BEGIN
    ALTER TABLE dbo.Events ADD RejectionReason NVARCHAR(MAX) NULL;
END
GO

IF COL_LENGTH('dbo.Events', 'IsPriority') IS NULL
BEGIN
    ALTER TABLE dbo.Events ADD IsPriority BIT NOT NULL
        CONSTRAINT DF_Events_IsPriority DEFAULT (0);
END
GO

/* --- 2. Таблица Registrations (напоминания по почте) --- */
IF COL_LENGTH('dbo.Registrations', 'ReminderSent') IS NULL
BEGIN
    ALTER TABLE dbo.Registrations ADD ReminderSent BIT NOT NULL
        CONSTRAINT DF_Registrations_ReminderSent DEFAULT (0);
END
GO

/* --- 3. Таблица News --- */
IF OBJECT_ID('dbo.News', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.News (
        NewsID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        Title NVARCHAR(255) NOT NULL,
        Summary NVARCHAR(600) NULL,
        BodyHtml NVARCHAR(MAX) NOT NULL,
        RelatedEventID INT NULL,
        IsPublished BIT NOT NULL CONSTRAINT DF_News_IsPublished DEFAULT (0),
        PublishedAt DATETIME NULL,
        AuthorUserID INT NULL,
        CreatedAt DATETIME NOT NULL CONSTRAINT DF_News_CreatedAt DEFAULT (GETDATE()),
        CONSTRAINT FK_News_Event FOREIGN KEY (RelatedEventID) REFERENCES dbo.Events(EventID),
        CONSTRAINT FK_News_Author FOREIGN KEY (AuthorUserID) REFERENCES dbo.Users(UserID)
    );
END
GO

/* --- 4. Таблица EventMaterials --- */
IF OBJECT_ID('dbo.EventMaterials', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.EventMaterials (
        MaterialID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        EventID INT NOT NULL,
        UserID INT NOT NULL,
        MaterialType NVARCHAR(20) NOT NULL,
        TextContent NVARCHAR(MAX) NULL,
        FilePath NVARCHAR(500) NULL,
        OriginalName NVARCHAR(255) NULL,
        CreatedAt DATETIME NOT NULL CONSTRAINT DF_EventMaterials_CreatedAt DEFAULT (GETDATE()),
        CONSTRAINT FK_EventMaterials_Event FOREIGN KEY (EventID) REFERENCES dbo.Events(EventID) ON DELETE CASCADE,
        CONSTRAINT FK_EventMaterials_User FOREIGN KEY (UserID) REFERENCES dbo.Users(UserID)
    );
END
GO

/* Проверка: должны быть три новых столбца в Events */
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = N'dbo' AND TABLE_NAME = N'Events'
  AND COLUMN_NAME IN (N'ModerationStatus', N'RejectionReason', N'IsPriority')
ORDER BY COLUMN_NAME;

/*
  --- 5. Категория акции может быть NULL (удаление категории без ошибки БД) ---
  Выполните database/schema_category_nullable.sql
*/

/*
  --- 6. Итоги организаторов (текст + файлы для новостей) ---
  Выполните database/schema_event_outcomes.sql
*/

/* --- 7. Мягкое удаление категорий (IsActive) --- */
IF COL_LENGTH('dbo.Categories', 'IsActive') IS NULL
BEGIN
    ALTER TABLE dbo.Categories ADD IsActive BIT NOT NULL
        CONSTRAINT DF_Categories_IsActive DEFAULT (1);
END
GO

UPDATE dbo.Categories SET IsActive = 1 WHERE IsActive IS NULL;
GO

