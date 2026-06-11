// Konfiguracja aplikacji

const runtime = typeof window !== 'undefined' ? window.__VTT_CONFIG__ : undefined

export const BASE_PATH = runtime?.basePath ?? import.meta.env.VITE_BASE_PATH ?? '/vtt/room1/'
export const API_PATH = import.meta.env.VITE_API_PATH || 'backend/api.php'
export const API_BASE = `${BASE_PATH}${API_PATH}`

export const GRID_SIZE = 128
export const CELL_SIZE = 64

export const ENABLE_L5R = runtime?.enableL5r ?? import.meta.env.VITE_ENABLE_L5R === 'true'