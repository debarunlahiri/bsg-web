<?php
declare(strict_types=1);

function loadEnvironment(string $path): void
{
    if (!is_readable($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name) || getenv($name) !== false) continue;

        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

loadEnvironment(__DIR__ . '/.env');

function setting(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function database(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $server = new PDO(
        'mysql:host=' . setting('BSG_DB_HOST', '127.0.0.1') . ';port=' . setting('BSG_DB_PORT', '3306') . ';charset=utf8mb4',
        setting('BSG_DB_USER', 'root'),
        setting('BSG_DB_PASS'),
        $options
    );
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', setting('BSG_DB_NAME', 'bsg')) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $pdo = new PDO(
        'mysql:host=' . setting('BSG_DB_HOST', '127.0.0.1') . ';port=' . setting('BSG_DB_PORT', '3306') . ';dbname=' . setting('BSG_DB_NAME', 'bsg') . ';charset=utf8mb4',
        setting('BSG_DB_USER', 'root'),
        setting('BSG_DB_PASS'),
        $options
    );

    $pdo->exec(<<<SQL
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $registrationColumns = $pdo->query('SHOW COLUMNS FROM registrations')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('marital_status', $registrationColumns, true)) {
        $pdo->exec("ALTER TABLE registrations ADD marital_status ENUM('Married', 'Unmarried') NOT NULL DEFAULT 'Unmarried' AFTER business_address");
    }
    if (!in_array('husband_name', $registrationColumns, true)) {
        $pdo->exec('ALTER TABLE registrations ADD husband_name VARCHAR(100) NULL AFTER marital_status');
    }
    if (!in_array('wife_name', $registrationColumns, true)) {
        $pdo->exec('ALTER TABLE registrations ADD wife_name VARCHAR(100) NULL AFTER husband_name');
    }

    $pdo->exec(<<<SQL
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS admin_users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);

    return $pdo;
}
