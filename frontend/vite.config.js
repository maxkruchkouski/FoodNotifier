import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    server: {
        port: 3000,
        host: '0.0.0.0',
    },
    build: {
        rollupOptions: {
            input: {
                main: resolve(__dirname, 'index.html'),
                kitchen: resolve(__dirname, 'kitchen.html'),
                board: resolve(__dirname, 'board.html'),
                admin: resolve(__dirname, 'admin.html'), // Явно регистрируем админку
            },
        },
    },
});
