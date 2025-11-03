<?php
// admin/payment_systems.php
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
    
    if ($action === 'update') {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'enabled_') === 0) {
                $id = str_replace('enabled_', '', $key);
                $stmt = $db->prepare("UPDATE payment_systems SET enabled = :enabled WHERE id = :id");
                $stmt->bindParam(':enabled', $value);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
            }
        }
    }
    
    if ($action === 'save_settings' && !empty($_POST['system_id'])) {
        $systemId = intval($_POST['system_id']);
        $settings = json_encode([
            'api_key' => trim($_POST['api_key'] ?? ''),
            'secret_key' => trim($_POST['secret_key'] ?? ''),
            'merchant_id' => trim($_POST['merchant_id'] ?? ''),
            'webhook_url' => trim($_POST['webhook_url'] ?? '')
        ]);
        
        $stmt = $db->prepare("UPDATE payment_systems SET settings = :settings WHERE id = :id");
        $stmt->bindParam(':settings', $settings);
        $stmt->bindParam(':id', $systemId);
        $stmt->execute();
    }
    
    header('Location: /admin/payment_systems.php');
    exit;
}

// Получаем список платежных систем
$stmt = $db->query("SELECT * FROM payment_systems ORDER BY name");
$payment_systems = $stmt->fetchAll();

$additional_css = ['/assets/css/admin/payment_systems.css'];
$additional_js = ['/assets/js/admin/payment_systems.js'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>Управление платежными системами</h1>

<form method="POST">
    <input type="hidden" name="action" value="update">
    
    <table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Тип</th>
            <th>Статус</th>
            <th>Настройки</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payment_systems as $ps): 
            $settings = json_decode($ps['settings'] ?? '{}', true);
        ?>
            <tr>
                <td><?php echo $ps['id']; ?></td>
                <td><?php echo htmlspecialchars($ps['name']); ?></td>
                <td><span class="badge"><?php echo htmlspecialchars($ps['type'] ?? 'N/A'); ?></span></td>
                <td>
                    <select name="enabled_<?php echo $ps['id']; ?>" class="status-select">
                        <option value="1" <?php echo $ps['enabled'] ? 'selected' : ''; ?>>Включено</option>
                        <option value="0" <?php echo !$ps['enabled'] ? 'selected' : ''; ?>>Выключено</option>
                    </select>
                    <?php if ($ps['enabled']): ?>
                        <span class="status-on">Вкл</span>
                    <?php else: ?>
                        <span class="status-off">Выкл</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="btn-settings" onclick="showSettings(<?php echo $ps['id']; ?>, '<?php echo htmlspecialchars(addslashes($ps['name'])); ?>', <?php echo htmlspecialchars(json_encode($settings)); ?>)">
                        Настроить
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    </table>
    
    <div class="form-actions">
        <button type="submit" class="btn-primary">Сохранить изменения</button>
    </div>
</form>

<!-- Модальное окно настроек -->
<div id="settingsModal">
    <div>
        <h3 id="modalTitle">Настройки платежной системы</h3>
        
        <form method="POST" id="settingsForm">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="system_id" id="modalSystemId">
            
            <div class="modal-form-group">
                <label>API Key:</label>
                <input type="text" name="api_key" id="modalApiKey">
            </div>
            
            <div class="modal-form-group">
                <label>Secret Key:</label>
                <input type="text" name="secret_key" id="modalSecretKey">
            </div>
            
            <div class="modal-form-group">
                <label>Merchant ID:</label>
                <input type="text" name="merchant_id" id="modalMerchantId">
            </div>
            
            <div class="modal-form-group">
                <label>Webhook URL:</label>
                <input type="text" name="webhook_url" id="modalWebhookUrl" placeholder="https://domen.pw/api/payment/webhook/">
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Сохранить</button>
                <button type="button" onclick="closeSettings()">Отмена</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

