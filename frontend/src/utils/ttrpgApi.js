/**
 * FreeRoll ↔ TTRPG Manager proxy helpers (server holds the API key in SQLite).
 */

const PROXY_TIMEOUT_MS = 15000

/** @type {Set<AbortController>} */
const activeProxyControllers = new Set()

/** Abort in-flight proxy/upload fetches so local actions (disconnect) are not stuck behind them. */
export function abortTtrpgProxyRequests() {
  for (const controller of activeProxyControllers) {
    try {
      controller.abort()
    } catch {
      /* ignore */
    }
  }
  activeProxyControllers.clear()
}

async function fetchWithTimeout(url, options = {}, timeoutMs = PROXY_TIMEOUT_MS) {
  const controller = new AbortController()
  activeProxyControllers.add(controller)
  const timer = setTimeout(() => controller.abort(), timeoutMs)
  try {
    return await fetch(url, { ...options, signal: controller.signal })
  } finally {
    clearTimeout(timer)
    activeProxyControllers.delete(controller)
  }
}

function abortedResult(err) {
  const aborted = err?.name === 'AbortError'
  return {
    ok: false,
    success: false,
    status: 0,
    error: aborted ? 'Request timed out' : (err?.message || 'Request failed'),
  }
}

export async function ttrpgProxy(apiBase, { method = 'GET', path, query, json, headers } = {}) {
  try {
    const res = await fetchWithTimeout(`${apiBase}?action=ttrpg-proxy`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ method, path, query, json, headers }),
    })
    const data = await res.json().catch(() => ({}))
    return { ok: res.ok && data.success, status: res.status, ...data }
  } catch (err) {
    return abortedResult(err)
  }
}

export async function ttrpgSetKey(apiBase, { apiKey, baseUrl }) {
  try {
    const res = await fetchWithTimeout(`${apiBase}?action=ttrpg-set-key`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ apiKey, baseUrl }),
    }, 20000)
    return res.json()
  } catch (err) {
    return abortedResult(err)
  }
}

export async function ttrpgClearKey(apiBase) {
  // Intentionally not tracked in activeProxyControllers — disconnect must not abort itself.
  const res = await fetch(`${apiBase}?action=ttrpg-clear-key`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({}),
  })
  return res.json()
}

export async function ttrpgSelectCampaign(apiBase, campaignId) {
  try {
    const res = await fetchWithTimeout(`${apiBase}?action=ttrpg-select-campaign`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ campaignId }),
    }, 10000)
    return res.json()
  } catch (err) {
    return abortedResult(err)
  }
}

export async function ttrpgUploadAsset(apiBase, campaignId, file) {
  const fd = new FormData()
  fd.append('campaignId', String(campaignId))
  fd.append('image', file)
  try {
    const res = await fetchWithTimeout(`${apiBase}?action=ttrpg-upload-asset`, {
      method: 'POST',
      credentials: 'include',
      body: fd,
    }, 120000)
    return res.json()
  } catch (err) {
    return abortedResult(err)
  }
}

export async function ttrpgUploadHandbook(apiBase, { file, title, language, campaignId }) {
  const fd = new FormData()
  fd.append('pdf', file)
  fd.append('title', title)
  if (language) fd.append('language', language)
  if (campaignId != null && campaignId !== '') fd.append('campaign_id', String(campaignId))
  try {
    const res = await fetchWithTimeout(`${apiBase}?action=ttrpg-upload-handbook`, {
      method: 'POST',
      credentials: 'include',
      body: fd,
    }, 120000)
    return res.json()
  } catch (err) {
    return abortedResult(err)
  }
}

export function unwrapList(payload) {
  const body = payload?.data
  if (!body) return []
  if (Array.isArray(body.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

export function unwrapItem(payload) {
  const body = payload?.data
  if (!body) return null
  if (body.data && !Array.isArray(body.data)) return body.data
  return body
}
