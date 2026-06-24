<?php
/**
 * Единая шапка HTML. $pageTitle — заголовок вкладки; $headExtra — доп. теги в <head>.
 */
if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = APP_NAME;
} else {
    $pageTitle = $pageTitle . ' — ' . APP_NAME;
}
$bodyClass = isset($bodyClass) ? trim((string)$bodyClass) : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            try {
                if (localStorage.getItem('dobrohub-theme') === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>
    <title><?= escape($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Mono:wght@400;700&display=swap" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="icon" href="<?= APP_URL ?>/assets/img/dobrohub-logo.png" type="image/png">
    <?= $headExtra ?? '' ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . escape($bodyClass) . '"' : '' ?>>
