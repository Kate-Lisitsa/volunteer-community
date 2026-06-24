<?php
require_once __DIR__ . '/demo_image_loader.php';

/**
 * Реальные волонтёрские акции (Беларусь) для наполнения каталога.
 */
function realEventsImageCatalog(array $forceKeys = []): array
{
    $q = 'w=960&q=82&auto=format&fit=crop';

    return demoPrepareImageCatalog([
        'snow_dispatch' => [
            'file' => 'assets/images/events/snow-dispatch.jpg',
            'url' => "https://images.unsplash.com/photo-1558618666-fcd25c85cd64?{$q}",
        ],
        'christmas_miracles' => [
            'file' => 'assets/images/events/christmas-miracles.jpg',
            'url' => 'https://images.pexels.com/photos/1708601/pexels-photo-1708601.jpeg?auto=compress&cs=tinysrgb&w=960',
        ],
        'from_the_heart' => [
            'file' => 'assets/images/events/from-the-heart.jpg',
            'url' => "https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?{$q}",
        ],
        'mercy_animals' => [
            'file' => 'assets/images/events/mercy-animals.jpg',
            'url' => "https://images.unsplash.com/photo-1601758228041-f3b2795255f1?{$q}",
        ],
        'christmas_everyone' => [
            'file' => 'assets/images/events/christmas-everyone.jpg',
            'url' => "https://images.unsplash.com/photo-1512389142860-9c449e58a543?{$q}",
        ],
        'relay_of_kindness' => [
            'file' => 'assets/images/events/relay-of-kindness.jpg',
            'url' => "https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?{$q}",
        ],
        'elderly_help' => [
            'file' => 'assets/images/events/elderly-help.jpg',
            'url' => "https://images.unsplash.com/photo-1581578731548-c64695cc6952?{$q}",
        ],
        'orthodox_belarus' => [
            'file' => 'assets/images/events/orthodox-belarus.jpg',
            'url' => "https://images.unsplash.com/photo-1522202176988-66273c2fd55f?{$q}",
        ],
        'pioneer_quest' => [
            'file' => 'assets/images/events/pioneer-quest.jpg',
            'url' => "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?{$q}",
        ],
        'defense_ready' => [
            'file' => 'assets/images/events/defense-ready.jpg',
            'url' => "https://images.unsplash.com/photo-1529156069898-49953e39b3ac?{$q}",
        ],
        'ironstar' => [
            'file' => 'assets/images/events/ironstar.jpg',
            'url' => "https://images.unsplash.com/photo-1530549387789-4c1017266635?{$q}",
        ],
        'citizens_belarus' => [
            'file' => 'assets/images/events/citizens-belarus.jpg',
            'url' => "https://images.unsplash.com/photo-1522071820081-009f0129c71c?{$q}",
        ],
        'colors_of_life' => [
            'file' => 'assets/images/events/colors-of-life.jpg',
            'url' => "https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?{$q}",
        ],
        'shrines_restore' => [
            'file' => 'assets/images/events/shrines-restore.jpg',
            'url' => 'https://images.pexels.com/photos/208745/pexels-photo-208745.jpeg?auto=compress&cs=tinysrgb&w=960',
        ],
    ], $forceKeys);
}

function realEventsCatalog(): array
{
    return [
        [
            'title' => 'Волонтерская инициатива «Снежный десант»',
            'category' => 'Помощь пожилым',
            'eventDate' => '2026-12-15 10:00:00',
            'location' => 'Партизанский район г. Минска (частный сектор)',
            'maxParticipants' => null,
            'isPriority' => 1,
            'organizerKey' => 'minsk_social',
            'imageKey' => 'snow_dispatch',
            'description' => 'Волонтеры помогают пожилым минчанам и маломобильным гражданам с уборкой снега на придомовых территориях в частном секторе. Заявки принимаются в зимний период в будние дни с 09:00 до 18:00. Для участия можно оставить заявку по телефону 8 (017) 294-33-23. Лимит участников не установлен — подключаем волонтёров по мере поступления обращений.',
        ],
        [
            'title' => 'Республиканская благотворительная акция «Чудеса на Рождество»',
            'category' => 'Помощь детям',
            'eventDate' => '2026-12-25 12:00:00',
            'location' => 'Все регионы Республики Беларусь',
            'maxParticipants' => null,
            'isPriority' => 1,
            'organizerKey' => 'brsm_secretary',
            'imageKey' => 'christmas_miracles',
            'description' => 'Совместная акция БРСМ и БРПО в рождественские дни. Символом акции являются красные рукавички. Волонтеры и тимуровцы дарят праздник и подарки детям, окружают их заботой и вниманием. Акция проходит в декабре и январе по всей стране. Присоединяйтесь, чтобы подарить детям тепло и радость.',
        ],
        [
            'title' => 'Республиканская благотворительная акция «От всей души»',
            'category' => 'Помощь пожилым',
            'eventDate' => '2026-12-20 11:00:00',
            'location' => 'Все регионы Республики Беларусь',
            'maxParticipants' => null,
            'isPriority' => 1,
            'organizerKey' => 'brsm_secretary',
            'imageKey' => 'from_the_heart',
            'description' => 'Акция направлена на поддержку пожилых людей. Волонтеры поздравляют ветеранов и одиноких пенсионеров, дарят подарки и оказывают посильную бытовую помощь. Проводится БРСМ в зимний период по всей Беларуси. Участие открыто для всех, кто готов уделить время и внимание старшему поколению.',
        ],
        [
            'title' => 'Областная благотворительная акция «Милосердие без границ»',
            'category' => 'Помощь животным',
            'eventDate' => '2026-09-01 10:00:00',
            'location' => 'Волковысский район, Гродненская область',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'animal_shelter',
            'imageKey' => 'mercy_animals',
            'description' => 'Акция по помощи бездомным животным. Участники собирают и передают в приюты корма, лежанки, наполнители, поводки, лекарства, миски и игрушки. Также можно помогать сотрудникам приютов в уходе за животными: выгул, игры, уборка. Сбор и помощь организуются в течение осенне-зимнего сезона.',
        ],
        [
            'title' => 'XV республиканская акция «Рождество приходит к каждому»',
            'category' => 'Социальная помощь',
            'eventDate' => '2027-01-10 09:00:00',
            'location' => 'Свято-Духов кафедральный собор, Минск, ул. Кирилла и Мефодия, 3',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'cathedral_charity',
            'imageKey' => 'christmas_everyone',
            'description' => 'Помощь подопечным социальных пансионатов для взрослых: людям с инвалидностью и одиноким пожилым. Можно передать вещи и товары первой необходимости, внести пожертвование или стать волонтером по фасовке и доставке помощи. Пункт сбора работает ежедневно с 08:00 до 20:00 в предрождественский период.',
        ],
        [
            'title' => '«Эстафета добра»',
            'category' => 'Помощь пожилым',
            'eventDate' => '2026-12-05 10:00:00',
            'location' => 'По всей Беларуси (свыше 6 тысяч волонтеров, 689 адресов)',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'brsm_dobroe_serdce',
            'imageKey' => 'relay_of_kindness',
            'description' => 'Волонтеры движения «Доброе сердце» БРСМ помогают пожилым людям с уборкой придомовых территорий от снега, колкой дров и другими бытовыми вопросами. Каждая заявка рассматривается индивидуально. Акция проходит в зимний период по всей стране.',
        ],
        [
            'title' => 'Благотворительная акция «Мы выбираем помощь пожилым людям»',
            'category' => 'Помощь пожилым',
            'eventDate' => '2026-11-20 10:00:00',
            'location' => 'Гомельская область и другие регионы',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'gomel_volunteers',
            'imageKey' => 'elderly_help',
            'description' => 'Волонтеры организуют трудовые десанты: помощь в уборке дворовых территорий, подготовке жилищ к зиме, колке дров, сборе урожая, доставке продуктов с ярмарок пожилым и нуждающимся людям. Акция проводится ежегодно в зимний период.',
        ],
        [
            'title' => 'Творческий семейный проект «Дорогами православной Беларуси вместе»',
            'category' => 'Культура',
            'eventDate' => '2026-10-19 10:00:00',
            'location' => 'Онлайн (участие через Viber: +375 29 385-16-09)',
            'maxParticipants' => 100,
            'isPriority' => 0,
            'organizerKey' => 'orthodox_project',
            'imageKey' => 'orthodox_belarus',
            'description' => 'Семейный проект для интересующихся православной духовностью, историей и культурой Беларуси. Участники выполняют творческие задания по этапам и присылают фото; лучшие работы публикуют на YouTube-канале. 100 самых активных семей получают подарки. Проект проходит в несколько этапов с октября по январь.',
        ],
        [
            'title' => 'Республиканский конкурс «Пионерская прокачка»',
            'category' => 'Молодёжное волонтерство',
            'eventDate' => '2026-11-30 18:00:00',
            'location' => 'Республика Беларусь (онлайн)',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'brpo_pioneer',
            'imageKey' => 'pioneer_quest',
            'description' => 'Конкурс на создание квестов, раскрасок для октябрят, онлайн-проектов и онлайн-викторин. Участники разрабатывают интерактивный контент для детей и молодёжи. Приём работ продолжается до конца года.',
        ],
        [
            'title' => 'Республиканская акция «К защите Отечества готов!»',
            'category' => 'Патриотическое волонтерство',
            'eventDate' => '2027-02-10 10:00:00',
            'location' => 'Республика Беларусь',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'brpo_patriot',
            'imageKey' => 'defense_ready',
            'description' => 'Патриотическая акция ОО «БРПО» и ОО «БРСМ», направленная на подготовку молодёжи к защите Отечества, развитие военно-патриотических качеств и уважения к защитникам Родины. Мероприятия проходят в феврале по всей стране.',
        ],
        [
            'title' => 'IRONSTAR MINSK 2026 (волонтерская программа)',
            'category' => 'Спортивное волонтерство',
            'eventDate' => '2026-06-20 08:00:00',
            'location' => 'Минск, Беларусь',
            'maxParticipants' => 730,
            'isPriority' => 1,
            'organizerKey' => 'ironstar',
            'imageKey' => 'ironstar',
            'description' => 'Уникальное спортивное событие международного уровня. Волонтеры помогают в организации крупного мероприятия, знакомятся с новыми людьми и окунаются в атмосферу праздника спорта. Смены с 08:00 до 16:00. Контакт для волонтёров: volunteer@iron-star.com',
        ],
        [
            'title' => 'Всебелорусская акция «Мы – граждане Беларуси»',
            'category' => 'Патриотическое волонтерство',
            'eventDate' => '2027-03-15 10:00:00',
            'location' => 'Республика Беларусь',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'citizens_youth',
            'imageKey' => 'citizens_belarus',
            'description' => 'Торжественное вручение паспортов молодым гражданам, достигшим 14-летнего возраста. Волонтеры помогают в организации церемоний, поздравлений и мероприятий, посвящённых Дню Конституции. Акция проходит в марте по всей стране.',
        ],
        [
            'title' => 'Благотворительный марафон «Все краски жизни для тебя»',
            'category' => 'Помощь детям',
            'eventDate' => '2026-07-12 18:00:00',
            'location' => 'Гомельская область и другие регионы',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'brsm_gomel',
            'imageKey' => 'colors_of_life',
            'description' => 'Марафон направлен на помощь конкретным тяжелобольным детям и подросткам. Организуются благотворительные концерты, на которых собираются средства для лечения и поддержки нуждающихся детей. Визитная карточка БРСМ с 2009 года; мероприятия проходят в течение года по отдельному графику.',
        ],
        [
            'title' => 'Благотворительная акция «Восстановление святынь Беларуси»',
            'category' => 'Культура',
            'eventDate' => '2026-08-15 10:00:00',
            'location' => 'Гомельская область и другие регионы',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'shrines_restore',
            'imageKey' => 'shrines_restore',
            'description' => 'Ежегодная совместная с основными конфессиями акция по благоустройству и восстановлению культовых объектов: храмов, монастырей, часовен, придорожных крестов и святых источников. Волонтеры помогают в ремонте, уборке и сохранении исторического наследия. Работы проводятся в течение года по графику.',
        ],
    ];
}

/** Категории, которые нужны для реальных акций (создаются при отсутствии). */
function realEventsRequiredCategories(): array
{
    return [
        'Помощь пожилым',
        'Помощь детям',
        'Помощь животным',
        'Социальная помощь',
        'Культура',
        'Молодёжное волонтерство',
        'Патриотическое волонтерство',
        'Спортивное волонтерство',
    ];
}
