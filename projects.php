<?php
// projects.php - страница проектов

// SEO настройки
$page_title = 'Все проекты CS2 - CS2 Мониторинг';
$page_description = 'Все проекты CS2 с лучшими серверами. Просмотрите рейтинги, описания и информацию о проектах Counter-Strike 2. Найдите проект для себя.';
$page_keywords = 'CS2, Counter-Strike 2, проекты, рейтинг проектов, лучшие проекты, CS2 проекты, серверы';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/projects.php';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/projects.css'];

require_once __DIR__ . '/includes/header.php';

// Получаем список проектов
$stmt = $db->query("
    SELECT p.*, u.username,
           (SELECT COUNT(*) FROM project_servers WHERE project_id = p.id) as servers_count
    FROM projects p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.status = 'active'
    ORDER BY p.total_rating DESC, p.created_at DESC
");
$projects = $stmt->fetchAll();
?>

<?php if ($auth->isLoggedIn()): ?>
<div style="margin-bottom: 2rem;">
    <a href="/add_project.php" class="btn btn-success">Добавить проект</a>
</div>
<?php endif; ?>

<div class="projects-grid">
    <?php if (empty($projects)): ?>
        <div style="padding: 2rem; text-align: center;">
            <p>Проекты пока не добавлены</p>
        </div>
    <?php else: ?>
        <?php foreach ($projects as $project): ?>
            <div class="project-card">
                <?php if ($project['logo']): ?>
                    <div class="project-logo">
                        <img src="<?php echo htmlspecialchars($project['logo']); ?>" alt="<?php echo htmlspecialchars($project['name']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="project-info">
                    <h3>
                        <a href="/project.php?id=<?php echo $project['id']; ?>">
                            <?php echo htmlspecialchars($project['name']); ?>
                        </a>
                    </h3>
                    
                    <div class="project-meta">
                        <span>Рейтинг: <?php echo number_format($project['total_rating']); ?></span>
                        <span>🖥️ Серверов: <?php echo $project['servers_count']; ?></span>
                    </div>
                    
                    <div class="project-description">
                        <?php echo mb_substr(strip_tags($project['description']), 0, 150); ?>...
                    </div>
                    
                    <div class="project-links">
                        <?php if ($project['website']): ?>
                            <a href="<?php echo htmlspecialchars($project['website']); ?>" target="_blank" class="btn btn-sm">🌐 Сайт</a>
                        <?php endif; ?>
                        <?php if ($project['discord']): ?>
                            <a href="https://discord.gg/<?php echo htmlspecialchars($project['discord']); ?>" target="_blank" class="btn btn-sm">💬 Discord</a>
                        <?php endif; ?>
                        <?php if ($project['vk']): ?>
                            <a href="<?php echo htmlspecialchars($project['vk']); ?>" target="_blank" class="btn btn-sm">📱 VK</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

