/**
 * Shared parsing for template HTML stored in vtt_notes_* (same rules as NoteEditor DOM pass).
 */

export function extractBodyContent(html) {
  if (!html || typeof html !== 'string') return ''
  const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i)
  return bodyMatch ? bodyMatch[1] : html
}

/**
 * @param {string} templateHtml - full HTML or body fragment
 * @returns {{ fieldNames: string[], fieldLabels: Record<string, string> }}
 */
export function getTemplateFieldMetaFromHtml(templateHtml) {
  if (!templateHtml || typeof document === 'undefined') {
    return { fieldNames: [], fieldLabels: {} }
  }
  const body = extractBodyContent(templateHtml)
  const parser = new DOMParser()
  const doc = parser.parseFromString('<div id="__note_root__">' + body + '</div>', 'text/html')
  const container = doc.getElementById('__note_root__')
  if (!container) return { fieldNames: [], fieldLabels: {} }

  const fieldEntries = [...container.querySelectorAll('[data-field]')]
    .map((el) => {
      const name = el.getAttribute('data-field')
      if (!name) return null
      let label = name
      let prev = el.previousElementSibling
      while (prev && prev.tagName === 'BR') prev = prev.previousElementSibling
      if (prev && prev.tagName === 'STRONG') {
        label = prev.textContent.replace(/:\s*$/, '').trim() || name
      } else {
        const next = el.nextElementSibling
        if (next && next.tagName === 'STRONG') {
          label = next.textContent.replace(/:\s*$/, '').trim() || name
        }
      }
      return { name, label }
    })
    .filter(Boolean)

  const fieldNames = [...new Set(fieldEntries.map((e) => e.name))]
  const fieldLabels = Object.fromEntries(fieldEntries.map((e) => [e.name, e.label]))
  return { fieldNames, fieldLabels }
}

/**
 * @param {string} templateHtml
 * @param {string} fieldName
 */
export function isTemplateCheckboxField(templateHtml, fieldName) {
  if (!templateHtml || !fieldName || typeof document === 'undefined') return false
  const body = extractBodyContent(templateHtml)
  const parser = new DOMParser()
  const doc = parser.parseFromString('<div id="__note_root__">' + body + '</div>', 'text/html')
  const container = doc.getElementById('__note_root__')
  if (!container) return false
  for (const el of container.querySelectorAll('[data-field]')) {
    if (el.getAttribute('data-field') === fieldName) {
      return el.tagName === 'INPUT' && el.getAttribute('type') === 'checkbox'
    }
  }
  return false
}

/**
 * Read parsed note JSON from localStorage (same key as NoteEditor).
 * @param {string} noteId
 * @returns {{ mode?: string, title?: string, templateHtml?: string, fields?: Record<string, unknown>, content?: string } | null}
 */
export function readNoteStorageData(noteId) {
  const raw = localStorage.getItem(`vtt_notes_${noteId}`)
  if (!raw) return null
  try {
    const data = JSON.parse(raw)
    return typeof data === 'object' && data ? data : null
  } catch {
    return null
  }
}

/**
 * Field value for macros when editor is not mounted (template mode, values in data.fields).
 */
export function getStoredTemplateFieldValue(data, fieldName) {
  if (!data || data.mode !== 'template') return ''
  const fields = data.fields || {}
  const val = fields[fieldName]
  const html = data.templateHtml || ''
  if (isTemplateCheckboxField(html, fieldName)) {
    return val === true || val === 'true' || val === 'on'
  }
  if (val === undefined || val === null) return ''
  return val
}
