import { defineConfig } from 'vite'

const port = (name: 'PEANUT_BROWSER_BACKEND_PORT' | 'PEANUT_BROWSER_FRONTEND_PORT'): number => {
  const raw = process.env[name]
  if (raw === undefined || raw === '') throw new Error(`${name} is required`)
  const value = Number(raw)
  if (!Number.isInteger(value) || value < 1 || value > 65_535) {
    throw new Error(`${name} must be an integer between 1 and 65535`)
  }
  return value
}
const backendPort = port('PEANUT_BROWSER_BACKEND_PORT')
const frontendPort = port('PEANUT_BROWSER_FRONTEND_PORT')

export default defineConfig({
  preview: {
    host: '127.0.0.1',
    port: frontendPort,
    strictPort: true,
    proxy: {
      '/api': {
        target: `http://127.0.0.1:${backendPort}`,
      },
    },
  },
})
