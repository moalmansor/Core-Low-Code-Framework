import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'

// Development: `npm run dev` serves the SPA on :5173 and proxies the API to
// Laravel on :8000 (same-origin cookies, CSRF and CSP stay server-side).
// Production: `npm run build` writes the bundle and its manifest into the
// Laravel public directory, where resources/views/app.blade.php loads it
// with the per-request CSP nonce.
const backend = process.env.LCF_BACKEND_URL ?? 'http://localhost:8000'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    strictPort: true,
    fs: { allow: ['..'] }, // UI string catalogs live in backend/resources/ui-strings
    proxy: {
      '/api': { target: backend, changeOrigin: false },
      '/sanctum': { target: backend, changeOrigin: false },
      '/auth/sso': { target: backend, changeOrigin: false },
    },
  },
  build: {
    outDir: '../backend/public/build',
    emptyOutDir: true,
    manifest: 'manifest.json',
    assetsDir: 'assets',
    sourcemap: false,
    rollupOptions: { input: 'src/main.ts' },
  },
  base: process.env.NODE_ENV === 'production' ? '/build/' : '/',
  test: {
    environment: 'jsdom',
    include: ['src/**/*.spec.ts', 'tests/**/*.spec.ts'],
  },
} as never)
