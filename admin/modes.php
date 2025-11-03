<?php
// admin/modes.php
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
    $id = intval($_POST['id'] ?? 0);
    
    if ($action === 'add' && !empty($_POST['name'])) {
        $name = trim($_POST['name']);
        $code = strtolower(str_replace(' ', '_', $name));
        $stmt = $db->prepare("INSERT INTO game_modes (name, code) VALUES (:name, :code)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':code', $code);
        $stmt->execute();
    } elseif ($action === 'edit' && $id > 0 && !empty($_POST['name'])) {
        $name = trim($_POST['name']);
        $code = !empty($_POST['code']) ? trim($_POST['code']) : strtolower(str_replace(' ', '_', $name));
        $stmt = $db->prepare("UPDATE game_modes SET name = :name, code = :code WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM game_modes WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }
    
    header('Location: /admin/modes.php');
    exit;
}

// Получаем список режимов
$stmt = $db->query("
    SELECT * FROM game_modes
    ORDER BY name
");
$modes = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/modes.css'];
$additional_js = ['/assets/js/admin/modes.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление режимами</h1>

<div class="admin-form-card">
    <h3>Добавить режим</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-add-wrapper">
            <div class="form-add-field">
                <input type="text" name="name" placeholder="Название режима" required>
            </div>
            <button type="submit" class="btn-primary">Добавить</button>
        </div>
    </form>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Код</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($modes as $mode): ?>
            <tr>
                <td><?php echo $mode['id']; ?></td>
                <td><?php echo htmlspecialchars($mode['name']); ?></td>
                <td><code><?php echo htmlspecialchars($mode['code'] ?? ''); ?></code></td>
                <td>
                    <div class="action-buttons-wrapper">
                        <button onclick="editMode(<?php echo $mode['id']; ?>, '<?php echo htmlspecialchars(addslashes($mode['name'])); ?>', '<?php echo htmlspecialchars(addslashes($mode['code'] ?? '')); ?>')" class="btn-edit">Изменить</button>
                        <form method="POST" class="inline-form delete-form" data-message="Удалить режим?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $mode['id']; ?>">
                            <button type="submit" class="btn-danger-sm">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="editModal">
    <div>
        <h3>Редактировать режим</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            
            <div class="modal-form-group">
                <label>Название:</label>
                <input type="text" name="name" id="editName" required placeholder="Например: Competitive">
            </div>
            
            <div class="modal-form-group">
                <label>Код:</label>
                <input type="text" name="code" id="editCode" required placeholder="Например: competitive">
                <small>Используется для идентификации режима</small>
            </div>
            
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

