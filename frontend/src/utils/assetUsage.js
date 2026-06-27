/**
 * Collect template IDs referenced in local notepads and token character panels.
 */
export function collectClientTemplateUsage() {
  const used = new Map()

  const addUsage = (templateId, source) => {
    if (!templateId || typeof templateId !== 'string') return
    const existing = used.get(templateId) || []
    if (!existing.includes(source)) {
      existing.push(source)
    }
    used.set(templateId, existing)
  }

  try {
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (!key) continue

      if (key.startsWith('vtt_notes_')) {
        try {
          const data = JSON.parse(localStorage.getItem(key))
          if (data?.templateId) {
            addUsage(data.templateId, key.replace('vtt_notes_', 'notepad '))
          }
        } catch {
          /* ignore invalid JSON */
        }
        continue
      }

      if (key.startsWith('vtt_token_note_')) {
        try {
          const data = JSON.parse(localStorage.getItem(key))
          if (data?.templateId) {
            addUsage(data.templateId, 'token panel')
          }
        } catch {
          /* ignore invalid JSON */
        }
      }
    }
  } catch {
    /* localStorage unavailable */
  }

  return used
}

export function isTemplateInClientUse(templateId, clientUsage) {
  return clientUsage.has(templateId)
}

export function getTemplateClientUseSources(templateId, clientUsage) {
  return clientUsage.get(templateId) || []
}

export function isAssetInServerUse(type, id, serverUsage) {
  if (!serverUsage || !id) return false
  const bucket = serverUsage[type]
  if (!bucket) return false

  let key = id
  if (type === 'background' || type === 'paper' || type === 'template') {
    key = id.split(/[/\\]/).pop()
  }
  return Boolean(bucket[key])
}

export function getAssetServerUseScenes(type, id, serverUsage) {
  if (!serverUsage || !id) return []
  const bucket = serverUsage[type]
  if (!bucket) return []

  let key = id
  if (type === 'background' || type === 'paper' || type === 'template') {
    key = id.split(/[/\\]/).pop()
  }
  return bucket[key] || []
}
