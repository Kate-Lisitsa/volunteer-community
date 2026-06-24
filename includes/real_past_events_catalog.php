<?php
require_once __DIR__ . '/demo_image_loader.php';

/**
 * Прошедшие акции (архив): не попадают в публичный каталог, видны в админке и отчётах.
 */
function realPastEventsImageCatalog(array $forceKeys = []): array
{
    $q = 'w=960&q=82&auto=format&fit=crop';

    return demoPrepareImageCatalog([
        'eco_boom' => [
            'file' => 'assets/images/events/past-eco-boom.jpg',
            'url' => 'https://images.pexels.com/photos/1034364/pexels-photo-1034364.jpeg?auto=compress&cs=tinysrgb&w=960',
        ],
        'forest_rebirth' => [
            'file' => 'assets/images/events/past-forest-rebirth.jpg',
            'url' => 'https://images.pexels.com/photos/957024/forest-trees-perspective-bright-957024.jpeg?auto=compress&cs=tinysrgb&w=960',
        ],
        'seven_steps_past' => [
            'file' => 'assets/images/events/past-seven-steps.jpg',
            'url' => "https://images.unsplash.com/photo-1576091160550-2173dba999ef?{$q}",
        ],
        'wish_tree_past' => [
            'file' => 'assets/images/events/past-wish-tree.jpg',
            'url' => 'https://images.pexels.com/photos/1661904/pexels-photo-1661904.jpeg?auto=compress&cs=tinysrgb&w=960',
        ],
    ], $forceKeys);
}

function realPastEventsCatalog(): array
{
    return [
        [
            'title' => 'Трудовая молодежная акция «ЭКО_БУМ»',
            'category' => 'Экологическое волонтерство',
            'eventDate' => '2026-04-12 10:00:00',
            'createdAt' => '2026-04-12 09:00:00',
            'location' => 'Памятник природы «Луковая гора», Бобруйск',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'brsm_bobruisk',
            'imageKey' => 'eco_boom',
            'description' => 'Акция прошла в рамках марафона молодёжи и студенчества. Активисты БРСМ занимались благоустройством территории памятника природы: высаживали деревья и кустарники, убирали мусор. Цель — сохранение уникального природного объекта и развитие волонтёрского движения среди молодёжи. Источник: bobruisk.gov.by',
        ],
        [
            'title' => 'Республиканская добровольческая акция «Дай лесу новае жыццё!»',
            'category' => 'Экологическое волонтерство',
            'eventDate' => '2026-05-08 10:00:00',
            'createdAt' => '2026-05-08 09:30:00',
            'location' => 'Речицкое лесничество (возле д. Гиров), Гомельская область',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'forestry_rechitsa',
            'imageKey' => 'forest_rebirth',
            'description' => 'Акция посвящена Году благоустройства. Волонтёры (архивисты, студенты, представители общественных организаций) высаживали саженцы сосны и дуба на участках, пострадавших от ветровала после стихии. Цель — восстановление лесов Гомельщины. Источник: archives.gov.by',
        ],
        [
            'title' => 'Волонтерская акция «7 шагов по дороге добра»',
            'category' => 'Социальная помощь',
            'eventDate' => '2026-05-22 11:00:00',
            'createdAt' => '2026-05-22 10:00:00',
            'location' => 'Витебская область',
            'maxParticipants' => null,
            'isPriority' => 0,
            'organizerKey' => 'med_union_vitebsk',
            'imageKey' => 'seven_steps_past',
            'description' => 'Масштабный проект медицинских работников Витебщины, посвящённый Году белорусской женщины. Направления: благоустройство территорий медучреждений, уход за воинскими захоронениями, адресная помощь одиноким пожилым, поддержка приютов для животных, донорство, организация праздников для детей в больницах. В рамках акции участвовали сотни медицинских работников. Источник: vitprofmed.by',
        ],
        [
            'title' => 'Республиканская благотворительная кампания «Ёлка желаний»',
            'category' => 'Социальная помощь',
            'eventDate' => '2026-06-05 12:00:00',
            'createdAt' => '2026-06-05 11:00:00',
            'location' => 'Вся Беларусь (торговые центры, бизнес-центры)',
            'maxParticipants' => 4357,
            'isPriority' => 0,
            'organizerKey' => 'red_cross',
            'imageKey' => 'wish_tree_past',
            'description' => 'Ежегодная акция Белорусского Красного Креста. Жители выбирали открытку с желанием на ёлке, покупали подарок и оставляли его под ёлкой. Итоги: более 22 500 человек получили помощь, включая детей из семей в СОП (3 032), детей с инвалидностью (6 980), детей из малообеспеченных семей (6 361), пожилых людей (5 249). Сумма пожертвований от физлиц — более Br32 тыс., от юрлиц — более Br129 тыс. Источник: belta.by',
        ],
    ];
}

function realPastEventsRequiredCategories(): array
{
    return [
        'Экологическое волонтерство',
        'Социальная помощь',
    ];
}
