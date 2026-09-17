import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    // The Laravel CORS config allows exactly this origin (FRONTEND_URL). If 5173
    // is busy, fail loudly instead of silently moving to 5174 and hitting
    // confusing CORS errors on every request.
    strictPort: true,
  },
})
