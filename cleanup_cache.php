<?php
// cleanup_cache.php - скрипт для очистки устаревшего кеша
// Запускать через cron каждый час: 0 * * * * php /path/to/cleanup_cache.php

require_once __DIR__ . '/config/cache.php';

echo "=== Очистка устаревшего кеша ===" . PHP_EOL;
echo "Время: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// Очищаем устаревшие записи
$deleted = $cache->cleanExpired();

echo "Удалено устаревших файлов кеша: $deleted" . PHP_EOL;

// Опционально: полная очистка кеша раз в день (если текущий час = 3)
if (date('H') == '03') {
    $cache->clear();
    echo "Выполнена полная очистка кеша (ежедневная процедура)" . PHP_EOL;
}

echo PHP_EOL . "✅ Очистка завершена" . PHP_EOL;

