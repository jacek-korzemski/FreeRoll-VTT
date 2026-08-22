// Konfiguracja aplikacji

const runtime = typeof window !== 'undefined' ? window.__VTT_CONFIG__ : undefined

const DEV_BASE_PATH = '/'
const DEV_API_PATH = 'http://localhost:8080/backend/api.php'
const PROD_BASE_PATH = '/vtt/room1/'
const PROD_API_PATH = 'backend/api.php'

export const BASE_PATH = import.meta.env.DEV
  ? DEV_BASE_PATH
  : (runtime?.basePath ?? import.meta.env.VITE_BASE_PATH ?? PROD_BASE_PATH)

export const API_PATH = import.meta.env.DEV
  ? (import.meta.env.VITE_API_PATH || DEV_API_PATH)
  : (import.meta.env.VITE_API_PATH ?? PROD_API_PATH)

function resolveApiBase(basePath, apiPath) {
  if (/^https?:\/\//i.test(apiPath)) return apiPath
  const base = basePath.endsWith('/') ? basePath : `${basePath}/`
  const rel = apiPath.startsWith('/') ? apiPath.slice(1) : apiPath
  return `${base}${rel}`
}

function originFromApiPath(apiPath) {
  if (/^https?:\/\//i.test(apiPath)) {
    return `${new URL(apiPath).origin}/`
  }
  return DEV_BASE_PATH
}

export const API_BASE = resolveApiBase(BASE_PATH, API_PATH)

// Static files (tokens, map tiles, backgrounds) live on the PHP server. In dev the
// frontend runs on a different origin, so asset URLs must point at localhost:8080.
export const ASSET_BASE = import.meta.env.DEV ? originFromApiPath(API_PATH) : BASE_PATH

export const GRID_SIZE = 128
export const CELL_SIZE = 64

// L5R support is gated at BUILD time: when VITE_ENABLE_L5R is not 'true', the flag
// is a literal `false`, so Vite/Rollup strips every L5R branch and dynamic import
// from the bundle. The deploy-time runtime flag may only DISABLE a feature that was
// actually built in — it can never enable code that was never emitted.
const L5R_BUILD = import.meta.env.VITE_ENABLE_L5R === 'true'
export const ENABLE_L5R = L5R_BUILD && (runtime?.enableL5r ?? true)

export const COLOR_TEMPLATE = import.meta.env.DEV
  ? (import.meta.env.VITE_COLOR_TEMPLATE || runtime?.colorTemplate || 'crimson')
  : (runtime?.colorTemplate ?? import.meta.env.VITE_COLOR_TEMPLATE ?? 'crimson')