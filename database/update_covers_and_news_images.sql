/* DobroHub: обложки акций + галерея новостей. Выполнить в SSMS для базы VolunteerCommunity. */

USE [VolunteerCommunity];
GO

IF COL_LENGTH('dbo.Events', 'CoverImagePath') IS NULL
BEGIN
    ALTER TABLE dbo.Events ADD CoverImagePath NVARCHAR(500) NULL;
END
GO

IF OBJECT_ID('dbo.NewsImages', 'U') IS NULL
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
