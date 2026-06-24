<?php
/** Общая шапка сайта с навигацией */
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= APP_URL ?>/index.php">
            <img class="logo-img" src="<?= APP_URL ?>/assets/img/dobrohub-logo.png" width="44" height="44" alt="DobroHub — логотип: руки и символ помощи">
            <span class="logo-text"><?= escape(APP_NAME) ?></span>
        </a>
        <div class="header-actions">
            <button type="button" class="theme-toggle" id="themeToggle" aria-label="Включить тёмную тему" title="Сменить тему">
                <span class="theme-toggle__icon theme-toggle__icon--light" aria-hidden="true">☀</span>
                <span class="theme-toggle__icon theme-toggle__icon--dark" aria-hidden="true">☾</span>
            </button>
            <button type="button" class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="mainNav" aria-label="Меню"></button>
        </div>
        <nav class="main-nav" id="mainNav" aria-label="Основная навигация">
            <?php include __DIR__ . '/menu.php'; ?>
        </nav>
    </div>
</header>
