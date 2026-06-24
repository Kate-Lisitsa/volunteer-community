-- =====================================================
-- DobroHub: схема + очистка + тестовые данные
-- База: VolunteerCommunity (при необходимости замените USE)
-- =====================================================
-- Тестовый пароль для ВСЕХ учёток ниже:  password
-- (хеш bcrypt совместим с PHP password_verify)
-- =====================================================

USE [VolunteerCommunity];
GO

SET NOCOUNT ON;

/* ---------- 1. Добавляем столбцы под текущий проект (если ещё нет) ---------- */
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

IF COL_LENGTH('dbo.Registrations', 'ReminderSent') IS NULL
BEGIN
    ALTER TABLE dbo.Registrations ADD ReminderSent BIT NOT NULL
        CONSTRAINT DF_Registrations_ReminderSent DEFAULT (0);
END
GO

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

/* ---------- 2. Очистка (сначала зависимые таблицы DobroHub) ---------- */
EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all";
GO

IF OBJECT_ID('dbo.EventMaterials', 'U') IS NOT NULL DELETE FROM dbo.EventMaterials;
IF OBJECT_ID('dbo.News', 'U') IS NOT NULL DELETE FROM dbo.News;
DELETE FROM dbo.ActivityLog;
DELETE FROM dbo.Registrations;
DELETE FROM dbo.Events;
DELETE FROM dbo.Categories;
DELETE FROM dbo.Users;
GO

DBCC CHECKIDENT ('dbo.Users', RESEED, 0);
DBCC CHECKIDENT ('dbo.Categories', RESEED, 0);
DBCC CHECKIDENT ('dbo.Events', RESEED, 0);
DBCC CHECKIDENT ('dbo.Registrations', RESEED, 0);
DBCC CHECKIDENT ('dbo.ActivityLog', RESEED, 0);
IF OBJECT_ID('dbo.News', 'U') IS NOT NULL DBCC CHECKIDENT ('dbo.News', RESEED, 0);
IF OBJECT_ID('dbo.EventMaterials', 'U') IS NOT NULL DBCC CHECKIDENT ('dbo.EventMaterials', RESEED, 0);
GO

/* ---------- 3. Наполнение ---------- */
INSERT INTO dbo.Categories (CategoryName, Description) VALUES
(N'Помощь животным', N'Мероприятия в приютах и помощь бездомным животным'),
(N'Экология', N'Уборка парков, посадка деревьев'),
(N'Помощь пожилым', N'Шефская помощь и социальная поддержка'),
(N'Образование', N'Обучение и мастер-классы для детей и взрослых'),
(N'Донорство', N'Сдача крови и популяризация донорства'),
(N'Культура', N'Помощь в проведении культурных мероприятий');
GO

-- Один bcrypt-хеш для пароля: password (один пакет с DECLARE)
DECLARE @pw NVARCHAR(255) = N'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- Если в Users НЕТ RegisteredAt / IsActive — удалите эти столбцы из списка и из VALUES.
INSERT INTO dbo.Users (Email, PasswordHash, FullName, Phone, Role, RegisteredAt, IsActive) VALUES
(N'admin@test.by', @pw, N'Администратор Системы', N'+375291234567', N'admin', GETDATE(), 1),
(N'user@test.by', @pw, N'Иван Петров', N'+375337654321', N'user', GETDATE(), 1),
(N'anna@test.by', @pw, N'Анна Смирнова', N'+375447778899', N'user', GETDATE(), 1),
(N'dmitry@test.by', @pw, N'Дмитрий Волонтер', NULL, N'user', GETDATE(), 1);
GO

-- Если нет CreatedAt / IsPublished — уберите эти поля из INSERT и списка столбцов (но тогда доработайте таблицу под проект).
INSERT INTO dbo.Events (
    Title, Description, CategoryID, CreatorUserID, EventDate, Location, MaxParticipants,
    CreatedAt, IsPublished, ModerationStatus, RejectionReason, IsPriority
) VALUES
(N'Уборка в парке Челюскинцев', N'Собираемся, чтобы сделать парк чище. Весь инвентарь (перчатки, мешки) предоставим.',
 2, 2, N'2026-06-15 10:00:00', N'Минск, парк Челюскинцев (вход со стороны пр. Независимости)', 30,
 GETDATE(), 1, N'approved', NULL, 1),

(N'Помощь приюту "Добрик"', N'Нужна помощь с выгулом собак, уборкой вольеров и закупкой корма.',
 1, 3, N'2026-06-18 11:00:00', N'Минский р-н, д. Боровая, ул. Центральная, 5', 8,
 GETDATE(), 1, N'approved', NULL, 1),

(N'Обучение работе со смартфоном для пожилых', N'Поможем бабушкам и дедушкам освоить мессенджеры и госуслуги.',
 4, 2, N'2026-06-20 15:00:00', N'Минск, ТЦ "Корона" (зона коворкинга), пр-т Дзержинского, 100', 12,
 GETDATE(), 1, N'approved', NULL, 0),

(N'Посадка деревьев в Лошицком парке', N'Озеленение парка, восстановление аллей.',
 2, 4, N'2026-06-22 09:00:00', N'Минск, Лошицкий парк (сбор у главного входа)', 25,
 GETDATE(), 1, N'approved', NULL, 0),

(N'День донора в Минске', N'Совместная сдача крови в городском центре трансфузиологии. Важно взять паспорт!',
 5, 2, N'2026-06-25 08:30:00', N'Минск, ул. Семашко, 8 (Городской центр крови)', 15,
 GETDATE(), 1, N'approved', NULL, 0),

(N'Помощь в проведении фестиваля "Волат"', N'Волонтеры на регистрацию, навигацию и помощь участникам.',
 6, 3, N'2026-07-01 09:00:00', N'Минск, ст. м. "Московская" (выход к "Минск-Арене")', 40,
 GETDATE(), 1, N'approved', NULL, 0),

(N'Уборка на территории Минского моря', N'Экологическая акция по очистке береговой линии Заславского водохранилища.',
 2, 4, N'2026-06-29 10:00:00', N'Заславль, пляж "Озеро" (ост. "Санаторий Юность")', 20,
 GETDATE(), 1, N'approved', NULL, 0);
GO

-- Если нет ReminderSent — уберите столбец и значение 0 из INSERT.
INSERT INTO dbo.Registrations (EventID, UserID, Status, ReminderSent) VALUES
(1, 2, N'confirmed', 0),
(1, 3, N'confirmed', 0),
(2, 2, N'confirmed', 0),
(3, 4, N'confirmed', 0),
(4, 3, N'confirmed', 0),
(4, 2, N'confirmed', 0),
(5, 4, N'confirmed', 0),
(6, 2, N'confirmed', 0),
(7, 3, N'confirmed', 0),
(7, 4, N'confirmed', 0);
GO

INSERT INTO dbo.ActivityLog (UserID, EventID, ActionType) VALUES
(2, 1, N'create_event'),
(3, 2, N'create_event'),
(4, 4, N'create_event'),
(2, 1, N'register'),
(3, 1, N'register'),
(2, 2, N'register'),
(4, 3, N'register'),
(3, 4, N'register'),
(2, 4, N'register'),
(4, 5, N'register'),
(2, 6, N'register'),
(3, 7, N'register'),
(4, 7, N'register');
GO

EXEC sp_MSforeachtable "ALTER TABLE ? CHECK CONSTRAINT all";
GO

/* ---------- 4. Проверка ---------- */
SELECT N'Users' AS TableName, COUNT(*) AS RecordCount FROM dbo.Users
UNION ALL SELECT N'Categories', COUNT(*) FROM dbo.Categories
UNION ALL SELECT N'Events', COUNT(*) FROM dbo.Events
UNION ALL SELECT N'Registrations', COUNT(*) FROM dbo.Registrations
UNION ALL SELECT N'ActivityLog', COUNT(*) FROM dbo.ActivityLog
UNION ALL SELECT N'News', COUNT(*) FROM dbo.News
UNION ALL SELECT N'EventMaterials', COUNT(*) FROM dbo.EventMaterials;

SELECT UserID, Email, FullName, Role, IsActive FROM dbo.Users;

SELECT EventID, Title, EventDate, IsPublished, ModerationStatus, IsPriority FROM dbo.Events;

PRINT N'Готово. Вход: admin@test.by / user@test.by — пароль: password';
GO

USE [master];
GO
ALTER DATABASE [VolunteerCommunity] SET MULTI_USER;
GO
