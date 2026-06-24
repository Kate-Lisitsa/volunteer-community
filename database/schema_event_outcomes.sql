/*
  DobroHub — итоги акции от организатора (текст + фото/видео для админа и новостей).

  Выполнить в SSMS один раз для базы (имя как в config, по умолчанию VolunteerCommunity).
*/
USE [VolunteerCommunity];
GO

IF OBJECT_ID(N'dbo.EventOutcomeFiles', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.EventOutcomeFiles (
        FileID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        OutcomeID INT NOT NULL,
        MaterialType NVARCHAR(20) NOT NULL,
        FilePath NVARCHAR(500) NOT NULL,
        OriginalName NVARCHAR(255) NULL,
        CreatedAt DATETIME NOT NULL CONSTRAINT DF_EventOutcomeFiles_CreatedAt DEFAULT (GETDATE())
    );
END
GO

IF OBJECT_ID(N'dbo.EventOutcomes', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.EventOutcomes (
        OutcomeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        EventID INT NOT NULL,
        BodyText NVARCHAR(MAX) NULL,
        SubmittedAt DATETIME NOT NULL CONSTRAINT DF_EventOutcomes_SubmittedAt DEFAULT (GETDATE()),
        UpdatedAt DATETIME NULL,
        CONSTRAINT UQ_EventOutcomes_Event UNIQUE (EventID),
        CONSTRAINT FK_EventOutcomes_Event FOREIGN KEY (EventID) REFERENCES dbo.Events(EventID) ON DELETE CASCADE
    );
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE name = N'FK_EventOutcomeFiles_Outcome'
      AND parent_object_id = OBJECT_ID(N'dbo.EventOutcomeFiles')
)
BEGIN
    ALTER TABLE dbo.EventOutcomeFiles
    ADD CONSTRAINT FK_EventOutcomeFiles_Outcome
        FOREIGN KEY (OutcomeID) REFERENCES dbo.EventOutcomes(OutcomeID) ON DELETE CASCADE;
END
GO
