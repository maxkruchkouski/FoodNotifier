// Service Worker для FoodNotifier
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data.json(); } catch (e) { data = { title: 'FoodNotifier', body: 'Статус заказа обновлён' }; }

    const options = {
        body: data.body || 'Статус заказа обновлён',
        icon: '/icon-192.png',
        badge: '/icon-192.png',
        data: { url: data.url || '/' },
        vibrate: [200, 100, 200],
    };

    event.waitUntil(self.registration.showNotification(data.title || 'FoodNotifier', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url || '/'));
});
