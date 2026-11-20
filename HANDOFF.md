# HANDOFF — FoodNotifier SaaS

> Этот файл — «передачаһ
> Обновляся его при завершении каждого этапа.

## Текущее состояние (на момент передачи)

**Этап:** 1 — Мультитенантность (single-DB,-изоляция по `restaurant_id`).
**Статус:** ✅ ��авершен (backend + фронтенд). Проверено вручную через curl и в браузере.

## Что сделано

Simple проект. Главное — это база из шагов 1-7.


### Backend (Laravel 13 / PHP 8.4)
- `app/Models/Concerns/BelongsToTenant.php` — трейт: глобальный скоуп по `tenant()->id` + автоподстановка `restaurant_id` при создании.
- Модели `Order`, `Analytics` используют `BelongsToTenant`.
- `app/Http/Middleware/ResolveTenant.php` — резолв тенанта из `X-Restaurant-Id` (приоритет), query `?restaurant=`, поддомена или пути. **Принимает и `id`, и `slug`.**
- `app/Http/Middleware/EnsureAdminAccess.php` — блокирует `/api/admin/*` (кроме `/user`, `/logout`) для `staff` (403).

### Роли и логин
- `users.position` (`chef`, `cashier`, `manager`, `NULL` для owner).
- `login.html` — редирект по `position`: cashier → `/cashier.html`, chef → `/kitchen.html`, owner → `/admin.html`.
- Токены: `staff` — до конца дня, `owner`/`admin` — 2 дня.

### MVP (Этап 1.5)
- `frontend/cashier.html` — экран кассира: ввод номера, `POST /api/orders`, список последних 10, звук.
- `frontend/index.html` — страница статуса гостя (`?restaurant=slug`), автообновление, toast.
- `GET /api/orders/status?order_number=...` — публичный эндпоинт (без авторизации).

### Дашборд (Этап 4)
- `GET /api/admin/analytics/orders-series?days=7|30` — `series`, `by_status`, `by_hour`, `avg_cooking_seconds`.
- `frontend/admin.html` — Chart.js v4 (CDN), 3 графика (заказы/статусы/часы), переключатель 7/30, мобильный адаптив.

### Техдолги
- Автотесты `TenantIsolationTest` — не создан.
- `intl` PHP-расширение не установлено.
- Web Push — не реализован.
- `?order=55` в URL для `index.html` — не реализован.

### Дорожная карта
- [x] Этап 1. Мультитенантность
- [x] Этап 1.5. MVP: cashier + index
- [x] Этап 1.6. Логин/роли
- [x] Этап 1.7. Тест через localhost.run
- [x] Этап 4 (часть 1). Дашборд: графики
- [ ] Этап 1.8. Аналитика по часам
- [ ] Этап 2. Web Push
- [ ] Этап 3. Биллинг
- [ ] Этап 5. Real-time
- [ ] Этап 6. unconfirmed
- [ ] Этап 7. Онбординг

### Логины
- Владелец: `admin@foodnotifier.by` / `password` (2 дня)
- Повар: `chef@foodnotifier.by` / `password` (до конца дня)
- Кассир: `cashier@foodnotifier.by` / `password` (до конца дня)
