<?php
// shop.php - страница магазина

// SEO настройки
$page_title = 'Магазин - CS2 Мониторинг';
$page_description = 'Купите VIP для своего сервера и увеличите его рейтинг в CS2 мониторинге. Привлекайте больше игроков на свой сервер.';
$page_keywords = 'CS2, магазин, VIP сервер, рейтинг, купить';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/shop.php';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/shop.css'];
$additional_js = ['/assets/js/shop.js'];

require_once __DIR__ . '/includes/header.php';

// Загружаем конфигурацию VIP планов
$vip_plans = require __DIR__ . '/config/vip_plans.php';

// Получаем информацию о пользователе
$user_balance = 0;
if ($auth->isLoggedIn()) {
    $user_id = $auth->getUserId();
    $user_stmt = $db->prepare("SELECT balance FROM users WHERE id = :id");
    $user_stmt->bindParam(":id", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->fetch();
    
    try {
        $user_balance = floatval($user['balance'] ?? 0);
    } catch (Exception $e) {
        $user_balance = 0;
    }
}
?>

<div class="form-container">
    <?php if ($auth->isLoggedIn()): ?>
        <div class="balance-block">
            <div class="balance-block-content">
                <div>
                    <strong class="balance-label">Ваш баланс:</strong>
                    <span class="balance-amount">
                        <?php echo number_format($user_balance, 2, '.', ' '); ?> ₽
                    </span>
                </div>
                <a href="/balance.php" class="btn btn-primary">
                    Пополнить
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!$auth->isLoggedIn()): ?>
        <div class="alert alert-warning">
            Для покупки необходимо <a href="/login.php">войти</a> или <a href="/register.php">зарегистрироваться</a>
        </div>
    <?php endif; ?>
</div>

<div class="shop-grid">
    
    <!-- Покупка рейтинга -->
    <div class="shop-item-wrapper">
        <div class="shop-item">
            <div class="shop-item-header">
                <h3>Покупка рейтинга</h3>
                <p>
                    Увеличьте рейтинг вашего сервера
                </p>
            </div>
            
            <div class="info-block info-primary">
                <p>
                    <strong>Важно:</strong> Постоянные очки рейтинга не имеют срока действия, и остаются навсегда. Рейтинг сервера является исключительной единицей конкретного сервера, не подлежит переносу или любым иным конвертациям, если иные условия не предусмотрены системой сайта.
                </p>
            </div>
            
            <?php if ($auth->isLoggedIn()): ?>
                <?php
                // Получаем серверы пользователя
                $user_servers_stmt = $db->prepare("
                    SELECT s.id, s.name, s.rating 
                    FROM servers s 
                    WHERE s.user_id = :user_id AND s.status = 'active'
                    ORDER BY s.name
                ");
                $user_servers_stmt->bindParam(":user_id", $_SESSION['user_id']);
                $user_servers_stmt->execute();
                $user_servers = $user_servers_stmt->fetchAll();
                ?>
                
                <?php if (empty($user_servers)): ?>
                    <div class="alert alert-warning">
                        У вас пока нет активных серверов. <a href="/add_server.php">Добавьте сервер</a> для покупки рейтинга.
                    </div>
                <?php else: ?>
                    <form id="rating-purchase-form" onsubmit="purchaseRating(event)">
                        <div class="form-group">
                            <label for="server_id">Выберите сервер:</label>
                            <select id="server_id" name="server_id" required>
                                <option value="">-- Выберите сервер --</option>
                                <?php foreach ($user_servers as $server): ?>
                                    <option value="<?php echo $server['id']; ?>" data-rating="<?php echo $server['rating']; ?>">
                                        <?php echo htmlspecialchars($server['name']); ?> (Текущий рейтинг: <?php echo $server['rating']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="rating_amount">Количество рейтинга:</label>
                            <input type="number" id="rating_amount" name="rating_amount" min="1" max="100000" value="1" required oninput="updatePrice()">
                            <p class="form-help-text">
                                1 рейтинг = 1 рубль (максимум 100,000 за раз)
                            </p>
                        </div>
                        
                        <div class="rating-calculator">
                            <div class="rating-calculator-row">
                                <span>Стоимость:</span>
                                <span class="total-price-display">
                                    <span id="total-price">1</span> ₽
                                </span>
                            </div>
                            <div class="rating-calculator-row">
                                <span>Оплата:</span>
                                <span id="payment-method">С баланса</span>
                            </div>
                            <div class="rating-calculator-row">
                                <span>Достаточно средств:</span>
                                <span id="balance-check" style="color: <?php echo ($user_balance >= 1) ? '#28a745' : '#dc3545'; ?>;"><?php echo ($user_balance >= 1) ? 'Да' : 'Нет'; ?></span>
                            </div>
                            <div class="rating-calculator-row">
                                <span>Текущий рейтинг:</span>
                                <span id="current-rating-display">-</span>
                            </div>
                            <div class="rating-calculator-row">
                                <span>Рейтинг после покупки:</span>
                                <span id="new-rating-display" class="new-rating-display">-</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full-width">
                            <span id="purchase-btn-text">Купить рейтинг</span>
                            <span id="purchase-btn-spinner" class="btn-spinner">🔄</span>
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    Для покупки рейтинга необходимо <a href="/login.php">войти</a> или <a href="/register.php">зарегистрироваться</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Покупка VIP -->
    <div class="shop-item-wrapper">
        <div class="shop-item">
            <div class="shop-item-header">
                <h3>VIP статус для сервера</h3>
            </div>
            
            <?php if ($auth->isLoggedIn()): ?>
                <?php
                // Получаем серверы пользователя с информацией о VIP
                $user_servers_vip_stmt = $db->prepare("
                    SELECT s.id, s.name, sv.vip_until
                    FROM servers s 
                    LEFT JOIN server_vip sv ON s.id = sv.server_id
                    WHERE s.user_id = :user_id AND s.status = 'active'
                    ORDER BY s.name
                ");
                $user_servers_vip_stmt->bindParam(":user_id", $_SESSION['user_id']);
                $user_servers_vip_stmt->execute();
                $user_servers_vip = $user_servers_vip_stmt->fetchAll();
                ?>
                
                <?php if (empty($user_servers_vip)): ?>
                    <div class="alert alert-warning alert-centered">
                        У вас пока нет активных серверов. <a href="/add_server.php">Добавьте сервер</a> для покупки VIP статуса.
                    </div>
                <?php else: ?>
                    <div class="info-block info-warning">
                        <p>
                            <strong>Важно:</strong> 1 VIP = 1 Сервер. VIP статус увеличивает видимость сервера в мониторинге и предоставляет дополнительные преимущества.
                        </p>
                    </div>
                    
                    <div class="vip-cards-grid">
                    <?php foreach ($vip_plans as $plan): ?>
                        <?php
                        // Определяем стили карточки
                        $card_bg = $plan['card_style'] === 'gradient' 
                            ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;' 
                            : 'background: white;';
                        $card_border = $plan['border_color'] ? "border: 2px solid {$plan['border_color']};" : 'border: 2px solid #667eea;';
                        $card_title_color = $plan['title_color'] ?? '#667eea';
                        $card_price_color = $plan['card_style'] === 'gradient' ? 'white' : '#333';
                        $card_desc_color = $plan['card_style'] === 'gradient' ? 'rgba(255,255,255,0.9)' : '#999';
                        
                        // Стиль кнопки
                        $button_bg = $plan['button_bg'] ?? '#667eea';
                        $button_color = $plan['button_color'] ?? 'white';
                        $button_style = "width: 100%; margin-top: 1rem; background: {$button_bg}; color: {$button_color}; font-weight: 600;";
                        if ($plan['card_style'] === 'gradient') {
                            $button_style .= ' border: none;';
                        }
                        ?>
                        <div class="vip-item <?php echo $plan['card_style'] === 'gradient' ? 'gradient' : ''; ?>" style="<?php echo $card_bg; ?> border: 2px solid <?php echo $plan['border_color'] ?? '#667eea'; ?>;">
                            <?php if (!empty($plan['badge_text'])): ?>
                                <div class="vip-badge" style="background: <?php echo $plan['badge_color']; ?>;">
                                    <?php echo htmlspecialchars($plan['badge_text']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <h4 style="color: <?php echo $card_title_color; ?>;">
                                <?php echo htmlspecialchars($plan['name']); ?>
                            </h4>
                            
                            <div class="vip-price" style="color: <?php echo $card_price_color; ?>;">
                                <?php echo number_format($plan['price'], 0, '.', ','); ?> ₽
                            </div>
                            
                            <p class="vip-desc" style="color: <?php echo $card_desc_color; ?>;">
                                <?php echo htmlspecialchars($plan['description']); ?>
                            </p>
                            
                            <button onclick="showVipForm(<?php echo $plan['weeks']; ?>, <?php echo $plan['price']; ?>)" class="btn" style="width: 100%; margin-top: 1rem; background: <?php echo $plan['button_bg'] ?? '#667eea'; ?>; color: <?php echo $plan['button_color'] ?? 'white'; ?>;">
                                Выбрать
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Форма покупки VIP (скрыта по умолчанию) -->
                    <div id="vip-purchase-form" class="vip-purchase-form" style="display: none;">
                    <h4>Оформление VIP статуса</h4>
                    <form id="vip-form" onsubmit="purchaseVip(event)">
                        <input type="hidden" id="vip-weeks" name="weeks">
                        <input type="hidden" id="vip-price" name="price">
                        
                        <div class="form-group">
                            <label for="vip-server-id">Выберите сервер:</label>
                            <select id="vip-server-id" name="server_id" required>
                                <option value="">-- Выберите сервер --</option>
                                <?php foreach ($user_servers_vip as $server): 
                                    $vip_active = false;
                                    $vip_until = null;
                                    if (!empty($server['vip_until'])) {
                                        $vip_until_date = new DateTime($server['vip_until']);
                                        $now = new DateTime();
                                        if ($vip_until_date > $now) {
                                            $vip_active = true;
                                            $vip_until = $vip_until_date;
                                        }
                                    }
                                ?>
                                    <option value="<?php echo $server['id']; ?>" data-vip-active="<?php echo $vip_active ? '1' : '0'; ?>" data-vip-until="<?php echo $vip_active ? $vip_until->format('Y-m-d H:i:s') : ''; ?>">
                                        <?php echo htmlspecialchars($server['name']); ?>
                                        <?php if ($vip_active): ?>
                                            (VIP до: <?php echo $vip_until->format('d.m.Y H:i'); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="vip-name-color">Цвет названия сервера (необязательно):</label>
                            <div class="color-input-wrapper">
                                <input type="color" id="vip-name-color" name="name_color" value="#000000" 
                                       class="color-input"
                                       title="Выберите цвет для названия сервера">
                                <input type="text" id="vip-name-color-text" value="" 
                                       class="color-text-input"
                                       placeholder="Оставьте пустым для цвета по умолчанию" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
                                       oninput="syncColorInputs()">
                                <button type="button" onclick="resetColor()" class="btn btn-gray">
                                    Убрать цвет
                                </button>
                            </div>
                            <p class="form-help-text">
                                Выберите цвет, которым будет отображаться название вашего сервера в списке. Если не выбрать цвет, название будет отображаться стандартным цветом.
                            </p>
                            <div id="color-preview" class="color-preview-container">
                                <strong>Пример:</strong> <span id="color-preview-text" class="color-preview-text">Название сервера</span>
                            </div>
                        </div>
                        
                        <div class="payment-summary">
                            <div class="payment-summary-row">
                                <span>Период:</span>
                                <span id="vip-period-display">-</span>
                            </div>
                            <div class="payment-summary-row">
                                <span>Стоимость:</span>
                                <span class="payment-summary-value price" id="vip-price-display">-</span>
                            </div>
                            <div class="payment-summary-row">
                                <span>Оплата:</span>
                                <span>С баланса</span>
                            </div>
                            <div class="payment-summary-row payment-summary-row-hidden" id="vip-balance-check-container">
                                <span>Достаточно средств:</span>
                                <span class="payment-summary-value" id="vip-balance-check">-</span>
                            </div>
                            <div class="payment-summary-row">
                                <span>Текущий VIP статус:</span>
                                <span id="vip-current-status">-</span>
                            </div>
                            <div class="payment-summary-row">
                                <span>VIP до:</span>
                                <span class="payment-summary-value success" id="vip-until-display">-</span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="button" onclick="hideVipForm()" class="btn btn-gray btn-flex">
                                Отмена
                            </button>
                            <button type="submit" class="btn btn-primary btn-flex">
                                <span id="vip-purchase-btn-text">Купить VIP</span>
                                <span id="vip-purchase-btn-spinner" class="btn-spinner">🔄</span>
                            </button>
                    </div>
                    </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning alert-centered">
                    Для покупки VIP статуса необходимо <a href="/login.php">войти</a> или <a href="/register.php">зарегистрироваться</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

