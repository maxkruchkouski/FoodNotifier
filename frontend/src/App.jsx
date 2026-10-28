import { useState, useEffect } from 'react'
import CustomerBoard from './components/CustomerBoard'
import GuestTracker from './components/GuestTracker'
import KitchenMonitor from './components/KitchenMonitor'

export default function App() {
    const [view, setView] = useState('admin') // admin, board, track, kitchen
    const [selectedSlug, setSelectedSlug] = useState('subway')
    const [data, setData] = useState(null)
    const [period, setPeriod] = useState('week')
    const [newName, setNewName] = useState('')
    const [newSlug, setNewSlug] = useState('')

    useEffect(() => {
        const params = new URLSearchParams(window.location.search)
        if (params.get('view')) setView(params.get('view'))
        if (params.get('restaurant_id')) setSelectedSlug(params.get('restaurant_id'))
        if (params.get('restaurant')) setSelectedSlug(params.get('restaurant'))
    }, [])

    const loadData = async () => {
        try {
            const res = await fetch(`/api/v1/dashboard?stats=${selectedSlug}&period=${period}`)
            if (!res.ok) return
            const json = await res.json()
            setData(json)
        } catch (err) {
            console.error("API Error:", err)
        }
    }

    useEffect(() => {
        if (view === 'admin') loadData()
    }, [selectedSlug, period, view])

    const handleCreate = async (e) => {
        e.preventDefault()
        if (!newName || !newSlug) return
        try {
            const res = await fetch('/api/v1/restaurants', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: newName, slug: newSlug })
            })
            if (res.ok) { setNewName(''); setNewSlug(''); loadData(); }
        } catch (e) {}
    }

    const handleToggle = async (id) => {
        await fetch(`/api/v1/restaurants/${id}/toggle`, { method: 'PATCH' })
        loadData()
    }

    const handleDelete = async (id) => {
        if (confirm("Удалить заведение?")) {
            await fetch(`/api/v1/restaurants/${id}`, { method: 'DELETE' })
            loadData()
        }
    }

    if (view === 'board') return <CustomerBoard restaurantId={selectedSlug} />
    if (view === 'track') return <GuestTracker />
    if (view === 'kitchen') return <KitchenMonitor restaurantId={selectedSlug} />

    if (!data) {
        return (
            <div style={{ background: '#0c0c0e', color: '#fff', padding: '20px', minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                Загрузка экосистемы FoodNotifier...
            </div>
        )
    }

    const metrics = data.metrics || { count_total_visits: 0, count_active_orders: 0, avg_wait_time: '0 мин', avg_cook_time: '0 мин', chart_data: [] }
    const restaurants = data.restaurants || []

    return (
        <div style={{ background: '#0c0c0e', color: '#fff', minHeight: '100vh', padding: '30px', fontFamily: 'sans-serif' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h2>🥑 FoodNotifier SaaS Панель Администратора</h2>
                <div style={{ display: 'flex', gap: '10px' }}>
                    <button onClick={() => setView('kitchen')} style={{ background: '#34495e', color: '#fff', border: 'none', padding: '10px 15px', borderRadius: '6px', cursor: 'pointer' }}>👨‍🍳 На кухню</button>
                    <button onClick={() => setView('board')} style={{ background: '#ff9f1c', color: '#000', border: 'none', padding: '10px 15px', borderRadius: '6px', cursor: 'pointer' }}>📺 На экран зала</button>
                </div>
            </div>

            <div style={{ display: 'flex', gap: '20px', marginBottom: '30px' }}>
                <div style={{ background: '#16161a', padding: '20px', borderRadius: '12px', flex: 1, border: '1px solid #272730' }}>
                    <div style={{ color: '#8e929b', fontSize: '13px' }}>Всего сканирований</div>
                    <div style={{ fontSize: '24px', color: '#ff9f1c', fontWeight: 'bold' }}>{metrics.count_total_visits}</div>
                </div>
                <div style={{ background: '#16161a', padding: '20px', borderRadius: '12px', flex: 1, border: '1px solid #272730' }}>
                    <div style={{ color: '#8e929b', fontSize: '13px' }}>Заказов в работе</div>
                    <div style={{ fontSize: '24px', color: '#ff9f1c', fontWeight: 'bold' }}>{metrics.count_active_orders}</div>
                </div>
                <div style={{ background: '#16161a', padding: '20px', borderRadius: '12px', flex: 1, border: '1px solid #272730' }}>
                    <div style={{ color: '#8e929b', fontSize: '13px' }}>Ожидание повара</div>
                    <div style={{ fontSize: '24px', color: '#e74c3c', fontWeight: 'bold' }}>{metrics.avg_wait_time}</div>
                </div>
                <div style={{ background: '#16161a', padding: '20px', borderRadius: '12px', flex: 1, border: '1px solid #272730' }}>
                    <div style={{ color: '#8e929b', fontSize: '13px' }}>Чистая готовка</div>
                    <div style={{ fontSize: '24px', color: '#2ecc71', fontWeight: 'bold' }}>{metrics.avg_cook_time}</div>
                </div>
            </div>

            <div style={{ display: 'flex', gap: '30px' }}>
                <div style={{ width: '40%', background: '#16161a', padding: '20px', borderRadius: '12px', border: '1px solid #272730' }}>
                    <h3>🏪 Мои Заведения</h3>
                    <form onSubmit={handleCreate} style={{ marginBottom: '20px', display: 'flex', gap: '10px' }}>
                        <input value={newName} onChange={e => setNewName(e.target.value)} placeholder="Название" style={{ background: '#202026', border: '1px solid #272730', color: '#fff', padding: '8px', borderRadius: '6px', flex: 1 }} required />
                        <input value={newSlug} onChange={e => setNewSlug(e.target.value)} placeholder="slug" style={{ background: '#202026', border: '1px solid #272730', color: '#fff', padding: '8px', borderRadius: '6px', flex: 1 }} required />
                        <button type="submit" style={{ background: '#ff9f1c', border: 'none', padding: '8px 15px', borderRadius: '6px', fontWeight: 'bold' }}>+</button>
                    </form>
                    {restaurants.map(res => (
                        <div key={res.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid #272730' }}>
              <span onClick={() => setSelectedSlug(res.slug)} style={{ cursor: 'pointer', color: selectedSlug === res.slug ? '#ff9f1c' : '#fff', fontWeight: selectedSlug === res.slug ? 'bold' : 'normal' }}>
                {res.name} ({res.slug})
              </span>
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <button onClick={() => handleToggle(res.id)} style={{ background: res.is_active ? '#2ecc71' : '#e74c3c', border: 'none', padding: '3px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 'bold', cursor: 'pointer' }}>{res.is_active ? 'Вкл' : 'Выкл'}</button>
                                <button onClick={() => handleDelete(res.id)} style={{ background: 'none', border: '1px solid #e74c3c', color: '#e74c3c', padding: '3px 8px', borderRadius: '4px', fontSize: '11px', cursor: 'pointer' }}>×</button>
                            </div>
                        </div>
                    ))}
                </div>

                <div style={{ width: '60%', background: '#16161a', padding: '20px', borderRadius: '12px', border: '1px solid #272730' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '15px' }}>
                        <h3>📊 Трафик кликов: {selectedSlug}</h3>
                        <select value={period} onChange={e => setPeriod(e.target.value)} style={{ background: '#202026', color: '#fff', border: '1px solid #272730', padding: '5px', borderRadius: '6px' }}>
                            <option value="week">Неделя</option>
                            <option value="month">Месяц</option>
                        </select>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'end', height: '150px', background: '#111114', padding: '15px', gap: '12px', borderRadius: '10px' }}>
                        {(metrics.chart_data || []).map((item, idx) => {
                            const maxCount = Math.max(...metrics.chart_data.map(d => d.count)) || 1;
                            return (
                                <div key={idx} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', height: '100%', justifyContent: 'end' }}>
                                    <div style={{ color: '#ff9f1c', fontSize: '11px' }}>{item.count}</div>
                                    <div style={{ width: '100%', height: `${(item.count / maxCount) * 100}%`, background: '#ff9f1c', borderRadius: '4px 4px 0 0' }}></div>
                                    <div style={{ color: '#8e929b', fontSize: '10px', marginTop: '6px' }}>{item.label}</div>
                                </div>
                            )
                        })}
                    </div>
                </div>
            </div>
        </div>
    )
}
