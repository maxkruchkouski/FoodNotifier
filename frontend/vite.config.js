import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
    plugins: [react()],
    server: {
        port: 3000,
        host: true,
        // Настройка для корректной работы HMR через прокси Nginx
        hmr: {
            clientPort: 8000
        }
    }
})
