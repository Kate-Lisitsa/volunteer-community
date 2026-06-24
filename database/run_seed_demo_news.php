<?php
/**
 * Однократное добавление демо-новостей. Запуск из корня проекта:
 * php database/run_seed_demo_news.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';

$db = Database::getInstance();

$admin = $db->fetchOne($db->query(
    "SELECT TOP 1 UserID FROM Users WHERE Role = N'admin' ORDER BY UserID"
));
if (!$admin) {
    fwrite(STDERR, "Не найден администратор.\n");
    exit(1);
}
$adminId = (int)$admin['UserID'];

$newsItems = [
    [
        'title' => 'Итоги уборки в парке Челюскинцев',
        'summary' => 'Волонтёры собрали более 40 мешков мусора и привели в порядок аллеи у главного входа.',
        'body' => '<p>15 июня прошла экологическая акция в парке Челюскинцев. На площадку вышли 28 участников.</p><p>Работали перчатки и мешки, часть отходов отправили на переработку.</p>',
        'eventId' => 1,
        'daysAgo' => 12,
        'image' => 'assets/images/hero/slide-1.jpg',
    ],
    [
        'title' => 'Приют «Добрик»: выходные с собаками',
        'summary' => 'Помогли с выгулом, уборкой вольеров и раздачей корма — спасибо всем, кто приехал.',
        'body' => '<p>Команда из 8 человек провела субботу в приюте. Собаки получили прогулки и внимание.</p>',
        'eventId' => 2,
        'daysAgo' => 11,
        'image' => 'assets/images/hero/slide-2.jpg',
    ],
    [
        'title' => 'Мастер-класс для пожилых: первые результаты',
        'summary' => 'Участники научились пользоваться мессенджерами и записываться на приём через госуслуги.',
        'body' => '<p>На занятии в коворкинге разобрали установку приложений, видеозвонки и базовую безопасность в сети.</p>',
        'eventId' => 3,
        'daysAgo' => 10,
        'image' => null,
    ],
    [
        'title' => 'Посадили 120 саженцев в Лошицком парке',
        'summary' => 'Совместно с администрацией парка восстановили часть аллеи после зимы.',
        'body' => '<p>Утром встретились у главного входа, распределили инвентарь и работали до обеда.</p>',
        'eventId' => 4,
        'daysAgo' => 9,
        'image' => 'assets/images/hero/slide-1.jpg',
    ],
    [
        'title' => 'День донора: 14 сдач крови',
        'summary' => 'Волонтёры сопровождали доноров и помогали с регистрацией в центре трансфузиологии.',
        'body' => '<p>Акция прошла организованно, новые доноры получили консультации и памятки.</p>',
        'eventId' => 5,
        'daysAgo' => 8,
        'image' => 'assets/images/hero/slide-2.jpg',
    ],
    [
        'title' => 'Фестиваль «Волат»: работа волонтёров',
        'summary' => 'На регистрации и навигации помогали 35 участников — гости быстро находили нужные площадки.',
        'body' => '<p>Команда работала в две смены, выдали бейджи и ориентировала гостей на площадках.</p>',
        'eventId' => 6,
        'daysAgo' => 7,
        'image' => null,
    ],
    [
        'title' => 'Эко-акция на Минском море завершена',
        'summary' => 'Очистили береговую линию и собрали пластик отдельно для вывоза.',
        'body' => '<p>Несмотря на ветер, участники заполнили 25 мешков. Чай и перекус организовали на месте.</p>',
        'eventId' => 7,
        'daysAgo' => 6,
        'image' => 'assets/images/hero/slide-2.jpg',
    ],
    [
        'title' => 'Новые волонтёры в DobroHub',
        'summary' => 'За месяц зарегистрировались десятки участников — добро пожаловать в сообщество!',
        'body' => '<p>Напоминаем: после регистрации можно сразу записываться на акции в каталоге.</p>',
        'eventId' => null,
        'daysAgo' => 5,
        'image' => null,
    ],
    [
        'title' => 'Как предложить свою акцию',
        'summary' => 'Краткая инструкция для организаторов: заполните форму и дождитесь проверки модератором.',
        'body' => '<p>Укажите место, дату и описание. После одобрения акция появится в каталоге.</p>',
        'eventId' => null,
        'daysAgo' => 4,
        'image' => null,
    ],
    [
        'title' => 'Итоги весенней недели добрых дел',
        'summary' => 'Подводим итоги нескольких акций: экология, помощь пожилым и работа на мероприятиях.',
        'body' => '<p>Спасибо всем, кто находил время помогать. Следите за новыми датами в каталоге.</p>',
        'eventId' => null,
        'daysAgo' => 3,
        'image' => 'assets/images/hero/slide-1.jpg',
    ],
    [
        'title' => 'Субботник во дворе: мини-отчёт',
        'summary' => 'Жители и волонтёры прибрали детскую площадку и клумбы — результат виден сразу.',
        'body' => '<p>Работа заняла три часа, мусор вывезли совместно с управляющей компанией.</p>',
        'eventId' => null,
        'daysAgo' => 2,
        'image' => null,
    ],
    [
        'title' => 'Благодарность участникам мая',
        'summary' => 'Отдельное спасибо тем, кто выходил на акции в будни и помогал с организацией.',
        'body' => '<p>Ваш вклад делает город чище и людям вокруг — спокойнее.</p>',
        'eventId' => null,
        'daysAgo' => 1,
        'image' => 'assets/images/hero/slide-2.jpg',
    ],
];

$added = 0;
foreach ($newsItems as $item) {
    $exists = $db->fetchOne($db->query(
        'SELECT NewsID FROM News WHERE Title = ?',
        [$item['title']]
    ));
    if ($exists) {
        continue;
    }

    $eventId = $item['eventId'];
    if ($eventId !== null) {
        $ev = $db->fetchOne($db->query('SELECT EventID FROM Events WHERE EventID = ?', [$eventId]));
        if (!$ev) {
            $eventId = null;
        }
    }

    $db->query(
        'INSERT INTO News (Title, Summary, BodyHtml, RelatedEventID, IsPublished, PublishedAt, AuthorUserID, CreatedAt)
         VALUES (?, ?, ?, ?, 1, DATEADD(DAY, ?, GETDATE()), ?, GETDATE())',
        [
            $item['title'],
            $item['summary'],
            $item['body'],
            $eventId,
            -1 * (int)$item['daysAgo'],
            $adminId,
        ]
    );

    $newId = (int)$db->lastInsertId();
    if ($newId > 0 && !empty($item['image'])) {
        $table = $db->fetchOne($db->query(
            "SELECT OBJECT_ID('dbo.NewsImages', 'U') AS tid"
        ));
        if (!empty($table['tid'])) {
            $db->query(
                'INSERT INTO NewsImages (NewsID, FilePath, SortOrder) VALUES (?, ?, 0)',
                [$newId, $item['image']]
            );
        }
    }
    $added++;
}

$total = (int)($db->fetchOne($db->query('SELECT COUNT(*) AS c FROM News WHERE IsPublished = 1'))['c'] ?? 0);
echo "Добавлено новостей: {$added}. Всего опубликовано: {$total}.\n";
