<?php
// index.php

// SEO настройки
$page_title = 'CS2 Мониторинг серверов - Найти лучший CS2 сервер';
$page_description = 'Мониторинг CS2 серверов в реальном времени. Находите лучшие серверы Counter-Strike 2, проверяйте онлайн игроков, карту, пинг и рейтинг. Добавляйте свои серверы и развивайте свой проект.';
$page_keywords = 'CS2, Counter-Strike 2, мониторинг серверов, CS2 сервер, онлайн серверы, рейтинг серверов, серверы CS2, лучшие серверы, найти сервер CS2';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/index.css'];
$additional_js = ['/assets/js/index.js'];

require_once __DIR__ . '/config/cache.php';
require_once __DIR__ . '/includes/header.php';

// Функция для валидации hex цвета
function isValidHexColor($color) {
    return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color);
}

// Функция для построения URL пагинации с фильтрами
function buildPaginationUrl($page_num, $filters) {
    $params = [];
    if ($page_num > 1) {
        $params['page'] = $page_num;
    }
    if (!empty($filters['game_mode_id'])) {
        $params['game_mode_id'] = intval($filters['game_mode_id']);
    }
    if (!empty($filters['tag_id'])) {
        $params['tag_id'] = intval($filters['tag_id']);
    }
    if (!empty($filters['map_id'])) {
        $params['map_id'] = intval($filters['map_id']);
    }
    if (!empty($filters['min_players'])) {
        $params['min_players'] = intval($filters['min_players']);
    }
    if (!empty($filters['search'])) {
        $params['search'] = urlencode($filters['search']);
    }
    return $params ? '/?' . http_build_query($params) : '/';
}

// Функция для построения условий WHERE запроса
function buildWhereConditions(&$query, &$params, $filters) {
    if (!empty($filters['game_id'])) {
        $query .= " AND s.game_id = :game_id";
        $params[':game_id'] = intval($filters['game_id']);
    }
    if (!empty($filters['game_mode_id'])) {
        $query .= " AND s.game_mode_id = :game_mode_id";
        $params[':game_mode_id'] = intval($filters['game_mode_id']);
    }
    if (!empty($filters['map_code'])) {
        $query .= " AND s.current_map = :map_code";
        $params[':map_code'] = $filters['map_code'];
    }
    if (!empty($filters['min_players'])) {
        $min_players = max(0, min(64, intval($filters['min_players'])));
        $query .= " AND s.current_players >= :min_players";
        $params[':min_players'] = $min_players;
    }
    if (!empty($filters['tag_id'])) {
        $query .= " AND st.tag_id = :tag_id";
        $params[':tag_id'] = intval($filters['tag_id']);
    }
    if (!empty($filters['search'])) {
        // Ограничиваем длину поискового запроса для безопасности
        $search = mb_substr(trim($filters['search']), 0, 100);
        if (!empty($search)) {
            $query .= " AND (s.name LIKE :search
                         OR m.name LIKE :search
                         OR m.code LIKE :search
                         OR s.current_map LIKE :search
                         OR REPLACE(s.current_map, 'de_', '') LIKE :search
                         OR mm.name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
    }
}

// Получаем параметры фильтров с валидацией
$game_id = !empty($_GET['game_id']) ? intval($_GET['game_id']) : '';
$game_mode_id = !empty($_GET['game_mode_id']) ? intval($_GET['game_mode_id']) : '';
$map_id = !empty($_GET['map_id']) ? intval($_GET['map_id']) : '';
$min_players = !empty($_GET['min_players']) ? max(0, min(64, intval($_GET['min_players']))) : 0;
$tag_id = !empty($_GET['tag_id']) ? intval($_GET['tag_id']) : '';
$search = mb_substr(trim($_GET['search'] ?? ''), 0, 100);

// Инициализируем map_code
$map_code = null;

// Получаем номер страницы для пагинации
$page = isset($_GET['page']) ? max(1, min(10000, intval($_GET['page']))) : 1;
$servers_per_page = 20;
$offset = ($page - 1) * $servers_per_page;

// Запрос для получения серверов
$query = "SELECT s.*, g.name as game_name, gm.name as game_mode_name, m.name as map_name, m.code as map_code, m.image as map_image, s.current_map,
          u.username as owner_name,
          COALESCE(s.rating, 0) as rating,
          sv.vip_until, sv.name_color as vip_color
          FROM servers s
          LEFT JOIN games g ON s.game_id = g.id
          LEFT JOIN game_modes gm ON s.game_mode_id = gm.id
          LEFT JOIN maps m ON s.map_id = m.id
          LEFT JOIN maps mm ON mm.code = s.current_map
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN server_vip sv ON s.id = sv.server_id AND sv.vip_until > NOW()";

// Добавляем JOIN для тегов если фильтруем по тегам
if (!empty($tag_id)) {
    $query .= " INNER JOIN server_tags st ON s.id = st.server_id";
}

$query .= " WHERE s.status = 'active'";

$params = [];

// Получаем код карты если указан map_id
if (!empty($map_id)) {
    $cache_key = "map_code_{$map_id}";
    $map_code = cache($cache_key, function() use ($db, $map_id) {
        $map_stmt = $db->prepare("SELECT code FROM maps WHERE id = :map_id LIMIT 1");
        $map_stmt->bindValue(':map_id', intval($map_id), PDO::PARAM_INT);
        $map_stmt->execute();
        $map_data = $map_stmt->fetch();
        return $map_data['code'] ?? null;
    }, 3600);
}

// Собираем фильтры
$filters = [
    'game_id' => $game_id,
    'game_mode_id' => $game_mode_id,
    'map_code' => $map_code,
    'min_players' => $min_players,
    'tag_id' => $tag_id,
    'search' => $search
];

// Добавляем условия WHERE
buildWhereConditions($query, $params, $filters);

// Группируем по ID если фильтруем по тегам (перед ORDER BY!)
if (!empty($tag_id)) {
    $query .= " GROUP BY s.id";
}

// Сортируем: сначала VIP серверы (vip_until > NOW()), затем по рейтингу, затем по количеству игроков, затем по имени
$query .= " ORDER BY 
    CASE WHEN sv.vip_until IS NOT NULL AND sv.vip_until > NOW() THEN 0 ELSE 1 END ASC,
    rating DESC, 
    s.current_players DESC, 
    s.name ASC";

// Применяем LIMIT и OFFSET
$query .= " LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $servers_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$servers = $stmt->fetchAll();

// Подсчитываем общее количество серверов для пагинации
$count_query = "SELECT COUNT(DISTINCT s.id) as total
          FROM servers s
          LEFT JOIN games g ON s.game_id = g.id
          LEFT JOIN game_modes gm ON s.game_mode_id = gm.id
          LEFT JOIN maps m ON s.map_id = m.id
          LEFT JOIN maps mm ON mm.code = s.current_map
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN server_vip sv ON s.id = sv.server_id AND sv.vip_until > NOW()";

if (!empty($tag_id)) {
    $count_query .= " INNER JOIN server_tags st ON s.id = st.server_id";
}

$count_query .= " WHERE s.status = 'active'";

$count_params = [];
buildWhereConditions($count_query, $count_params, $filters);

$count_stmt = $db->prepare($count_query);
foreach ($count_params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$count_result = $count_stmt->fetch();
$total_servers = intval($count_result['total'] ?? 0);
$total_pages = max(1, ceil($total_servers / $servers_per_page));

// Ограничиваем максимальный номер страницы
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $servers_per_page;
}

// Получаем списки для фильтров с кешированием (кеш на 1 час)

$games = cache('games_list', function() use ($db) {
    return $db->query("SELECT * FROM games ORDER BY name")->fetchAll();
}, 3600);

$modes = cache('modes_list', function() use ($db) {
    return $db->query("SELECT * FROM game_modes ORDER BY name")->fetchAll();
}, 3600);

$maps = cache('maps_list', function() use ($db) {
    return $db->query("SELECT * FROM maps ORDER BY name")->fetchAll();
}, 3600);

// Получаем теги
try {
    $tags = cache('tags_list', function() use ($db) {
        return $db->query("SELECT * FROM tags ORDER BY name")->fetchAll();
    }, 3600);
} catch (PDOException $e) {
    $tags = [];
}

// Получаем топ карт (по количеству серверов на карте)
$top_maps = cache('top_maps', function() use ($db) {
    return $db->query("
        SELECT 
            m.id,
            m.name,
            m.code,
            m.image,
            COUNT(DISTINCT s.id) as servers_count
        FROM maps m
        LEFT JOIN servers s ON (s.current_map = m.code OR s.map_id = m.id) AND s.status = 'active'
        GROUP BY m.id, m.name, m.code, m.image
        HAVING COUNT(DISTINCT s.id) > 0
        ORDER BY servers_count DESC, m.name ASC
        LIMIT 6
    ")->fetchAll();
}, 3600);

// Подготавливаем фильтры для функции построения URL
$url_filters = [
    'game_mode_id' => $game_mode_id,
    'tag_id' => $tag_id,
    'map_id' => $map_id,
    'min_players' => $min_players ?: null,
    'search' => $search ?: null
];
?>

<?php if (isset($_GET['steam_register'])): ?>
    <div class="alert alert-success">
        Регистрация через Steam успешно завершена! Добро пожаловать!
    </div>
<?php endif; ?>
<?php if (isset($_GET['steam_login'])): ?>
    <div class="alert alert-success">
        Вы успешно вошли через Steam!
    </div>
<?php endif; ?>

<div class="filters-modern">
    <form method="GET" action="" id="filterForm">
        <!-- Режимы игры (большие кнопки) -->
        <div class="filter-modes">
            <a href="/" class="mode-btn <?php echo empty($game_mode_id) ? 'active' : ''; ?>" data-mode="">
                Все режимы
            </a>
            <?php foreach ($modes as $mode): ?>
                <?php 
                $mode_id = intval($mode['id']);
                $mode_url = '?game_mode_id=' . $mode_id;
                if ($map_id) $mode_url .= '&map_id=' . intval($map_id);
                if ($min_players) $mode_url .= '&min_players=' . intval($min_players);
                ?>
                <a href="<?php echo htmlspecialchars($mode_url); ?>" 
                   class="mode-btn <?php echo $game_mode_id == $mode_id ? 'active' : ''; ?>" 
                   data-mode="<?php echo $mode_id; ?>">
                    <?php echo htmlspecialchars($mode['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Теги (маленькие кнопки) -->
        <?php if (count($tags) > 0): ?>
        <div class="filter-tags">
            <a href="/" class="tag-btn <?php echo empty($_GET['tag_id']) ? 'active' : ''; ?>" data-tag="">
                Все теги
            </a>
            <?php foreach ($tags as $tag): ?>
                <?php 
                $tag_id_val = intval($tag['id']);
                $tag_url = '?tag_id=' . $tag_id_val;
                if ($game_mode_id) $tag_url .= '&game_mode_id=' . intval($game_mode_id);
                if ($min_players) $tag_url .= '&min_players=' . intval($min_players);
                $tag_color = htmlspecialchars($tag['color'] ?? '#667eea');
                // Валидация hex цвета
                if (!isValidHexColor($tag_color)) {
                    $tag_color = '#667eea';
                }
                ?>
                <a href="<?php echo htmlspecialchars($tag_url); ?>" 
                   class="tag-btn <?php echo isset($_GET['tag_id']) && intval($_GET['tag_id']) == $tag_id_val ? 'active' : ''; ?>" 
                   data-tag="<?php echo $tag_id_val; ?>"
                   style="--tag-color: <?php echo $tag_color; ?>;">
                    <?php echo htmlspecialchars($tag['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Топ карт -->
        <?php if (count($top_maps) > 0): ?>
        <div class="top-maps-section">
            <h3 class="top-maps-title">🔥 Топ карт</h3>
            <div class="top-maps-grid">
                <?php foreach ($top_maps as $map): ?>
                    <?php 
                    $map_id_val = intval($map['id']);
                    $map_url = '?map_id=' . $map_id_val;
                    if ($game_mode_id) $map_url .= '&game_mode_id=' . intval($game_mode_id);
                    if ($min_players) $map_url .= '&min_players=' . intval($min_players);
                    ?>
                    <div class="top-map-card" onclick="window.location.href='<?php echo htmlspecialchars($map_url); ?>'">
                        <?php if (!empty($map['image'])): ?>
                            <div class="top-map-image" style="background-image: url('<?php echo htmlspecialchars($map['image'], ENT_QUOTES, 'UTF-8'); ?>');">
                                <div class="top-map-overlay">
                                    <span class="top-map-name"><?php echo htmlspecialchars($map['name']); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="top-map-image top-map-placeholder">
                                <div class="top-map-overlay">
                                    <span class="top-map-name"><?php echo htmlspecialchars($map['name']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Дополнительные фильтры -->
        <div class="filter-extra">
            <div class="filter-search">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                <input type="text" name="search" placeholder="Поиск по названию или карте" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
            </div>
            
            <div class="filter-slider">
                <label for="min_players">Минимальный онлайн на сервере (от 0 до 64)</label>
                <input type="range" id="min_players" name="min_players" min="0" max="64" value="<?php echo intval($min_players); ?>">
                <span class="slider-value"><?php echo intval($min_players); ?></span>
            </div>
        </div>
        
        <!-- Скрытые поля для сохранения фильтров -->
        <input type="hidden" name="game_mode_id" id="hidden_mode" value="<?php echo intval($game_mode_id); ?>">
        <input type="hidden" name="tag_id" id="hidden_tag" value="<?php echo !empty($_GET['tag_id']) ? intval($_GET['tag_id']) : ''; ?>">
    </form>
</div>


<?php if ($auth->isLoggedIn()): ?>
    <div style="margin-bottom: 1.5rem;">
        <a href="/add_server.php" class="btn btn-success">Добавить сервер</a>
    </div>
<?php endif; ?>

<div class="main-content-grid">
<div class="servers-section">
<div class="servers-table">
    <?php if (empty($servers)): ?>
        <div style="padding: 2rem; text-align: center;">
            <p>Серверы не найдены</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Сервер</th>
                    <th>IP Адрес</th>
                    <th>Рейтинг</th>
                    <th>Игроки</th>
                    <th>Карта</th>
                </tr>
            </thead>
            <tbody id="servers-tbody">
                <?php foreach ($servers as $server): ?>
                    <tr data-server-id="<?php echo intval($server['id']); ?>" data-server-ip="<?php echo htmlspecialchars($server['ip'], ENT_QUOTES, 'UTF-8'); ?>" data-server-port="<?php echo intval($server['port']); ?>">
                        <td>
                            <?php 
                            // Проверяем, активен ли VIP и есть ли цвет
                            $has_vip = !empty($server['vip_until']);
                            $server_color = '';
                            if ($has_vip && !empty($server['vip_color'])) {
                                $color = htmlspecialchars($server['vip_color'], ENT_QUOTES, 'UTF-8');
                                // Валидация hex цвета для безопасности
                                if (isValidHexColor($color)) {
                                    $server_color = $color;
                                }
                            }
                            ?>
                            <a href="/server.php?id=<?php echo intval($server['id']); ?>" class="server-name-link">
                                <div class="server-name"<?php if ($server_color): ?> style="color: <?php echo $server_color; ?>; font-weight: 600;"<?php endif; ?>>
                                    <?php if ($has_vip): ?><span style="color: #ffc107; margin-right: 5px;">👑</span><?php endif; ?>
                                    <?php echo htmlspecialchars($server['name']); ?>
                                </div>
                            </a>
                            <?php if (!empty($server['features'])): ?>
                                <div style="font-size: 0.875rem; color: #666; margin-top: 0.25rem;">
                                    <?php echo htmlspecialchars($server['features']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="server-ip-container">
                                <?php 
                                $server_ip_port = htmlspecialchars($server['ip'] . ':' . $server['port'], ENT_QUOTES, 'UTF-8');
                                ?>
                                <span class="server-ip" onclick="copyToClipboard('<?php echo $server_ip_port; ?>', this)" title="Нажмите чтобы скопировать">
                                    <?php echo $server_ip_port; ?>
                                </span>
                                <a href="steam://connect/<?php echo $server_ip_port; ?>" class="connect-icon" title="Подключиться через Steam">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span style="color: #ffc107; font-weight: 600; font-size: 1.1rem;">
                                <?php echo intval($server['rating'] ?? 0); ?>
                            </span>
                        </td>
                        <td class="server-players">
                            <span class="players-count"><?php echo intval($server['current_players']); ?></span> / 
                            <span class="max-players"><?php echo intval($server['max_players']); ?></span>
                            <span class="update-indicator" style="display: none; margin-left: 5px; color: #6c757d; font-size: 0.8em;">🔄</span>
                        </td>
                        <td class="server-map"><span class="loading-text">Загрузка...</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php 
        // Показываем информацию о пагинации
        $current_count = count($servers);
        ?>
        
        <?php if ($total_servers > 0): ?>
            <div style="margin-top: 1rem; padding: 0.75rem; background: var(--bg-tertiary); border-radius: 8px; text-align: center; color: var(--text-secondary); font-size: 0.9rem;">
                Показано серверов: <?php echo intval($current_count); ?> из <?php echo intval($total_servers); ?><?php if ($total_pages > 1): ?> (Страница <?php echo intval($page); ?> из <?php echo intval($total_pages); ?>)<?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($total_servers > $servers_per_page): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars(buildPaginationUrl($page - 1, $url_filters), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn pagination-prev">
                        ← Назад
                    </a>
                <?php endif; ?>
                
                <div class="pagination-pages">
                    <?php
                    // Показываем первую страницу
                    if ($page > 3): ?>
                        <a href="<?php echo htmlspecialchars(buildPaginationUrl(1, $url_filters), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn">1</a>
                        <?php if ($page > 4): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php
                    // Показываем страницы вокруг текущей
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="<?php echo htmlspecialchars(buildPaginationUrl($i, $url_filters), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo intval($i); ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php
                    // Показываем последнюю страницу
                    if ($page < $total_pages - 2): ?>
                        <?php if ($page < $total_pages - 3): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars(buildPaginationUrl($total_pages, $url_filters), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn"><?php echo intval($total_pages); ?></a>
                    <?php endif; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo htmlspecialchars(buildPaginationUrl($page + 1, $url_filters), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn pagination-next">
                        Вперед →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>

<?php
// Получаем случайный проект с рейтингом > 100000
// Используем session для запоминания случайного проекта на время сессии
$randomProject = null;
if (!isset($_SESSION['random_project_id']) || !isset($_SESSION['random_project_time']) || (time() - $_SESSION['random_project_time']) > 300) {
    // Получаем новый случайный проект раз в 5 минут
    $projectStmt = $db->query("
        SELECT p.*, 
               (SELECT COUNT(*) FROM project_servers WHERE project_id = p.id) as servers_count
        FROM projects p
        WHERE p.status = 'active' AND p.total_rating >= 100000
        ORDER BY RAND()
        LIMIT 1
    ");
    if ($projectStmt->rowCount() > 0) {
        $randomProject = $projectStmt->fetch();
        $_SESSION['random_project_id'] = intval($randomProject['id']);
        $_SESSION['random_project_data'] = $randomProject;
        $_SESSION['random_project_time'] = time();
    }
} else {
    // Используем сохранённый проект из сессии
    $randomProject = $_SESSION['random_project_data'];
}
?>

<div class="sidebar-section">
    <div class="random-project-widget">
        <h3>Случайный проект</h3>
        
        <?php if ($randomProject): ?>
            <div class="random-project-card">
                <?php if (!empty($randomProject['logo'])): ?>
                    <div class="random-project-logo">
                        <a href="/project.php?id=<?php echo intval($randomProject['id']); ?>">
                            <img src="<?php echo htmlspecialchars($randomProject['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($randomProject['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="random-project-info">
                    <h4>
                        <a href="/project.php?id=<?php echo intval($randomProject['id']); ?>">
                            <?php echo htmlspecialchars($randomProject['name']); ?>
                        </a>
                    </h4>
                    
                    <div class="random-project-stats">
                        <span>Рейтинг: <?php echo number_format(intval($randomProject['total_rating'])); ?></span>
                        <span>🖥️ <?php echo intval($randomProject['servers_count'] ?? 0); ?> серверов</span>
                    </div>
                    
                    <div class="random-project-description">
                        <?php echo htmlspecialchars(mb_substr(strip_tags($randomProject['description'] ?? ''), 0, 100)); ?>...
                    </div>
                    
                    <a href="/project.php?id=<?php echo intval($randomProject['id']); ?>" class="btn btn-primary btn-block">
                        Подробнее
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="no-project-message">
                <p>Пока нет проектов с рейтингом выше 100000</p>
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="/add_project.php" class="btn btn-sm">Создать проект</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 1.5rem; text-align: center;">
        <a href="/projects.php" class="btn btn-secondary">Все проекты</a>
    </div>
</div>

</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>

