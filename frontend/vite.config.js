import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// El build (dist/) se sube a /public_html/ en el hosting compartido.
// base '/' porque la SPA vive en la raíz del dominio.
export default defineConfig({
  base: '/',
  plugins: [vue()],
  server: {
    // Puerto de desarrollo del frontend (poco común para evitar choques).
    port: 51731,
    // En desarrollo, /api se redirige al servidor PHP local (puerto backend definido abajo).
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:18081',
        changeOrigin: true,
      },
    },
  },
})
