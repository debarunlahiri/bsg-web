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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mobile (mobile),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
