/*
  DobroHub — категория новости (та же таблица Categories, что и у акций).

  Выполнить в SSMS один раз для базы VolunteerCommunity (или через run_schema_news_category.php).
*/
USE [VolunteerCommunity];
GO

IF COL_LENGTH('dbo.News', 'CategoryID') IS NULL
BEGIN
    ALTER TABLE dbo.News ADD CategoryID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE name = N'FK_News_Category'
      AND parent_object_id = OBJECT_ID(N'dbo.News')
)
BEGIN
    ALTER TABLE dbo.News
    ADD CONSTRAINT FK_News_Category
        FOREIGN KEY (CategoryID) REFERENCES dbo.Categories (CategoryID);
END
GO

/* Категория из связанной акции (экология, донорство и т.д.) */
UPDATE n
SET n.CategoryID = e.CategoryID
FROM dbo.News n
INNER JOIN dbo.Events e ON e.EventID = n.RelatedEventID
WHERE e.CategoryID IS NOT NULL
  AND (n.CategoryID IS NULL OR n.CategoryID IN (
      SELECT CategoryID FROM dbo.Categories
      WHERE CategoryName IN (N'Отчёты с акций', N'Анонсы и объявления')
  ));
GO

/* Снять устаревшие «редакционные» категории, если акции нет */
UPDATE n
SET n.CategoryID = NULL
FROM dbo.News n
INNER JOIN dbo.Categories c ON c.CategoryID = n.CategoryID
WHERE c.CategoryName IN (N'Отчёты с акций', N'Анонсы и объявления')
  AND n.RelatedEventID IS NULL;
GO
