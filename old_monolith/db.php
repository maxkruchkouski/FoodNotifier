<?php
// db.php
declare(strict_types=1);

// Проверяем, запущен ли код внутри контейнера Docker
$host = (file_exists('/.dockerenv')) ? 'db' : '127.0.0.1';

$db   = 'food_notifire';
$user = 'postgres';
$pass = 'my_secret_password';
$port = '5432';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Ошибка подключения к PostgreSQL: " . $e->getMessage());
}

// 1. Создаем таблицу пользователей (Владельцы заведений и супер-админ)
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'owner', -- 'superadmin', 'owner'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

// 2. Создаем таблицу зарегистрированных ресторанов
$pdo->exec("CREATE TABLE IF NOT EXISTS restaurants (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL, -- Короткое имя для URL (burger_club)
    name VARCHAR(100) NOT NULL,       -- Название для вывода
    is_active BOOLEAN DEFAULT TRUE,    -- Флаг для коммерческого авто-биллинга
    owner_id INT REFERENCES users(id) ON DELETE SET NULL, -- Привязка к владельцу
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

// 3. Создаем таблицу заказов
$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    restaurant_id VARCHAR(50) NOT NULL, 
    order_number VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'cooking', -- 'cooking', 'ready', 'archived'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cooking_started_at TIMESTAMP,         
    ready_at TIMESTAMP                    -- Для аналитики кухонного KPI
);");

// 4. Создаем таблицу аналитики визитов
$pdo->exec("CREATE TABLE IF NOT EXISTS analytics (
    id SERIAL PRIMARY KEY,
    restaurant_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");
