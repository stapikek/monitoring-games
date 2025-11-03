-- ============================================
-- База данных для мониторинга CS2 серверов
-- Полная версия со всеми таблицами и обновлениями
-- ============================================
-- ВАЖНО: Перед импортом выберите нужную базу данных в phpMyAdmin или выполните: USE `имя_вашей_базы`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- ОСНОВНЫЕ ТАБЛИЦЫ
-- ============================================

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `steam_id` varchar(17) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `steam_id` (`steam_id`),
  KEY `idx_balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица игр
CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица режимов игры
CREATE TABLE IF NOT EXISTS `game_modes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица карт
CREATE TABLE IF NOT EXISTS `maps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица тегов
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица серверов
CREATE TABLE IF NOT EXISTS `servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `port` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `game_mode_id` int(11) DEFAULT NULL,
  `map_id` int(11) DEFAULT NULL COMMENT 'Deprecated: карта берется из Steam Query API',
  `current_players` int(11) DEFAULT 0,
  `max_players` int(11) DEFAULT 64,
  `peak_players` int(11) DEFAULT 0,
  `rating` int(11) DEFAULT 0,
  `current_map` varchar(100) DEFAULT NULL,
  `description` text,
  `features` text,
  `discord_url` varchar(255) DEFAULT NULL,
  `vk_url` varchar(255) DEFAULT NULL,
  `site_url` varchar(255) DEFAULT NULL,
  `location_country` varchar(100) DEFAULT NULL,
  `location_country_code` varchar(2) DEFAULT NULL,
  `location_city` varchar(100) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('active','pending','rejected') DEFAULT 'pending',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `game_mode_id` (`game_mode_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `idx_rating` (`rating`),
  KEY `idx_current_map` (`current_map`),
  KEY `idx_location_country` (`location_country_code`),
  CONSTRAINT `servers_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  CONSTRAINT `servers_ibfk_2` FOREIGN KEY (`game_mode_id`) REFERENCES `game_modes` (`id`),
  CONSTRAINT `servers_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица связей серверов и тегов
CREATE TABLE IF NOT EXISTS `server_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_tag_unique` (`server_id`, `tag_id`),
  KEY `server_id` (`server_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `server_tags_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `server_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица голосов за серверы
CREATE TABLE IF NOT EXISTS `server_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `server_id` (`server_id`),
  KEY `user_id` (`user_id`),
  KEY `voted_at` (`voted_at`),
  KEY `idx_user_server_voted` (`user_id`, `server_id`, `voted_at` DESC),
  CONSTRAINT `server_votes_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `server_votes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица отзывов о серверах
CREATE TABLE IF NOT EXISTS `server_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `server_id` (`server_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `server_reviews_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `server_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица VIP статусов серверов
CREATE TABLE IF NOT EXISTS `server_vip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_id` int(11) NOT NULL,
  `vip_until` datetime NOT NULL,
  `name_color` varchar(7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_id` (`server_id`),
  KEY `vip_until` (`vip_until`),
  CONSTRAINT `server_vip_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица проектов
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `discord` varchar(100) DEFAULT NULL,
  `vk` varchar(255) DEFAULT NULL,
  `total_rating` int(11) DEFAULT 0,
  `status` enum('pending','active','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projects_user` (`user_id`),
  KEY `idx_projects_status` (`status`),
  KEY `idx_projects_rating` (`total_rating`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Связь проектов с серверами
CREATE TABLE IF NOT EXISTS `project_servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_server` (`project_id`, `server_id`),
  CONSTRAINT `project_servers_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_servers_ibfk_2` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица хостингов
CREATE TABLE IF NOT EXISTS `hostings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text,
  `website_url` varchar(255) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `reviews_count` int(11) DEFAULT 0,
  `status` enum('active','pending','inactive') DEFAULT 'pending',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица связи хостингов и игр
CREATE TABLE IF NOT EXISTS `hosting_games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hosting_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `custom_game_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hosting_id` (`hosting_id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `hosting_games_ibfk_1` FOREIGN KEY (`hosting_id`) REFERENCES `hostings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hosting_games_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица отзывов о хостингах
CREATE TABLE IF NOT EXISTS `hosting_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hosting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hosting_id` (`hosting_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `hosting_reviews_ibfk_1` FOREIGN KEY (`hosting_id`) REFERENCES `hostings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hosting_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица платежных систем
CREATE TABLE IF NOT EXISTS `payment_systems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `api_key` text,
  `secret_key` text,
  `merchant_id` varchar(255) DEFAULT NULL,
  `webhook_url` varchar(500) DEFAULT NULL,
  `settings` text,
  `min_amount` decimal(10,2) DEFAULT 1.00,
  `max_amount` decimal(10,2) DEFAULT 100000.00,
  `fee_percent` decimal(5,2) DEFAULT 0.00,
  `fee_fixed` decimal(10,2) DEFAULT 0.00,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица платежей
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `payment_system_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `payment_id` varchar(255) DEFAULT NULL,
  `payment_url` text,
  `metadata` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_payment_system_id` (`payment_system_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_id` (`payment_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`payment_system_id`) REFERENCES `payment_systems` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица настроек сайта
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_logo` varchar(255) DEFAULT NULL,
  `site_logo_text` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- НАЧАЛЬНЫЕ ДАННЫЕ
-- ============================================

-- Игры
INSERT INTO `games` (`name`, `code`, `icon`) VALUES
('Counter Strike 2', 'cs2', 'game-icons/icons8-counter-strike-2.svg'),
('Counter Strike: Global Offensive', 'csgo', 'game-icons/icons8-counter-strike-global-offensive.svg'),
('Counter Strike 1.6', 'cs16', 'game-icons/icons8-counter-strike-2.svg'),
('Counter Strike: Source', 'css', 'game-icons/icons8-counter-strike-source.svg')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Режимы игры
INSERT INTO `game_modes` (`name`, `code`) VALUES
('DeathMatch', 'dm'),
('Public', 'public'),
('Arena', 'arena'),
('AIM', 'aim'),
('GunGame', 'gungame'),
('Jailbreak', 'jailbreak'),
('Surf', 'surf'),
('Zombie Escape', 'zombie'),
('DeathRun', 'deathrun'),
('Hide&Seek', 'hideandseek'),
('Retake', 'retake'),
('KZ', 'kz'),
('PropHunt', 'prophunt')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Карты
INSERT INTO `maps` (`name`, `code`) VALUES
('Mirage', 'de_mirage'),
('Dust 2', 'de_dust2'),
('Inferno', 'de_inferno'),
('Nuke', 'de_nuke'),
('Overpass', 'de_overpass'),
('Vertigo', 'de_vertigo'),
('Ancient', 'de_ancient'),
('Anubis', 'de_anubis'),
('Mirage FPS', 'de_mirage_fps'),
('AWP Lego', 'awp_lego_2'),
('Sandstone', 'de_sandstone_new')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Теги
INSERT INTO `tags` (`name`, `color`) VALUES
('FPS Boost', '#27ae60'),
('VIP', '#f39c12'),
('Skins', '#3498db'),
('128 Tick', '#9b59b6'),
('Античит', '#e74c3c'),
('Новичкам', '#1abc9c'),
('Профессионалам', '#e67e22'),
('Активные админы', '#16a085')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Платежные системы
INSERT INTO `payment_systems` (`name`, `type`, `enabled`, `is_default`, `description`, `min_amount`, `max_amount`) VALUES
('FreeKassa', 'freekassa', 0, 0, 'Оплата через FreeKassa', 1.00, 100000.00),
('ЮKassa', 'yookassa', 0, 0, 'Оплата через ЮKassa', 1.00, 100000.00),
('Stripe', 'stripe', 0, 0, 'Оплата через Stripe', 1.00, 100000.00),
('PayPal', 'paypal', 0, 0, 'Оплата через PayPal', 1.00, 100000.00),
('Криптовалюты', 'crypto', 0, 0, 'Оплата криптовалютами', 10.00, 100000.00),
('Банковский перевод', 'bank_transfer', 0, 0, 'Оплата банковским переводом (в ручном режиме)', 100.00, 100000.00)
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Администратор (пароль: admin)
INSERT INTO `users` (`username`, `email`, `password`, `is_admin`, `balance`) VALUES
('admin', 'admin@cs2-monitoring.ru', '$2y$10$wRdfjWU6Hb5O/vHlq42zhOd6gIvZuHrzYllYgjxVMmddvVhYknVAy', 1, 0.00)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Настройки сайта
INSERT INTO `site_settings` (`id`, `site_logo_text`) VALUES (1, 'CS2 Мониторинг')
ON DUPLICATE KEY UPDATE `site_logo_text`='CS2 Мониторинг';

-- ============================================
-- КОНЕЦ ФАЙЛА
-- ============================================

