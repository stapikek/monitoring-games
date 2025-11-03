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
                                  ps.api_key, ps.secret_key, ps.merchant_id, ps.webhook_url
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
    
    // Проверяем статус
    if ($payment['status'] == 'completed') {
        header("Location: /balance.php?success=payment_completed");
        exit;
    }
    
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
                <div class="warning-box">
                    <h4 class="warning-title">Ручная обработка платежа</h4>
                    <p class="warning-text">
                        Для завершения платежа администратор должен подтвердить получение платежа вручную. 
                        Пожалуйста, дождитесь подтверждения.
                    </p>
                    <p class="warning-text">
                        <strong>Сохраните ID платежа:</strong> <code class="warning-code"><?php echo htmlspecialchars($payment['payment_id']); ?></code>
                    </p>
                </div>
            <?php else: ?>
                <div class="warning-box">
                    <h4 class="warning-title">Интеграция в разработке</h4>
                    <p class="warning-text">
                        Интеграция с платежной системой "<?php echo htmlspecialchars($payment['payment_system_name']); ?>" 
                        еще не настроена. Для настройки:
                    </p>
                    <ol class="warning-ol">
                        <li>Перейдите в <a href="/admin/payment_systems.php" class="warning-link">админ панель → Платежные системы</a></li>
                        <li>Настройте API ключи и параметры для выбранной системы</li>
                        <li>Для ЮKassa, Stripe, PayPal нужно создать соответствующие обработчики в <code>/api/payment/</code></li>
                    </ol>
                    <p class="warning-text">
                        <strong>ID платежа для тестирования:</strong> 
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

