<?php
// profile.php

// SEO настройки
$page_title = 'Профиль - CS2 Мониторинг';
$page_description = 'Управляйте своим профилем CS2 мониторинга. Просматривайте свои серверы, проекты, баланс и настройки аккаунта.';
$page_keywords = 'CS2, профиль, личный кабинет, настройки, баланс';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/profile.php';

// Подключаем дополнительные CSS
$additional_css = ['/assets/css/profile.css'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/steam_auth.php';

if (!$auth->isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user_id = $auth->getUserId();
$steamAuth = new SteamAuth($db);

// Получаем информацию о пользователе
$user_stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$user_stmt->bindParam(":id", $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch();

// Получаем серверы пользователя
$servers_stmt = $db->prepare("SELECT s.*, g.name as game_name, gm.name as game_mode_name, m.name as map_name
                              FROM servers s
                              LEFT JOIN games g ON s.game_id = g.id
                              LEFT JOIN game_modes gm ON s.game_mode_id = gm.id
                              LEFT JOIN maps m ON s.map_id = m.id
                              WHERE s.user_id = :user_id
                              ORDER BY s.created_at DESC");
$servers_stmt->bindParam(":user_id", $user_id);
$servers_stmt->execute();
$user_servers = $servers_stmt->fetchAll();

// Получаем проекты пользователя
$projects_stmt = $db->prepare("SELECT p.*, 
                               (SELECT COUNT(*) FROM project_servers WHERE project_id = p.id) as servers_count
                               FROM projects p
                               WHERE p.user_id = :user_id
                               ORDER BY p.created_at DESC");
$projects_stmt->bindParam(":user_id", $user_id);
$projects_stmt->execute();
$user_projects = $projects_stmt->fetchAll();
?>

<?php if (isset($_GET['steam_linked'])): ?>
    <div class="alert alert-success">
        Steam аккаунт успешно привязан!
    </div>
<?php endif; ?>

<?php if (isset($_GET['steam_error'])): ?>
    <div class="alert alert-error">
        Ошибка при привязке Steam аккаунта. Возможно, этот Steam аккаунт уже привязан к другому пользователю.
    </div>
<?php endif; ?>

<!-- Баланс -->
<div class="balance-section">
    <?php
    $user_balance = 0;
    try {
        if (isset($user['balance'])) {
            $user_balance = floatval($user['balance']);
        }
    } catch (Exception $e) {
        $user_balance = 0;
    }
    ?>
    <div class="balance-display">
        <span class="balance-label">Текущий баланс:</span>
        <span class="balance-amount"><?php echo number_format($user_balance, 2, '.', ' '); ?> ₽</span>
    </div>
    <a href="/balance.php" class="btn btn-success btn-balance">Пополнить баланс</a>
</div>

<div class="profile-form-container">
    <h3>Информация об аккаунте</h3>
    <div class="form-group">
        <label>Имя пользователя:</label>
        <p><?php echo htmlspecialchars($user['username']); ?></p>
    </div>
    
    <div class="form-group">
        <label>Email:</label>
        <p><?php echo htmlspecialchars($user['email'] ?? 'Не указан'); ?></p>
    </div>
    
    <div class="form-group">
        <label>Steam аккаунт:</label>
        <?php if (!empty($user['steam_id'])): ?>
            <p class="steam-linked">
                ✓ Привязан (Steam ID: <?php echo htmlspecialchars($user['steam_id']); ?>)
            </p>
        <?php else: ?>
            <p class="steam-unlinked">Не привязан</p>
            <a href="/steam_auth.php" class="btn btn-steam btn-steam-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="steam-icon">
                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                </svg>
                Привязать Steam
            </a>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <label>Дата регистрации:</label>
        <p><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></p>
    </div>
    
    <div class="form-group">
        <label>Всего серверов:</label>
        <p><?php echo count($user_servers); ?></p>
    </div>
    
    <div class="form-group">
        <label>Всего проектов:</label>
        <p><?php echo count($user_projects); ?></p>
    </div>
</div>

<h3 class="section-title-spacing" id="projects">Мои проекты</h3>

<div class="section-actions">
    <a href="/add_project.php" class="btn btn-success">Добавить проект</a>
</div>

<div class="projects-grid">
    <?php if (empty($user_projects)): ?>
        <div class="empty-state">
            <p>У вас пока нет проектов</p>
            <a href="/add_project.php" class="btn btn-primary empty-state-action">Добавить проект</a>
        </div>
    <?php else: ?>
        <?php foreach ($user_projects as $project): ?>
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
                    
                    <div class="project-status">
                        <span class="status-badge status-<?php echo $project['status']; ?>">
                            <?php 
                            if ($project['status'] === 'active') echo 'Активен';
                            elseif ($project['status'] === 'pending') echo 'На модерации';
                            else echo 'Отклонен';
                            ?>
                        </span>
                    </div>
                    
                    <div class="project-actions">
                        <a href="/project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">Просмотр</a>
                        <a href="/edit_project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-warning">Редактировать</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<h3 class="section-title-spacing" id="servers">Мои серверы</h3>

<div class="profile-servers-table">
    <?php if (empty($user_servers)): ?>
        <div class="table-empty-state">
            <p>У вас пока нет серверов</p>
            <a href="/add_server.php" class="btn btn-primary table-empty-action">Добавить сервер</a>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>IP Адрес</th>
                    <th>Игроки</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_servers as $server): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($server['name']); ?></td>
                        <td><?php echo htmlspecialchars($server['ip'] . ':' . $server['port']); ?></td>
                        <td><?php echo $server['current_players']; ?> / <?php echo $server['max_players']; ?></td>
                        <td>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            switch($server['status']) {
                                case 'active':
                                    $status_class = 'status-active';
                                    $status_text = 'Активен';
                                    break;
                                case 'pending':
                                    $status_class = 'status-pending';
                                    $status_text = 'На модерации';
                                    break;
                                case 'rejected':
                                    $status_class = 'status-rejected';
                                    $status_text = 'Отклонен';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td>
                            <a href="/edit_server.php?id=<?php echo $server['id']; ?>" class="btn btn-primary btn-edit-sm">Редактировать</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<h3 class="section-title-spacing" id="settings">Настройки</h3>

<div class="profile-form-container">
    <h3>Настройки аккаунта</h3>
    
    <div class="form-group">
        <label>Привязка аккаунтов:</label>
        <div class="account-settings-group">
            <div class="steam-setting-item">
                <strong>Steam:</strong>
                <?php if (!empty($user['steam_id'])): ?>
                    <span class="steam-linked-badge">✓ Привязан</span>
                    <span class="steam-id-badge">
                        (ID: <?php echo htmlspecialchars($user['steam_id']); ?>)
                    </span>
                <?php else: ?>
                    <a href="/steam_auth.php" class="btn btn-steam btn-steam-inline">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="steam-icon">
                            <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                        </svg>
                        Привязать Steam
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label>Безопасность:</label>
        <div class="security-group">
            <?php if (!empty($user['email'])): ?>
                <div class="security-item">
                    <strong>Email:</strong> 
                    <span class="security-badge">✓ Подтвержден</span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($user['password'])): ?>
                <div class="security-item">
                    <strong>Пароль:</strong> 
                    <span class="security-badge">✓ Установлен</span>
                </div>
            <?php endif; ?>
            
            <?php if (empty($user['password']) && empty($user['email'])): ?>
                <div class="security-note">
                    Вы вошли через Steam. Для повышения безопасности привяжите email и установите пароль.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="form-group">
        <label>Уведомления:</label>
        <div class="notifications-note">
            Настройки уведомлений будут доступны в будущих обновлениях.
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>

