/**
 * FreeRoll ↔ TTRPG Manager proxy helpers (server holds the API key in SQLite).
 */

export async function ttrpgProxy(apiBase, { method = 'GET', path, query, json, headers } = {}) {
  const res = await fetch(`${apiBase}?action=ttrpg-proxy`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ method, path, query, json, headers }),
  })
  const data = await res.json().catch(() => ({}))
  return { ok: res.ok && data.success, status: res.status, ...data }
}

export async function ttrpgSetKey(apiBase, { apiKey, baseUrl }) {
  const res = await fetch(`${apiBase}?action=ttrpg-set-key`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ apiKey, baseUrl }),
  })
  return res.json()
}

export async function ttrpgClearKey(apiBase) {
  const res = await fetch(`${apiBase}?action=ttrpg-clear-key`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({}),
  })
  return res.json()
}

export async function ttrpgSelectCampaign(apiBase, campaignId) {
  const res = await fetch(`${apiBase}?action=ttrpg-select-campaign`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ campaignId }),
  })
  return res.json()
}

export async function ttrpgUploadAsset(apiBase, campaignId, file) {
  const fd = new FormData()
  fd.append('campaignId', String(campaignId))
  fd.append('image', file)
  const res = await fetch(`${apiBase}?action=ttrpg-upload-asset`, {
    method: 'POST',
    credentials: 'include',
    body: fd,
  })
  return res.json()
}

export async function ttrpgUploadHandbook(apiBase, { file, title, language, campaignId }) {
  const fd = new FormData()
  fd.append('pdf', file)
  fd.append('title', title)
  if (language) fd.append('language', language)
  if (campaignId != null && campaignId !== '') fd.append('campaign_id', String(campaignId))
  const res = await fetch(`${apiBase}?action=ttrpg-upload-handbook`, {
    method: 'POST',
    credentials: 'include',
    body: fd,
  })
  return res.json()
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
