<?php
// kitchen.php
require_once 'db.php';
$check_slug = $_GET['restaurant'] ?? 'default_cafe';

$stmt = $pdo->prepare("SELECT is_active FROM restaurants WHERE slug = ?");
$stmt->execute([$check_slug]);
$is_active = $stmt->fetchColumn();

if ($is_active === false || (int)$is_active === 0) {
    die("<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='background:#0c0c0e; color:#fff; font-family:sans-serif; text-align:center; padding-top:100px;'><h2>🔒 Доступ заблокирован</h2><p style='color:#8e929b;'>Срок действия лицензии FoodNotifier истек. Пожалуйста, пополните баланс в панели администратора.</p></body></html>");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель кухни | FoodNotifier</title>
    <style>
        :root { --bg-color: #141419; --card-bg: #1e1e24; --accent-color: #ff9f1c; --success-color: #2ecc71; --success-hover: #27ae60; --text-main: #ffffff; --text-muted: #7f8c8d; }
        body { font-family: -apple-system, sans-serif; background-color: var(--bg-color); color: var(--text-main); margin: 0; padding: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; background-color: var(--card-bg); padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #2d2d35; }
        header h1 { font-size: 20px; margin: 0; font-weight: 600; }
        .status-badge { background-color: rgba(46, 204, 113, 0.1); color: var(--success-color); padding: 6px 14px; border-radius: 30px; font-size: 14px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
        .status-dot { width: 8px; height: 8px; background-color: var(--success-color); border-radius: 50%; }
        .grid-title { font-size: 18px; color: var(--text-muted); margin-bottom: 15px; }
        .orders-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .order-card { background-color: var(--card-bg); border: 1px solid #2d2d35; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
        .order-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .order-num { font-size: 36px; font-weight: 800; color: var(--accent-color); margin: 0; }
        .order-time { font-size: 13px; color: var(--text-muted); background: #282830; padding: 4px 8px; border-radius: 6px; }
        .ready-btn { width: 100%; padding: 14px; background-color: var(--success-color); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .ready-btn:hover { background-color: var(--success-hover); }
        #no-orders { grid-column: 1 / -1; text-align: center; padding: 60px; background-color: var(--card-bg); border-radius: 16px; border: 1px dashed #3d3d45; color: var(--text-muted); font-style: italic; }
    </style>
</head>
<body>
<header>
    <h1 id="kitchen-title">👨‍🍳 Монитор кухни | FoodNotifier</h1>
    <div class="status-badge"><span class="status-dot"></span> ОНЛАЙН</div>
</header>
<div class="grid-title">Заказы в работе:</div>
<div class="orders-grid" id="orders-container"></div>
<div id="no-orders" style="display: none;">На кухне тишина... Активных заказов нет.</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const restaurantId = urlParams.get('restaurant') || 'default_cafe';
    document.getElementById('kitchen-title').innerText = `👨‍🍳 Кухня: ${restaurantId}`;

    function loadOrders() {
        fetch(`api.php?action=get_cooking_orders&restaurant_id=${restaurantId}`)
            .then(res => res.json())
            .then(orders => {
                const container = document.getElementById('orders-container');
                const noOrdersMsg = document.getElementById('no-orders');
                container.innerHTML = '';

                if (!orders || orders.length === 0) { noOrdersMsg.style.display = 'block'; return; }
                noOrdersMsg.style.display = 'none';

                orders.forEach(order => {
                    const card = document.createElement('div');
                    card.className = 'order-card';

                    // Стилизуем карточку: если заказ в очереди — делаем рамку нейтральной, если готовится — подсвечиваем золотом
                    if (order.status === 'cooking') {
                        card.style.borderColor = 'var(--accent-color)';
                        card.style.background = '#22222b';
                    }

                    // Динамическая кнопка в зависимости от статуса заказа
                    let actionButton = '';
                    if (order.status === 'pending') {
                        actionButton = `<button class="ready-btn" style="background-color: var(--accent-color); color: #000;" onclick="startCooking(${order.id})">В РАБОТУ 👨‍🍳</button>`;
                    } else {
                        actionButton = `<button class="ready-btn" style="background-color: var(--success-color);" onclick="markAsReady(${order.id})">ГОТОВ 🛎️</button>`;
                    }

                    card.innerHTML = `
                        <div class="order-header">
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">
                                    ${order.status === 'pending' ? '⏳ В очереди' : '🔥 Готовится'}
                                </span>
                                <h2 class="order-num">#${order.order_number}</h2>
                            </div>
                            <span class="order-time">⏱️ ${order.created_at}</span>
                        </div>
                        ${actionButton}
                    `;
                    container.appendChild(card);
                });
            });
    }

    // Функция: повар нажал "В работу"
    function startCooking(orderId) {
        let formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('restaurant_id', restaurantId);

        fetch(`api.php?action=start_cooking&restaurant_id=${restaurantId}`, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if (data.success) loadOrders(); });
    }

    // Функция: повар нажал "Готов"
    function markAsReady(orderId) {
        let formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('restaurant_id', restaurantId);

        fetch(`api.php?action=mark_ready&restaurant_id=${restaurantId}`, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if (data.success) loadOrders(); });
    }

    setInterval(loadOrders, 3000); loadOrders();
</script>


</body></html>
