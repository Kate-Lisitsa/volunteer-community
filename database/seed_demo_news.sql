/*
  DobroHub — демо-новости для раздела «Новости» (сетка 2×3, пагинация по 6).
  В SSMS: выберите базу VolunteerCommunity и выполните этот файл.

  Существующие новости не удаляются — добавляются только те, которых ещё нет по заголовку.
  Нужен хотя бы один пользователь с ролью admin (AuthorUserID).
*/

USE [VolunteerCommunity];
GO

DECLARE @adminId INT = (SELECT TOP 1 UserID FROM dbo.Users WHERE Role = N'admin' ORDER BY UserID);
IF @adminId IS NULL
BEGIN
    RAISERROR(N'Не найден администратор (Users.Role = admin). Сначала создайте пользователей.', 16, 1);
    RETURN;
END

DECLARE @news TABLE (
    Title NVARCHAR(300) NOT NULL,
    Summary NVARCHAR(600) NOT NULL,
    BodyHtml NVARCHAR(MAX) NOT NULL,
    RelatedEventID INT NULL,
    PublishedAt DATETIME NOT NULL,
    ImagePath NVARCHAR(500) NULL
);

INSERT INTO @news (Title, Summary, BodyHtml, RelatedEventID, PublishedAt, ImagePath) VALUES
(N'Итоги уборки в парке Челюскинцев',
 N'Волонтёры собрали более 40 мешков мусора и привели в порядок аллеи у главного входа.',
 N'<p>15 июня прошла экологическая акция в парке Челюскинцев. На площадку вышли 28 участников.</p><p>Работали перчатки и мешки, часть отходов отправили на переработку.</p>',
 1, DATEADD(DAY, -12, GETDATE()), N'assets/images/hero/slide-1.jpg'),

(N'Приют «Добрик»: выходные с собаками',
 N'Помогли с выгулом, уборкой вольеров и раздачей корма — спасибо всем, кто приехал.',
 N'<p>Команда из 8 человек провела субботу в приюте. Собаки получили прогулки и внимание.</p>',
 2, DATEADD(DAY, -11, GETDATE()), N'assets/images/hero/slide-2.jpg'),

(N'Мастер-класс для пожилых: первые результаты',
 N'Участники научились пользоваться мессенджерами и записываться на приём через госуслуги.',
 N'<p>На занятии в коворкинге разобрали установку приложений, видеозвонки и базовую безопасность в сети.</p>',
 3, DATEADD(DAY, -10, GETDATE()), NULL),

(N'Посадили 120 саженцев в Лошицком парке',
 N'Совместно с администрацией парка восстановили часть аллеи после зимы.',
 N'<p>Утром встретились у главного входа, распределили инвентарь и работали до обеда.</p>',
 4, DATEADD(DAY, -9, GETDATE()), N'assets/images/hero/slide-1.jpg'),

(N'День донора: 14 сдач крови',
 N'Волонтёры сопровождали доноров и помогали с регистрацией в центре трансфузиологии.',
 N'<p>Акция прошла организованно, новые доноры получили консультации и памятки.</p>',
 5, DATEADD(DAY, -8, GETDATE()), N'assets/images/hero/slide-2.jpg'),

(N'Фестиваль «Волат»: работа волонтёров',
 N'На регистрации и навигации помогали 35 участников — гости быстро находили нужные площадки.',
 N'<p>Команда работала в две смены, выдали бейджи и ориентировала гостей на площадках.</p>',
 6, DATEADD(DAY, -7, GETDATE()), NULL),

(N'Эко-акция на Минском море завершена',
 N'Очистили береговую линию и собрали пластик отдельно для вывоза.',
 N'<p>Несмотря на ветер, участники заполнили 25 мешков. Чай и перекус организовали на месте.</p>',
 7, DATEADD(DAY, -6, GETDATE()), N'assets/images/hero/slide-2.jpg'),

(N'Новые волонтёры в DobroHub',
 N'За месяц зарегистрировались десятки участников — добро пожаловать в сообщество!',
 N'<p>Напоминаем: после регистрации можно сразу записываться на акции в каталоге.</p>',
 NULL, DATEADD(DAY, -5, GETDATE()), NULL),

(N'Как предложить свою акцию',
 N'Краткая инструкция для организаторов: заполните форму и дождитесь проверки модератором.',
 N'<p>Укажите место, дату и описание. После одобрения акция появится в каталоге.</p>',
 NULL, DATEADD(DAY, -4, GETDATE()), NULL),

(N'Итоги весенней недели добрых дел',
 N'Подводим итоги нескольких акций: экология, помощь пожилым и работа на мероприятиях.',
 N'<p>Спасибо всем, кто находил время помогать. Следите за новыми датами в каталоге.</p>',
 NULL, DATEADD(DAY, -3, GETDATE()), N'assets/images/hero/slide-1.jpg'),

(N'Субботник во дворе: мини-отчёт',
 N'Жители и волонтёры прибрали детскую площадку и клумбы — результат виден сразу.',
 N'<p>Работа заняла три часа, мусор вывезли совместно с управляющей компанией.</p>',
 NULL, DATEADD(DAY, -2, GETDATE()), NULL),

(N'Благодарность участникам мая',
 N'Отдельное спасибо тем, кто выходил на акции в будни и помогал с организацией.',
 N'<p>Ваш вклад делает город чище и людям вокруг — спокойнее.</p>',
 NULL, DATEADD(DAY, -1, GETDATE()), N'assets/images/hero/slide-2.jpg');

INSERT INTO dbo.News (Title, Summary, BodyHtml, RelatedEventID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
SELECT n.Title, n.Summary, n.BodyHtml,
       CASE WHEN n.RelatedEventID IS NOT NULL AND EXISTS (SELECT 1 FROM dbo.Events e WHERE e.EventID = n.RelatedEventID)
            THEN n.RelatedEventID ELSE NULL END,
       1, n.PublishedAt, @adminId, GETDATE()
FROM @news n
WHERE NOT EXISTS (SELECT 1 FROM dbo.News x WHERE x.Title = n.Title);

IF OBJECT_ID('dbo.NewsImages', 'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.NewsImages (NewsID, FilePath, SortOrder)
    SELECT nw.NewsID, n.ImagePath, 0
    FROM @news n
    INNER JOIN dbo.News nw ON nw.Title = n.Title
    WHERE n.ImagePath IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM dbo.NewsImages ni
          WHERE ni.NewsID = nw.NewsID AND ni.FilePath = n.ImagePath
      );
END

SELECT NewsID, Title, IsPublished, PublishedAt
FROM dbo.News
WHERE IsPublished = 1
ORDER BY PublishedAt DESC;
GO
