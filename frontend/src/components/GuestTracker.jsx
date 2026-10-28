import { useState, useEffect, useRef } from 'react'

export default function GuestTracker() {
    const [restaurantId, setRestaurantId] = useState('default_cafe')
    const [orderNumber, setOrderNumber] = useState('')
    const [currentOrderId, setCurrentOrderId] = useState(null)
    const [screen, setScreen] = useState('form') // form, status
    const [orderStatus, setOrderStatus] = useState('pending')
    const checkInterval = useRef(null)

    useEffect(() => {
        const params = new URLSearchParams(window.location.search)
        const resId = params.get('restaurant') || 'default_cafe'
        setRestaurantId(resId)

        fetch('/api/v1/analytics/track', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ restaurant_id: resId })
        }).catch(() => {})

        const savedId = localStorage.getItem(`active_order_id_${resId}`)
        const savedNum = localStorage.getItem(`active_order_num_${resId}`)
        if (savedId && savedNum) {
            setCurrentOrderId(savedId)
            setOrderNumber(savedNum)
            setScreen('status')
        }
        return () => clearInterval(checkInterval.current)
    }, [])

    useEffect(() => {
        if (screen === 'status' && currentOrderId) {
            const fetchStatus = async () => {
                const res = await fetch(`/api/v1/orders/${currentOrderId}/status?restaurant_id=${restaurantId}`)
                const data = await res.json()
                if (data.status) setOrderStatus(data.status)
            }
            fetchStatus()
            checkInterval.current = setInterval(fetchStatus, 4000)
        } else {
            clearInterval(checkInterval.current)
        }
        return () => clearInterval(checkInterval.current)
    }, [screen, currentOrderId])

    const handleStartTracking = async (e) => {
        e.preventDefault()
        const res = await fetch(`/api/v1/orders?restaurant_id=${restaurantId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_number: orderNumber })
        })
        const data = await res.json()
        if (data.success) {
            setCurrentOrderId(data.order_id)
            localStorage.setItem(`active_order_id_${restaurantId}`, data.order_id)
            localStorage.setItem(`active_order_num_${restaurantId}`, orderNumber)
            setScreen('status')
        }
    }

    return (
        <div style={{ background: '#121212', color: '#fff', minHeight: '100vh', display: 'flex', justifyContent: 'center', alignItems: 'center', fontFamily: 'sans-serif' }}>
            <div style={{ background: '#1e1e1e', padding: '30px 24px', borderRadius: '20px', width: '340px', textAlign: 'center', border: '1px solid #2d2d2d' }}>
                <h1 style={{ fontSize: '24px', margin: '0 0 10px 0', color: '#ff9f1c' }}>FoodNotifier</h1>
                <p style={{ color: '#a0a0a0', fontSize: '12px' }}>Заведение: {restaurantId}</p>

                {screen === 'form' ? (
                    <form onSubmit={handleStartTracking}>
                        <input type="number" value={orderNumber} onChange={e => setOrderNumber(e.target.value)} placeholder="Номер заказа" style={{ width: '100%', padding: '14px', background: '#2a2a2a', border: '1px solid #3d3d3d', borderRadius: '8px', color: '#fff', textAlign: 'center', fontSize: '20px', margin: '20px 0', boxSizing: 'border-box' }} required />
                        <button type="submit" style={{ width: '100%', padding: '14px', background: '#ff9f1c', border: 'none', borderRadius: '8px', fontWeight: 'bold', cursor: 'pointer' }}>Следить</button>
                    </form>
                ) : (
                    <div>
                        <div style={{ background: '#2a2a2a', padding: '6px 12px', borderRadius: '20px', display: 'inline-block', margin: '15px 0' }}>ЗАКАЗ № {orderNumber}</div>
                        <h2 style={{ color: orderStatus === 'ready' ? '#2ecc71' : '#ff9f1c', margin: '20px 0' }}>
                            {orderStatus === 'ready' ? '🎉 Готов!' : orderStatus === 'cooking' ? '🔥 Готовится...' : '⏳ В очереди'}
                        </h2>
                        <button onClick={() => { localStorage.clear(); setScreen('form'); }} style={{ background: 'none', border: '1px solid #3d3d3d', color: '#a0a0a0', padding: '8px', width: '100%', borderRadius: '8px', cursor: 'pointer' }}>Сбросить</button>
                    </div>
                )}
            </div>
        </div>
    )
}
