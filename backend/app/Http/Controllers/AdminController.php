<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Restaurant;
use App\Services\QrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    /**
     * Создание нового клиента (SaaS Владельца франшизы)
     */
    public function createUser(Request $request)
    {
        // В будущем здесь будет проверка: if ($request->user()->role !== 'superadmin')

        $email = filter_var(trim($request->input('email')), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Некорректный формат Email!'], 422);
        }

        $rawPassword = Str::random(8); // Генерируем читаемый временный пароль вместо bin2hex

        try {
            $user = User::create([
                'email' => $email,
                'password_hash' => Hash::make($rawPassword),
                'role' => 'owner'
            ]);

            // Логика отправки письма из монолита
            $subject = "Доступ к FoodNotifier SaaS";
            $message = "Здравствуйте! Для вашей сети ресторанов создан аккаунт.\nЛогин: $email\nВременный пароль: $rawPassword";
            @mail($email, $subject, $message, "From: no-reply@foodnotifier.ru");

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
        $restaurant = Restaurant::query()->findOrFail($id);
        $restaurant->is_active = !$restaurant->is_active;
        $restaurant->save();

        return response()->json(['success' => true, 'is_active' => $restaurant->is_active]);
    }

    /**
     * Удаление ресторана из экосистемы
     */
    public function deleteRestaurant($id)
    {
        Restaurant::query()->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Получение списка всех владельцев (для выпадающего списка в панели)
     */
    public function getOwners()
    {
        $owners = User::query()->where('role', 'owner')->get(['id', 'email']);
        return response()->json($owners);
    }

    /**
     * Список подключенных ресторанов (с изоляцией данных)
     */
    public function getRestaurants(Request $request)
    {
        // Имитируем старую логику: если superadmin — отдаем всё, если owner — только его точки
        $role = $request->header('X-User-Role', 'superadmin');
        $userId = (int)$request->header('X-User-Id', 0);

        if ($role === 'superadmin') {
            $restaurants = Restaurant::query()->orderBy('id', 'desc')->get();
        } else {
            $restaurants = Restaurant::query()->where('owner_id', $userId)->orderBy('id', 'desc')->get();
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
