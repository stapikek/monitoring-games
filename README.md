# CS2 Мониторинг Серверов

Система мониторинга игровых серверов Counter-Strike 2 с функционалом добавления серверов, проектов, отзывов и платежной системы.

## Требования

- PHP 7.4 или выше
- MySQL 5.7 или выше (или MariaDB 10.3+)
- Apache/Nginx веб-сервер с поддержкой mod_rewrite/nginx rewrite
- PHP расширения: PDO, PDO_MySQL, JSON, mbstring, cURL
- Доступ к crontab для автоматических задач

## Установка

### 1. Загрузка файлов

Загрузите все файлы проекта в директорию вашего веб-сервера:

```bash
# Пример для Apache
/var/www/html/

# Пример для Nginx
/var/www/example.com/
```

### 2. Настройка прав доступа

Создайте необходимые директории и установите права:

```bash
cd /path/to/project

# Создание директорий
mkdir -p cache logs uploads/projects

# Установка прав
chmod 755 cache logs uploads/projects
chown -R www-data:www-data cache logs uploads/projects
```

### 3. Настройка базы данных

Откройте файл `config/database.php` и настройте параметры подключения:

```php
private $host = "localhost";
private $db_name = "monitoring";
private $username = "your_username";
private $password = "your_password";
```

Создайте базу данных и импортируйте схему:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE monitoring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE monitoring;
SOURCE /path/to/project/sql/database_full.sql;
```

Или через phpMyAdmin:

1. Создайте новую базу данных `monitoring`
2. Выберите кодировку `utf8mb4_unicode_ci`
3. Перейдите на вкладку "Импорт"
4. Выберите файл `sql/database_full.sql`
5. Нажмите "Вперед"

По умолчанию создается администратор:
- Логин: `admin`
- Пароль: `admin`

**ВАЖНО**: Смените пароль администратора сразу после установки.

### 4. Настройка веб-сервера

#### Apache

Создайте или отредактируйте `.htaccess` в корне проекта:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # HTTPS редирект (раскомментируйте если используете SSL)
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # Защита конфигов
    <FilesMatch "^config\.|^includes\.|^sql\.">
        Order Allow,Deny
        Deny from all
    </FilesMatch>
</IfModule>
```

#### Nginx

Добавьте в конфигурацию сайта:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/project;
    index index.php index.html;

    # Защита конфигов
    location ~ ^/(config|includes|sql)/ {
        deny all;
        return 404;
    }

    # PHP обработка
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Защита от доступа к системным файлам
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

### 5. Настройка Cron задач

#### Автоматическая установка (рекомендуется)

```bash
cd /path/to/project
php setup_cron.php --install
```

#### Ручная установка

```bash
crontab -e
```

Добавьте следующие строки:

```
# Обновление информации о серверах каждые 5 минут
*/5 * * * * /usr/bin/php /path/to/project/api/update_all_servers.php >> /path/to/project/logs/server_update.log 2>&1

# Очистка устаревшего кеша каждый час
0 * * * * /usr/bin/php /path/to/project/cleanup_cache.php >> /path/to/project/logs/cache_cleanup.log 2>&1
```

Замените `/path/to/project` на фактический путь к проекту.

### 6. Генерация Sitemap

Для генерации sitemap.xml:

```bash
php generate_sitemap.php
```

Для автоматической генерации добавьте в cron:

```
# Генерация sitemap каждый день в 2:00
0 2 * * * /usr/bin/php /path/to/project/generate_sitemap.php
```

### 7. Настройка платежных систем

Перейдите в админ-панель: `https://your-domain.com/admin/payment_systems.php`

Добавьте и настройте платежные системы:

1. Выберите платежную систему
2. Введите API ключи и настройки
3. Укажите комиссию и лимиты
4. Активируйте систему

## Структура проекта

```
project/
├── admin/                 # Административная панель
├── api/                   # API endpoints
├── assets/                # CSS, JS, изображения
│   ├── css/
│   └── js/
├── config/                # Конфигурационные файлы
│   ├── auth.php          # Аутентификация
│   ├── database.php      # База данных
│   └── steam_auth.php    # Steam авторизация
├── docs/                  # Документация
├── includes/              # Общие включения
├── sql/                   # SQL схемы
│   └── database_full.sql # Полная схема БД
├── uploads/               # Загруженные файлы
├── cache/                 # Кеш файлы
├── logs/                  # Логи
├── .htaccess             # Apache конфиг
├── robots.txt            # SEO конфиг
├── sitemap.xml           # Карта сайта
└── README.md             # Документация
```

## Безопасность

### SQL Injection
Все SQL запросы используют prepared statements PDO. Входные данные валидируются через `intval()`, `floatval()`, `filter_var()` и экранируются через `htmlspecialchars()`.

### XSS Protection
Все пользовательские данные экранируются при выводе через `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')`.

### Аутентификация
- Пароли хешируются через `password_hash()` с алгоритмом bcrypt
- Поддержка Steam OpenID авторизации
- Защита от CSRF реализуется через проверку сессий

### Рекомендации
1. Используйте HTTPS для всего сайта
2. Регулярно обновляйте пароли администратора
3. Настройте firewall для защиты от брутфорса
4. Регулярно проверяйте логи на подозрительную активность
5. Делайте резервные копии базы данных

## Документация

Дополнительная документация находится в директории `docs/`:

- `SECURITY_AUDIT.md` - Отчет о безопасности
- `API_DOCUMENTATION.md` - Документация API
- `AUTO_UPDATE_SETUP.md` - Настройка автоматических обновлений
- `PATH_CONFIGURATION.md` - Конфигурация путей
- `PROJECTS_GUIDE.md` - Руководство по проектам

## Поддерживаемые функции

### Для пользователей
- Регистрация и авторизация (включая Steam)
- Добавление и управление серверами
- Создание проектов из нескольких серверов
- Покупка рейтинга для серверов
- Покупка VIP статуса для серверов
- Пополнение баланса через платежные системы
- Просмотр отзывов о хостингах
- Голосование за серверы

### Для администраторов
- Модерация серверов и проектов
- Управление пользователями
- Управление играми, режимами и картами
- Управление тегами
- Управление платежными системами
- Управление хостингами
- Просмотр статистики
- Управление настройками сайта

## Мониторинг и логирование

### Логи приложения

Все логи сохраняются в директории `logs/`:

- `auth.log` - события авторизации
- `server.log` - события серверов (голосование, покупки)
- `vip.log` - покупки VIP статуса
- `admin.log` - действия администраторов
- `error.log` - системные ошибки
- `server_update.log` - обновления серверов (cron)
- `cache_cleanup.log` - очистка кеша (cron)

### Просмотр логов

```bash
# Последние записи
tail -n 100 logs/server.log

# Поиск ошибок
grep ERROR logs/error.log

# Мониторинг в реальном времени
tail -f logs/server_update.log
```

### Проверка Cron

Проверьте работу cron задач:

```bash
# Просмотр текущих задач
crontab -l

# Проверка логов обновления серверов
tail -f logs/server_update.log

# Проверка логов очистки кеша
tail -f logs/cache_cleanup.log
```

## Производительность

### Кеширование
Система использует файловый кеш для часто запрашиваемых данных:
- Списки игр, режимов, карт - кеш 1 час
- Информация о картах - кеш 1 час
- Топ карт - кеш 30 минут

### Оптимизация базы данных
- Использование индексов на часто запрашиваемых полях
- Ограничение выборки по LIMIT
- Использование JOIN вместо множественных запросов

### HTTP заголовки
Добавлены кеширующие заголовки для статических ресурсов (CSS, JS, изображения) со сроком 1 год.

## Обновление

Для обновления проекта:

1. Сделайте резервную копию базы данных
2. Загрузите новые файлы
3. Проверьте изменения в `sql/` директории
4. Выполните необходимые миграции БД
5. Очистите кеш: `php cleanup_cache.php`
6. Проверьте логи на ошибки

## Поддержка

При возникновении проблем:

1. Проверьте логи в директории `logs/`
2. Убедитесь, что PHP версия соответствует требованиям
3. Проверьте права доступа к директориям
4. Проверьте работу cron задач
5. Убедитесь, что база данных доступна
