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
        
        // Получаем тип платежной системы для определения необходимых полей
        $stmt = $db->prepare("SELECT type FROM payment_systems WHERE id = :id");
        $stmt->bindParam(':id', $systemId);
        $stmt->execute();
        $system = $stmt->fetch();
        
        if (!$system) {
            header('Location: /admin/payment_systems.php?error=system_not_found');
            exit;
        }
        
        $type = $system['type'];
        $settings = [];
        
        // Определяем поля в зависимости от типа платежной системы
        switch ($type) {
            case 'freekassa':
                $settings = [
                    'merchant_id' => trim($_POST['merchant_id'] ?? ''),
                    'secret_key' => trim($_POST['secret_key'] ?? ''),
                    'secret_key2' => trim($_POST['secret_key2'] ?? ''),
                    'shop_id' => trim($_POST['shop_id'] ?? '')
                ];
                break;
                
            case 'yookassa':
                $settings = [
                    'shop_id' => trim($_POST['shop_id'] ?? ''),
                    'secret_key' => trim($_POST['secret_key'] ?? ''),
                    'webhook_url' => trim($_POST['webhook_url'] ?? '')
                ];
                break;
                
            case 'stripe':
                $settings = [
                    'publishable_key' => trim($_POST['publishable_key'] ?? ''),
                    'secret_key' => trim($_POST['secret_key'] ?? ''),
                    'webhook_secret' => trim($_POST['webhook_secret'] ?? '')
                ];
                break;
                
            case 'paypal':
                $settings = [
                    'client_id' => trim($_POST['client_id'] ?? ''),
                    'client_secret' => trim($_POST['client_secret'] ?? ''),
                    'mode' => trim($_POST['mode'] ?? 'sandbox')
                ];
                break;
                
            case 'crypto':
                $settings = [
                    'api_key' => trim($_POST['api_key'] ?? ''),
                    'wallet_address' => trim($_POST['wallet_address'] ?? ''),
                    'network' => trim($_POST['network'] ?? 'bitcoin')
                ];
                break;
                
            case 'bank_transfer':
                $settings = [
                    'account_number' => trim($_POST['account_number'] ?? ''),
                    'bank_name' => trim($_POST['bank_name'] ?? ''),
                    'inn' => trim($_POST['inn'] ?? ''),
                    'bik' => trim($_POST['bik'] ?? ''),
                    'recipient_name' => trim($_POST['recipient_name'] ?? '')
                ];
                break;
                
            default:
                $settings = [
                    'api_key' => trim($_POST['api_key'] ?? ''),
                    'secret_key' => trim($_POST['secret_key'] ?? ''),
                    'merchant_id' => trim($_POST['merchant_id'] ?? ''),
                    'webhook_url' => trim($_POST['webhook_url'] ?? '')
                ];
        }
        
        $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $stmt = $db->prepare("UPDATE payment_systems SET settings = :settings WHERE id = :id");
        $stmt->bindParam(':settings', $settingsJson);
        $stmt->bindParam(':id', $systemId);
        $stmt->execute();
    }
    
    header('Location: /admin/payment_systems.php');
    exit;
}

// Получаем список платежных систем с типом
$stmt = $db->query("SELECT id, name, type, enabled, is_default, settings FROM payment_systems ORDER BY name");
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
                    <button type="button" class="btn-settings" onclick="showSettings(<?php echo $ps['id']; ?>, '<?php echo htmlspecialchars(addslashes($ps['name'])); ?>', '<?php echo htmlspecialchars($ps['type']); ?>', <?php echo htmlspecialchars(json_encode($settings, JSON_UNESCAPED_UNICODE)); ?>)">
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
            
            <!-- Поля будут динамически добавлены через JavaScript -->
            <div id="settingsFields"></div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Сохранить</button>
                <button type="button" onclick="closeSettings()">Отмена</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

