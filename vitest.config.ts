import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: [{
      find: /^vue$/,
      replacement: fileURLToPath(new URL('./frontend/node_modules/vue/dist/vue.esm-bundler.js', import.meta.url)),
    }],
  },
})
