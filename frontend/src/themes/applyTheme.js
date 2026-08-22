import catalog from './themes.json'

export const DEFAULT_THEME_ID = catalog.default || 'crimson'

export function hexToRgb(hex) {
  if (typeof hex !== 'string') return null
  let h = hex.trim().replace('#', '')
  if (h.length === 3) {
    h = h.split('').map((c) => c + c).join('')
  }
  if (!/^[0-9a-fA-F]{6}$/.test(h)) return null
  const n = parseInt(h, 16)
  return `${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}`
}

export function resolveThemeId(id) {
  if (id && catalog.themes?.[id]) return id
  if (catalog.themes?.[DEFAULT_THEME_ID]) return DEFAULT_THEME_ID
  const first = catalog.themes && Object.keys(catalog.themes)[0]
  return first || DEFAULT_THEME_ID
}

export function getTheme(id) {
  return catalog.themes[resolveThemeId(id)]
}

export function themeCssVars(tokens) {
  const vars = {}
  if (!tokens || typeof tokens !== 'object') return vars
  for (const [key, value] of Object.entries(tokens)) {
    vars[key] = value
    if (typeof value === 'string' && value.startsWith('#')) {
      const rgb = hexToRgb(value)
      if (rgb) vars[`${key}-rgb`] = rgb
    }
  }
  return vars
}

export function applyTheme(themeId, root = typeof document !== 'undefined' ? document.documentElement : null) {
  if (!root) return resolveThemeId(themeId)
  const id = resolveThemeId(themeId)
  const theme = catalog.themes[id]
  const vars = themeCssVars(theme?.tokens)
  root.dataset.theme = id
  for (const [key, value] of Object.entries(vars)) {
    root.style.setProperty(`--${key}`, value)
  }
  return id
}

export function listThemes() {
  return Object.entries(catalog.themes || {}).map(([id, theme]) => ({
    id,
    name: theme.name || { en: id, pl: id },
  }))
}

export default catalog
