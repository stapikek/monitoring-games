<?php
// admin/users.php
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
        $stmt = $db->prepare("SELECT id, username, is_admin FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $user = $stmt->fetch();
    }
    
    if ($action === 'toggle_admin' && $id > 0) {
        $stmt = $db->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $newStatus = $user['is_admin'] == 1 ? 'removed' : 'granted';
        Logger::admin("Admin rights $newStatus", ['user_id' => $id, 'username' => $user['username'] ?? 'N/A']);
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        Logger::admin("User deleted", ['user_id' => $id, 'username' => $user['username'] ?? 'N/A']);
    }
    
    header('Location: /admin/users.php');
    exit;
}

// Получаем список пользователей
$stmt = $db->query("
    SELECT u.*, COUNT(s.id) as servers_count
    FROM users u
    LEFT JOIN servers s ON u.id = s.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/users.css'];
$additional_js = ['/assets/js/admin/users.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление пользователями</h1>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Баланс</th>
            <th>Серверов</th>
            <th>Steam</th>
            <th>Админ</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo number_format($user['balance'] ?? 0, 2); ?> ₽</td>
                <td><?php echo $user['servers_count']; ?></td>
                <td><?php echo $user['steam_id'] ? '✓' : '-'; ?></td>
                <td><?php echo $user['is_admin'] ? '✓' : '-'; ?></td>
                <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                <td>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="toggle_admin">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-sm"><?php echo $user['is_admin'] ? '🔻 Снять права' : '⭐ Дать права'; ?></button>
                    </form>
                    <form method="POST" class="inline-form delete-form" data-message="Удалить пользователя?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">🗑 Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>


