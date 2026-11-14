import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// O painel chama /api/... na própria origem e o Vite repassa para o Laravel, então não
// há CORS no caminho e o endereço da API fica em um lugar só.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.VITE_API_PROXY ?? 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
