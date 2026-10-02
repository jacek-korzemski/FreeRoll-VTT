import { BASE_PATH } from '../../config'

/** Set once by the first table that opens after this change. Other tables start empty. */
export const LEGACY_CLAIM_KEY = 'vtt_legacy_claimed'

const EXACT_LEGACY_KEYS = new Set([
  'vtt_player_name',
  'vtt_macros',
  'vtt_counters_private',
  'vtt_bottom_panel_height',
  'vtt_notes_config',
  'vtt_local_pdfs',
])

const LEGACY_PREFIXES = ['vtt_notes_', 'vtt_token_note_']

const GLOBAL_KEYS = new Set(['vtt_client_id', 'dev_gm', LEGACY_CLAIM_KEY])

export function tableScopeFromBasePath(basePath) {
  const path = String(basePath ?? '/').replace(/^\/+|\/+$/g, '')
  return path || 'local'
}

export function tableScope() {
  return tableScopeFromBasePath(BASE_PATH)
}

export function scopedStorageKey(key, scope = tableScope()) {
  return `vttscope:${scope}:${key}`
}

function isLegacyTableKey(key) {
  if (!key || GLOBAL_KEYS.has(key) || key.startsWith('vttscope:')) return false
  if (EXACT_LEGACY_KEYS.has(key)) return true
  return LEGACY_PREFIXES.some((prefix) => key.startsWith(prefix))
}

/**
 * Move unscoped table data into the current table's namespace.
 * The first caller to write LEGACY_CLAIM_KEY owns the old keys; later tables leave them alone.
 * @returns {string|null} scope that claimed the legacy data, or null if storage is unavailable
 */
export function migrateLegacyStorage() {
  try {
    const scope = tableScope()
    const existing = localStorage.getItem(LEGACY_CLAIM_KEY)
    if (existing) return existing

    localStorage.setItem(LEGACY_CLAIM_KEY, scope)
    if (localStorage.getItem(LEGACY_CLAIM_KEY) !== scope) {
      return localStorage.getItem(LEGACY_CLAIM_KEY)
    }

    const toMove = []
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (isLegacyTableKey(key)) toMove.push(key)
    }
    for (const key of toMove) {
      const value = localStorage.getItem(key)
      if (value != null) localStorage.setItem(scopedStorageKey(key, scope), value)
      localStorage.removeItem(key)
    }
    return scope
  } catch {
    return null
  }
}

export function getTableItem(key) {
  try {
    return localStorage.getItem(scopedStorageKey(key))
  } catch {
    return null
  }
}

export function setTableItem(key, value) {
  localStorage.setItem(scopedStorageKey(key), value)
}

export function removeTableItem(key) {
  try {
    localStorage.removeItem(scopedStorageKey(key))
  } catch {
    /* ignore */
  }
}

/** Logical keys (without the table scope prefix) stored for the current table. */
export function listTableKeys() {
  const prefix = `vttscope:${tableScope()}:`
  const keys = []
  try {
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(prefix)) keys.push(key.slice(prefix.length))
    }
  } catch {
    /* localStorage unavailable */
  }
  return keys
}
