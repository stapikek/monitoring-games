<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - CS2 Мониторинг</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/admin_modals.css">
    <?php 
    // Подключаем дополнительные CSS файлы если они указаны
    if (isset($additional_css)) {
        foreach ($additional_css as $css_file) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file) . '">' . "\n    ";
        }
    }
    ?>
</head>
<body>
    <div class="admin-layout">
        <button class="admin-mobile-toggle" id="admin-mobile-toggle">
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="sidebar-header">
                <h2>Панель управления</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/" class="nav-item">Главная</a>
                <a href="/admin/servers.php" class="nav-item">Серверы</a>
                <a href="/admin/projects.php" class="nav-item">Проекты</a>
                <a href="/admin/users.php" class="nav-item">Пользователи</a>
                <a href="/admin/games.php" class="nav-item">Игры</a>
                <a href="/admin/modes.php" class="nav-item">Режимы</a>
                <a href="/admin/maps.php" class="nav-item">Карты</a>
                <a href="/admin/tags.php" class="nav-item">Теги</a>
                <a href="/admin/payment_systems.php" class="nav-item">Платежные системы</a>
                <a href="/admin/hostings.php" class="nav-item">Хостинги</a>
                <div class="nav-divider"></div>
                <a href="/" class="nav-item">На сайт</a>
                <a href="/logout.php" class="nav-item">Выйти</a>
            </nav>
        </aside>
        <main class="admin-content">
            <div class="container">

