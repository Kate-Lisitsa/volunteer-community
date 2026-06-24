<?php
// includes/menu.php - Навигация для трех режимов
?>
<ul class="nav-list">
    <li><a href="<?= APP_URL ?>/index.php">Главная</a></li>
    <li><a href="<?= APP_URL ?>/pages/events.php">Каталог акций</a></li>
    <li><a href="<?= APP_URL ?>/pages/news.php">Новости</a></li>
    
    <?php if (isLoggedIn()): ?>
        <!-- Авторизованный пользователь (включая администратора) -->
        <li><a href="<?= APP_URL ?>/pages/create_event.php">Предложить акцию</a></li>
        <li><a href="<?= APP_URL ?>/pages/profile.php">Личный кабинет</a></li>
        
        <?php if (isAdmin()): ?>
            <!-- Только для администратора -->
            <li><a href="<?= APP_URL ?>/pages/admin/dashboard.php">Админ</a></li>
            <li><a href="<?= APP_URL ?>/pages/admin/reports.php">Отчёты</a></li>
        <?php endif; ?>
        
        <li><a href="<?= APP_URL ?>/pages/logout.php">Выйти</a></li>
        
    <?php else: ?>
        <!-- Гость (неавторизованный) -->
        <li><a href="<?= APP_URL ?>/pages/login.php">Вход</a></li>
        <li><a href="<?= APP_URL ?>/pages/register.php">Регистрация</a></li>
    <?php endif; ?>
</ul>