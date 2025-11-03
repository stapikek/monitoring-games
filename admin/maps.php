<?php
// admin/maps.php - управление картами
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

$message = '';
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    
    if ($action === 'add' && !empty($_POST['name']) && !empty($_POST['code'])) {
        try {
            $name = trim($_POST['name']);
            $code = trim($_POST['code']);
            $stmt = $db->prepare("INSERT INTO maps (name, code, image) VALUES (:name, :code, :image)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':code', $code);
            $image = !empty($_POST['image']) ? trim($_POST['image']) : null;
            $stmt->bindParam(':image', $image);
            $stmt->execute();
            $message = "Карта успешно добавлена";
        } catch (PDOException $e) {
            $error = "Ошибка при добавлении карты: " . $e->getMessage();
        }
    } elseif ($action === 'edit' && $id > 0) {
        try {
            $name = trim($_POST['name']);
            $code = trim($_POST['code']);
            $stmt = $db->prepare("UPDATE maps SET name = :name, code = :code, image = :image WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':code', $code);
            $image = !empty($_POST['image']) ? trim($_POST['image']) : null;
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "Карта успешно обновлена";
        } catch (PDOException $e) {
            $error = "Ошибка при обновлении карты: " . $e->getMessage();
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM maps WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = "Карта успешно удалена";
        } catch (PDOException $e) {
            $error = "Ошибка при удалении карты: " . $e->getMessage();
        }
    }
}

// Получаем все карты
$stmt = $db->query("SELECT * FROM maps ORDER BY name ASC");
$maps = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/maps.css'];
$additional_js = ['/assets/js/admin/maps.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление картами</h1>

<?php if ($message): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Форма добавления карты -->
<div class="admin-form-card">
    <h3>Добавить карту</h3>
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add">
        
        <div class="form-group">
            <label>Название карты:</label>
            <input type="text" name="name" required placeholder="Например: Dust 2">
            <small>Полное название карты</small>
        </div>
        
        <div class="form-group">
            <label>Код карты:</label>
            <input type="text" name="code" required placeholder="Например: de_dust2">
            <small>Техническое название карты (используется сервером)</small>
        </div>
        
        <div class="form-group">
            <label>URL изображения карты:</label>
            <input type="url" name="image" placeholder="https://example.com/maps/de_dust2.jpg">
            <small>Прямая ссылка на изображение карты (не обязательно). Рекомендуемый размер: 800x450px</small>
        </div>
        
        <button type="submit" class="btn-primary">Добавить карту</button>
    </form>
</div>

<!-- Список карт -->
<h2 class="section-title">Список карт (<?php echo count($maps); ?>)</h2>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Превью</th>
            <th>Название</th>
            <th>Код</th>
            <th>Изображение</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maps as $map): ?>
        <tr>
            <td><?php echo $map['id']; ?></td>
            <td>
                <?php if (!empty($map['image'])): ?>
                    <img src="<?php echo htmlspecialchars($map['image']); ?>" 
                         alt="<?php echo htmlspecialchars($map['name']); ?>"
                         class="map-preview-img">
                <?php else: ?>
                    <div class="map-preview-placeholder">
                        <?php echo strtoupper(substr($map['code'], 0, 6)); ?>
                    </div>
                <?php endif; ?>
            </td>
            <td class="map-name-cell"><?php echo htmlspecialchars($map['name']); ?></td>
            <td class="map-code-cell"><?php echo htmlspecialchars($map['code']); ?></td>
            <td>
                <?php if (!empty($map['image'])): ?>
                    <a href="<?php echo htmlspecialchars($map['image']); ?>" target="_blank" class="map-image-link">
                        🖼️ Открыть
                    </a>
                <?php else: ?>
                    <span class="empty-image-text">Нет изображения</span>
                <?php endif; ?>
            </td>
            <td>
                <button onclick="editMap(<?php echo $map['id']; ?>, '<?php echo htmlspecialchars(addslashes($map['name'])); ?>', '<?php echo htmlspecialchars(addslashes($map['code'])); ?>', '<?php echo htmlspecialchars(addslashes($map['image'] ?? '')); ?>')" 
                        class="btn-edit">
                    Изменить
                </button>
                <form method="POST" class="inline-form delete-form" data-message="Удалить карту?">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $map['id']; ?>">
                    <button type="submit" class="btn-danger-sm">Удалить</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Модальное окно редактирования -->
<div id="editModal">
    <div>
        <h3>Редактировать карту</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            
            <div class="modal-form-group">
                <label>Название:</label>
                <input type="text" name="name" id="editName" required placeholder="Например: Dust 2">
            </div>
            
            <div class="modal-form-group">
                <label>Код:</label>
                <input type="text" name="code" id="editCode" required placeholder="Например: de_dust2">
            </div>
            
            <div class="modal-form-group">
                <label>URL изображения:</label>
                <input type="url" name="image" id="editImage" placeholder="https://example.com/maps/de_dust2.jpg">
                <small>Оставьте пустым, чтобы удалить изображение</small>
            </div>
            
            <!-- Превью изображения -->
            <div id="imagePreview" class="image-preview">
                <label>Текущее изображение:</label>
                <img id="previewImg" src="" alt="Preview">
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeEdit()">Отмена</button>
                <button type="submit" class="btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

