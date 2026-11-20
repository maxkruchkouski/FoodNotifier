<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\QrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Авторизация администратора / владельца (Генерация токена Sanctum)
     */
    public function login(Request $request)

    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 1. Ищем пользователя вручную по email
        $user = User::where('email', $request->email)->first();

        // 2. Сверяем пароль по вашему кастомному полю password_hash
        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['success' => false, 'message' => 'Неверные учетные данные'], 401);
        }

        // 3. Проверяем роль (Разрешаем доступ только admin и owner)
        if (!in_array($user->role, ['superadmin', 'admin', 'owner', 'staff'])) {
            return response()->json(['success' => false, 'message' => 'Доступ запрещен'], 403);
        }

        // ИСКЛЮЧИЛИ ОПАСНЫЙ Auth::login($user), так как Sanctum авторизует напрямую через токены

        // 4. Генерируем рабочий Sanctum токен
        $expiresAt = in_array($user->role, ['superadmin', 'admin', 'owner'])
            ? now()->addDays(2)      // владелец/админ — 2 дня
            : now()->endOfDay();      // staff — до конца дня
        $token = $user->createToken('admin-access', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }



    /**
     * Создание нового клиента (SaaS Владельца франшизы)
     */
    public function createUser(Request $request)
    {
        // Защита: проверку роли теперь делает middleware или этот блок:
        if (!in_array($request->user()->role, ['superadmin', 'admin'])) {
            return response()->json(['success' => false, 'message' => 'Недостаточно прав!'], 403);
        }

        $email = filter_var(trim($request->input('email')), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Некорректный формат Email!'], 422);
        }

        $rawPassword = Str::random(8);

        try {
            $user = User::create([
                'email' => $email,
                'password' => Hash::make($rawPassword), // Внимание: изменено с password_hash на password под Laravel Auth
                'role' => 'owner'
            ]);

            // Логика отправки письма
            $subject = "Доступ к FoodNotifier SaaS";
            $message = "Здравствуйте! Для вашей сети ресторанов создан аккаунт.\nЛогин: $email\nВременный пароль: $rawPassword";

            // Временное решение на mail(). Для продакшна лучше использовать Mail::to()
            mail($email, $subject, $message, "From: no-reply@foodnotifier.ru");

            return response()->json([
                'success' => true,
                'message' => "Аккаунт создан! Пароль: {$rawPassword}"
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Такой пользователь уже существует!'], 409);
        }
    }

    /**
     * Регистрация новой точки выдачи (Ресторана)
     */
    public function createRestaurant(Request $request)
    {
        $slug = trim($request->input('slug'));
        $name = trim($request->input('name'));
        $ownerId = $request->input('owner_id');

        if (empty($slug) || empty($name)) {
            return response()->json(['success' => false, 'message' => 'Заполните все поля'], 400);
        }

        try {
            $restaurant = Restaurant::create([
                'slug' => $slug,
                'name' => $name,
                'owner_id' => $ownerId ?: null,
                'is_active' => true
            ]);

            return response()->json(['success' => true, 'restaurant' => $restaurant]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "Ошибка: Ресторан со slug '{$slug}' уже существует!"], 409);
        }
    }

    /**
     * Биллинг: Включение / Выключение коммерческого тарифа (Блокировка за неуплату)
     */
    public function toggleTariff(Request $request)
    {
        $id = (int)$request->input('id');
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->is_active = !$restaurant->is_active;
        $restaurant->save();

        return response()->json(['success' => true, 'is_active' => $restaurant->is_active]);
    }

    /**
     * Удаление ресторана из экосистемы
     */
    public function deleteRestaurant($id)
    {
        Restaurant::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Получение списка всех владельцев (для выпадающего списка в панели)
     */
    public function getOwners()
    {
        $owners = User::where('role', 'owner')->get(['id', 'email']);
        return response()->json($owners);
    }

    /**
     * Список подключенных ресторанов с жесткой изоляцией данных через Sanctum
     */
    public function getRestaurants(Request $request)
    {
        // Данные берутся из токена авторизованного юзера
        $user = $request->user();

        if (in_array($user->role, ['superadmin', 'admin'])) {
            $restaurants = Restaurant::orderBy('id', 'desc')->get();
        } else {
            // Если зашел Owner — отдаем строго его рестораны
            $restaurants = Restaurant::where('owner_id', $user->id)->orderBy('id', 'desc')->get();
        }

        return response()->json($restaurants);
    }

    /**
     * Интеграция нашего автономного генератора QR-кодов
     */
    public function generateQr(Request $request)
    {
        $slug = $request->query('slug');
        // Собираем правильный SaaS URL для гостя
        $url = "http://" . $request->getHttpHost() . "/frontend/index.html?restaurant=" . $slug;

        return response()->json([
            'url' => $url,
            'svg' => QrService::getSVG($url)
        ]);
    }
}
