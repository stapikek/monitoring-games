<?php
// admin/tags.php
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
        $stmt = $db->prepare("INSERT INTO tags (name) VALUES (:name)");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
    } elseif ($action === 'edit' && $id > 0 && !empty($_POST['name'])) {
        $name = trim($_POST['name']);
        $stmt = $db->prepare("UPDATE tags SET name = :name WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM tags WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }
    
    header('Location: /admin/tags.php');
    exit;
}

// Получаем список тегов
$stmt = $db->query("SELECT * FROM tags ORDER BY name");
$tags = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/tags.css'];
$additional_js = ['/assets/js/admin/tags.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление тегами</h1>

<div class="admin-form-card">
    <h3>Добавить тег</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-add-wrapper">
            <div class="form-add-field">
                <input type="text" name="name" placeholder="Название тега" required>
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
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tags as $tag): ?>
            <tr>
                <td><?php echo $tag['id']; ?></td>
                <td><?php echo htmlspecialchars($tag['name']); ?></td>
                <td>
                    <div class="action-buttons-wrapper">
                        <button onclick="editTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars(addslashes($tag['name'])); ?>')" class="btn-edit">Изменить</button>
                        <form method="POST" class="inline-form delete-form" data-message="Удалить тег?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $tag['id']; ?>">
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
        <h3>Редактировать тег</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <input type="text" name="name" id="editName" required>
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

