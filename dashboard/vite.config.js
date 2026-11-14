import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// O painel chama /api/... na própria origem e o Vite repassa para o Laravel, então não
// há CORS no caminho e o endereço da API fica em um lugar só.
//
// Em produção quem faz esse mesmo repasse é a função api/[...caminho].js na Vercel.
// Aqui o proxy também leva a chave de leitura, para o painel local continuar funcionando
// quando o servidor está fechado por MAPA_CHAVE_LEITURA. Defina VITE_API_KEY com o mesmo
// valor em dashboard/.env.local — ele nunca vai para o pacote, só para esta configuração.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [react()],
    server: {
      port: 5173,
      proxy: {
        '/api': {
          target: env.VITE_API_PROXY ?? 'http://127.0.0.1:8000',
          changeOrigin: true,
          headers: env.VITE_API_KEY ? { 'x-api-key': env.VITE_API_KEY } : {},
        },
      },
    },
  }
})
