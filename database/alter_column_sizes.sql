/*
  DobroHub — уменьшение длины полей (SQL Server / SSMS).
  База: VolunteerCommunity (как в includes/config.php).

  ВАЖНО:
  1) Перед ALTER выполняется проверка: если есть значения длиннее нового лимита,
     скрипт остановится с ошибкой — сначала сократите или удалите такие записи.
  2) PasswordHash (bcrypt ~60 символов) в 200 помещается.
  3) Пути к файлам > 200 символов нужно обновить вручную или сократить в БД.
*/
USE [VolunteerCommunity];
GO

SET NOCOUNT ON;

DECLARE @maxLen INT;
DECLARE @msg NVARCHAR(400);

/* --- Проверка длины данных --- */
IF COL_LENGTH('dbo.ActivityLog', 'Details') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(Details)) FROM dbo.ActivityLog;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'ActivityLog.Details: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF COL_LENGTH('dbo.Users', 'AvatarPath') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(AvatarPath)) FROM dbo.Users WHERE AvatarPath IS NOT NULL;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'Users.AvatarPath: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF COL_LENGTH('dbo.Users', 'PasswordHash') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(PasswordHash)) FROM dbo.Users;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'Users.PasswordHash: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF OBJECT_ID(N'dbo.NewsImages', N'U') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(FilePath)) FROM dbo.NewsImages;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'NewsImages.FilePath: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF COL_LENGTH('dbo.News', 'Summary') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(Summary)) FROM dbo.News WHERE Summary IS NOT NULL;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'News.Summary: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF COL_LENGTH('dbo.News', 'Title') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(Title)) FROM dbo.News;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'News.Title: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF OBJECT_ID(N'dbo.EventMaterials', N'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.EventMaterials', 'FilePath') IS NOT NULL
    BEGIN
        SELECT @maxLen = MAX(LEN(FilePath)) FROM dbo.EventMaterials WHERE FilePath IS NOT NULL;
        IF @maxLen > 200
        BEGIN
            SET @msg = N'EventMaterials.FilePath: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
            THROW 50001, @msg, 1;
        END
    END
    IF COL_LENGTH('dbo.EventMaterials', 'OriginalName') IS NOT NULL
    BEGIN
        SELECT @maxLen = MAX(LEN(OriginalName)) FROM dbo.EventMaterials WHERE OriginalName IS NOT NULL;
        IF @maxLen > 200
        BEGIN
            SET @msg = N'EventMaterials.OriginalName: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
            THROW 50001, @msg, 1;
        END
    END
END

IF COL_LENGTH('dbo.Categories', 'Description') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(Description)) FROM dbo.Categories WHERE Description IS NOT NULL;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'Categories.Description: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF COL_LENGTH('dbo.Events', 'CoverImagePath') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(CoverImagePath)) FROM dbo.Events WHERE CoverImagePath IS NOT NULL;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'Events.CoverImagePath: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

IF COL_LENGTH('dbo.Events', 'Location') IS NOT NULL
BEGIN
    SELECT @maxLen = MAX(LEN(Location)) FROM dbo.Events;
    IF @maxLen > 200
    BEGIN
        SET @msg = N'Events.Location: есть значения длиннее 200 (макс. ' + CAST(@maxLen AS NVARCHAR(10)) + N').';
        THROW 50001, @msg, 1;
    END
END

/* --- Изменение типов --- */
IF COL_LENGTH('dbo.ActivityLog', 'Details') IS NOT NULL
    ALTER TABLE dbo.ActivityLog ALTER COLUMN Details NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Users', 'AvatarPath') IS NOT NULL
    ALTER TABLE dbo.Users ALTER COLUMN AvatarPath NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Users', 'PasswordHash') IS NOT NULL
    ALTER TABLE dbo.Users ALTER COLUMN PasswordHash NVARCHAR(200) NOT NULL;

IF OBJECT_ID(N'dbo.NewsImages', N'U') IS NOT NULL
    ALTER TABLE dbo.NewsImages ALTER COLUMN FilePath NVARCHAR(200) NOT NULL;

IF COL_LENGTH('dbo.News', 'Summary') IS NOT NULL
    ALTER TABLE dbo.News ALTER COLUMN Summary NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.News', 'Title') IS NOT NULL
    ALTER TABLE dbo.News ALTER COLUMN Title NVARCHAR(200) NOT NULL;

IF OBJECT_ID(N'dbo.EventMaterials', N'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.EventMaterials', 'FilePath') IS NOT NULL
        ALTER TABLE dbo.EventMaterials ALTER COLUMN FilePath NVARCHAR(200) NULL;
    IF COL_LENGTH('dbo.EventMaterials', 'OriginalName') IS NOT NULL
        ALTER TABLE dbo.EventMaterials ALTER COLUMN OriginalName NVARCHAR(200) NULL;
END

IF COL_LENGTH('dbo.Categories', 'Description') IS NOT NULL
    ALTER TABLE dbo.Categories ALTER COLUMN Description NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Events', 'CoverImagePath') IS NOT NULL
    ALTER TABLE dbo.Events ALTER COLUMN CoverImagePath NVARCHAR(200) NULL;

IF COL_LENGTH('dbo.Events', 'Location') IS NOT NULL
    ALTER TABLE dbo.Events ALTER COLUMN Location NVARCHAR(200) NOT NULL;

PRINT N'Длины полей обновлены.';

/* --- Проверка --- */
SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = N'dbo'
  AND (
    (TABLE_NAME = N'ActivityLog' AND COLUMN_NAME = N'Details')
    OR (TABLE_NAME = N'Users' AND COLUMN_NAME IN (N'AvatarPath', N'PasswordHash'))
    OR (TABLE_NAME = N'NewsImages' AND COLUMN_NAME = N'FilePath')
    OR (TABLE_NAME = N'News' AND COLUMN_NAME IN (N'Summary', N'Title'))
    OR (TABLE_NAME = N'EventMaterials' AND COLUMN_NAME IN (N'FilePath', N'OriginalName'))
    OR (TABLE_NAME = N'Categories' AND COLUMN_NAME = N'Description')
    OR (TABLE_NAME = N'Events' AND COLUMN_NAME IN (N'CoverImagePath', N'Location'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
GO
