<?php
/**
 * Вкладки админ-панели. Перед include задайте $adminCurrent одним из ключей ниже.
 */
$adminCurrent = $adminCurrent ?? '';
$adminTabs = [
    'dashboard' => ['href' => APP_URL . '/pages/admin/dashboard.php', 'label' => 'Сводка'],
    'events' => ['href' => APP_URL . '/pages/admin/events.php', 'label' => 'Акции'],
    'moderation' => ['href' => APP_URL . '/pages/admin/moderation.php', 'label' => 'Модерация'],
    'outcomes' => ['href' => APP_URL . '/pages/admin/outcomes.php', 'label' => 'Итоги'],
    'categories' => ['href' => APP_URL . '/pages/admin/categories.php', 'label' => 'Категории'],
    'users' => ['href' => APP_URL . '/pages/admin/users.php', 'label' => 'Пользователи'],
    'news' => ['href' => APP_URL . '/pages/admin/news.php', 'label' => 'Новости'],
];
?>
        <nav class="admin-tabs" aria-label="Разделы администратора">
            <?php foreach ($adminTabs as $key => $tab): ?>
                <a class="<?= $adminCurrent === $key ? 'is-active' : '' ?>" href="<?= $tab['href'] ?>"><?= escape($tab['label']) ?></a>
            <?php endforeach; ?>
        </nav>
