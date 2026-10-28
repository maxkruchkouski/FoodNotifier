import { useState, useEffect, useRef } from 'react'

export default function CustomerBoard({ restaurantId }) {
    const [cookingOrders, setCookingOrders] = useState([])
    const [readyOrders, setReadyOrders] = useState([])
    const [error, setError] = useState(null)
    const prevReadyIds = useRef([])

    const fetchBoardOrders = async () => {
        try {
            const response = await fetch(`/api/v1/board/orders?restaurant_id=${restaurantId}`)
            if (!response.ok) {
                if (response.status === 403) throw new Error('billing_blocked')
                throw new Error('Ошибка сервера')
            }
            const orders = await response.json()
            setCookingOrders(orders.filter(o => o.status === 'cooking'))
            const ready = orders.filter(o => o.status === 'ready')
            setReadyOrders(ready)
            setError(null)

            const currentReadyIds = ready.map(o => o.order_number)
            const hasNewReady = currentReadyIds.some(num => !prevReadyIds.current.includes(num))
            if (hasNewReady && prevReadyIds.current.length > 0) playSound()
            prevReadyIds.current = currentReadyIds
        } catch (err) {
            setError(err.message === 'billing_blocked' ? 'Доступ заблокирован. Оплатите тариф.' : 'Соединение потеряно...')
        }
    }

    const playSound = () => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)()
            const osc = ctx.createOscillator()
            const gain = ctx.createGain()
            osc.type = 'sine'
            osc.frequency.setValueAtTime(587.33, ctx.currentTime)
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.15)
            gain.gain.setValueAtTime(0.1, ctx.currentTime)
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4)
            osc.connect(gain)
            gain.connect(ctx.destination)
            osc.start()
            osc.stop(ctx.currentTime + 0.4)
        } catch (e) {}
    }

    useEffect(() => {
        if (!restaurantId) return
        fetchBoardOrders()
        const interval = setInterval(fetchBoardOrders, 5000)
        return () => clearInterval(interval)
    }, [restaurantId])

    if (error) return <div style={{ background: '#000', color: '#e74c3c', height: '100vh', display: 'flex', justifyContent: 'center', alignItems: 'center', fontSize: '24px' }}>⚠️ {error}</div>

    return (
        <div style={{ background: '#0a0a0c', color: '#fff', height: '100vh', width: '100vw', display: 'flex', overflow: 'hidden', fontFamily: 'sans-serif' }}>
            <div style={{ width: '50%', borderRight: '4px solid #1c1c24', display: 'flex', flexDirection: 'column' }}>
                <div style={{ background: '#13131a', padding: '30px', textAlign: 'center', fontSize: '32px', fontWeight: 'bold', color: '#8e929b' }}>ГОТОВЯТСЯ</div>
                <div style={{ padding: '40px', display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))', gap: '25px' }}>
                    {cookingOrders.map((o, i) => <div key={i} style={{ background: '#16161e', borderRadius: '16px', padding: '20px', textAlign: 'center', fontSize: '36px', fontWeight: 'bold', color: '#a5a7b0' }}>{o.order_number}</div>)}
                </div>
            </div>
            <div style={{ width: '50%', display: 'flex', flexDirection: 'column', background: '#050c05' }}>
                <div style={{ background: '#1c3d1c', padding: '30px', textAlign: 'center', fontSize: '32px', fontWeight: 'bold', color: '#2ecc71' }}>ГОТОВО!</div>
                <div style={{ padding: '40px', display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: '30px' }}>
                    {readyOrders.map((o, i) => <div key={i} style={{ background: '#2ecc71', borderRadius: '20px', padding: '25px', textAlign: 'center', fontSize: '54px', fontWeight: '#000', color: '#000' }}>{o.order_number}</div>)}
                </div>
            </div>
        </div>
    )
}
