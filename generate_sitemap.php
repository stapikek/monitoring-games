<?php
/**
 * Генератор динамического sitemap.xml
 * Включает все публичные страницы, серверы и проекты
 */

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Получаем домен динамически из переменных окружения или $_SERVER
// Примечание: при запуске из CLI может потребоваться установить HTTP_HOST через переменную окружения
$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? getenv('HTTP_HOST') ?? 'example.com');

// Начинаем формировать XML
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Статичные страницы
$staticPages = [
    ['/', '1.0', 'daily'],
    ['/projects.php', '0.9', 'daily'],
    ['/shop.php', '0.8', 'weekly'],
    ['/login.php', '0.5', 'monthly'],
    ['/register.php', '0.5', 'monthly'],
];

foreach ($staticPages as $page) {
    $xml .= "    <url>\n";
    $xml .= "        <loc>" . $baseUrl . htmlspecialchars($page[0]) . "</loc>\n";
    $xml .= "        <changefreq>" . $page[2] . "</changefreq>\n";
    $xml .= "        <priority>" . $page[1] . "</priority>\n";
    $xml .= "    </url>\n";
}

// Добавляем активные серверы
try {
    $servers_stmt = $db->query("SELECT id, last_updated FROM servers WHERE status = 'active' ORDER BY last_updated DESC LIMIT 1000");
    $servers = $servers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($servers as $server) {
        $xml .= "    <url>\n";
        $xml .= "        <loc>" . $baseUrl . "/server.php?id=" . $server['id'] . "</loc>\n";
        $xml .= "        <changefreq>hourly</changefreq>\n";
        $xml .= "        <priority>0.8</priority>\n";
        if ($server['last_updated']) {
            $xml .= "        <lastmod>" . date('Y-m-d', strtotime($server['last_updated'])) . "</lastmod>\n";
        }
        $xml .= "    </url>\n";
    }
} catch (PDOException $e) {
    error_log("Sitemap generation error for servers: " . $e->getMessage());
}

// Добавляем активные проекты
try {
    $projects_stmt = $db->query("SELECT id, created_at FROM projects WHERE status = 'active' ORDER BY created_at DESC LIMIT 500");
    $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($projects as $project) {
        $xml .= "    <url>\n";
        $xml .= "        <loc>" . $baseUrl . "/project.php?id=" . $project['id'] . "</loc>\n";
        $xml .= "        <changefreq>daily</changefreq>\n";
        $xml .= "        <priority>0.7</priority>\n";
        if ($project['created_at']) {
            $xml .= "        <lastmod>" . date('Y-m-d', strtotime($project['created_at'])) . "</lastmod>\n";
        }
        $xml .= "    </url>\n";
    }
} catch (PDOException $e) {
    error_log("Sitemap generation error for projects: " . $e->getMessage());
}

$xml .= '</urlset>';

// Сохраняем sitemap
file_put_contents(__DIR__ . '/sitemap.xml', $xml);

echo "Sitemap generated successfully!\n";
echo "Total URLs: " . (count($staticPages) + count($servers ?? []) + count($projects ?? [])) . "\n";

