/*
  ОТКАТ упрощения схемы (только структура таблиц и FK).
  Данные из удалённых таблиц без резервной копии не восстановить.
  Выполнить в SSMS для базы VolunteerCommunity.
*/
USE [VolunteerCommunity];
GO

/* --- NewsImages --- */
IF OBJECT_ID(N'dbo.NewsImages', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.NewsImages (
        ImageID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        NewsID INT NOT NULL,
        FilePath NVARCHAR(500) NOT NULL,
        SortOrder INT NOT NULL CONSTRAINT DF_NewsImages_Sort DEFAULT (0),
        CONSTRAINT FK_NewsImages_News FOREIGN KEY (NewsID) REFERENCES dbo.News(NewsID) ON DELETE CASCADE
    );
END
GO

/* --- EventOutcomeFiles --- */
IF OBJECT_ID(N'dbo.EventOutcomeFiles', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.EventOutcomeFiles (
        FileID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        OutcomeID INT NOT NULL,
        MaterialType NVARCHAR(20) NOT NULL,
        FilePath NVARCHAR(500) NOT NULL,
        OriginalName NVARCHAR(255) NULL,
        CreatedAt DATETIME NOT NULL CONSTRAINT DF_EventOutcomeFiles_CreatedAt DEFAULT (GETDATE()),
        CONSTRAINT FK_EventOutcomeFiles_Outcome FOREIGN KEY (OutcomeID) REFERENCES dbo.EventOutcomes(OutcomeID) ON DELETE CASCADE
    );
END
GO

/* --- ActivityLog --- */
IF OBJECT_ID(N'dbo.ActivityLog', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.ActivityLog (
        LogID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        EventID INT NULL,
        ActionType NVARCHAR(50) NOT NULL,
        ActionDate DATETIME NOT NULL CONSTRAINT DF_ActivityLog_ActionDate DEFAULT (GETDATE()),
        Details NVARCHAR(500) NULL,
        CONSTRAINT FK_ActivityLog_Users FOREIGN KEY (UserID) REFERENCES dbo.Users(UserID),
        CONSTRAINT FK_ActivityLog_Events FOREIGN KEY (EventID) REFERENCES dbo.Events(EventID)
    );
END
GO

/* --- EventMaterials --- */
IF OBJECT_ID(N'dbo.EventMaterials', N'U') IS NULL
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

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_Events_Users' AND parent_object_id = OBJECT_ID(N'dbo.Events'))
BEGIN
    ALTER TABLE dbo.Events
    ADD CONSTRAINT FK_Events_Users FOREIGN KEY (CreatorUserID) REFERENCES dbo.Users(UserID);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_News_Author' AND parent_object_id = OBJECT_ID(N'dbo.News'))
BEGIN
    ALTER TABLE dbo.News
    ADD CONSTRAINT FK_News_Author FOREIGN KEY (AuthorUserID) REFERENCES dbo.Users(UserID);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_News_Event' AND parent_object_id = OBJECT_ID(N'dbo.News'))
BEGIN
    ALTER TABLE dbo.News
    ADD CONSTRAINT FK_News_Event FOREIGN KEY (RelatedEventID) REFERENCES dbo.Events(EventID);
END
GO

PRINT N'Структура для отката создана. Перенесите данные из CoverImagePath/GalleryJson/FilesJson вручную при необходимости.';
