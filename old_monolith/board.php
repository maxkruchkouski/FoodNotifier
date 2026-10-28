<?php
// board.php
require_once 'db.php';
$check_slug = $_GET['restaurant'] ?? 'default_cafe';

$stmt = $pdo->prepare("SELECT is_active FROM restaurants WHERE slug = ?");
$stmt->execute([$check_slug]);
$is_active = $stmt->fetchColumn();

if ($is_active === false || (int)$is_active === 0) {
    die("<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='background:#0c0c0e; color:#fff; font-family:sans-serif; text-align:center; padding-top:100px;'><h2>🔒 Доступ заблокирован</h2><p style='color:#8e929b;'>Срок действия лицензии FoodNotifier истек или заведение не найдено. Пожалуйста, пополните баланс в панели администратора.</p></body></html>");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Табло заказов | FoodNotifier</title>
    <style>
        :root { --bg-color: #0a0a0c; --column-bg: #13131a; --accent-color: #ff9f1c; --success-color: #2ecc71; --text-main: #ffffff; --text-muted: #6f727a; }
        body { font-family: -apple-system, sans-serif; background-color: var(--bg-color); color: var(--text-main); margin: 0; padding: 0; height: 100vh; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        header { background-color: #111116; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1c1c24; }
        header h1 { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: 1px; }
        .board-container { display: flex; flex: 1; height: calc(100vh - 80px); }
        .column { flex: 1; background-color: var(--column-bg); padding: 30px; display: flex; flex-direction: column; box-sizing: border-box; }
        .column:first-child { border-right: 4px solid var(--bg-color); }
        .column-title { font-size: 32px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #22222c; text-align: center; }
        .cooking-title { color: var(--accent-color); }
        .ready-title { color: var(--success-color); }
        .orders-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 20px; align-content: start; overflow-y: auto; flex: 1; }
        .order-number { font-size: 46px; font-weight: 800; text-align: center; padding: 15px 10px; border-radius: 12px; background-color: #1c1c24; border: 1px solid #2d2d3a; }
        .column:first-child .order-number { color: #e0e0e6; }
        .column:last-child .order-number { color: var(--success-color); background-color: rgba(46, 204, 113, 0.07); border-color: rgba(46, 204, 113, 0.3); animation: flash 1.5s infinite alternate; }
        .audio-prompt { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10, 10, 12, 0.95); display: flex; justify-content: center; align-items: center; z-index: 9999; cursor: pointer; }
        .prompt-box { background: #1e1e24; padding: 40px 50px; border-radius: 20px; text-align: center; border: 2px solid var(--accent-color); box-shadow: 0 15px 35px rgba(0,0,0,0.6); }
        .prompt-box h2 { margin: 0 0 12px 0; color: var(--accent-color); font-size: 26px; }
        .prompt-box button { margin-top: 20px; padding: 12px 30px; font-size: 16px; font-weight: bold; color: #000; background-color: var(--accent-color); border: none; border-radius: 8px; cursor: pointer; }
        .hidden { display: none !important; }
        @keyframes flash { 0% { border-color: rgba(46, 204, 113, 0.2); } 100% { border-color: rgba(46, 204, 113, 0.8); box-shadow: 0 0 20px rgba(46, 204, 113, 0.2); } }
    </style>
</head>
<body>
<div id="audio-prompt" class="audio-prompt">
    <div class="prompt-box">
        <h2>📢 Голосовое оповещение залов</h2>
        <p style="color:#a0a0a0; margin:0; font-size: 15px;">Активация звукового табло для заведения.</p>
        <button>Запустить электронное табло</button>
    </div>
</div>
<header>
    <h1 id="board-title">🍽️ Табло заказов</h1>
    <div style="font-size: 18px; color: var(--text-muted); font-weight: 500;" id="clock">00:00:00</div>
</header>
<div class="board-container">
    <div class="column"><div class="column-title cooking-title">Готовятся • Preparing</div><div class="orders-list" id="cooking-list"></div></div>
    <div class="column"><div class="column-title ready-title">Готовы! • Ready</div><div class="orders-list" id="ready-list"></div></div>
</div>
<script>
    const urlParams = new URLSearchParams(window.location.search);
    const restaurantId = urlParams.get('restaurant') || 'default_cafe';
    document.getElementById('board-title').innerText = `🍽️ Табло зала: ${restaurantId}`;

    let previousReadyOrders = [];
    let isVoiceAllowed = false;

    setInterval(() => { document.getElementById('clock').innerText = new Date().toLocaleTimeString('ru-RU'); }, 1000);

    document.getElementById('audio-prompt').addEventListener('click', function() {
        this.classList.add('hidden'); isVoiceAllowed = true;
        if ('speechSynthesis' in window) { window.speechSynthesis.speak(new SpeechSynthesisUtterance('')); }
    });

    function getRussianVoice() {
        if (!('speechSynthesis' in window)) return null;
        const voices = window.speechSynthesis.getVoices();
        return voices.find(v => v.lang === 'ru-RU' && v.name.includes('Google')) || voices.find(v => v.lang === 'ru-RU') || null;
    }

    function speakOrder(orderNumber) {
        if (!isVoiceAllowed || !('speechSynthesis' in window)) return;
        const utterance = new SpeechSynthesisUtterance(`Заказ номер ${orderNumber} готов`);
        utterance.lang = 'ru-RU'; utterance.rate = 0.85;
        const voice = getRussianVoice();
        if (voice) utterance.voice = voice;
        window.speechSynthesis.speak(utterance);
    }

    function updateBoard() {
        fetch(`api.php?action=get_board_orders&restaurant_id=${restaurantId}`)
            .then(res => res.json())
            .then(orders => {
                const cookingList = document.getElementById('cooking-list');
                const readyList = document.getElementById('ready-list');
                cookingList.innerHTML = ''; readyList.innerHTML = '';
                let currentReadyOrders = [];

                orders.forEach(order => {
                    const div = document.createElement('div');
                    div.className = 'order-number'; div.innerText = order.order_number;
                    if (order.status === 'cooking') cookingList.appendChild(div);
                    else if (order.status === 'ready') { readyList.appendChild(div); currentReadyOrders.push(order.order_number); }
                });

                currentReadyOrders.forEach(num => { if (!previousReadyOrders.includes(num)) speakOrder(num); });
                previousReadyOrders = currentReadyOrders;
            });
    }
    updateBoard(); setInterval(updateBoard, 3000);
    if ('speechSynthesis' in window) { window.speechSynthesis.onvoiceschanged = getRussianVoice; }
</script>
</body></html>
