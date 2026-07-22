CREATE DATABASE IF NOT EXISTS bsg
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bsg;

CREATE TABLE IF NOT EXISTS registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    father_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(150) NOT NULL,
    house_number VARCHAR(80) NOT NULL,
    locality VARCHAR(150) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pin_code VARCHAR(6) NOT NULL,
    occupation ENUM('Business', 'Job', 'Shop', 'Home Maker') NOT NULL,
    business_name VARCHAR(150) NULL,
    business_category VARCHAR(150) NULL,
    business_address TEXT NULL,
    marital_status ENUM('Married', 'Unmarried') NOT NULL DEFAULT 'Unmarried',
    husband_name VARCHAR(100) NULL,
    wife_name VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mobile (mobile),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS family_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NOT NULL,
    member_type ENUM('Son', 'Daughter') NOT NULL,
    name VARCHAR(100) NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    marital_status ENUM('Married', 'Unmarried') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_family_registration (registration_id),
    CONSTRAINT fk_family_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
