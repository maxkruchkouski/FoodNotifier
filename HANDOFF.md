cd ~/PhpstormProjects/FoodNotifier && cat > HANDOFF.md <<'EOF'
# HANDOFF — FoodNotifier SaaS

> Этот файл — «передача» между чатами/разработчиками.
> Обновляй его при завершении каждого этапа.

## Текущее состояние (на момент передачи)

**Этап:** 1 — Мультитенантность (single-DB, изоляция по `restaurant_id`).
**Статус:** ✅ Завершён (backend + фронтенд). Проверено вручную через curl и в браузере.

## Что сделано

### База данных
- Миграция `2026_11_14_000000_refactor_orders_analytics_to_foreign_keys.php` — `orders.restaurant_id` и `analytics.restaurant_id` теперь `BIGINT` FK на `restaurants.id` (cascadeOnDelete).
- Миграция `2026_11_14_000001_add_is_superadmin_to_users_table.php` — флаг `is_superadmin` в `users`.
- Сидер создаёт: суперадмин `admin@foodnotifier.by` / `password`, owner `owner@test.by` / `password`, ресторан `coffee-house`.
- Пароли хранятся в `users.password_hash` (bcrypt через `Hash::make()`), Laravel мапит через `getAuthPassword()`.

### Backend (Laravel 13 / PHP 8.4)
- `app/Models/Concerns/BelongsToTenant.php` — трейт: глобальный скоуп по `tenant()->id` + автоподстановка `restaurant_id` при создании.
- Модели `Order`, `Analytics` используют `BelongsToTenant`.
- `app/Http/Middleware/ResolveTenant.php` — резолв тенанта из `X-Restaurant-Id` (приоритет), query `?restaurant=`, поддомена или пути (по `TENANCY_MODE`).
- `app/Http/Middleware/EnsureTenantAccess.php` — проверяет, что user (owner/staff) имеет доступ к тенанту.
- `app/Http/Middleware/CheckRestaurantBilling.php` — блокирует, если `tenant()->is_active = false`.
- `config/tenancy.php` — конфиг: `mode`, `query_key`, `header`, `domain`.
- `Restaurant::booted` — глобальный скоуп `'owner'`: не-суперадмин видит только свои рестораны.
- `AppServiceProvider` — `Gate::define('manage-saas', fn($u) => $u->is_superadmin)`.
- `routes/api.php` — middleware `resolve.tenant`, `check.billing`, `ensure.tenant.access` на нужных группах.
- `bootstrap/app.php` — алиасы middleware.

### Контроллеры
- `OrderController` (Api) — все методы через `tenant()->id`, `getActiveRestaurants()` без тенанта (публичный список).
- `AnalyticsController` (Api) — KPI и графики через `tenant()->id`.
- `DashboardController` — рефакторинг под `is_superadmin`.

### Frontend (vanilla JS)
- `frontend/index.html` — гость: `apiFetch()` с `X-Restaurant-Id`, брендинг, современный стиль.
- `frontend/kitchen.html` — кухня: `apiFetch()`, JSON-body вместо `FormData`, обработка 403.
- `frontend/board.html` — табло: `apiFetch()`, убран `?restaurant_id=`.
- `frontend/login.html` — относительный `/api/admin/login`, без дефолтных `value`.
- `frontend/admin.html` — дашборд: `apiFetch()` с Sanctum-токеном, авто-редирект на 401, вкладки (Дашборд / Рестораны / Владельцы).

### Проверено
- `curl` — изоляция между `coffee-house` и `other-coffee` (заказы не пересекаются).
- Биллинг-блокировка — 403 `billing_blocked` при `is_active = false`.
- Логин в админку — `admin@foodnotifier.by` / `password` работает.
- Фронтенд — 5 страниц работают в браузере.

## Ключевые решения (не менять без причины)

| Решение | Значение | Почему |
|---|---|---|
| Модель изоляции | Single-DB, `restaurant_id` FK | Простота, до сотен клиентов хватает |
| Тенант = | Ресторан (не владелец) | Владелец сети управляет несколькими тенантами |
| Резолв тенанта | `X-Restaurant-Id` header (приоритет) + query/subdomain/path | Гибко переключается через `.env` |
| Пароли | bcrypt в `password_hash`, маппинг в `User::getAuthPassword()` | Совместимость с монолитом |
| Суперадмин | Флаг `is_superadmin`, без хардкода email | Масштабируется, нет магических строк |

## Известные технические долги

- Автотесты `TenantIsolationTest` — файл не создан, но smoke-тесты вручную прошли. Создать перед этапом 2.
- `docker-compose.yml` — удалить `version: '3.8'` (устаревшее поле, WARN в каждом выводе).
- `frontend/package.json` — там React 19 + Vite, но фронтенд пока vanilla HTML. Решить: переписывать на React или оставить vanilla.
- `README.md` — есть, но проверить, что все команды актуальны.
- Этап 1.5 (следующий): `menu_items` + `order_items` + цены → появится выручка и средний чек на дашборде (сейчас `orders.total` nullable, но не заполняется).
- `intl` PHP-расширение не установлено → `php artisan db:table` падает. Добавить в `Dockerfile`.

## Дорожная карта

- [x] **Этап 1.** Мультитенантность
- [ ] **Этап 2.** Биллинг (Stripe + YooKassa, `BillingGateway`)
- [ ] **Этап 3.** Онбординг (self-service регистрация, wizard)
- [x] **Этап 4.** Дашборд владельца (графики Chart.js: заказы/статусы/часы; переключатель 7/30; эндпоинт `/api/admin/analytics/orders-series`)
- [ ] **Этап 5.** Real-time (Laravel Reverb, убрать поллинг)
- [ ] **Этап 6.** Уведомления гостям (Telegram bot)

## Как поднять проект

```bash
cd FoodNotifier
docker compose up -d --build
docker compose exec backend composer install
docker compose exec backend cp .env.example .env
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate:fresh --seed


### Дашборд (Этап 4, часть 1)
- `GET /api/admin/analytics/orders-series?days=7|30` — `series`, `by_status`, `by_hour`, `avg_cooking_seconds`.
- `AnalyticsController::getOrdersSeries()` — новый метод.
- `frontend/admin.html` — Chart.js v4 (CDN), 3 графика, переключатель 7/30, обновление при смене ресторана.
- `database/seeders/OrderTestSeeder.php` — тестовые заказы (79 шт. за 14 дней).
- URL фронтенда: `http://localhost:8000/admin.html` (без `/frontend/` — nginx root = frontend/).


