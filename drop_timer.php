<?php
// drop_timer.php - Таймер выпадения дропа CS2

// SEO настройки
$page_title = 'Таймер выпадения Дропа в CS2 - CS2 Мониторинг';
$page_description = 'Узнайте, когда обнулится еженедельный лимит получения предметов в Counter-Strike 2. Таймер показывает время до следующего дропа кейсов и скинов.';
$page_keywords = 'CS2, дроп, таймер дропа, кейсы, скины, выпадение предметов';
$canonical_url = 'https://' . $_SERVER['HTTP_HOST'] . '/drop_timer.php';

// Подключаем дополнительные CSS и JS
$additional_css = ['/assets/css/drop_timer.css'];
$additional_js = ['/assets/js/drop_timer.js'];

require_once __DIR__ . '/includes/header.php';
?>

<div style="padding: 0.5rem 0;">

<div class="drop-timer-page">
    <div class="drop-timer-header">
        <h1>Таймер выпадения Дропа в CS2</h1>
    </div>

    <div class="drop-timer-content">
        <div class="drop-timer-box">
            <img src="https://domen.pw/img/cases/case.webp" alt="CS2 Case" />
            <div class="drop-timer-display" id="timer">
                <span id="days" class="drop-timer-unit">0</span> дн
                <span id="hours" class="drop-timer-unit">0</span> ч
                <span id="minutes" class="drop-timer-unit">0</span> мин
                <span id="seconds" class="drop-timer-unit">0</span> сек
            </div>
        </div>

        <div class="drop-timer-info">
            <p>
                В Counter-Strike 2 существует система Дропа предметов. 1 раз в неделю, после достижения нового уровня - вам предлагается на выбор два из трех бесплатных предметов. Это могут быть как кейсы и капсулы, скины на оружие, а также различные графити. На данной странице вы можете узнать, когда обнулится еженедельный лимит получения предметов.
            </p>
            <p>
                Таймер, и еженедельный отсчет времени - когда обнулится Дроп предметов (выпадение кейсов и скинов) в Counter-Strike 2.
            </p>
        </div>

        <div class="drop-timer-note">
            * Обнуление дропа происходит каждую Среду, в 4:00 по МСК. Вместе с этим, за пару часов до сброса - Steam уходит на профилактические работы
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

