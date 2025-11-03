<?php
// hostings.php - Страница хостингов

// SEO настройки
$page_title = 'Хостинги CS2 - CS2 Мониторинг';
$page_description = 'Сравните цены различных хостингов CS2, просматривайте отзывы клиентов и средний рейтинг. Надежный хостинг для игровых серверов Counter Strike 2.';
$page_keywords = 'CS2, хостинг, игровые серверы, Counter Strike, DDoS защита';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/hostings.php';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/hostings.css'];

require_once __DIR__ . '/includes/header.php';

// Получаем список активных хостингов с поддерживаемыми играми
$stmt = $db->prepare("
    SELECT h.*, 
           AVG(hr.rating) as avg_rating,
           COUNT(hr.id) as reviews_count,
           GROUP_CONCAT(
               COALESCE(g.name, hg.custom_game_name) 
               SEPARATOR ', '
           ) as supported_games
    FROM hostings h
    LEFT JOIN hosting_reviews hr ON h.id = hr.hosting_id
    LEFT JOIN hosting_games hg ON h.id = hg.hosting_id
    LEFT JOIN games g ON hg.game_id = g.id
    WHERE h.status = 'active'
    GROUP BY h.id
    ORDER BY h.sort_order ASC, h.name ASC
");
$stmt->execute();
$hostings = $stmt->fetchAll();
?>

<div class="hostings-page">
    <div class="hostings-intro">
        <h2>Собираетесь открывать свой сервер, и находитесь в поисках качественного игрового хостинга серверов Counter Strike 2?</h2>
        <p>
            Хороший хостинг игровых серверов Counter Strike должен радовать стабильной работой не зависимо от онлайна и загруженности сервера, помимо этого, сервер должен иметь защиту от DDoS атак.
        </p>
        <p>
            От этих параметров напрямую зависит конечная стоимость хостинга. Мы поможем вам сравнить цены различных хостингов, просматривайте отзывы от клиентов, а так-же средний рейтинг.
        </p>
    </div>

    <?php if (empty($hostings)): ?>
        <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <p>Хостинги пока не добавлены</p>
        </div>
    <?php else: ?>
        <?php foreach ($hostings as $hosting): ?>
            <div class="hosting-card">
                <div class="hosting-logo">
                    <?php if (!empty($hosting['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($hosting['logo']); ?>" alt="<?php echo htmlspecialchars($hosting['name']); ?>">
                    <?php else: ?>
                        <span style="font-size: 2rem; color: #ccc; font-weight: bold;"><?php echo strtoupper(substr($hosting['name'], 0, 2)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="hosting-info">
                    <h3 class="hosting-name"><?php echo htmlspecialchars($hosting['name']); ?></h3>
                    <div class="hosting-rating">
                        <span class="star">⭐</span>
                        <span><?php echo number_format($hosting['avg_rating'] ?: $hosting['rating'], 1); ?>/5</span>
                        <span>(<?php echo $hosting['reviews_count']; ?> отзывов)</span>
                    </div>
                    <?php if (!empty($hosting['supported_games'])): ?>
                        <div class="hosting-games" style="margin-bottom: 0.75rem;">
                            <strong style="color: var(--text-primary);">Поддерживаемые игры:</strong>
                            <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($hosting['supported_games']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="hosting-description">
                        <?php echo nl2br(htmlspecialchars($hosting['description'])); ?>
                    </div>
                    <div class="hosting-buttons">
                        <?php if (!empty($hosting['website_url'])): ?>
                            <a href="<?php echo htmlspecialchars($hosting['website_url']); ?>" target="_blank" class="btn-visit-site">
                                Перейти на сайт <?php echo htmlspecialchars($hosting['name']); ?>
                            </a>
                        <?php endif; ?>
                        <a href="/hosting.php?id=<?php echo $hosting['id']; ?>" class="btn-details">
                            Подробнее
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

