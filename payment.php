<?php
// payment.php - страница оплаты платежа

// Подключаем дополнительные CSS
$additional_css = ['/assets/css/payment.css'];

require_once __DIR__ . '/includes/header.php';

if (!$auth->isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($payment_id <= 0) {
    header("Location: /balance.php?error=invalid_payment");
    exit;
}

// Получаем информацию о платеже
try {
    $payment_stmt = $db->prepare("SELECT p.*, ps.name as payment_system_name, ps.type as payment_system_type,
                                  ps.settings as payment_system_settings, ps.api_key, ps.secret_key, ps.merchant_id, ps.webhook_url
                                  FROM payments p
                                  LEFT JOIN payment_systems ps ON p.payment_system_id = ps.id
                                  WHERE p.id = :id AND p.user_id = :user_id LIMIT 1");
    $payment_stmt->bindParam(":id", $payment_id);
    $payment_stmt->bindParam(":user_id", $_SESSION['user_id']);
    $payment_stmt->execute();
    $payment = $payment_stmt->fetch();
    
    if (!$payment) {
        header("Location: /balance.php?error=payment_not_found");
        exit;
    }
    
    // Парсим настройки платежной системы
    $payment['settings'] = json_decode($payment['payment_system_settings'] ?? '{}', true);
    
    // Проверяем статус
    if ($payment['status'] == 'completed') {
        header("Location: /balance.php?success=payment_completed");
        exit;
    }
    
    // Проверяем наличие ошибки в URL
    $error = $_GET['error'] ?? '';
    
} catch (PDOException $e) {
    header("Location: /balance.php?error=db_error");
    exit;
}
?>

<div class="form-container">
    <div class="payment-container">
        <div class="payment-amount-section">
            <h3 class="payment-amount-title">Сумма к оплате</h3>
            <div class="payment-amount-value">
                <?php echo number_format($payment['final_amount'], 2, '.', ' '); ?> ₽
            </div>
            <?php if ($payment['fee'] > 0): ?>
                <p class="payment-fee-note">
                    Из них комиссия: <?php echo number_format($payment['fee'], 2, '.', ' '); ?> ₽
                </p>
            <?php endif; ?>
        </div>
        
        <div class="payment-info-box">
            <p class="payment-info-row"><strong>Платежная система:</strong> <?php echo htmlspecialchars($payment['payment_system_name']); ?></p>
            <p class="payment-info-row"><strong>ID платежа:</strong> <code><?php echo htmlspecialchars($payment['payment_id']); ?></code></p>
            <p class="payment-info-row"><strong>Статус:</strong> 
                <span class="badge <?php 
                    echo $payment['status'] == 'completed' ? 'badge-success' : 
                        ($payment['status'] == 'processing' ? 'badge-info' : 
                        ($payment['status'] == 'failed' ? 'badge-danger' : 'badge-warning')); 
                ?>">
                    <?php
                    $status_names = [
                        'pending' => 'Ожидает оплаты',
                        'processing' => 'Обрабатывается',
                        'completed' => 'Завершен',
                        'failed' => 'Ошибка',
                        'cancelled' => 'Отменен'
                    ];
                    echo $status_names[$payment['status']] ?? $payment['status'];
                    ?>
                </span>
            </p>
        </div>
        
        <?php if ($payment['status'] == 'pending'): ?>
            <?php if ($payment['payment_system_type'] == 'bank_transfer'): ?>
                <?php
                $settings = json_decode($payment['settings'] ?? '{}', true);
                ?>
                <div class="warning-box">
                    <h4 class="warning-title">Банковский перевод</h4>
                    <p class="warning-text">
                        <strong>Номер счета:</strong> <code><?php echo htmlspecialchars($settings['account_number'] ?? ''); ?></code><br>
                        <strong>Банк:</strong> <?php echo htmlspecialchars($settings['bank_name'] ?? ''); ?><br>
                        <strong>БИК:</strong> <?php echo htmlspecialchars($settings['bik'] ?? ''); ?><br>
                        <strong>ИНН:</strong> <?php echo htmlspecialchars($settings['inn'] ?? ''); ?><br>
                        <strong>Получатель:</strong> <?php echo htmlspecialchars($settings['recipient_name'] ?? ''); ?>
                    </p>
                    <p class="warning-text">
                        <strong>Сумма к переводу:</strong> <?php echo number_format($payment['final_amount'], 2, '.', ' '); ?> ₽<br>
                        <strong>ID платежа (укажите в назначении платежа):</strong> <code class="warning-code"><?php echo htmlspecialchars($payment['payment_id']); ?></code>
                    </p>
                    <p class="warning-text">
                        После перевода средств, администратор подтвердит платеж вручную. Обычно это занимает 1-3 рабочих дня.
                    </p>
                </div>
            <?php elseif ($payment['payment_system_type'] == 'crypto'): ?>
                <?php
                $metadata = json_decode($payment['metadata'] ?? '{}', true);
                ?>
                <div class="warning-box">
                    <h4 class="warning-title">Криптовалютный платеж</h4>
                    <p class="warning-text">
                        <strong>Сеть:</strong> <?php echo htmlspecialchars(strtoupper($metadata['network'] ?? 'bitcoin')); ?><br>
                        <strong>Адрес кошелька:</strong> <code class="warning-code"><?php echo htmlspecialchars($metadata['wallet_address'] ?? ''); ?></code><br>
                        <strong>Сумма:</strong> <?php echo number_format($payment['final_amount'], 2, '.', ' '); ?> ₽ (или эквивалент в выбранной криптовалюте)
                    </p>
                    <p class="warning-text">
                        Отправьте средства на указанный адрес. После подтверждения транзакции в блокчейне, средства будут зачислены на ваш баланс автоматически.
                    </p>
                    <p class="warning-text">
                        <strong>ID платежа:</strong> <code class="warning-code"><?php echo htmlspecialchars($payment['payment_id']); ?></code>
                    </p>
                </div>
            <?php elseif (!empty($payment['payment_url']) && strpos($payment['payment_url'], 'http') === 0): ?>
                <div class="payment-actions">
                    <a href="<?php echo htmlspecialchars($payment['payment_url']); ?>" class="btn btn-primary" target="_blank">
                        Перейти к оплате
                    </a>
                </div>
            <?php elseif ($error == 'api_error'): ?>
                <div class="warning-box">
                    <h4 class="warning-title">Ошибка при создании платежа</h4>
                    <p class="warning-text">
                        Произошла ошибка при подключении к платежной системе "<?php echo htmlspecialchars($payment['payment_system_name']); ?>".
                    </p>
                    <p class="warning-text">
                        Возможные причины:
                    </p>
                    <ol class="warning-ol">
                        <li>Неверные API ключи в настройках платежной системы</li>
                        <li>Проблемы с подключением к серверам платежной системы</li>
                        <li>Неверные параметры в настройках (например, неверная валюта или сумма)</li>
                    </ol>
                    <p class="warning-text">
                        <strong>Что делать:</strong>
                    </p>
                    <ol class="warning-ol">
                        <li>Проверьте правильность API ключей в <a href="/admin/payment_systems.php" class="warning-link">админ панели</a></li>
                        <li>Убедитесь, что платежная система включена</li>
                        <li>Проверьте логи сервера для получения подробной информации об ошибке</li>
                    </ol>
                    <p class="warning-text">
                        <strong>ID платежа:</strong> 
                        <code class="warning-code"><?php echo htmlspecialchars($payment['payment_id']); ?></code>
                    </p>
                </div>
            <?php elseif ($error == 'connection'): ?>
                <div class="warning-box">
                    <h4 class="warning-title">Ошибка подключения</h4>
                    <p class="warning-text">
                        Не удалось подключиться к платежной системе "<?php echo htmlspecialchars($payment['payment_system_name']); ?>".
                        Проверьте подключение к интернету или попробуйте позже.
                    </p>
                </div>
            <?php else: ?>
                <div class="warning-box">
                    <h4 class="warning-title">Настройка платежной системы</h4>
                    <p class="warning-text">
                        Для использования платежной системы "<?php echo htmlspecialchars($payment['payment_system_name']); ?>" 
                        необходимо настроить API ключи:
                    </p>
                    <ol class="warning-ol">
                        <li>Перейдите в <a href="/admin/payment_systems.php" class="warning-link">админ панель → Платежные системы</a></li>
                        <li>Нажмите кнопку "Настроить" напротив нужной платежной системы</li>
                        <li>Заполните все обязательные поля (API ключи, идентификаторы и т.д.)</li>
                        <li>Сохраните настройки</li>
                        <li>Убедитесь, что платежная система включена (статус "Включено")</li>
                    </ol>
                    <p class="warning-text">
                        <strong>ID платежа:</strong> 
                        <code class="warning-code"><?php echo htmlspecialchars($payment['payment_id']); ?></code>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="payment-actions">
            <a href="/balance.php" class="btn btn-secondary">Вернуться к пополнению</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

