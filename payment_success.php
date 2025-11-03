<?php
// payment_success.php - страница успешной оплаты

// Подключаем дополнительные CSS
$additional_css = ['/assets/css/payment_success.css'];
$additional_js = ['/assets/js/payment_success.js'];

require_once __DIR__ . '/includes/header.php';

if (!$auth->isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

if ($payment_id <= 0) {
    header("Location: /balance.php");
    exit;
}

// Получаем информацию о платеже
try {
    $payment_stmt = $db->prepare("SELECT p.*, ps.name as payment_system_name, ps.type as payment_system_type
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
    
    // Получаем текущий баланс
    $balance_stmt = $db->prepare("SELECT balance FROM users WHERE id = :id LIMIT 1");
    $balance_stmt->bindParam(":id", $_SESSION['user_id']);
    $balance_stmt->execute();
    $balance_data = $balance_stmt->fetch();
    $current_balance = floatval($balance_data['balance'] ?? 0);
    
} catch (PDOException $e) {
    header("Location: /balance.php?error=db_error");
    exit;
}
?>

<div class="form-container">
    <div class="payment-status-container">
        <?php if ($payment['status'] == 'completed'): ?>
            <div class="payment-icon success">✓</div>
            <h3 class="payment-title success">Оплата успешно выполнена!</h3>
            <div class="payment-amount">
                <?php echo number_format($payment['amount'], 2, '.', ' '); ?> ₽
            </div>
            <p class="payment-description">
                Зачислено на ваш баланс
            </p>
            <div class="payment-details">
                <p><span class="detail-label">Ваш текущий баланс:</span> 
                    <span class="balance-value">
                        <?php echo number_format($current_balance, 2, '.', ' '); ?> ₽
                    </span>
                </p>
                <p><span class="detail-label">ID платежа:</span> <code><?php echo htmlspecialchars($payment['payment_id']); ?></code></p>
                <p><span class="detail-label">Платежная система:</span> <?php echo htmlspecialchars($payment['payment_system_name']); ?></p>
                <?php if ($payment['fee'] > 0): ?>
                    <p><span class="detail-label">Комиссия:</span> <?php echo number_format($payment['fee'], 2, '.', ' '); ?> ₽</p>
                <?php endif; ?>
            </div>
            <div class="payment-actions">
                <a href="/shop.php" class="btn btn-primary">Перейти в магазин</a>
                <a href="/balance.php" class="btn btn-secondary">Пополнить еще</a>
            </div>
        <?php elseif ($payment['status'] == 'processing'): ?>
            <div class="payment-icon processing">🔄</div>
            <h3 class="payment-title processing">Платеж обрабатывается</h3>
            <p class="payment-description">
                Пожалуйста, подождите. Обычно обработка занимает несколько секунд.
            </p>
            <div class="payment-details">
                <p><span class="detail-label">Сумма:</span> <?php echo number_format($payment['final_amount'], 2, '.', ' '); ?> ₽</p>
                <p><span class="detail-label">ID платежа:</span> <code><?php echo htmlspecialchars($payment['payment_id']); ?></code></p>
            </div>
            <p class="payment-auto-refresh">
                Страница автоматически обновится через несколько секунд...
            </p>
        <?php elseif ($payment['status'] == 'failed' || $payment['status'] == 'cancelled'): ?>
            <div class="payment-icon failed">✗</div>
            <h3 class="payment-title failed">Оплата не выполнена</h3>
            <p class="payment-description">
                Платеж был отклонен или отменен.
            </p>
            <div class="payment-info-box">
                <p><span class="detail-label">ID платежа:</span> <code><?php echo htmlspecialchars($payment['payment_id']); ?></code></p>
            </div>
            <div class="payment-actions">
                <a href="/balance.php" class="btn btn-primary">Попробовать снова</a>
            </div>
        <?php else: ?>
            <div class="payment-icon waiting">⏳</div>
            <h3 class="payment-title waiting">Ожидание оплаты</h3>
            <p class="payment-description">
                Платеж ожидает оплаты.
            </p>
            <div class="payment-details">
                <p><span class="detail-label">Сумма:</span> <?php echo number_format($payment['final_amount'], 2, '.', ' '); ?> ₽</p>
                <p><span class="detail-label">ID платежа:</span> <code><?php echo htmlspecialchars($payment['payment_id']); ?></code></p>
            </div>
            <div class="payment-actions">
                <a href="/balance.php" class="btn btn-secondary">Вернуться</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

