/*
  DobroHub — обновление схемы под ТЗ (SQL Server).
  Выполнить один раз в базе VolunteerCommunity.
*/

-- Модерация и приоритетные акции
IF COL_LENGTH('dbo.Events', 'ModerationStatus') IS NULL
BEGIN
    ALTER TABLE dbo.Events ADD ModerationStatus NVARCHAR(20) NOT NULL
        CONSTRAINT DF_Events_ModerationStatus DEFAULT ('approved');
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

-- Напоминания по e-mail
IF COL_LENGTH('dbo.Registrations', 'ReminderSent') IS NULL
BEGIN
    ALTER TABLE dbo.Registrations ADD ReminderSent BIT NOT NULL
        CONSTRAINT DF_Registrations_ReminderSent DEFAULT (0);
END
GO

-- Новости (HTML из редактора)
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

-- Материалы после акции (текст / фото / видео)
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
