<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $tenant = app('tenant');
        abort_unless($tenant, 404, 'Tenant not resolved');

        $request->validate([
            'subscription.endpoint'      => 'required|string',
            'subscription.keys.p256dh'   => 'required|string',
            'subscription.keys.auth'     => 'required|string',
            'order_number'               => 'nullable|string',
        ]);

        $sub = $request->input('subscription');

        PushSubscription::updateOrCreate(
            ['endpoint' => $sub['endpoint']],
            [
                'restaurant_id' => $tenant->id,
                'order_number'  => $request->input('order_number'),
                'public_key'    => $sub['keys']['p256dh'] ?? null,
                'auth_token'    => $sub['keys']['auth'] ?? null,
            ]
        );

        return response()->json(['ok' => true]);
    }
}
