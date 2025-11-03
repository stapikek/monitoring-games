<?php
// config/vip_plans.php - настройки VIP планов для магазина

return [
    [
        'id' => 1, // Уникальный идентификатор плана (для внутреннего использования)
        'name' => 'VIP 1 месяц', // Название плана, отображается в карточке
        'weeks' => 4, // Продолжительность VIP статуса в неделях (4 недели = 1 месяц)
        'price' => 150, // Цена плана в рублях
        'discount_percent' => 0, // Процент скидки (0 = без скидки)
        'description' => 'без скидки', // Описание под ценой (скидка или другое)
        'badge_text' => '', // Текст бейджа со скидкой (если пусто - бейдж не показывается)
        'badge_color' => '', // Цвет фона бейджа (hex код, например #28a745)
        'card_style' => 'normal', // Стиль карточки: normal (обычный), gradient (градиентный фон), golden (золотой)
        'title_color' => '#667eea', // Цвет заголовка плана (hex код)
        'border_color' => '#667eea', // Цвет границы карточки (hex код)
        'button_bg' => '#667eea', // Цвет фона кнопки (hex код)
        'button_color' => 'white', // Цвет текста кнопки (hex код или 'white', 'black' и т.д.)
    ],
    [
        'id' => 2,
        'name' => 'VIP 3 месяца',
        'weeks' => 12,
        'price' => 420,
        'discount_percent' => 0,
        'description' => 'без скидки',
        'badge_text' => '',
        'badge_color' => '',
        'card_style' => 'normal',
        'title_color' => '#667eea',
        'border_color' => '#667eea',
        'button_bg' => '#667eea',
        'button_color' => 'white',
    ],
    [
        'id' => 3,
        'name' => 'VIP 6 месяцев',
        'weeks' => 24,
        'price' => 840,
        'discount_percent' => 7,
        'description' => 'скидка 7%',
        'badge_text' => '-7%',
        'badge_color' => '#28a745',
        'card_style' => 'gradient',
        'title_color' => 'white',
        'border_color' => '#764ba2',
        'button_color' => 'white',
        'button_bg' => '#667eea', // Цвет фона кнопки (hex код, добавляется только для gradient стиля)
    ],
    [
        'id' => 4,
        'name' => 'VIP 1 год',
        'weeks' => 48,
        'price' => 1500,
        'discount_percent' => 17,
        'description' => 'скидка 17%',
        'badge_text' => '-17%',
        'badge_color' => '#dc3545',
        'card_style' => 'golden',
        'title_color' => '#ffc107',
        'border_color' => '#ffc107',
        'button_color' => '#333',
        'button_bg' => '#ffc107', // Цвет фона кнопки (hex код, добавляется только для gradient стиля)
    ],
];

