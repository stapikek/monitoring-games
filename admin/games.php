<?php
// admin/games.php
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

// Проверяем наличие колонки icon
$iconColumnExists = true;
try {
    $db->query("SELECT icon FROM games LIMIT 1");
} catch (PDOException $e) {
    $iconColumnExists = false;
}

if (!$iconColumnExists) {
    try {
        $db->exec("ALTER TABLE games ADD COLUMN icon VARCHAR(255) NULL AFTER code");
        $iconColumnExists = true; // Обновляем после добавления
    } catch (PDOException $e) {
        // ignore if cannot alter
    }
}

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/logger.php';
    
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    
    if ($action === 'add' && !empty($_POST['name'])) {
        $name = trim($_POST['name']);
        $icon = trim($_POST['icon'] ?? '');
        if ($iconColumnExists) {
            $stmt = $db->prepare("INSERT INTO games (name, icon) VALUES (:name, :icon)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':icon', $icon);
        } else {
            $stmt = $db->prepare("INSERT INTO games (name) VALUES (:name)");
            $stmt->bindParam(':name', $name);
        }
        $stmt->execute();
        Logger::admin("Game added", ['name' => $name]);
    } elseif ($action === 'edit' && $id > 0 && !empty($_POST['name'])) {
        $name = trim($_POST['name']);
        $icon = trim($_POST['icon'] ?? '');
        if ($iconColumnExists) {
            $stmt = $db->prepare("UPDATE games SET name = :name, icon = :icon WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':icon', $icon);
            $stmt->bindParam(':id', $id);
        } else {
            $stmt = $db->prepare("UPDATE games SET name = :name WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $id);
        }
        $stmt->execute();
        Logger::admin("Game updated", ['game_id' => $id, 'name' => $name]);
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("SELECT name FROM games WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $game = $stmt->fetch();
        
        $stmt = $db->prepare("DELETE FROM games WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("Game deleted", ['game_id' => $id, 'name' => $game['name'] ?? 'N/A']);
    }
    
    header('Location: /admin/games.php');
    exit;
}

// Получаем список игр

$stmt = $db->query("SELECT * FROM games ORDER BY name");
$games = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/games.css'];
$additional_js = ['/assets/js/admin/games.js'];

 require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление играми</h1>

<div class="admin-form-card">
    <h3>Добавить игру</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-add-fields">
            <div class="form-add-field">
                <input type="text" name="name" placeholder="Название игры" required>
            </div>
            <?php if ($iconColumnExists): ?>
            <div class="form-add-field">
                <input type="url" name="icon" placeholder="Ссылка на иконку (PNG/SVG)">
            </div>
            <?php endif; ?>
            <button type="submit" class="btn-primary">Добавить</button>
        </div>
    </form>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <?php if ($iconColumnExists): ?><th>Иконка</th><?php endif; ?>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($games as $game): ?>
            <tr>
                <td><?php echo $game['id']; ?></td>
                <td id="game_name_<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['name']); ?></td>
                <?php if ($iconColumnExists): ?>
                <td>
                    <?php if (!empty($game['icon'])): ?>
                        <img src="<?php echo htmlspecialchars($game['icon']); ?>" alt="icon" class="game-icon">
                    <?php else: ?>
                        <span class="empty-icon-text">нет</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td>
                    <div class="action-buttons-wrapper">
                        <button onclick="editGame(<?php echo $game['id']; ?>, '<?php echo htmlspecialchars(addslashes($game['name'])); ?>', '<?php echo htmlspecialchars(addslashes($game['icon'] ?? '')); ?>')" class="btn-edit">Изменить</button>
                        <form method="POST" class="inline-form delete-form" data-message="Удалить игру?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
                            <button type="submit" class="btn-danger-sm">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Модальное окно редактирования -->
<div id="editModal">
    <div>
        <h3>Редактировать игру</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <input type="text" name="name" id="editName" required>
            <?php if ($iconColumnExists): ?>
            <input type="url" name="icon" id="editIcon" placeholder="Ссылка на иконку (PNG/SVG)">
            <?php endif; ?>
            <div class="modal-actions">
                <button type="button" onclick="closeEdit()">Отмена</button>
                <button type="submit" class="btn-primary">Сохранить</button>
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

