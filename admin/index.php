<?php
// admin/index.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: /login.php');
    exit;
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    require_once __DIR__ . '/../config/logger.php';
    
    $site_logo_text = trim($_POST['site_logo_text'] ?? '');
    
    $stmt = $db->prepare("UPDATE site_settings SET site_logo_text = :site_logo_text, site_logo = NULL WHERE id = 1");
    $stmt->bindParam(':site_logo_text', $site_logo_text);
    $stmt->execute();
    
    Logger::admin("Site logo updated", ['logo_text' => $site_logo_text]);
    
    header('Location: /admin/index.php?settings_updated=1');
    exit;
}

// Обработка очистки кеша
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    require_once __DIR__ . '/../config/logger.php';
    require_once __DIR__ . '/../config/cache.php';
    
    try {
        $cache->clear();
        Logger::admin("Cache cleared manually", ['admin_id' => $_SESSION['user_id']]);
        
        header('Location: /admin/index.php?cache_cleared=1');
        exit;
    } catch (Exception $e) {
        header('Location: /admin/index.php?cache_error=1');
        exit;
    }
}

// Получаем настройки сайта
$settings_stmt = $db->query("SELECT site_logo_text FROM site_settings WHERE id = 1 LIMIT 1");
$site_settings = $settings_stmt->fetch();
$current_site_logo_text = $site_settings['site_logo_text'] ?? '';

// Основная статистика
$stats = [];

try {
    // Серверы
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM servers WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_servers'] = $result['cnt'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM servers WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_servers'] = $result['cnt'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM servers");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_servers'] = $result['cnt'] ?? 0;

    // Пользователи
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_users'] = $result['cnt'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users WHERE is_admin = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['admin_users'] = $result['cnt'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users WHERE steam_id IS NOT NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['steam_users'] = $result['cnt'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at) = CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['new_users_today'] = $result['cnt'] ?? 0;

    // Проекты
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM projects WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_projects'] = $result['cnt'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM projects WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_projects'] = $result['cnt'] ?? 0;

    // VIP и рейтинг
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM server_vip WHERE vip_until > NOW()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['vip_servers'] = $result['cnt'] ?? 0;

    $stmt = $db->query("SELECT COALESCE(SUM(rating), 0) as total FROM servers");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_rating'] = $result['total'] ?? 0;

    $stmt = $db->query("SELECT COALESCE(SUM(peak_players), 0) as total FROM servers");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_peak_players'] = $result['total'] ?? 0;

    // Голоса (последние 24 часа)
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM server_votes WHERE voted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['votes_24h'] = $result['cnt'] ?? 0;

    // Последние серверы
    $stmt = $db->query("SELECT s.*, u.username, g.name as game_name 
                        FROM servers s 
                        LEFT JOIN users u ON s.user_id = u.id 
                        LEFT JOIN games g ON s.game_id = g.id 
                        WHERE s.status = 'pending' 
                        ORDER BY s.created_at DESC 
                        LIMIT 5");
    $pending_servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Последние пользователи
    $stmt = $db->query("SELECT id, username, email, created_at, is_admin 
                        FROM users 
                        ORDER BY created_at DESC 
                        LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Последние проекты
    $stmt = $db->query("SELECT p.*, u.username 
                        FROM projects p 
                        LEFT JOIN users u ON p.user_id = u.id 
                        WHERE p.status = 'pending' 
                        ORDER BY p.created_at DESC 
                        LIMIT 5");
    $pending_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    // Устанавливаем значения по умолчанию
    $stats = [
        'active_servers' => 0,
        'pending_servers' => 0,
        'total_servers' => 0,
        'total_users' => 0,
        'admin_users' => 0,
        'steam_users' => 0,
        'new_users_today' => 0,
        'active_projects' => 0,
        'pending_projects' => 0,
        'vip_servers' => 0,
        'total_rating' => 0,
        'total_peak_players' => 0,
        'votes_24h' => 0
    ];
    $pending_servers = [];
    $recent_users = [];
    $pending_projects = [];
}

$additional_css = ['/assets/css/admin/index.css'];
$additional_js = ['/assets/js/admin/index.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Панель администратора</h1>

<?php if (isset($_GET['settings_updated'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => showGlobalMessage('Настройки успешно обновлены!', 'success'));</script>
<?php endif; ?>
<?php if (isset($_GET['cache_cleared'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => showGlobalMessage('Кеш успешно очищен!', 'success'));</script>
<?php endif; ?>
<?php if (isset($_GET['cache_error'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => showGlobalMessage('Ошибка при очистке кеша!', 'error'));</script>
<?php endif; ?>

<!-- Основная статистика -->
<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-info">
            <h3>Активных серверов</h3>
            <p class="stat-number"><?php echo $stats['active_servers']; ?></p>
            <span class="stat-subtitle">Из <?php echo $stats['total_servers']; ?> всего</span>
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-info">
            <h3>На модерации</h3>
            <p class="stat-number"><?php echo $stats['pending_servers']; ?></p>
            <span class="stat-subtitle">Серверов ожидают</span>
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-info">
            <h3>Пользователей</h3>
            <p class="stat-number"><?php echo $stats['total_users']; ?></p>
            <span class="stat-subtitle">+<?php echo $stats['new_users_today']; ?> сегодня</span>
        </div>
    </div>
    
    <div class="stat-card info">
        <div class="stat-info">
            <h3>Активных проектов</h3>
            <p class="stat-number"><?php echo $stats['active_projects']; ?></p>
            <span class="stat-subtitle"><?php echo $stats['pending_projects']; ?> на модерации</span>
        </div>
    </div>
    
    <div class="stat-card vip">
        <div class="stat-info">
            <h3>VIP серверов</h3>
            <p class="stat-number"><?php echo $stats['vip_servers']; ?></p>
            <span class="stat-subtitle">Активных VIP</span>
        </div>
    </div>
    
    <div class="stat-card rating">
        <div class="stat-info">
            <h3>Общий рейтинг</h3>
            <p class="stat-number"><?php echo number_format($stats['total_rating']); ?></p>
            <span class="stat-subtitle"><?php echo $stats['votes_24h']; ?> голосов за 24ч</span>
        </div>
    </div>
    
    <div class="stat-card players">
        <div class="stat-info">
            <h3>Пик игроков</h3>
            <p class="stat-number"><?php echo number_format($stats['total_peak_players']); ?></p>
            <span class="stat-subtitle">Суммарно</span>
        </div>
    </div>
    
    <div class="stat-card steam">
        <div class="stat-info">
            <h3>Steam пользователей</h3>
            <p class="stat-number"><?php echo $stats['steam_users']; ?></p>
            <span class="stat-subtitle"><?php echo $stats['admin_users']; ?> администраторов</span>
        </div>
    </div>
</div>

<!-- Настройки сайта -->
<div class="admin-settings">
    <h2>Настройки сайта</h2>
    <form method="POST">
        <input type="hidden" name="update_settings" value="1">
        
        <div class="form-group">
            <label>Текст логотипа:</label>
            <input type="text" name="site_logo_text" value="<?php echo htmlspecialchars($current_site_logo_text); ?>" placeholder="CS2 Мониторинг" class="form-control">
            <p class="form-help">Введите текст, который будет отображаться в шапке сайта вместо стандартного "CS2 Мониторинг".</p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">Сохранить</button>
        </div>
    </form>
    
    <!-- Кнопка очистки кеша -->
    <div class="cache-section">
        <h3>Управление кешем</h3>
        <form method="POST" class="clear-cache-form">
            <input type="hidden" name="clear_cache" value="1">
            <button type="submit" class="cache-clear-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                    <path d="M21 3v5h-5"></path>
                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                    <path d="M8 16H3v5"></path>
                </svg>
                Обновить кеш
            </button>
        </form>
        <p class="cache-help">Очищает весь кеш сайта для обновления данных.</p>
    </div>
</div>

<!-- Быстрые действия -->
<div class="quick-actions">
    <h2>Быстрые действия</h2>
    <div class="action-buttons">
        <a href="servers.php" class="action-btn primary">
            <span>Управление серверами</span>
        </a>
        <a href="users.php" class="action-btn success">
            <span>Управление пользователями</span>
        </a>
        <a href="projects.php" class="action-btn info">
            <span>Управление проектами</span>
        </a>
        <a href="games.php" class="action-btn warning">
            <span>Управление играми</span>
        </a>
    </div>
</div>

<!-- Секции с таблицами -->
<div class="admin-tables">
    <!-- Серверы на модерации -->
    <?php if (count($pending_servers) > 0): ?>
    <div class="admin-section">
        <h2>Серверы на модерации (<?php echo count($pending_servers); ?>)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>IP:Порт</th>
                    <th>Игра</th>
                    <th>Владелец</th>
                    <th>Дата добавления</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_servers as $server): ?>
                <tr>
                    <td><?php echo $server['id']; ?></td>
                    <td><?php echo htmlspecialchars($server['name']); ?></td>
                    <td><code><?php echo htmlspecialchars($server['ip'] . ':' . $server['port']); ?></code></td>
                    <td><?php echo htmlspecialchars($server['game_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($server['username'] ?? 'N/A'); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($server['created_at'])); ?></td>
                    <td>
                        <a href="servers.php?action=approve&id=<?php echo $server['id']; ?>" class="btn-small success">Одобрить</a>
                        <a href="servers.php?action=reject&id=<?php echo $server['id']; ?>" class="btn-small danger">Отклонить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="servers.php" class="view-all-link">Показать все серверы →</a>
    </div>
    <?php endif; ?>

    <!-- Проекты на модерации -->
    <?php if (count($pending_projects) > 0): ?>
    <div class="admin-section">
        <h2>Проекты на модерации (<?php echo count($pending_projects); ?>)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Владелец</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_projects as $project): ?>
                <tr>
                    <td><?php echo $project['id']; ?></td>
                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($project['description'] ?? '', 0, 50)); ?>...</td>
                    <td><?php echo htmlspecialchars($project['username'] ?? 'N/A'); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($project['created_at'])); ?></td>
                    <td>
                        <a href="projects.php?action=approve&id=<?php echo $project['id']; ?>" class="btn-small success">Одобрить</a>
                        <a href="projects.php?action=reject&id=<?php echo $project['id']; ?>" class="btn-small danger">Отклонить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="projects.php" class="view-all-link">Показать все проекты →</a>
    </div>
    <?php endif; ?>

    <!-- Последние пользователи -->
    <div class="admin-section">
        <h2>Последние пользователи (<?php echo count($recent_users); ?>)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя пользователя</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Дата регистрации</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="role-badge <?php echo $user['is_admin'] ? 'admin' : 'user'; ?>">
                            <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                        </span>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="users.php" class="view-all-link">Показать всех пользователей →</a>
    </div>
</div>

<style>
/* Статистические карточки */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #667eea;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-card.primary { border-left-color: #667eea; }
.stat-card.warning { border-left-color: #f59e0b; }
.stat-card.success { border-left-color: #10b981; }
.stat-card.info { border-left-color: #3b82f6; }
.stat-card.vip { border-left-color: #8b5cf6; }
.stat-card.rating { border-left-color: #f59e0b; }
.stat-card.players { border-left-color: #ec4899; }
.stat-card.steam { border-left-color: #06b6d4; }

.stat-info h3 {
    margin: 0 0 0.5rem 0;
    color: #666;
    font-size: 0.85rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
    color: #1f2937;
    line-height: 1;
}

.stat-subtitle {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Быстрые действия */
.quick-actions {
    margin: 3rem 0;
}

.quick-actions h2 {
    margin-bottom: 1.5rem;
    color: #1f2937;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.action-btn {
    display: block;
    text-align: center;
    padding: 1rem 1.5rem;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    text-decoration: none;
    color: #1f2937;
    font-weight: 500;
    transition: all 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.action-btn.primary:hover { border-color: #667eea; color: #667eea; }
.action-btn.success:hover { border-color: #10b981; color: #10b981; }
.action-btn.info:hover { border-color: #3b82f6; color: #3b82f6; }
.action-btn.warning:hover { border-color: #f59e0b; color: #f59e0b; }

/* Таблицы */
.admin-tables {
    margin-top: 3rem;
}

.admin-section {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.admin-section h2 {
    margin: 0 0 1.5rem 0;
    color: #1f2937;
    font-size: 1.25rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table thead {
    background: #f9fafb;
}

.data-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}

.data-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.2s ease;
}

.data-table tbody tr:hover {
    background: #f9fafb;
}

.data-table td {
    padding: 1rem;
    color: #374151;
}

.data-table code {
    background: #f3f4f6;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
    color: #1f2937;
    font-family: 'Courier New', monospace;
}

.btn-small {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.2s ease;
    margin-right: 0.5rem;
}

.btn-small.success {
    background: #10b981;
    color: white;
}

.btn-small.success:hover {
    background: #059669;
}

.btn-small.danger {
    background: #ef4444;
    color: white;
}

.btn-small.danger:hover {
    background: #dc2626;
}

.role-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.role-badge.admin {
    background: #fef3c7;
    color: #92400e;
}

.role-badge.user {
    background: #e0e7ff;
    color: #3730a3;
}

.view-all-link {
    display: inline-block;
    margin-top: 1rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.2s ease;
}

.view-all-link:hover {
    color: #5a67d8;
    text-decoration: underline;
}

/* Кнопка очистки кеша */
.cache-clear-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}

.cache-clear-btn:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
    transform: translateY(-1px);
}

.cache-clear-btn svg {
    animation: none;
}

.cache-clear-btn:hover svg {
    animation: rotate 1s linear;
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Адаптивность */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.5rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
}
</style>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
