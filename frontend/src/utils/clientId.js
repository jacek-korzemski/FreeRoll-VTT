const STORAGE_KEY = 'vtt_client_id'

export function getOrCreateClientId() {
  try {
    let id = localStorage.getItem(STORAGE_KEY)
    if (id && /^[a-zA-Z0-9-]{8,64}$/.test(id)) {
      return id
    }
    id = crypto.randomUUID()
    localStorage.setItem(STORAGE_KEY, id)
    return id
  } catch {
    const s = `fb-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`
    return s.length <= 64 ? s : s.slice(0, 64)
  }
}
