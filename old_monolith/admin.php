<?php
// admin.php
ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Moscow');
require_once 'db.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);


// 1. АВТОРИЗАЦИЯ С ШИФРОВАНИЕМ
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

if (isset($_POST['login'])) {
    $email = trim($_POST['user']);
    $password = trim($_POST['password']);

    if ($email === 'admin' && $password === 'secret_pass') {
        $_SESSION['auth'] = true;
        $_SESSION['user_id'] = 0;
        $_SESSION['user_role'] = 'superadmin';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['auth'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
        } else {
            $login_error = "Неверный Email или пароль!";
        }
    }
}

if (!isset($_SESSION['auth'])) { ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>SaaS Вход</title>
        <style>body{background:#0c0c0e;color:#fff;font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
            .login-card{background:#16161a;padding:40px;border-radius:16px;border:1px solid #272730;width:320px;text-align:center;}
            input{width:100%;padding:12px;margin:10px 0;background:#202026;border:1px solid #272730;color:#fff;border-radius:8px;box-sizing:border-box;}
            button{width:100%;padding:12px;background:#ff9f1c;border:none;font-weight:bold;border-radius:8px;cursor:pointer;color:#000;}</style>
    </head><body><form class="login-card" method="POST"><h2>FoodNotifier SaaS</h2>
        <?php if(isset($login_error)) echo "<p style='color:#e74c3c;'>$login_error</p>"; ?>
        <input type="text" name="user" placeholder="Email / Логин" required>
        <input type="password" name="password" placeholder="Пароль" required>
        <button type="submit" name="login">Войти в экосистему</button></form></body></html>
    <?php exit; }

// 2. БЛОК БИЗНЕС-ЛОГИКИ (CRUD + БИЛЛИНГ + СОЗДАНИЕ КЛИЕНТОВ)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user']) && $_SESSION['user_role'] === 'superadmin') {
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $error_msg = "Некорректный формат Email!";
        } else {
            $raw_password = bin2hex(random_bytes(4));
            $password_hash = password_hash($raw_password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'owner')");
                $stmt->execute([$email, $password_hash]);

                $subject = "Доступ к FoodNotifier SaaS";
                $message = "Здравствуйте! Для вашей сети ресторанов создан аккаунт.\nЛогин: $email\nВременный пароль: $raw_password\nВход: http://".$_SERVER['HTTP_HOST']."/admin.php";
                mail($email, $subject, $message, "From: no-reply@foodnotifier.ru");

                $success_msg = "Аккаунт создан! Доступы отправлены на $email (Пароль: $raw_password)";
            } catch (\Exception $e) { $error_msg = "Такой... пользователь уже существует!"; }
        }
    }

if (isset($_POST['add_restaurant'])) {
    $slug = trim($_POST['slug']); $name = trim($_POST['name']);
    $owner_id = isset($_POST['owner_id']) && $_POST['owner_id'] !== '' ? intval($_POST['owner_id']) : ($_SESSION['user_role'] === 'owner' ? $_SESSION['user_id'] : null);
    if (!empty($slug) && !empty($name)) {
        try {
            $pdo->prepare("INSERT INTO restaurants (slug, name, owner_id) VALUES (?, ?, ?)")->execute([$slug, $name, $owner_id]);
            // Перенаправляем на чистый URL без POST-данных, чтобы избежать дублирования при перезагрузке
            header("Location: admin.php");
            exit;
        } catch (\PDOException $e) {
            // Если поймали ошибку дубликата уникального ключа (SQLSTATE 23505)
            $error_msg = "Ошибка: Ресторан со slug '$slug' уже существует в экосистеме!";
        }
    }
}


if (isset($_GET['toggle_tariff'])) {
    $id = intval($_GET['toggle_tariff']);
    $new_status = ($_GET['status'] === '1') ? 0 : 1;
    $pdo->prepare("UPDATE restaurants SET is_active = ? WHERE id = ?")->execute([$new_status, $id]);
    header("Location: admin.php"); exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM restaurants WHERE id = ?")->execute([intval($_GET['delete'])]);
    header("Location: admin.php"); exit;
}

// 3. SaaS ИЗОЛЯЦИЯ ДАННЫХ
if ($_SESSION['user_role'] === 'superadmin') {
    $restaurants = $pdo->query("SELECT * FROM restaurants ORDER BY id DESC")->fetchAll();
    $all_owners = $pdo->query("SELECT id, email FROM users WHERE role = 'owner'")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE owner_id = ? ORDER BY id DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $restaurants = $stmt->fetchAll();
}

$selected_slug = $_GET['stats'] ?? ($restaurants[0]['slug'] ?? '');
$period = $_GET['period'] ?? 'week';

// 4. РАСЧЕТ СЧЕТЧИКОВ KPI ЗАВЕДЕНИЯ (Двухфакторная модель)
$count_total_visits = 0;
$count_active_orders = 0;
$avg_wait_time = "0 мин";   // Сколько чек ждал повара в очереди
$avg_cook_time = "0 мин";   // Сколько повар чисто готовил блюдо

if (!empty($selected_slug)) {
    // Всего визитов/сканирований заведения
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM analytics WHERE restaurant_id = ?");
    $stmt->execute([$selected_slug]);
    $count_total_visits = $stmt->fetchColumn();

    // Заказов сейчас на кухне в работе (очередь + готовка)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE restaurant_id = ? AND (status = 'pending' OR status = 'cooking')");
    $stmt->execute([$selected_slug]);
    $count_active_orders = $stmt->fetchColumn();

    // KPI 1: Среднее время ожидания повара (от чека до клика "В работу")
    $stmt = $pdo->prepare("SELECT ROUND(AVG(EXTRACT(EPOCH FROM (cooking_started_at - created_at))/60)) FROM orders WHERE restaurant_id = ? AND cooking_started_at IS NOT NULL");
    $stmt->execute([$selected_slug]);
    $wait_minutes = $stmt->fetchColumn();
    if ($wait_minutes !== null) $avg_wait_time = $wait_minutes . " мин";

    // KPI 2: Среднее чистая скорость готовки (от "В работу" до "Готов")
    $stmt = $pdo->prepare("SELECT ROUND(AVG(EXTRACT(EPOCH FROM (ready_at - cooking_started_at))/60)) FROM orders WHERE restaurant_id = ? AND ready_at IS NOT NULL AND cooking_started_at IS NOT NULL");
    $stmt->execute([$selected_slug]);
    $cook_minutes = $stmt->fetchColumn();
    if ($cook_minutes !== null) $avg_cook_time = $cook_minutes . " мин";
}

// Выгрузка исторических данных для гистограммы трафика (Синхронизировано с МСК)
if ($period === 'month') {
    $sql_stats = "SELECT to_char(created_at, 'DD.MM') as label, COUNT(*) as count FROM analytics WHERE restaurant_id = ? AND created_at >= (CURRENT_TIMESTAMP - INTERVAL '30 days') GROUP BY label ORDER BY label ASC";
} else {
    $sql_stats = "SELECT to_char(created_at, 'Dy') as label, COUNT(*) as count FROM analytics WHERE restaurant_id = ? AND created_at >= (CURRENT_TIMESTAMP - INTERVAL '7 days') GROUP BY label ORDER BY min(created_at) ASC";
}
$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute([$selected_slug]);
$chart_data = $stmt_stats->fetchAll();


function drawChart($data) {
    if (empty($data)) return "<div style='color:#6f727a;padding:30px;text-align:center;'>Пока нет данных для графиков.</div>";
    $max = max(array_column($data, 'count')) ?: 1;
    $svg = '<svg viewBox="0 0 500 150" width="100%" height="150" style="background:#111114;border-radius:10px;padding:10px;">';
    $step = 440 / count($data); $x = 30;
    foreach ($data as $d) {
        $h = ($d['count'] / $max) * 90; $y = 120 - $h;
        $svg .= '<rect x="'.$x.'" y="'.$y.'" width="22" height="'.$h.'" fill="#ff9f1c" rx="4"/>';
        $svg .= '<text x="'.($x+11).'" y="140" fill="#8e929b" font-size="9" text-anchor="middle">'.$d['label'].'</text>';
        $svg .= '<text x="'.($x+11).'" y="'.($y-6).'" fill="#fff" font-size="9" font-weight="bold" text-anchor="middle">'.$d['count'].'</text>';
        $x += $step;
    }
    return $svg . '</svg>';
}

// Подключаем наш внешний автономный генератор QR-кодов
require_once 'qr.php';

if (isset($_GET['get_qr_ajax'])) {
    header('Content-Type: application/json');
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/index.php?restaurant=" . $_GET['slug'];
    echo json_encode(['url' => $url, 'svg' => SaaSQR::getSVG($url)]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>SaaS Console | FoodNotifier</title>
    <style>
        :root {
            --bg-color: #0c0c0e; --card-bg: #16161a; --accent-color: #ff9f1c; --border-color: #272730; --text-muted: #8e929b;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg-color); color: #fff; margin: 0; padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        header {
            background: var(--card-bg); padding: 15px 25px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;
        }
        .layout { display: grid; grid-template-columns: 350px 1fr; gap: 20px; }
        .panel-card {
            background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 20px; margin-bottom: 20px;
        }
        .panel-card h2 {
            font-size: 16px; margin: 0 0 15px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; color: var(--accent-color);
        }
        input, select {
            width: 100%; padding: 10px; margin-bottom: 12px; background: #202026; border: 1px solid var(--border-color); color: #fff; border-radius: 8px; box-sizing: border-box;
        }
        .btn {
            background: var(--accent-color); border: none; padding: 12px; font-weight: bold; border-radius: 8px; cursor: pointer; width: 100%; color: #000; transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: #1f1f26; border: 1px solid var(--border-color); padding: 15px; border-radius: 10px; text-align: center; }
        .stat-val { font-size: 24px; font-weight: bold; color: var(--accent-color); margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-muted); font-size: 13px; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: bold; text-decoration: none; display: inline-block; }
        .badge-ok { background: rgba(46,204,113,0.15); color: #2ecc71; }
        .badge-err { background: rgba(231,76,60,0.15); color: #e74c3c; }

        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-content {
            background: #16161a; padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); text-align: center; max-width: 320px; position: relative;
        }
        .qr-container {
            width: 240px; height: 240px; margin: 20px auto; background: #fff; padding: 10px; border-radius: 12px;
        }
        .close-modal {
            position: absolute; top: 10px; right: 15px; color: var(--text-muted); font-size: 20px; cursor: pointer; text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h2 style="margin:0;">🚀 FoodNotifier SaaS Console <span style="font-size:12px; color:var(--text-muted); font-weight:normal; margin-left:10px;">Роль: <?php echo $_SESSION['user_role']; ?></span></h2>
        <a href="?logout=1" style="color:#e74c3c; font-weight:bold; text-decoration:none;">Выйти 🚪</a>
    </header>

    <div class="layout">
        <!-- ЛЕВАЯ КОЛОНКА (ФОРМЫ УПРАВЛЕНИЯ) -->
        <div>
            <?php if ($_SESSION['user_role'] === 'superadmin'): ?>
                <div class="panel-card">
                    <h2>➕ Регистрация Клиента (SaaS)</h2>
                    <?php if(isset($success_msg)) echo "<p style='color:#2ecc71; font-size:13px;'>$success_msg</p>"; ?>
                    <?php if(isset($error_msg)) echo "<p style='color:#e74c3c; font-size:13px;'>$error_msg</p>"; ?>
                    <form method="POST">
                        <input type="email" name="email" placeholder="Email владельца франшизы / сети" required>
                        <button type="submit" name="create_user" class="btn">Выслать ключи доступа</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="panel-card">
                <h2>➕ Добавить точку выдачи</h2>
                <form method="POST">
                    <input type="text" name="slug" placeholder="Уникальный Slug (например: burger_club)" required>
                    <input type="text" name="name" placeholder="Название (например: Бургер Клаб)" required>
                    <?php if($_SESSION['user_role'] === 'superadmin'): ?>
                        <select name="owner_id">
                            <option value="">Без привязки (Свободный)</option>
                            <?php foreach($all_owners as $o): ?>
                                <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['email']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="submit" name="add_restaurant" class="btn">Зарегистрировать ресторан</button>
                </form>
            </div>
        </div>

        <!-- ПРАВАЯ КОЛОНКА (АНАЛИТИКА) -->
        <div>
            <?php if (!empty($selected_slug)): ?>
                <div class="panel-card">
                    <h2>📊 Аналитика и KPI поваров: <?php echo htmlspecialchars($selected_slug); ?></h2>
                    <div class="stat-grid">
                        <div class="stat-box"><div>Сканирований QR</div><div class="stat-val"><?php echo $count_total_visits; ?></div></div>
                        <div class="stat-box"><div>Заказов в работе</div><div class="stat-val"><?php echo $count_active_orders; ?></div></div>
                        <div class="stat-box"><div>Ожидание повара</div><div class="stat-val" style="color:#ff6b6b;"><?php echo $avg_wait_time; ?></div></div>
                        <div class="stat-box"><div>Чистая готовка</div><div class="stat-val" style="color:#2ecc71;"><?php echo $avg_cook_time; ?></div></div>
                    </div>
                    <div style="margin-bottom:15px; text-align:right; font-size:13px;">
                        <a href="?stats=<?php echo $selected_slug; ?>&period=week" style="color: #fff; margin-right:15px; text-decoration:none;">📅 Неделя</a>
                        <a href="?stats=<?php echo $selected_slug; ?>&period=month" style="color: #fff; text-decoration:none;">📅 30 дней</a>
                    </div>
                    <?php echo drawChart($chart_data); ?>
                </div>
            <?php endif; ?>

            <div class="panel-card">
                <h2>🏢 Подключенные рестораны экосистемы</h2>
                <?php if (empty($restaurants)): ?>
                    <p style="color:var(--text-muted); font-style:italic;">У вас пока нет зарегистрированных заведений.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr><th>Название</th><th>Slug в URL</th><th>Биллинг (Тариф)</th><th>Действия</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach($restaurants as $r): ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($r['name']); ?></b></td>
                                <td><code><?php echo htmlspecialchars($r['slug']); ?></code></td>
                                <td>
                                    <a href="?toggle_tariff=<?php echo $r['id']; ?>&status=<?php echo $r['is_active'] ? '1' : '0'; ?>" class="badge <?php echo $r['is_active'] ? 'badge-ok' : 'badge-err'; ?>">
                                        <?php echo $r['is_active'] ? '🟢 Активен' : '🔴 Заблокирован'; ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="?stats=<?php echo $r['slug']; ?>" style="color:var(--accent-color); margin-right:12px; text-decoration:none; font-size:14px;">📈 Метрики</a>
                                    <a href="#" onclick="openQrModal('<?php echo $r['slug']; ?>', '<?php echo htmlspecialchars($r['name']); ?>'); return false;" style="color:#2ecc71; margin-right:12px; text-decoration:none; font-size:14px;">📱 QR-код</a>
                                    <a href="?delete=<?php echo $r['id']; ?>" style="color:#e74c3c; text-decoration:none; font-size:14px;" onclick="return confirm('Вы уверены?')">❌ Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- МОДАЛЬНОЕ ОКНО ДЛЯ ПРОСМОТРА SVG QR-КОДА -->
<div id="qr-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeQrModal()">&times;</span>
        <h3 id="modal-rest-name" style="margin:0 0 5px 0; font-size:18px;">QR-код</h3>
        <p style="color:var(--text-muted); font-size:12px; margin:0;">Сканируя его, гости попадают на экран ожидания</p>
        <div id="qr-svg-holder" class="qr-container"></div>
        <p id="modal-rest-url" style="font-size:11px; word-break:break-all; color:var(--accent-color); margin:10px 0 0 0; background:#202026; padding:8px; border-radius:6px;"></p>
    </div>
</div>
<script>
    function openQrModal(slug, name) {
        document.getElementById('modal-rest-name').innerText = name;
        const holder = document.getElementById('qr-svg-holder');
        const urlHolder = document.getElementById('modal-rest-url');
        holder.innerHTML = '<div style="color:#000; padding-top:100px; font-size:13px;">Генерация...</div>';
        urlHolder.innerText = '';

        fetch(`admin.php?get_qr_ajax=1&slug=${slug}`)
            .then(res => res.json())
            .then(data => {
                holder.innerHTML = data.svg;
                urlHolder.innerText = data.url;
                document.getElementById('qr-modal').style.display = 'flex';
            })
            .catch(err => {
                holder.innerHTML = '<div style="color:#e74c3c; padding-top:100px;">Ошибка генерации</div>';
            });
    }

    function closeQrModal() {
        document.getElementById('qr-modal').style.display = 'none';
    }

    window.onclick = function(e) {
        if (e.target === document.getElementById('qr-modal')) closeQrModal();
    }
</script>
<?php } // ВОТ ОНА! Закрываем фигурную скобку из 50-й строки, которая защищает админку ?>
</body>
</html>
