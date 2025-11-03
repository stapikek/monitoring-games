<?php
// balance.php - страница пополнения баланса

// SEO настройки
$page_title = 'Пополнить баланс - CS2 Мониторинг';
$page_description = 'Пополните баланс в CS2 мониторинге. Купите VIP для сервера и повысьте рейтинг. Безопасные платежи.';
$page_keywords = 'CS2, пополнить баланс, оплата';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/balance.php';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/balance.css'];
$additional_js = ['/assets/js/balance.js'];

require_once __DIR__ . '/includes/header.php';

if (!$auth->isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user_id = $auth->getUserId();

// Получаем текущий баланс пользователя
try {
    $balance_stmt = $db->prepare("SELECT balance FROM users WHERE id = :id");
    $balance_stmt->bindParam(":id", $user_id);
    $balance_stmt->execute();
    $user_data = $balance_stmt->fetch();
    $current_balance = floatval($user_data['balance'] ?? 0);
} catch (PDOException $e) {
    $current_balance = 0;
}

// Получаем доступные платежные системы
try {
    $payment_systems_stmt = $db->query("SELECT * FROM payment_systems WHERE enabled = 1 ORDER BY is_default DESC, name");
    $payment_systems = $payment_systems_stmt->fetchAll();
} catch (PDOException $e) {
    $payment_systems = [];
}
?>

<div class="form-container">
    <div class="balance-info">
        <h3>Ваш баланс</h3>
        <div class="balance-amount-display">
            <?php echo number_format($current_balance, 2, '.', ' '); ?> ₽
        </div>
        <p class="balance-help">
            Баланс можно использовать для покупки рейтинга и VIP статуса в <a href="/shop.php">магазине</a>
        </p>
    </div>
    
    <h3 class="section-title">Пополнить баланс</h3>
    
    <div class="amount-buttons-grid">
        <button onclick="setAmount(100)" class="btn amount-btn">
            <div class="amount-value">100 ₽</div>
        </button>
        <button onclick="setAmount(500)" class="btn amount-btn">
            <div class="amount-value">500 ₽</div>
        </button>
        <button onclick="setAmount(1000)" class="btn amount-btn">
            <div class="amount-value">1,000 ₽</div>
        </button>
        <button onclick="setAmount(2000)" class="btn amount-btn amount-btn-popular">
            <div class="amount-value">2,000 ₽</div>
            <div class="amount-badge">Популярно</div>
        </button>
        <button onclick="setAmount(5000)" class="btn amount-btn amount-btn-benefit">
            <div class="amount-value">5,000 ₽</div>
            <div class="amount-badge">Выгодно</div>
        </button>
    </div>
    
    <?php if (empty($payment_systems)): ?>
        <div class="warning-box">
            <h4>Внимание:</h4>
            <p>
                Платежные системы не настроены. Обратитесь к администратору или настройте платежные системы в <a href="/admin/payment_systems.php">админ панели</a>.
            </p>
        </div>
    <?php else: ?>
        <form id="balance-form" onsubmit="addBalance(event)" class="balance-form-wrapper">
            <div class="form-group-balance">
                <label for="amount">Сумма пополнения:</label>
                <input type="number" id="amount" name="amount" min="1" max="100000" step="0.01" value="100" required>
                <p class="form-help">
                    Минимальная сумма: 1 ₽, максимальная: 100,000 ₽
                </p>
            </div>
            
            <div class="form-group-balance">
                <label for="payment_system">Способ оплаты:</label>
                <select id="payment_system" name="payment_system" required>
                    <?php foreach ($payment_systems as $ps): ?>
                        <option value="<?php echo $ps['id']; ?>" <?php echo $ps['is_default'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ps['name']); ?>
                            <?php if ($ps['fee_percent'] > 0 || $ps['fee_fixed'] > 0): ?>
                                (комиссия: <?php 
                                    $fee_text = [];
                                    if ($ps['fee_percent'] > 0) $fee_text[] = $ps['fee_percent'] . '%';
                                    if ($ps['fee_fixed'] > 0) $fee_text[] = $ps['fee_fixed'] . ' ₽';
                                    echo implode(' + ', $fee_text);
                                ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($payment_systems)): ?>
                    <?php foreach ($payment_systems as $ps): ?>
                        <?php if (!empty($ps['description'])): ?>
                            <p id="desc-<?php echo $ps['id']; ?>" class="payment-desc">
                                <?php echo htmlspecialchars($ps['description']); ?>
                            </p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-primary btn-submit-balance">
                <span id="balance-btn-text">Пополнить баланс</span>
                <span id="balance-btn-spinner" class="btn-spinner">🔄</span>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

