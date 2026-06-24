import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// When L5R support is NOT built in, replace the dice-image barrel
// (src/assets/l5r/index.js) with an empty stub. The L5R dice panel is already
// dropped by dead-code elimination, but Rollup still loads that gated dynamic
// import while building the graph, which makes Vite emit the dice PNGs as
// orphaned assets (emitFile survives tree-shaking). Stubbing the module means
// the PNG imports are never seen, so no L5R image ends up in the package.
function l5rExcludeAssets(enabled) {
  const STUB =
    'export const RING_DICE_IMAGES = {};\n' +
    'export const SKILL_DICE_IMAGES = {};\n' +
    'export const RING_PREVIEW = "";\n' +
    'export const SKILL_PREVIEW = "";\n'
  return {
    name: 'l5r-exclude-assets',
    enforce: 'pre',
    load(id) {
      if (enabled) return null
      if (id.replace(/\\/g, '/').includes('/src/assets/l5r/index.js')) return STUB
      return null
    },
  }
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const l5rEnabled = env.VITE_ENABLE_L5R === 'true'

  return {
    plugins: [react(), l5rExcludeAssets(l5rEnabled)],
    // Dev server always at http://localhost:5173/ (not under /vtt/room1/).
    // Production builds use relative asset paths; deploy base path comes from PHP runtime config.
    base: mode === 'production' ? './' : '/',
    build: {
      outDir: 'dist',
      assetsDir: 'assets',
      // Generuj przewidywalne nazwy plików
      rollupOptions: {
        output: {
          entryFileNames: 'assets/[name].js',
          chunkFileNames: 'assets/[name]-[hash].js',
          assetFileNames: 'assets/[name].[ext]',
          manualChunks: {
            'vendor-react': ['react', 'react-dom'],
            'vendor-pdf': ['react-pdf', 'pdfjs-dist'],
          }
        }
      }
    },
    // API in dev: direct cross-origin calls to php -S localhost:8080 (see .env.development).
  }
})