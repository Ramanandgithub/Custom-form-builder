-- Form Builder Database Migration
-- Run this file to set up the database

CREATE DATABASE IF NOT EXISTS `form_builder` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `form_builder`;

-- Admins table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forms table
CREATE TABLE IF NOT EXISTS `forms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` VARCHAR(36) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
    INDEX idx_uuid (`uuid`),
    INDEX idx_active (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fields table
CREATE TABLE IF NOT EXISTS `fields` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `form_id` INT UNSIGNED NOT NULL,
    `field_type` ENUM('text','email','number','textarea','dropdown','radio','checkbox','file') NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `placeholder` VARCHAR(255) DEFAULT '',
    `is_required` TINYINT(1) DEFAULT 0,
    `sort_order` INT UNSIGNED DEFAULT 0,
    `options` JSON DEFAULT NULL COMMENT 'For dropdown, radio, checkbox options',
    `validation_rules` JSON DEFAULT NULL COMMENT 'min, max, pattern etc.',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    INDEX idx_form_id (`form_id`),
    INDEX idx_sort_order (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Submissions table
CREATE TABLE IF NOT EXISTS `submissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `form_id` INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    INDEX idx_form_id (`form_id`),
    INDEX idx_submitted_at (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Submission values table
CREATE TABLE IF NOT EXISTS `submission_values` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT UNSIGNED NOT NULL,
    `field_id` INT UNSIGNED NOT NULL,
    `value` TEXT,
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`field_id`) REFERENCES `fields`(`id`) ON DELETE CASCADE,
    INDEX idx_submission_id (`submission_id`),
    INDEX idx_field_id (`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrations tracking
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `ran_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `migrations` (`migration`) VALUES ('001_initial_schema');

-- Default admin (password: Admin@123)
INSERT IGNORE INTO `admins` (`username`, `email`, `password`)
VALUES (
    'admin',
    'admin@formbuilder.com',
    '$argon2id$v=19$m=65536,t=4,p=1$dGVzdHNhbHQxMjM0NTY$placeholder'
);
-- Note: Run migrations/seed.php to create the proper admin password hash