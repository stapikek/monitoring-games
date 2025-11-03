<?php
// admin/hostings.php
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

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $logo = trim($_POST['logo'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $website_url = trim($_POST['website_url'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        // Валидация статуса
        if (!in_array($status, ['pending', 'active', 'inactive'])) {
            $status = 'pending';
        }
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        $stmt = $db->prepare("INSERT INTO hostings (name, logo, description, website_url, status, sort_order) VALUES (:name, :logo, :description, :website_url, :status, :sort_order)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':logo', $logo);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':website_url', $website_url);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':sort_order', $sort_order);
        $stmt->execute();
        
        $hosting_id = $db->lastInsertId();
        
        // Добавляем поддерживаемые игры из таблицы games
        if (!empty($_POST['games']) && is_array($_POST['games'])) {
            foreach ($_POST['games'] as $game_id) {
                $game_id = intval($game_id);
                if ($game_id > 0) {
                    $game_stmt = $db->prepare("INSERT INTO hosting_games (hosting_id, game_id) VALUES (:hosting_id, :game_id)");
                    $game_stmt->bindParam(':hosting_id', $hosting_id);
                    $game_stmt->bindParam(':game_id', $game_id);
                    $game_stmt->execute();
                }
            }
        }
        
        // Добавляем кастомные игры
        if (!empty($_POST['custom_games']) && is_array($_POST['custom_games'])) {
            foreach ($_POST['custom_games'] as $custom_game_name) {
                $custom_game_name = trim($custom_game_name);
                if (!empty($custom_game_name)) {
                    $game_stmt = $db->prepare("INSERT INTO hosting_games (hosting_id, custom_game_name) VALUES (:hosting_id, :custom_game_name)");
                    $game_stmt->bindParam(':hosting_id', $hosting_id);
                    $game_stmt->bindParam(':custom_game_name', $custom_game_name);
                    $game_stmt->execute();
                }
            }
        }
        
        header('Location: /admin/hostings.php');
        exit;
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $logo = trim($_POST['logo'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $website_url = trim($_POST['website_url'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        // Валидация статуса
        if (!in_array($status, ['pending', 'active', 'inactive'])) {
            $status = 'pending';
        }
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        $stmt = $db->prepare("UPDATE hostings SET name = :name, logo = :logo, description = :description, website_url = :website_url, status = :status, sort_order = :sort_order WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':logo', $logo);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':website_url', $website_url);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':sort_order', $sort_order);
        $stmt->execute();
        
        // Обновляем поддерживаемые игры
        $del_stmt = $db->prepare("DELETE FROM hosting_games WHERE hosting_id = :hosting_id");
        $del_stmt->bindParam(':hosting_id', $id);
        $del_stmt->execute();
        
        // Добавляем поддерживаемые игры из таблицы games
        if (!empty($_POST['games']) && is_array($_POST['games'])) {
            foreach ($_POST['games'] as $game_id) {
                $game_id = intval($game_id);
                if ($game_id > 0) {
                    $game_stmt = $db->prepare("INSERT INTO hosting_games (hosting_id, game_id) VALUES (:hosting_id, :game_id)");
                    $game_stmt->bindParam(':hosting_id', $id);
                    $game_stmt->bindParam(':game_id', $game_id);
                    $game_stmt->execute();
                }
            }
        }
        
        // Добавляем кастомные игры
        if (!empty($_POST['custom_games']) && is_array($_POST['custom_games'])) {
            foreach ($_POST['custom_games'] as $custom_game_name) {
                $custom_game_name = trim($custom_game_name);
                if (!empty($custom_game_name)) {
                    $game_stmt = $db->prepare("INSERT INTO hosting_games (hosting_id, custom_game_name) VALUES (:hosting_id, :custom_game_name)");
                    $game_stmt->bindParam(':hosting_id', $id);
                    $game_stmt->bindParam(':custom_game_name', $custom_game_name);
                    $game_stmt->execute();
                }
            }
        }
        
        header('Location: /admin/hostings.php');
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        // Удаляем связи с играми
        $del_stmt = $db->prepare("DELETE FROM hosting_games WHERE hosting_id = :hosting_id");
        $del_stmt->bindParam(':hosting_id', $id);
        $del_stmt->execute();
        
        $stmt = $db->prepare("DELETE FROM hostings WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        header('Location: /admin/hostings.php');
        exit;
    }
}

// Получаем список хостингов
$stmt = $db->query("
    SELECT h.*, 
           AVG(hr.rating) as avg_rating,
           COUNT(hr.id) as reviews_count
    FROM hostings h
    LEFT JOIN hosting_reviews hr ON h.id = hr.hosting_id
    GROUP BY h.id
    ORDER BY h.sort_order ASC, h.name ASC
");
$hostings = $stmt->fetchAll();

// Получаем список игр для выбора
$games_stmt = $db->query("SELECT id, name FROM games ORDER BY name ASC");
$games = $games_stmt->fetchAll();

// Получаем поддерживаемые игры для каждого хостинга
$hosting_games_map = [];
$hosting_custom_games_map = [];
foreach ($hostings as $hosting) {
    $hg_stmt = $db->prepare("SELECT game_id FROM hosting_games WHERE hosting_id = ? AND game_id IS NOT NULL");
    $hg_stmt->execute([$hosting['id']]);
    $hosting_games_map[$hosting['id']] = array_column($hg_stmt->fetchAll(), 'game_id');
    
    $hg_stmt = $db->prepare("SELECT custom_game_name FROM hosting_games WHERE hosting_id = ? AND custom_game_name IS NOT NULL");
    $hg_stmt->execute([$hosting['id']]);
    $hosting_custom_games_map[$hosting['id']] = array_column($hg_stmt->fetchAll(), 'custom_game_name');
}

$additional_css = ['/assets/css/admin/hostings.css'];
$additional_js = ['/assets/js/admin/hostings.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление хостингами</h1>

<button onclick="showAddForm()" class="btn-add-hosting">
    Добавить хостинг
</button>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Логотип</th>
            <th>Название</th>
            <th>Рейтинг</th>
            <th>Отзывов</th>
            <th>Статус</th>
            <th>Сортировка</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($hostings)): ?>
            <tr>
                <td colspan="8" class="empty-state">Хостинги не добавлены</td>
            </tr>
        <?php else: ?>
            <?php foreach ($hostings as $hosting): ?>
                <tr>
                    <td><?php echo $hosting['id']; ?></td>
                    <td>
                        <?php if (!empty($hosting['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($hosting['logo']); ?>" alt="<?php echo htmlspecialchars($hosting['name']); ?>" class="hosting-logo">
                        <?php else: ?>
                            <span class="empty-icon">—</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($hosting['name']); ?></strong></td>
                    <td><?php echo number_format($hosting['avg_rating'] ?: 0, 1); ?>/5</td>
                    <td><?php echo $hosting['reviews_count']; ?></td>
                    <td>
                        <span class="badge <?php echo $hosting['status'] === 'active' ? 'badge-success' : ($hosting['status'] === 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                            <?php echo $hosting['status'] === 'active' ? 'Активен' : ($hosting['status'] === 'pending' ? 'На модерации' : 'Неактивен'); ?>
                        </span>
                    </td>
                    <td><?php echo $hosting['sort_order']; ?></td>
                    <td>
                        <?php
                        $hosting_data = $hosting;
                        $hosting_data['games'] = $hosting_games_map[$hosting['id']] ?? [];
                        $hosting_data['custom_games'] = $hosting_custom_games_map[$hosting['id']] ?? [];
                        ?>
                        <button onclick="showEditForm(<?php echo htmlspecialchars(json_encode($hosting_data)); ?>)" class="btn-edit">
                            Изменить
                        </button>
                        <form method="POST" class="inline-form delete-form" data-message="Вы уверены, что хотите удалить хостинг?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $hosting['id']; ?>">
                            <button type="submit" class="btn-danger">
                                Удалить
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Модальное окно для добавления/редактирования -->
<div id="modal">
    <div>
        <h2 id="modal-title">Добавить хостинг</h2>
        <form method="POST" id="modal-form">
            <input type="hidden" name="action" id="modal-action" value="add">
            <input type="hidden" name="id" id="modal-id">
            
            <div class="modal-form-group">
                <label>Название *</label>
                <input type="text" name="name" id="modal-name" required>
            </div>
            
            <div class="modal-form-group">
                <label>URL логотипа</label>
                <input type="url" name="logo" id="modal-logo" placeholder="https://example.com/logo.png">
            </div>
            
            <div class="modal-form-group">
                <label>URL сайта (с реферальной ссылкой)</label>
                <input type="url" name="website_url" id="modal-website" placeholder="https://example.com/?ref=partner">
            </div>
            
            <div class="modal-form-group">
                <label>Описание</label>
                <textarea name="description" id="modal-description" rows="5" placeholder="Описание хостинга"></textarea>
            </div>
            
            <div class="modal-form-group">
                <label>Поддерживаемые игры</label>
                <div class="games-list-container">
                    <?php foreach ($games as $game): ?>
                        <label>
                            <input type="checkbox" name="games[]" value="<?php echo $game['id']; ?>">
                            <?php echo htmlspecialchars($game['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="custom-games-input-wrapper">
                    <input type="text" id="custom-game-input" placeholder="Или введите название игры вручную">
                    <button type="button" onclick="addCustomGame()">Добавить</button>
                </div>
                <div id="custom-games-container"></div>
            </div>
            
            <!-- Скрытые поля для кастомных игр -->
            <div id="custom-games-hidden"></div>
            
            <div class="modal-form-group">
                <label>Статус</label>
                <select name="status" id="modal-status">
                    <option value="pending">На модерации</option>
                    <option value="active">Активен</option>
                    <option value="inactive">Неактивен</option>
                </select>
            </div>
            
            <div class="modal-form-group">
                <label>Порядок сортировки</label>
                <input type="number" name="sort_order" id="modal-sort" value="0">
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeModal()">Отмена</button>
                <button type="submit">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
// Обработка форм удаления
document.querySelectorAll('.delete-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = this.getAttribute('data-message') || 'Вы уверены?';
        showGlobalConfirm(message, function(result) {
            if (result) {
                form.submit();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

