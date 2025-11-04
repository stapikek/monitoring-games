<?php
// drop_timer.php - Таймер выпадения дропа CS2

// SEO настройки
$page_title = 'Счетчик сброса дропа CS2 - CS2 Мониторинг';
$page_description = 'Отслеживайте момент сброса недельного лимита на получение предметов в Counter-Strike 2. Счетчик отображает оставшееся время до следующего выпадения контейнеров и оружия.';
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
                В Counter-Strike 2 реализован механизм получения предметов. Еженедельно, после повышения уровня, игроку предоставляется возможность выбрать два предмета из трех доступных вариантов. В качестве награды могут выпасть контейнеры и капсулы, скины для оружия, а также графити. На этой странице вы сможете увидеть, сколько времени осталось до сброса недельного ограничения на получение предметов.
            </p>
            <p>
                Счетчик обратного отсчета показывает оставшееся время до момента сброса системы дропа (выпадения контейнеров и скинов) в Counter-Strike 2.
            </p>
        </div>

        <div class="drop-timer-note">
            * Сброс системы дропа осуществляется каждую среду в 04:00 по московскому времени. Помимо этого, за несколько часов до обнуления лимита Steam проводит техническое обслуживание системы.
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

