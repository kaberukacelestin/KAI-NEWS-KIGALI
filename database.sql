-- ============================================
-- KAI NEWS KIGALI — DATABASE SCHEMA
-- Database: kai_news_kigali
-- ============================================

--CREATE DATABASE IF NOT EXISTS `kai_news_kigali` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--USE `kai_news_kigali`;

-- ============================================
-- ADMIN USERS TABLE
-- ============================================
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(200),
  `profile_picture` VARCHAR(500) DEFAULT NULL,
  `role` ENUM('superadmin','editor','journalist','notifier','suspended') DEFAULT 'journalist',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin: username=admin, password=admin123 (change after setup!)
INSERT INTO `admins` (`username`, `email`, `password`, `full_name`, `role`)
VALUES ('admin', 'admin@kainews.rw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'superadmin');

-- ============================================
-- NEWS CATEGORIES TABLE
-- ============================================
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `name_rw` VARCHAR(200) NOT NULL,
  `name_en` VARCHAR(200) NOT NULL,
  `name_sw` VARCHAR(200) NOT NULL,
  `name_fr` VARCHAR(200) NOT NULL,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO `categories` (`slug`, `name_rw`, `name_en`, `name_sw`, `name_fr`, `sort_order`) VALUES
('ahabanza',     'Ahabanza',        'Breaking News',    'Habari za Haraka',  'Brèves',            1),
('politiki',     'Politiki',        'Politics',         'Siasa',             'Politique',         2),
('imikino',      'Imikino',         'Sports',           'Michezo',           'Sports',            3),
('ubutabera',    'Ubutabera',       'Justice',          'Haki',              'Justice',           4),
('imyidagaduro', 'Imyidagaduro',    'Entertainment',    'Burudani',          'Divertissement',    5),
('ubuzima',      'Ubuzima',         'Health',           'Afya',              'Santé',             6),
('utuntu',       'Utuntu n\'Utundi','Miscellaneous',    'Mengineyo',         'Divers',            7),
('mumahanga',    'Mumahanga',       'International',    'Harakati za Kimataifa','International', 8);

-- ============================================
-- NEWS ARTICLES TABLE
-- ============================================
CREATE TABLE `news` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `admin_id` INT NOT NULL,

  `title_rw` VARCHAR(500) NOT NULL,
  `description_rw` LONGTEXT NOT NULL,

  `title_en` VARCHAR(500),
  `description_en` LONGTEXT,

  `title_sw` VARCHAR(500),
  `description_sw` LONGTEXT,

  `title_fr` VARCHAR(500),
  `description_fr` LONGTEXT,

  `thumbnail` VARCHAR(500) NOT NULL,
  `inline_images` JSON DEFAULT NULL,
  `article_references` JSON DEFAULT NULL,
  `is_headline` TINYINT(1) DEFAULT 0,
  `is_featured` TINYINT(1) DEFAULT 0,
  `status` ENUM('published','draft') DEFAULT 'published',
  `views` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`),
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB;

-- ============================================
-- AI NEWS SOURCE QUEUE (all generated articles remain drafts)
-- ============================================
CREATE TABLE `ai_news_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `fingerprint` CHAR(64) NOT NULL UNIQUE,
  `location_label` VARCHAR(120) NOT NULL,
  `feed_label` VARCHAR(160) NOT NULL,
  `source_name` VARCHAR(200) NOT NULL,
  `source_title` VARCHAR(500) NOT NULL,
  `source_excerpt` TEXT,
  `source_url` VARCHAR(2048) NOT NULL,
  `source_published_at` DATETIME DEFAULT NULL,
  `title_rw` VARCHAR(500) DEFAULT NULL,
  `description_rw` LONGTEXT DEFAULT NULL,
  `title_en` VARCHAR(500) DEFAULT NULL,
  `description_en` LONGTEXT DEFAULT NULL,
  `category_id` INT DEFAULT NULL,
  `news_id` INT DEFAULT NULL,
  `status` ENUM('new','ready','drafted','ignored') NOT NULL DEFAULT 'new',
  `error_message` VARCHAR(500) DEFAULT NULL,
  `first_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `drafted_at` DATETIME DEFAULT NULL,
  INDEX `idx_ai_news_status_published` (`status`, `source_published_at`),
  INDEX `idx_ai_news_news_id` (`news_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NEWS GALLERY
-- ============================================
CREATE TABLE `news_gallery` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `news_id` INT NOT NULL,
  `image_path` VARCHAR(500) NOT NULL,
  `caption` VARCHAR(500),
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`news_id`) REFERENCES `news`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- ADVERTISEMENTS TABLE
-- ============================================
CREATE TABLE `advertisements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(300) NOT NULL,
  `image` VARCHAR(500) NOT NULL,
  `video` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500),
  `position` ENUM('banner_top','banner_top_photo_1','banner_top_photo_2','banner_top_photo_3','banner_top_photo_4','banner_mid','sidebar','footer','popup') DEFAULT 'banner_top',
  `is_active` TINYINT(1) DEFAULT 1,
  `is_animated` TINYINT(1) DEFAULT 0,
  `animation_type` ENUM('slide','fade','bounce','pulse','zoom','flip') DEFAULT 'fade',
  `start_date` DATE,
  `end_date` DATE,
  `clicks` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- JOURNALIST REGISTRATION REQUESTS
-- ============================================
CREATE TABLE `journalist_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(200) NOT NULL,
  `username`  VARCHAR(100) NOT NULL UNIQUE,
  `email`     VARCHAR(150) NOT NULL UNIQUE,
  `password`  VARCHAR(255) NOT NULL,
  `phone`     VARCHAR(50),
  `profile_picture` VARCHAR(500) DEFAULT NULL,
  `status`    ENUM('pending','approved','rejected') DEFAULT 'pending',
  `note`      VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- SITE SETTINGS TABLE
-- ============================================
CREATE TABLE `settings` (
  `key_name` VARCHAR(100) PRIMARY KEY,
  `value` TEXT
) ENGINE=InnoDB;

INSERT INTO `settings` VALUES
('site_name',        'KAI News Kigali'),
('site_tagline_rw',  'Amakuru Ahuza Kigali n\'Isi Yose'),
('site_tagline_en',  'News Connecting Kigali and the World'),
('site_tagline_sw',  'Habari Zinazounganisha Kigali na Dunia'),
('site_tagline_fr',  'L\'actualité qui connecte Kigali et le monde'),
('facebook_url',     ''),
('twitter_url',      ''),
('youtube_url',      ''),
('whatsapp_number',  '');

-- ============================================
-- NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_id` INT NOT NULL,
  `sender_id` INT,
  `type` ENUM('article_edit','article_delete','news_published','system','admin_message') DEFAULT 'system',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT,
  `related_article_id` INT DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`recipient_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`related_article_id`) REFERENCES `news`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- ACTIVITY LOG / AUDIT TRAIL TABLE
-- ============================================
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` ENUM('article','ad','setting','user','journalist_request') DEFAULT 'article',
  `entity_id` INT,
  `old_value` LONGTEXT,
  `new_value` LONGTEXT,
  `ip_address` VARCHAR(50),
  `user_agent` VARCHAR(500),
  `status` ENUM('success','failed') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
  INDEX idx_admin_action (admin_id, action),
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB;
