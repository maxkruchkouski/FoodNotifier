import { useState, useEffect, useRef } from 'react'

export default function KitchenMonitor({ restaurantId }) {
    const [orders, setOrders] = useState([])
    const [error, setError] = useState(null)
    const intervalRef = useRef(null)

    const loadOrders = async () => {
        try {
            const res = await fetch(`/api/v1/kitchen/orders?restaurant_id=${restaurantId}`)
            if (!res.ok) throw new Error(res.status === 403 ? 'blocked' : 'err')
            setOrders(await res.json())
            setError(null)
        } catch (err) {
            setError(err.message === 'blocked' ? '🔒 Срок действия лицензии FoodNotifier истек.' : 'Потеря соединения...')
        }
    }

    useEffect(() => {
        loadOrders()
        intervalRef.current = setInterval(loadOrders, 3000)
        return () => clearInterval(intervalRef.current)
    }, [restaurantId])

    const action = async (id, type) => {
        await fetch(`/api/v1/kitchen/orders/${id}/${type}?restaurant_id=${restaurantId}`, { method: 'PATCH' })
        loadOrders()
    }

    if (error) return <div style={{ background: '#0c0c0e', color: '#e74c3c', padding: '40px', textAlign: 'center', fontFamily: 'sans-serif' }}><h2>{error}</h2></div>

    return (
        <div style={{ background: '#141419', color: '#fff', minHeight: '100vh', padding: '20px', fontFamily: 'sans-serif' }}>
            <header style={{ background: '#1e1e24', padding: '15px', borderRadius: '12px', display: 'flex', justifyContent: 'space-between', border: '1px solid #2d2d35' }}>
                <h2>👨‍🍳 Монитор кухни: {restaurantId}</h2>
            </header>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '20px', marginTop: '20px' }}>
                {orders.map(o => (
                    <div key={o.id} style={{ background: o.status === 'cooking' ? '#22222b' : '#1e1e24', border: o.status === 'cooking' ? '1px solid #ff9f1c' : '1px solid #2d2d35', padding: '20px', borderRadius: '16px' }}>
                        <span style={{ fontSize: '11px', color: '#7f8c8d' }}>{o.status === 'cooking' ? 'ГОТОВИТСЯ' : 'В ОЧЕРЕДИ'}</span>
                        <h1 style={{ color: '#ff9f1c', margin: '5px 0' }}>#{o.order_number}</h1>
                        <button onClick={() => action(o.id, o.status === 'pending' ? 'start' : 'ready')} style={{ width: '100%', padding: '10px', background: o.status === 'pending' ? '#ff9f1c' : '#2ecc71', color: o.status === 'pending' ? '#000' : '#fff', border: 'none', borderRadius: '8px', fontWeight: 'bold', cursor: 'pointer', marginTop: '10px' }}>
                            {o.status === 'pending' ? 'В РАБОТУ 👨‍🍳' : 'ГОТОВ 🛎️'}
                        </button>
                    </div>
                ))}
            </div>
        </div>
    )
}
