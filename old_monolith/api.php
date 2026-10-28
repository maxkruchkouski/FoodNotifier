<?php
// api.php
ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Moscow');

require_once 'db.php';
date_default_timezone_set('Europe/Minsk');
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$restaurant_id = $_REQUEST['restaurant_id'] ?? '';

if (empty($restaurant_id)) {
    echo json_encode(['error' => 'Идентификатор заведения (restaurant_id) не указан']);
    exit;
}

// Защита коммерческого авто-биллинга: блокируем запросы неактивных ресторанов
$stmt = $pdo->prepare("SELECT is_active FROM restaurants WHERE slug = ?");
$stmt->execute([$restaurant_id]);
$is_active = $stmt->fetchColumn();

if ($is_active === false || (int)$is_active === 0) {
    echo json_encode(['success' => false, 'error' => 'billing_blocked', 'message' => 'Доступ заблокирован. Оплатите тариф.']);
    exit;
}

// 1. Создание заказа гостем (Статус по умолчанию теперь 'pending' - в очереди)
// 1. Создание заказа гостем (Статус по умолчанию 'pending')
if ($action === 'create_order') {
    $order_num = $_POST['order_number'] ?? '';
    if (empty($order_num)) {
        echo json_encode(['success' => false, 'message' => 'Введите номер заказа']);
        exit;
    }

    $current_time = date('Y-m-d H:i:s');
    // Запрос содержит 3 знака вопроса
    $stmt = $pdo->prepare('INSERT INTO orders (restaurant_id, order_number, status, created_at) VALUES (?, ?, \'pending\', ?)' );
    // Передаем строго 3 параметра в массив execute
    $stmt->execute([$restaurant_id, $order_num, $current_time]);

    // В PostgreSQL для получения id используем fetchColumn после INSERT ... RETURNING id
    $stmt = $pdo->query('SELECT LASTVAL()');
    $order_id = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'order_id' => $order_id]);
    exit;
}


// 2. Проверка статуса заказа (для index.php гостя)
if ($action === 'check_status') {
    $order_id = intval($_GET['order_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND restaurant_id = ?');
    $stmt->execute([$order_id, $restaurant_id]);
    $order = $stmt->fetch();
    echo json_encode(['status' => $order['status'] ?? 'not_found']);
    exit;
}


// 3. Список активных заказов на кухне (Берем чистое локальное время, сохраненное из PHP)
if ($action === 'get_cooking_orders') {
    $stmt = $pdo->prepare("
        SELECT id, order_number, status, to_char(created_at, 'HH24:MI') as created_at 
        FROM orders 
        WHERE (status = 'pending' OR status = 'cooking') AND restaurant_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$restaurant_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}


// 4. Повар берет заказ в работу (Фиксируем KPI: cooking_started_at)
if ($action === 'start_cooking') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cooking', cooking_started_at = NOW() WHERE id = ? AND restaurant_id = ?");
    echo json_encode(['success' => $stmt->execute([$order_id, $restaurant_id])]);
    exit;
}

// 5. Повар переводит заказ в "Готово" (Фиксируем KPI: ready_at)
if ($action === 'mark_ready') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE orders SET status = 'ready', ready_at = NOW() WHERE id = ? AND restaurant_id = ?");
    echo json_encode(['success' => $stmt->execute([$order_id, $restaurant_id])]);
    exit;
}

// 6. Вывод заказов на электронное табло зала конкретного ресторана
if ($action === 'get_board_orders') {
    $stmt = $pdo->prepare("
        SELECT order_number, status 
        FROM orders 
        WHERE restaurant_id = ? 
          AND created_at >= NOW() - INTERVAL '2 hours'
          AND (status = 'cooking' OR status = 'ready')
        ORDER BY status DESC, id ASC
    ");
    $stmt->execute([$restaurant_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Если передан неизвестный экшен
echo json_encode(['error' => 'Unknown action']);
