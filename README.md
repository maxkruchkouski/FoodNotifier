# FoodNotifier — SaaS для ресторанов и кофеен

Гости сканируют QR на столе → вводят номер заказа → видят статус готовки в реальном времени.
Кухня видит очередь на экране. Табло озвучивает готовые заказы.

**Стек:** Laravel 13 (PHP 8.4) + PostgreSQL 15 + Nginx + Node 20 (Vite) + vanilla JS-фронтенд.
**Архитектура:** мультитенантная (single-DB, изоляция по `restaurant_id`).

---

## Быстрый старт (Docker)

### 1. Клонировать

```bash
git clone <repo-url> FoodNotifier
cd FoodNotifier

docker compose up -d --build

# Установить PHP-зависимости
docker compose exec backend composer install

# Скопировать .env (если ещё нет)
docker compose exec backend cp .env.example .env

# Сгенерировать ключ приложения
docker compose exec backend php artisan key:generate

# Прогнать миграции + сидеры
docker compose exec backend php artisan migrate:fresh --seed


После этого в БД появятся:

Суперадмин: admin@foodnotifier.by / password

Владелец: owner@test.by / password

Ресторан: coffee-house (привязан к owner@test.by)


4. Открыть в браузере
Страница	URL	Кто
Вход в админку	http://localhost:8000/frontend/login.html	Владелец / суперадмин
Дашборд	http://localhost:8000/frontend/admin.html	Владелец / суперадмин
Гость (статус заказа)	http://localhost:8000/frontend/index.html?restaurant=coffee-house	Гость
Кухня	http://localhost:8000/frontend/kitchen.html?restaurant=coffee-house	Повар
Табло	http://localhost:8000/frontend/board.html?restaurant=coffee-house	Зал
Логин в админку: admin@foodnotifier.by / password.

4. Открыть в браузере
Страница	URL	Кто
Вход в админку	http://localhost:8000/frontend/login.html	Владелец / суперадмин
Дашборд	http://localhost:8000/frontend/admin.html	Владелец / суперадмин
Гость (статус заказа)	http://localhost:8000/frontend/index.html?restaurant=coffee-house	Гость
Кухня	http://localhost:8000/frontend/kitchen.html?restaurant=coffee-house	Повар
Табло	http://localhost:8000/frontend/board.html?restaurant=coffee-house	Зал
Логин в админку: admin@foodnotifier.by / password.