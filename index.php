<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$db = Database::getInstance();
$pub = sqlPublishedEvents('e');

$heroTotal = (int)$db->fetchOne($db->query("SELECT COUNT(*) as total FROM Events e WHERE $pub"))['total'];

$priPage = isset($_GET['pri_page']) ? max(1, (int)$_GET['pri_page']) : 1;
$priPer = 4;

$priCountSql = "SELECT COUNT(*) as c FROM Events e
    INNER JOIN Categories c ON e.CategoryID = c.CategoryID
    WHERE $pub AND " . sqlActiveCategory('c') . " AND e.IsPriority = 1 AND e.EventDate >= GETDATE()";
$priTotal = (int)$db->fetchOne($db->query($priCountSql))['c'];
$priTotalPages = $priTotal > 0 ? (int)ceil($priTotal / $priPer) : 1;
if ($priTotal > 0) {
    $priPage = min($priPage, $priTotalPages);
}
$priOffset = ($priPage - 1) * $priPer;

$priorityEvents = [];
if ($priTotal > 0) {
    $prioritySql = "SELECT e.*, c.CategoryName, u.FullName as CreatorName,
        (SELECT COUNT(*) FROM Registrations WHERE EventID = e.EventID AND Status = N'confirmed') as ParticipantsCount
        FROM Events e
        INNER JOIN Categories c ON e.CategoryID = c.CategoryID
        LEFT JOIN Users u ON e.CreatorUserID = u.UserID
        WHERE $pub AND " . sqlActiveCategory('c') . " AND e.IsPriority = 1 AND e.EventDate >= GETDATE()
        ORDER BY e.EventDate ASC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $priorityEvents = $db->fetchAll($db->query($prioritySql, [$priOffset, $priPer]));
}

$pageTitle = 'Главная';
$bodyClass = 'page-home';
$heroSlides = [
    APP_URL . '/assets/images/hero/slide-1.jpg',
    APP_URL . '/assets/images/hero/slide-2.jpg',
    APP_URL . '/assets/images/hero/slide-3.jpg',
];
include 'includes/html_head.php';
include 'includes/site_header.php';
?>
    <main id="main">
        <section class="hero-main hero-main--photo" data-hero-slider>
            <div class="hero-main__bg" aria-hidden="true">
                <ul class="hero-main__slides">
                    <?php foreach ($heroSlides as $i => $slideSrc): ?>
                        <li class="hero-main__slide<?= $i === 0 ? ' is-active' : '' ?>" style="background-image: url('<?= escape($slideSrc) ?>')"></li>
                    <?php endforeach; ?>
                </ul>
                <div class="hero-main__overlay"></div>
            </div>
            <div class="container hero-main__content">
                <div class="hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow">Волонтёрское сообщество</p>
                    <h1 class="hero-title">Находите добрые дела рядом и помогайте без лишней суеты</h1>
                    <p class="hero-text">
                        Платформа для согласования акций: экология, социальная поддержка, животные, образование.
                        Всё в одном месте.
                    </p>
                    <div class="hero-cta">
                        <a class="btn btn-lg" href="<?= APP_URL ?>/pages/events.php">Каталог акций</a>
                        <?php if (!isLoggedIn()): ?>
                            <a class="btn btn-lg btn-secondary" href="<?= APP_URL ?>/pages/register.php">Создать профиль</a>
                        <?php else: ?>
                            <a class="btn btn-lg btn-secondary" href="<?= APP_URL ?>/pages/create_event.php">Предложить акцию</a>
                        <?php endif; ?>
                    </div>
                </div>
                <aside class="hero-aside" aria-label="Кратко о сервисе">
                    <div class="hero-stat">
                        <span class="hero-stat__num"><?= $heroTotal ?></span>
                        <span class="hero-stat__lbl">акций в открытом каталоге</span>
                    </div>
                    <p class="hero-note">Полный каталог с фильтрами — в разделе «Каталог акций» в меню. Здесь только приоритетные акции по решению модератора.</p>
                </aside>
                </div>
            </div>
            <?php if (count($heroSlides) > 1): ?>
                <div class="hero-main__dots" role="tablist" aria-label="Фоновые фото">
                    <?php foreach ($heroSlides as $i => $slideSrc): ?>
                        <button type="button" class="hero-main__dot<?= $i === 0 ? ' is-active' : '' ?>" role="tab" aria-label="Фото <?= $i + 1 ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="home-about" aria-labelledby="home-about-heading">
            <div class="container">
                <h2 id="home-about-heading" class="section-title home-about__heading">Почему волонтёрство имеет значение</h2>
                <p class="home-about__intro">
                    DobroHub помогает людям находить акции поблизости. Ниже — коротко о том, что такое добровольческая помощь и зачем она нужна нам всем.
                </p>
                <div class="home-about__grid">
                    <article class="home-about__card">
                        <span class="home-about__tag">Что это</span>
                        <h3 class="home-about__title">Волонтёрство — добровольный вклад времени и сил</h3>
                        <p>
                            Это участие без обязательной оплаты: помощь экологическим акциям, поддержка уязвимых групп, работа на благих мероприятиях.
                            Вы сами выбираете тему и нагрузку; важно не «идеально уметь всё», а желание действовать сообща.
                        </p>
                    </article>
                    <article class="home-about__card">
                        <span class="home-about__tag">Зачем</span>
                        <h3 class="home-about__title">Меньше одиночества — больше пользы для города и людей</h3>
                        <p>
                            Каждая смена делает окружение чище, а людям спокойнее: рядом есть те, кто готов выйти из дома ради общего дела.
                            Организации получают руки и внимание, а участники — смысл, новые связи и уверенность, что мир можно чуть сдвинуть к лучшему.
                        </p>
                    </article>
                    <article class="home-about__card">
                        <span class="home-about__tag">Вдохновение</span>
                        <h3 class="home-about__title">Начните с малого — это уже много</h3>
                        <p>
                            Один выход в парк с мешком, один час на раздаче или консультации — уже вклад.
                            Выберите акцию в каталоге, запишитесь и приходите: часто главное сомнение — просто ни разу не пробовали. Ваш вклад уже начинается с одного маленького шага.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <?php if (!isLoggedIn()): ?>
            <div class="banner-guest">
                <div class="container">
                    Вы в гостевом режиме — <a href="<?= APP_URL ?>/pages/login.php">вход</a> или
                    <a href="<?= APP_URL ?>/pages/register.php">регистрация</a>, чтобы записываться на акции.
                </div>
            </div>
        <?php endif; ?>

        <div class="container content-stack">
            <?php if (!empty($priorityEvents)): ?>
                <section class="priority-block" id="priority-events">
                    <div class="section-head">
                        <h2 class="section-title">Приоритетные акции</h2>
                        <p class="section-sub">Отобраны модератором, отсортированы по дате</p>
                    </div>
                    <div class="events-grid events-grid--tight">
                        <?php foreach ($priorityEvents as $event): ?>
                            <?php $priorityCover = resolvedPublicFileUrl($event['CoverImagePath'] ?? ''); ?>
                            <article class="event-card" data-category="<?= (int)($event['CategoryID'] ?? 0) ?>">
                                <div class="event-card__media">
                                    <?php if ($priorityCover !== ''): ?>
                                        <a href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$event['EventID'] ?>">
                                            <img src="<?= escape($priorityCover) ?>" alt="" loading="lazy">
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="event-card__body">
                                    <h3><?= escape($event['Title']) ?></h3>
                                    <p class="event-card__cat"><?= escape($event['CategoryName'] ?? 'Без категории') ?></p>
                                    <p class="event-card__meta"><?= formatDate($event['EventDate']) ?></p>
                                    <p class="event-card__meta"><?= escape($event['Location']) ?></p>
                                    <p class="event-card__pop">Записались: <?= (int)($event['ParticipantsCount']) ?></p>
                                    <a class="btn btn-small" href="<?= APP_URL ?>/pages/event_details.php?id=<?= (int)$event['EventID'] ?>">Подробнее</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($priTotalPages > 1): ?>
                        <nav class="pagination pagination--tight" aria-label="Страницы приоритетных акций">
                            <?php
                            $priPageHash = '#priority-events';
                            $priPageBase = APP_URL . '/index.php?pri_page=';
                            ?>
                            <?php if ($priPage > 1): ?>
                                <a href="<?= escape($priPageBase . ($priPage - 1) . $priPageHash) ?>" class="pagination__arrow" aria-label="Предыдущая страница">←</a>
                            <?php else: ?>
                                <span class="disabled pagination__arrow" aria-hidden="true">←</span>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $priTotalPages; $i++): ?>
                                <?php if ($i === $priPage): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= escape($priPageBase . $i . $priPageHash) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($priPage < $priTotalPages): ?>
                                <a href="<?= escape($priPageBase . ($priPage + 1) . $priPageHash) ?>" class="pagination__arrow" aria-label="Следующая страница">→</a>
                            <?php else: ?>
                                <span class="disabled pagination__arrow" aria-hidden="true">→</span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="cta-catalog surface-block">
                <div class="cta-catalog__inner">
                    <div>
                        <h2 class="cta-catalog__title">Каталог акций</h2>
                    </div>
                    <a class="btn btn-lg" href="<?= APP_URL ?>/pages/events.php">Смотреть все акции</a>
                </div>
            </section>
        </div>
    </main>
<?php include 'includes/html_foot.php'; ?>
