<?php
// admin/projects.php
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
    require_once __DIR__ . '/../config/logger.php';
    
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    
    if ($id > 0) {
        $stmt = $db->prepare("SELECT p.*, u.username FROM projects p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $project = $stmt->fetch();
    }
    
    if ($action === 'approve' && $id > 0) {
        $stmt = $db->prepare("UPDATE projects SET status = 'active' WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("Project approved", ['project_id' => $id, 'project_name' => $project['name'] ?? 'N/A', 'owner' => $project['username'] ?? 'N/A']);
    } elseif ($action === 'reject' && $id > 0) {
        $stmt = $db->prepare("UPDATE projects SET status = 'rejected' WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("Project rejected", ['project_id' => $id, 'project_name' => $project['name'] ?? 'N/A', 'owner' => $project['username'] ?? 'N/A']);
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM projects WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("Project deleted", ['project_id' => $id, 'project_name' => $project['name'] ?? 'N/A', 'owner' => $project['username'] ?? 'N/A']);
    }
    
    header('Location: /admin/projects.php');
    exit;
}

// Получаем список проектов
$filter = $_GET['filter'] ?? 'all';
$where = "";
if ($filter === 'pending') {
    $where = "WHERE p.status = 'pending'";
} elseif ($filter === 'active') {
    $where = "WHERE p.status = 'active'";
} elseif ($filter === 'rejected') {
    $where = "WHERE p.status = 'rejected'";
}

$stmt = $db->query("
    SELECT p.*, u.username as owner_name,
           (SELECT COUNT(*) FROM project_servers WHERE project_id = p.id) as servers_count
    FROM projects p
    LEFT JOIN users u ON p.user_id = u.id
    $where
    ORDER BY p.created_at DESC
");
$projects = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/projects.css'];
$additional_js = ['/assets/js/admin/projects.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление проектами</h1>

<div class="admin-filters">
    <a href="?filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">Все</a>
    <a href="?filter=pending" class="<?php echo $filter === 'pending' ? 'active' : ''; ?>">На модерации</a>
    <a href="?filter=active" class="<?php echo $filter === 'active' ? 'active' : ''; ?>">Активные</a>
    <a href="?filter=rejected" class="<?php echo $filter === 'rejected' ? 'active' : ''; ?>">Отклоненные</a>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Владелец</th>
            <th>Серверов</th>
            <th>Рейтинг</th>
            <th>Статус</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($projects as $project): ?>
            <tr>
                <td><?php echo $project['id']; ?></td>
                <td><?php echo htmlspecialchars($project['name']); ?></td>
                <td><?php echo htmlspecialchars($project['owner_name'] ?? '-'); ?></td>
                <td><?php echo $project['servers_count']; ?></td>
                <td><?php echo number_format($project['total_rating']); ?></td>
                <td><span class="status-<?php echo $project['status']; ?>"><?php echo $project['status']; ?></span></td>
                <td><?php echo date('d.m.Y H:i', strtotime($project['created_at'])); ?></td>
                <td>
                    <?php if ($project['status'] === 'pending'): ?>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success">✓ Одобрить</button>
                        </form>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✗ Отклонить</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" class="inline-form delete-form" data-message="Удалить проект?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">🗑 Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>


