<?php
// hosting.php - Страница детальной информации о хостинге

$hosting_id = intval($_GET['id'] ?? 0);

if ($hosting_id <= 0) {
    header('Location: /hostings.php');
    exit;
}

// Получаем подключение к базе данных
require_once __DIR__ . '/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Получаем информацию о хостинге
$stmt = $db->prepare("
    SELECT h.*, 
           AVG(hr.rating) as avg_rating,
           COUNT(hr.id) as reviews_count
    FROM hostings h
    LEFT JOIN hosting_reviews hr ON h.id = hr.hosting_id
    WHERE h.id = :id AND h.status = 'active'
    GROUP BY h.id
");
$stmt->bindParam(':id', $hosting_id);
$stmt->execute();
$hosting = $stmt->fetch();

if (!$hosting) {
    header('Location: /hostings.php');
    exit;
}

// Получаем отзывы о хостинге
$stmt = $db->prepare("
    SELECT hr.*, u.username, u.avatar
    FROM hosting_reviews hr
    LEFT JOIN users u ON hr.user_id = u.id
    WHERE hr.hosting_id = :hosting_id
    ORDER BY hr.created_at DESC
");
$stmt->bindParam(':hosting_id', $hosting_id);
$stmt->execute();
$reviews = $stmt->fetchAll();

// Получаем поддерживаемые игры для хостинга (из таблицы games и кастомные)
$games_stmt = $db->prepare("
    SELECT g.id, g.name, g.code
    FROM games g
    INNER JOIN hosting_games hg ON g.id = hg.game_id
    WHERE hg.hosting_id = :hosting_id AND hg.game_id IS NOT NULL
    ORDER BY g.name
");
$games_stmt->bindParam(':hosting_id', $hosting_id);
$games_stmt->execute();
$games = $games_stmt->fetchAll();

// Получаем кастомные игры
$custom_games_stmt = $db->prepare("
    SELECT custom_game_name as name
    FROM hosting_games
    WHERE hosting_id = :hosting_id AND custom_game_name IS NOT NULL
    ORDER BY custom_game_name
");
$custom_games_stmt->bindParam(':hosting_id', $hosting_id);
$custom_games_stmt->execute();
$custom_games = $custom_games_stmt->fetchAll();

// SEO настройки
$page_title = htmlspecialchars($hosting['name']) . ' - Хостинг CS2';
$page_description = $hosting['description'] ? htmlspecialchars(substr(strip_tags($hosting['description']), 0, 160)) : '';
$page_keywords = 'CS2, хостинг, ' . htmlspecialchars($hosting['name']) . ', игровые серверы';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/hosting.php?id=' . $hosting_id;

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/hosting.css'];
$additional_js = ['/assets/js/hosting.js'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="hosting-detail-page">
    <div class="hosting-header">
        <div class="hosting-logo-large">
            <?php if (!empty($hosting['logo'])): ?>
                <img src="<?php echo htmlspecialchars($hosting['logo']); ?>" alt="<?php echo htmlspecialchars($hosting['name']); ?>">
            <?php else: ?>
                <span class="logo-placeholder-text"><?php echo strtoupper(substr($hosting['name'], 0, 2)); ?></span>
            <?php endif; ?>
        </div>
        <div class="hosting-header-info">
            <h1 class="hosting-header-name"><?php echo htmlspecialchars($hosting['name']); ?></h1>
            <div class="hosting-header-rating">
                <span class="star">⭐</span>
                <span><?php echo number_format($hosting['avg_rating'] ?: 0, 1); ?>/5</span>
                <span>(<?php echo $hosting['reviews_count']; ?> отзывов)</span>
            </div>
            <div class="hosting-description">
                <?php echo nl2br(htmlspecialchars($hosting['description'])); ?>
            </div>
        </div>
    </div>

    <div class="games-section">
        <h2>Поддерживаемые игры</h2>
        <div class="games-grid">
            <?php foreach ($games as $game): ?>
                <div class="game-card">
                    <?php echo htmlspecialchars($game['name']); ?>
                </div>
            <?php endforeach; ?>
            <?php foreach ($custom_games as $custom_game): ?>
                <div class="game-card">
                    <?php echo htmlspecialchars($custom_game['name']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($hosting['website_url'])): ?>
        <a href="<?php echo htmlspecialchars($hosting['website_url']); ?>" target="_blank" class="visit-button">
            Перейти на сайт <?php echo htmlspecialchars($hosting['name']); ?>
        </a>
    <?php endif; ?>

    <div class="reviews-section">
        <h2>Что вы думаете об этом хостинг-провайдере?</h2>
        
        <?php if ($auth->isLoggedIn()): ?>
            <div class="review-form">
                <h3>Добавить отзыв</h3>
                <form id="review-form" method="POST" action="/api/add_hosting_review.php">
                    <input type="hidden" name="hosting_id" value="<?php echo $hosting_id; ?>">
                    <input type="hidden" name="rating" id="rating-input" value="1">
                    
                    <div class="rating-stars" id="rating-stars">
                        <span class="star active" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                    
                    <textarea name="comment" placeholder="Напишите ваш отзыв..." required></textarea>
                    
                    <button type="submit">Добавить отзыв</button>
                </form>
            </div>
        <?php else: ?>
            <div class="login-warning-box">
                <p class="login-warning-text">Для добавления отзыва необходимо <a href="/login.php" class="login-warning-link">войти</a> в систему.</p>
            </div>
        <?php endif; ?>

        <h2>Отзывы (<?php echo count($reviews); ?>)</h2>
        
        <?php if (empty($reviews)): ?>
            <div class="empty-reviews-state">
                Отзывов пока нет. Будьте первым!
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <?php if (!empty($review['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($review['avatar']); ?>" alt="<?php echo htmlspecialchars($review['username']); ?>" class="review-avatar">
                        <?php else: ?>
                            <div class="review-avatar-placeholder"><?php echo strtoupper(mb_substr($review['username'], 0, 1)); ?></div>
                        <?php endif; ?>
                        <span class="review-author"><?php echo htmlspecialchars($review['username']); ?></span>
                        <div class="review-rating">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <span style="color: <?php echo $i < $review['rating'] ? '#ffc107' : '#ddd'; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="review-date">
                        <?php echo date('d.m.Y H:i', strtotime($review['created_at'])); ?>
                    </div>
                    <div class="review-text">
                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                    </div>
                    <?php if ($auth->isLoggedIn() && ($auth->isAdmin() || $review['user_id'] == $_SESSION['user_id'])): ?>
                        <div class="review-actions">
                            <button type="button" onclick="showDeleteConfirm(<?php echo $review['id']; ?>)">Удалить отзыв</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно подтверждения удаления -->
<div id="deleteConfirmModal">
    <div>
        <h3>Подтверждение удаления</h3>
        <p>Вы уверены, что хотите удалить этот отзыв?</p>
        <div class="delete-modal-actions">
            <button onclick="closeDeleteConfirm()" class="btn-cancel">Отмена</button>
            <button onclick="confirmDelete()" id="confirmDeleteBtn" class="btn-delete">Удалить</button>
        </div>
    </div>
</div>

<form id="deleteReviewForm" method="POST" action="/api/delete_hosting_review.php" style="display: none;">
    <input type="hidden" name="review_id" id="deleteReviewId">
    <input type="hidden" name="hosting_id" value="<?php echo $hosting_id; ?>">
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

