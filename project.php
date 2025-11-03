<?php
// project.php - страница проекта

$projectId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($projectId <= 0) {
    header('Location: /projects.php');
    exit;
}

// Получаем подключение к базе данных для получения данных проекта
require_once __DIR__ . '/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Получаем проект
$stmt = $db->prepare("
    SELECT p.*, u.username
    FROM projects p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = :id AND p.status = 'active'
");
$stmt->bindParam(':id', $projectId);
$stmt->execute();
$project = $stmt->fetch();

if (!$project) {
    header('Location: /projects.php');
    exit;
}

// SEO настройки
$page_title = htmlspecialchars($project['name']) . ' - Проект CS2';
$page_description = strip_tags($project['description'] ?? '');
if (mb_strlen($page_description) > 160) {
    $page_description = mb_substr($page_description, 0, 157) . '...';
}
$page_keywords = 'CS2, Counter-Strike 2, проект, серверы, ' . htmlspecialchars($project['name']);
$page_image = !empty($project['logo']) ? 'https://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($project['logo']) : null;
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/project.php?id=' . $projectId;

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/project.css'];

require_once __DIR__ . '/includes/header.php';

// Получаем серверы проекта
$stmt = $db->prepare("
    SELECT s.*, m.name as map_name, g.icon as game_icon
    FROM project_servers ps
    INNER JOIN servers s ON ps.server_id = s.id
    LEFT JOIN maps m ON s.map_id = m.id
    LEFT JOIN games g ON s.game_id = g.id
    WHERE ps.project_id = :project_id AND s.status = 'active'
");
$stmt->bindParam(':project_id', $projectId);
$stmt->execute();
$servers = $stmt->fetchAll();

// Обновляем общий рейтинг проекта
$totalRating = array_sum(array_column($servers, 'rating'));
$updateStmt = $db->prepare("UPDATE projects SET total_rating = :rating WHERE id = :id");
$updateStmt->bindParam(':rating', $totalRating);
$updateStmt->bindParam(':id', $projectId);
$updateStmt->execute();

// BB-коды в HTML
function parseBBCode($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace('/\[b\](.*?)\[\/b\]/is', '<strong>$1</strong>', $text);
    $text = preg_replace('/\[i\](.*?)\[\/i\]/is', '<em>$1</em>', $text);
    $text = preg_replace('/\[u\](.*?)\[\/u\]/is', '<u>$1</u>', $text);
    $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/is', '<a href="$1" target="_blank">$2</a>', $text);
    $text = nl2br($text);
    return $text;
}
?>

<div class="project-page">
    <!-- Баннер и статистика -->
    <div class="project-top">
        <?php if ($project['logo']): ?>
            <div class="project-banner">
                <div class="project-banner-overlay">
                    <h2><?php echo mb_strtoupper(htmlspecialchars($project['name'])); ?></h2>
                    <?php if ($project['website']): ?>
                        <p><?php echo htmlspecialchars(parse_url($project['website'], PHP_URL_HOST) ?: $project['website']); ?></p>
                    <?php endif; ?>
                </div>
                <img src="<?php echo htmlspecialchars($project['logo']); ?>" alt="<?php echo htmlspecialchars($project['name']); ?>">
            </div>
        <?php endif; ?>
        
        <div class="project-stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($servers); ?></div>
                <div class="stat-label">СЕРВЕРОВ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($servers, 'current_players')); ?></div>
                <div class="stat-label">СЕЙЧАС ИГРАЮТ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalRating, 0, '.', ' '); ?></div>
                <div class="stat-label">РЕЙТИНГ</div>
            </div>
        </div>
    </div>
    
    <!-- Кнопки социальных сетей -->
    <div class="project-social-buttons">
        <?php if ($project['website']): ?>
            <a href="<?php echo htmlspecialchars($project['website']); ?>" target="_blank" class="social-btn website">
                <span class="social-icon">🌐</span>
                <span class="social-text">Сайт: <?php echo htmlspecialchars(parse_url($project['website'], PHP_URL_HOST) ?: basename($project['website'])); ?></span>
            </a>
        <?php endif; ?>
        <?php if ($project['discord']): ?>
            <a href="https://discord.gg/<?php echo htmlspecialchars($project['discord']); ?>" target="_blank" class="social-btn discord">
                <span class="social-icon">💬</span>
                <span class="social-text">Discord: <?php echo htmlspecialchars($project['discord']); ?></span>
            </a>
        <?php endif; ?>
        <?php if ($project['vk']): ?>
            <a href="<?php echo htmlspecialchars($project['vk']); ?>" target="_blank" class="social-btn vk">
                <span class="social-icon">📱</span>
                <span class="social-text">ВКонтакте: <?php echo htmlspecialchars(basename($project['vk'])); ?></span>
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Описание проекта -->
    <div class="project-description">
        <h2>Описание проекта</h2>
        <div class="description-content">
            <?php echo parseBBCode($project['description']); ?>
        </div>
    </div>
    
    <!-- Список серверов -->
    <div class="project-servers-section">
        <h2>Серверы проекта</h2>
        
        <?php if (empty($servers)): ?>
            <div class="no-servers">
                <p>Нет активных серверов</p>
            </div>
        <?php else: ?>
            <div class="servers-grid">
                <?php foreach ($servers as $server): ?>
                    <div class="server-card">
                        <div class="server-icon">
                            <?php if (!empty($server['game_icon'])): ?>
                                <img src="<?php echo htmlspecialchars($server['game_icon']); ?>" alt="game" class="server-game-img">
                            <?php else: ?>
                                <span class="game-icon-fallback"></span>
                            <?php endif; ?>
                        </div>
                        <div class="server-details">
                            <div class="server-name">
                                <a href="/server.php?id=<?php echo $server['id']; ?>">
                                    <?php echo htmlspecialchars($server['name']); ?>
                                </a>
                            </div>
                            <div class="server-meta">
                                <span class="server-players">
                                    👥 <?php echo $server['current_players']; ?>/<?php echo $server['max_players']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

