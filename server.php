<?php
// server.php - страница просмотра сервера

$server_id = intval($_GET['id'] ?? 0);

if ($server_id == 0) {
    header("Location: /");
    exit;
}

// Получаем подключение к базе данных для получения данных сервера
require_once __DIR__ . '/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Получаем информацию о сервере
$stmt = $db->prepare("SELECT s.*, u.username as owner_name, g.name as game_name, gm.name as game_mode_name, m.name as map_name, m.code as map_code, m.image as map_image,
                      COALESCE(s.peak_players, s.max_players, 0) as peak_players
                      FROM servers s
                      LEFT JOIN users u ON s.user_id = u.id
                      LEFT JOIN games g ON s.game_id = g.id
                      LEFT JOIN game_modes gm ON s.game_mode_id = gm.id
                      LEFT JOIN maps m ON s.map_id = m.id
                      WHERE s.id = :id");
$stmt->bindParam(":id", $server_id);
$stmt->execute();
$server = $stmt->fetch();

if (!$server) {
    header("Location: /");
    exit;
}

// SEO настройки
$page_title = htmlspecialchars($server['name']) . ' - CS2 Сервер';
$page_description = 'CS2 сервер ' . htmlspecialchars($server['name']) . '. Игроки: ' . $server['current_players'] . '/' . $server['max_players'] . '. Рейтинг: ' . $server['rating'] . '. Подключись сейчас!';
$page_keywords = 'CS2, Counter-Strike 2, сервер, ' . htmlspecialchars($server['name']) . ', ' . htmlspecialchars($server['game_name']) . ', ' . htmlspecialchars($server['game_mode_name']);
$page_image = !empty($server['map_image']) ? 'https://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($server['map_image']) : null;
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/server.php?id=' . $server_id;

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/server.css'];
$additional_js = ['/assets/js/server.js'];

require_once __DIR__ . '/includes/header.php';

// Добавляем data-атрибут для JavaScript
echo '<div data-server-id="' . $server_id . '" class="server-data-attr"></div>';

// Используем рейтинг из базы данных (уже включает голоса и купленный рейтинг)
$server['rating'] = intval($server['rating'] ?? 0);

// Проверяем, может ли текущий пользователь голосовать
$can_vote = false;
$hours_left = 0;
$minutes_left = 0;
if ($auth->isLoggedIn()) {
    try {
        $vote_check_stmt = $db->prepare("
            SELECT voted_at 
            FROM server_votes 
            WHERE server_id = :server_id AND user_id = :user_id 
            ORDER BY voted_at DESC 
            LIMIT 1
        ");
        $vote_check_stmt->bindParam(":server_id", $server_id);
        $vote_check_stmt->bindParam(":user_id", $_SESSION['user_id']);
        $vote_check_stmt->execute();
        
        if ($vote_check_stmt->rowCount() > 0) {
            $last_vote = $vote_check_stmt->fetch();
            $vote_date = new DateTime($last_vote['voted_at']);
            $now = new DateTime();
            
            // Вычисляем разницу в секундах для точности
            $seconds_passed = $now->getTimestamp() - $vote_date->getTimestamp();
            $seconds_in_24h = 24 * 60 * 60; // 86400 секунд в 24 часах
            
            if ($seconds_passed >= $seconds_in_24h) {
                $can_vote = true;
            } else {
                $seconds_left = $seconds_in_24h - $seconds_passed;
                $hours_left = floor($seconds_left / 3600);
                $minutes_left = floor(($seconds_left % 3600) / 60);
                
                // Если осталось меньше часа, показываем минуты
                if ($hours_left == 0 && $minutes_left > 0) {
                    // Покажем только минуты
                } elseif ($hours_left > 0) {
                    // Покажем часы, минуты не нужны для отображения если есть часы
                }
            }
        } else {
            $can_vote = true;
        }
    } catch (PDOException $e) {
        $can_vote = true;
    }
}

// Получаем теги сервера
try {
    $tags_stmt = $db->prepare("SELECT t.* FROM tags t
                               INNER JOIN server_tags st ON t.id = st.tag_id
                               WHERE st.server_id = :id
                               ORDER BY t.name");
    $tags_stmt->bindParam(":id", $server_id);
    $tags_stmt->execute();
    $server_tags = $tags_stmt->fetchAll();
} catch (PDOException $e) {
    $server_tags = [];
}

// Вычисляем количество дней с момента добавления
$created_date = new DateTime($server['created_at']);
$now = new DateTime();
$days_diff = $created_date->diff($now)->days;

// Получаем последнее обновление
$last_updated = isset($server['updated_at']) && !empty($server['updated_at']) ? new DateTime($server['updated_at']) : $created_date;
$minutes_ago = $now->diff($last_updated)->i + ($now->diff($last_updated)->h * 60);
?>

<div class="container server-container">
    <!-- Заголовок и основные блоки -->
    <div class="server-main-title">
        <div class="server-title-row">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32" fill="#667eea" class="server-header-icon">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <h1><?php echo htmlspecialchars($server['name']); ?></h1>
            <span class="game-badge">CS2</span>
        </div>
        
        <div class="server-description">
            Сервер CS2
        </div>
        
        <!-- Три блока информации -->
        <div class="server-info-blocks">
            <div class="info-block">
                <div class="info-block-label">КАРТА</div>
                <div class="info-block-value" id="server-map-display"><span class="loading-text">Загрузка...</span></div>
            </div>
            
            <div class="info-block">
                <div class="info-block-label">ОНЛАЙН</div>
                <div class="info-block-value">
                    <span id="current-players"><?php echo $server['current_players']; ?></span> / 
                    <span id="max-players"><?php echo $server['max_players']; ?></span>
                </div>
            </div>
            
            <div class="info-block">
                <div class="info-block-label">ГОЛОСОВАНИЕ</div>
                <div class="info-block-value">
                    <?php if ($auth->isLoggedIn() && $can_vote): ?>
                        <button type="button" onclick="voteForServer(<?php echo $server['id']; ?>)" class="vote-button-large">
                            <span>👍</span>
                            <span id="vote-btn-text">Голосовать за сервер</span>
                            <span id="vote-btn-spinner" class="vote-btn-spinner">🔄</span>
                        </button>
                    <?php elseif ($auth->isLoggedIn()): ?>
                        <div class="vote-cooldown-text" id="vote-cooldown-info">
                            <?php if ($hours_left > 0): ?>
                                Доступно через <?php echo $hours_left; ?> 
                                <?php echo $hours_left === 1 ? 'час' : ($hours_left < 5 ? 'часа' : 'часов'); ?>
                            <?php elseif ($minutes_left > 0): ?>
                                Доступно через <?php echo $minutes_left; ?> 
                                <?php echo $minutes_left === 1 ? 'минуту' : ($minutes_left < 5 ? 'минуты' : 'минут'); ?>
                            <?php else: ?>
                                Можно голосовать
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <a href="/login.php" class="vote-button-large vote-button-link">
                            <span>👍</span>
                            <span>Голосовать за сервер</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Основной контент в две колонки -->
    <div class="server-main-content">
        <!-- Левая колонка -->
        <div>
            <div class="server-details-card">
                <!-- IP адрес -->
                <div class="ip-section">
                    <div class="ip-label">IP АДРЕС</div>
                    <div class="ip-address" onclick="copyToClipboard('<?php echo htmlspecialchars($server['ip'] . ':' . $server['port']); ?>', this)" title="Нажмите чтобы скопировать">
                        <?php echo htmlspecialchars($server['ip'] . ':' . $server['port']); ?>
                    </div>
                    <a href="steam://connect/<?php echo htmlspecialchars($server['ip'] . ':' . $server['port']); ?>" class="copy-button">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Подключиться
                    </a>
                </div>
                
                <!-- Детальная информация -->
                <div class="detail-row">
                    <span class="detail-label">Расположение:</span>
                    <span class="detail-value">🇷🇺 Россия</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Пинг:</span>
                    <span class="detail-value" id="server-ping">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Версия:</span>
                    <span class="detail-value" id="server-version">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Карта:</span>
                    <span class="detail-value" id="server-map"><span class="loading-text">Загрузка...</span></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Пик:</span>
                    <span class="detail-value"><span id="peak-players-detail"><?php echo intval($server['peak_players'] ?? $server['max_players'] ?? 0); ?></span> игрок(ов)</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Рейтинг:</span>
                    <span class="detail-value detail-value-warning">
                        <span id="server-rating"><?php echo $server['rating']; ?></span>
                    </span>
                </div>
                
                <!-- Миниатюра карты -->
                <div class="map-thumbnail" id="map-thumbnail"<?php if (!empty($server['map_image'])): ?> style="background-image: url('<?php echo htmlspecialchars($server['map_image']); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
                    <?php if (empty($server['map_image'])): ?>
                        <?php echo strtoupper($server['map_code'] ?? $server['map_name'] ?? 'UNKNOWN MAP'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Правая колонка -->
        <div>
            <div class="stats-sidebar">
                <h3 class="stats-sidebar-title">Статистика</h3>
                
                <div class="quick-info-item">
                    <svg class="quick-info-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#667eea">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                    </svg>
                    <div>
                        <div class="quick-info-label">Сервер добавлен</div>
                        <div class="quick-info-value">
                            <?php
                            if ($days_diff == 0) {
                                echo 'сегодня';
                            } elseif ($days_diff == 1) {
                                echo 'вчера';
                            } elseif ($days_diff < 30) {
                                echo $days_diff . ' ' . ($days_diff % 10 == 1 && $days_diff % 100 != 11 ? 'день' : ($days_diff % 10 >= 2 && $days_diff % 10 <= 4 && ($days_diff % 100 < 10 || $days_diff % 100 >= 20) ? 'дня' : 'дней')) . ' назад';
                            } elseif ($days_diff < 365) {
                                $months = floor($days_diff / 30);
                                echo $months . ' ' . ($months % 10 == 1 && $months % 100 != 11 ? 'месяц' : ($months % 10 >= 2 && $months % 10 <= 4 && ($months % 100 < 10 || $months % 100 >= 20) ? 'месяца' : 'месяцев')) . ' назад';
                            } else {
                                $years = floor($days_diff / 365);
                                echo $years . ' ' . ($years % 10 == 1 && $years % 100 != 11 ? 'год' : ($years % 10 >= 2 && $years % 10 <= 4 && ($years % 100 < 10 || $years % 100 >= 20) ? 'года' : 'лет')) . ' назад';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="quick-info-item quick-info-item-margin">
                    <svg class="quick-info-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#667eea">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <div>
                        <div class="quick-info-label">Последнее обновление</div>
                        <div class="quick-info-value" id="last-update">
                            <?php
                            if ($minutes_ago < 1) {
                                echo 'только что';
                            } elseif ($minutes_ago < 60) {
                                echo $minutes_ago . ' ' . ($minutes_ago % 10 == 1 && $minutes_ago % 100 != 11 ? 'минуту' : ($minutes_ago % 10 >= 2 && $minutes_ago % 10 <= 4 && ($minutes_ago % 100 < 10 || $minutes_ago % 100 >= 20) ? 'минуты' : 'минут')) . ' назад';
                            } else {
                                $hours = floor($minutes_ago / 60);
                                echo $hours . ' ' . ($hours % 10 == 1 && $hours % 100 != 11 ? 'час' : ($hours % 10 >= 2 && $hours % 10 <= 4 && ($hours % 100 < 10 || $hours % 100 >= 20) ? 'часа' : 'часов')) . ' назад';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($server_tags)): ?>
                    <div class="server-tags-section">
                        <h4 class="server-tags-title">Особенности</h4>
                        <div class="server-tags">
                            <?php foreach ($server_tags as $tag): ?>
                                <span class="tag-item" style="border-color: <?php echo htmlspecialchars($tag['color']); ?>; color: <?php echo htmlspecialchars($tag['color']); ?>;" data-tag-color="<?php echo htmlspecialchars($tag['color']); ?>">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Описание сервера -->
    <div class="server-description-section">
        <h3 class="section-title-blue">Об этом сервере</h3>
        
        <?php if (!empty($server['description'])): ?>
            <div class="server-description-text">
                <?php echo nl2br(htmlspecialchars($server['description'])); ?>
            </div>
        <?php else: ?>
            <div class="server-footer-note">
                Описание отсутствует
            </div>
        <?php endif; ?>
        
        <?php if (!empty($server['discord_url']) || !empty($server['vk_url']) || !empty($server['site_url'])): ?>
            <div class="social-buttons-wrapper">
                <?php if (!empty($server['site_url'])): ?>
                    <a href="<?php echo htmlspecialchars($server['site_url']); ?>" target="_blank" rel="noopener noreferrer" class="social-button">
                        <?php echo parse_url($server['site_url'], PHP_URL_HOST); ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($server['discord_url'])): ?>
                    <a href="<?php echo htmlspecialchars($server['discord_url']); ?>" target="_blank" rel="noopener noreferrer" class="social-button discord">
                        Discord
                    </a>
                <?php endif; ?>
                <?php if (!empty($server['vk_url'])): ?>
                    <a href="<?php echo htmlspecialchars($server['vk_url']); ?>" target="_blank" rel="noopener noreferrer" class="social-button telegram">
                        VK
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="section-divider">
            <div class="footer-info">
                <div>
                    <strong class="owner-info">Владелец:</strong> <?php echo htmlspecialchars($server['owner_name']); ?>
                </div>
                <?php if ($auth->isLoggedIn() && $auth->getUserId() == $server['user_id']): ?>
                    <a href="/edit_server.php?id=<?php echo $server['id']; ?>" class="edit-server-button">
                        Редактировать сервер
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="back-link">
        <a href="/">← Назад к списку серверов</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
