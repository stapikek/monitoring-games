<?php
// admin/servers.php
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
        $stmt = $db->prepare("SELECT s.*, u.username FROM servers s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $server = $stmt->fetch();
    }
    
    if ($action === 'approve' && $id > 0) {
        $stmt = $db->prepare("UPDATE servers SET status = 'active' WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("Server approved", ['server_id' => $id, 'server_name' => $server['name'] ?? 'N/A', 'ip' => ($server['ip'] ?? '') . ':' . ($server['port'] ?? ''), 'owner' => $server['username'] ?? 'N/A']);
    } elseif ($action === 'reject' && $id > 0) {
        $stmt = $db->prepare("UPDATE servers SET status = 'rejected' WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("Server rejected", ['server_id' => $id, 'server_name' => $server['name'] ?? 'N/A', 'ip' => ($server['ip'] ?? '') . ':' . ($server['port'] ?? ''), 'owner' => $server['username'] ?? 'N/A']);
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM servers WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("Server deleted", ['server_id' => $id, 'server_name' => $server['name'] ?? 'N/A', 'ip' => ($server['ip'] ?? '') . ':' . ($server['port'] ?? ''), 'owner' => $server['username'] ?? 'N/A']);
    }
    
    header('Location: /admin/servers.php');
    exit;
}

// Получаем список серверов
$filter = $_GET['filter'] ?? 'all';
$where = "";
if ($filter === 'pending') {
    $where = "WHERE s.status = 'pending'";
} elseif ($filter === 'active') {
    $where = "WHERE s.status = 'active'";
} elseif ($filter === 'rejected') {
    $where = "WHERE s.status = 'rejected'";
}

$stmt = $db->query("
    SELECT s.*, u.username as owner_name, g.name as game_name
    FROM servers s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN games g ON s.game_id = g.id
    $where
    ORDER BY s.created_at DESC
");
$servers = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/servers.css'];
$additional_js = ['/assets/js/admin/servers.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление серверами</h1>

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
            <th>IP:Порт</th>
            <th>Владелец</th>
            <th>Статус</th>
            <th>Рейтинг</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($servers as $server): ?>
            <tr>
                <td><?php echo $server['id']; ?></td>
                <td><?php echo htmlspecialchars($server['name']); ?></td>
                <td><?php echo htmlspecialchars($server['ip']); ?>:<?php echo $server['port']; ?></td>
                <td><?php echo htmlspecialchars($server['owner_name'] ?? '-'); ?></td>
                <td><span class="status-<?php echo $server['status']; ?>"><?php echo $server['status']; ?></span></td>
                <td><?php echo $server['rating'] ?? 0; ?></td>
                <td><?php echo date('d.m.Y H:i', strtotime($server['created_at'])); ?></td>
                <td>
                    <div class="action-buttons">
                        <a href="/server.php?id=<?php echo $server['id']; ?>" class="btn btn-sm btn-view">Просмотр</a>
                        <?php if ($server['status'] === 'pending'): ?>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?php echo $server['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">✓ Одобрить</button>
                            </form>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?php echo $server['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">✗ Отклонить</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" class="inline-form delete-form" data-message="Вы уверены, что хотите удалить сервер?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $server['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑 Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

