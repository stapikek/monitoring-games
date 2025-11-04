<?php
// includes/header.php - шапка сайта

session_start();

// Устанавливаем заголовки кеширования для статических ресурсов
$static_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'];
$current_extension = pathinfo($_SERVER['REQUEST_URI'] ?? '', PATHINFO_EXTENSION);

if (in_array($current_extension, $static_extensions)) {
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
}

if (!isset($database)) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

if (!isset($auth)) {
    require_once __DIR__ . '/../config/auth.php';
    $auth = new Auth($db);
}

// Получаем настройки сайта (логотип)
try {
    $settings_stmt = $db->query("SELECT site_logo_text FROM site_settings LIMIT 1");
    $site_settings = $settings_stmt->fetch();
    $site_logo_text = $site_settings['site_logo_text'] ?? null;
    $site_logo_length = mb_strlen($site_logo_text ?? '', 'UTF-8');
} catch (PDOException $e) {
    // Игнорируем если таблицы нет
    $site_logo_text = null;
    $site_logo_length = 0;
}

// Получаем баланс пользователя для навигации
$nav_balance = 0;
$servers_count = 0;
$nav_username = '';
if ($auth->isLoggedIn()) {
    try {
        $user_stmt = $db->prepare("SELECT balance, username FROM users WHERE id = :id LIMIT 1");
        $user_stmt->bindParam(":id", $_SESSION['user_id']);
        $user_stmt->execute();
        $user_data = $user_stmt->fetch();
        $nav_balance = floatval($user_data['balance'] ?? 0);
        $nav_username = $user_data['username'] ?? '';
        
        $servers_stmt = $db->prepare("SELECT COUNT(*) as count FROM servers WHERE user_id = :user_id AND status = 'active'");
        $servers_stmt->bindParam(":user_id", $_SESSION['user_id']);
        $servers_stmt->execute();
        $servers_data = $servers_stmt->fetch();
        $servers_count = intval($servers_data['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("Navigation balance error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'CS2 Мониторинг серверов - Найти лучший CS2 сервер'; ?></title>
    
    <?php
    // SEO метатеги
    $page_description = isset($page_description) ? htmlspecialchars($page_description) : 'Мониторинг CS2 серверов. Находите лучшие серверы Counter-Strike 2, проверяйте онлайн, карту, пинг и игроков в реальном времени. Добавляйте свои серверы и развивайте свой проект.';
    $page_keywords = isset($page_keywords) ? htmlspecialchars($page_keywords) : 'CS2, Counter-Strike 2, мониторинг серверов, CS2 сервер, онлайн серверы, рейтинг серверов, серверы CS2, лучшие серверы';
    ?>
    
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="<?php echo $page_keywords; ?>">
    <meta name="author" content="CS2 Мониторинг">
    <meta name="robots" content="index, follow">
    <meta name="language" content="Russian">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo isset($page_title) ? htmlspecialchars($page_title) : 'CS2 Мониторинг'; ?>">
    <meta property="og:description" content="<?php echo $page_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:site_name" content="CS2 Мониторинг">
    
    <?php if (isset($page_image)): ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($page_image); ?>">
    <?php endif; ?>
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo isset($page_title) ? htmlspecialchars($page_title) : 'CS2 Мониторинг'; ?>">
    <meta name="twitter:description" content="<?php echo $page_description; ?>">
    
    <script>
    // Применяем тему ДО загрузки CSS для предотвращения мерцания
    (function() {
        const theme = localStorage.getItem('siteTheme') || 
                      (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <?php 
    // Подключаем дополнительные CSS файлы если они указаны
    if (isset($additional_css)) {
        foreach ($additional_css as $css_file) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file) . '">' . "\n    ";
        }
    }
    ?>
    
    <?php if (isset($canonical_url)): ?>
        <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>">
    <?php endif; ?>
    
    <?php
    // JSON-LD структурированные данные (Schema.org)
    if (!isset($jsonLd)) {
        $siteUrl = 'https://' . $_SERVER['HTTP_HOST'];
        $jsonLd = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => "CS2 Мониторинг",
            "url" => $siteUrl,
            "description" => $page_description,
            "inLanguage" => "ru"
        ];
    }
    ?>
    <script type="application/ld+json">
    <?php echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>
</head>
<body<?php 
    // Используем user_balance если он передан из страницы, иначе nav_balance из header
    $final_balance = isset($user_balance) ? $user_balance : $nav_balance;
    if ($final_balance > 0 || isset($user_balance)): 
?> data-balance="<?php echo htmlspecialchars($final_balance); ?>"<?php endif; ?>>
    <header>
        <nav>
            <div class="nav-container">
                <a href="/" class="logo">
                    <?php if (!empty($site_logo_text)): ?>
                        <strong<?php echo ($site_logo_length <= 3) ? ' data-text-length="short"' : ''; ?>><?php echo htmlspecialchars($site_logo_text); ?></strong>
                    <?php else: ?>
                        <strong>CS2 Мониторинг</strong>
                    <?php endif; ?>
                </a>
                <button class="mobile-menu-toggle" id="mobile-menu-toggle">
                    <div class="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </button>
                <ul class="nav-menu">
                    <li><a href="/">Главная</a></li>
                    <li><a href="/projects.php">Проекты</a></li>
                    <li><a href="/shop.php">Магазин</a></li>
                    <li><a href="/hostings.php">Хостинги</a></li>
                    <li><a href="/drop_timer.php">Таймер выпадения дропа</a></li>
                    <li>
                        <button class="theme-toggle" id="themeToggle" title="Переключить тему">
                            <svg class="theme-icon theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                            </svg>
                            <svg class="theme-icon theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                        </button>
                    </li>
                    <?php if ($auth->isLoggedIn()): ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">
                                Профиль
                            </a>
                            <div class="dropdown-menu">
                                <div class="dropdown-header">
                                    <div class="dropdown-balance">
                                        <span>Баланс:</span>
                                        <span class="balance-amount"><?php echo number_format($nav_balance, 2, '.', ' '); ?> ₽</span>
                                    </div>
                                    <a href="/balance.php" class="dropdown-balance-btn">Пополнить баланс</a>
                                </div>
                                <ul class="dropdown-items">
                                    <li><a href="/add_server.php">Добавить сервер</a></li>
                                    <li><a href="/profile.php#servers">Ваши сервера</a></li>
                                    <li><a href="/profile.php#projects">Мои проекты</a></li>
                                    <li><a href="/profile.php">Настройки</a></li>
                                    <?php if ($auth->isAdmin()): ?>
                                        <li class="dropdown-divider"></li>
                                        <li><a href="/admin/">Админ-панель</a></li>
                                    <?php endif; ?>
                                    <li class="dropdown-divider"></li>
                                    <li><a href="/logout.php">Выйти</a></li>
                                </ul>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">
                                Войти / Регистрация
                            </a>
                            <div class="dropdown-menu">
                                <ul class="dropdown-items">
                                    <li><a href="/login.php">Войти</a></li>
                                    <li><a href="/register.php">Регистрация</a></li>
                                    <li class="dropdown-divider"></li>
                                    <li><a href="/steam_auth.php" class="btn-steam-dropdown">
                                        Войти через Steam
                                    </a></li>
                                </ul>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>
    <main>
        <div class="container">


