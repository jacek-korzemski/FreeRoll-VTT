/**
 * Template runtime: per-instance scoped CSS + sandboxed JS for note templates.
 *
 * Three call sites (NoteEditor, TokenNoteEditor, TemplateManager preview) share
 * this code. Each rendered template instance gets a unique scopeId; <style>
 * blocks are rewritten so their selectors only match inside the instance, and
 * <script> blocks are run via `new Function` with a curated `vtt` API.
 *
 * SECURITY NOTE: <script> bodies execute via `new Function` in the page origin.
 * Templates are uploaded/edited by GMs/admins only (backend/assets/templates),
 * so we treat them as TRUSTED CONTENT. Do not enable for untrusted uploads.
 */

import { executeDiceRoll, getEffectiveRollExpression } from './diceRollUtils'

const INJECTED_ATTR = 'data-vtt-injected'

/**
 * Parse a template HTML document and split it into body fragment, styles, and scripts.
 * - Strips <style> and <script> from the returned body so they are not double-mounted.
 * - Ignores <script src="..."> (we never execute external scripts).
 * - Falls back to treating input as a body fragment if no <body> is present.
 *
 * @param {string} html
 * @returns {{ body: string, styles: string[], scripts: string[] }}
 */
export function parseTemplateAssets(html) {
  if (!html || typeof html !== 'string') {
    return { body: '', styles: [], scripts: [] }
  }
  if (typeof DOMParser === 'undefined') {
    const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i)
    return { body: bodyMatch ? bodyMatch[1] : html, styles: [], scripts: [] }
  }

  const doc = new DOMParser().parseFromString(html, 'text/html')
  const styles = []
  const scripts = []

  doc.querySelectorAll('style').forEach((el) => {
    const css = el.textContent || ''
    if (css.trim()) styles.push(css)
  })

  doc.querySelectorAll('script').forEach((el) => {
    if (el.hasAttribute('src')) return
    const code = el.textContent || ''
    if (code.trim()) scripts.push(code)
  })

  let body = ''
  if (doc.body) {
    const bodyClone = doc.body.cloneNode(true)
    bodyClone.querySelectorAll('style, script').forEach((n) => n.remove())
    body = bodyClone.innerHTML
  } else {
    const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i)
    body = bodyMatch ? bodyMatch[1] : html
    body = body.replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '')
    body = body.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
  }

  return { body, styles, scripts }
}

/**
 * Rewrite a CSS string so every selector only matches inside an element with
 * `[data-vtt-scope="<scopeId>"]`. Handles nested at-rules and keeps
 * @keyframes / @font-face / @import unchanged.
 *
 * @param {string} cssText
 * @param {string} scopeId
 * @returns {string}
 */
export function scopeCss(cssText, scopeId) {
  if (!cssText || typeof cssText !== 'string') return ''
  const scopeSel = `[data-vtt-scope="${cssEscape(scopeId)}"]`
  const blocks = parseCssBlocks(cssText)
  return rebuild(blocks, scopeSel)

  function rebuild(items, scope) {
    return items
      .map((item) => {
        if (item.kind === 'rule') {
          return prefixSelectorList(item.selector, scope) + '{' + item.body + '}'
        }
        if (item.kind === 'at') {
          const name = item.name.toLowerCase()
          if (name === 'keyframes' || name === 'font-face' || name === 'import' || name === 'charset') {
            return '@' + item.name + (item.prelude ? ' ' + item.prelude : '') + (item.body !== null ? '{' + item.body + '}' : ';')
          }
          if (name === 'media' || name === 'supports' || name === 'container' || name === 'layer') {
            const inner = parseCssBlocks(item.body || '')
            return '@' + item.name + (item.prelude ? ' ' + item.prelude : '') + '{' + rebuild(inner, scope) + '}'
          }
          return '@' + item.name + (item.prelude ? ' ' + item.prelude : '') + (item.body !== null ? '{' + item.body + '}' : ';')
        }
        return ''
      })
      .join('\n')
  }
}

function prefixSelectorList(selectorList, scopeSel) {
  const parts = splitTopLevel(selectorList, ',')
  return parts
    .map((sel) => {
      const trimmed = sel.trim()
      if (!trimmed) return ''
      if (/^(:root|html|body)\b/i.test(trimmed)) {
        const rest = trimmed.replace(/^(:root|html|body)\b/i, '').trim()
        return rest ? `${scopeSel} ${rest}` : scopeSel
      }
      return `${scopeSel} ${trimmed}`
    })
    .filter(Boolean)
    .join(', ')
}

/**
 * Split CSS at the top level into items (rules / at-rules), respecting nested
 * braces, parentheses, strings and comments.
 */
function parseCssBlocks(text) {
  const items = []
  let i = 0
  const n = text.length
  while (i < n) {
    while (i < n && /\s/.test(text[i])) i++
    if (i >= n) break
    if (text[i] === '/' && text[i + 1] === '*') {
      const end = text.indexOf('*/', i + 2)
      if (end === -1) break
      i = end + 2
      continue
    }
    if (text[i] === '@') {
      const at = readAtRule(text, i)
      items.push(at.item)
      i = at.next
      continue
    }
    const rule = readRule(text, i)
    if (rule) {
      items.push(rule.item)
      i = rule.next
    } else {
      i++
    }
  }
  return items
}

function readAtRule(text, start) {
  let i = start + 1
  let name = ''
  while (i < text.length && /[a-zA-Z0-9_-]/.test(text[i])) {
    name += text[i]
    i++
  }
  let prelude = ''
  while (i < text.length && text[i] !== '{' && text[i] !== ';') {
    prelude += text[i]
    i++
  }
  prelude = prelude.trim()
  if (i >= text.length) {
    return { item: { kind: 'at', name, prelude, body: null }, next: text.length }
  }
  if (text[i] === ';') {
    return { item: { kind: 'at', name, prelude, body: null }, next: i + 1 }
  }
  const body = readBalanced(text, i)
  return { item: { kind: 'at', name, prelude, body: body.content }, next: body.next }
}

function readRule(text, start) {
  let i = start
  let selector = ''
  while (i < text.length && text[i] !== '{') {
    if (text[i] === '/' && text[i + 1] === '*') {
      const end = text.indexOf('*/', i + 2)
      if (end === -1) return null
      i = end + 2
      continue
    }
    if (text[i] === '"' || text[i] === "'") {
      const q = text[i]
      selector += text[i]
      i++
      while (i < text.length && text[i] !== q) {
        if (text[i] === '\\' && i + 1 < text.length) {
          selector += text[i] + text[i + 1]
          i += 2
        } else {
          selector += text[i]
          i++
        }
      }
      if (i < text.length) {
        selector += text[i]
        i++
      }
      continue
    }
    selector += text[i]
    i++
  }
  if (i >= text.length) return null
  const body = readBalanced(text, i)
  return { item: { kind: 'rule', selector: selector.trim(), body: body.content }, next: body.next }
}

function readBalanced(text, start) {
  let i = start + 1
  let depth = 1
  let content = ''
  while (i < text.length && depth > 0) {
    const ch = text[i]
    if (ch === '/' && text[i + 1] === '*') {
      const end = text.indexOf('*/', i + 2)
      const seg = end === -1 ? text.slice(i) : text.slice(i, end + 2)
      content += seg
      i += seg.length
      continue
    }
    if (ch === '"' || ch === "'") {
      const q = ch
      content += ch
      i++
      while (i < text.length && text[i] !== q) {
        if (text[i] === '\\' && i + 1 < text.length) {
          content += text[i] + text[i + 1]
          i += 2
        } else {
          content += text[i]
          i++
        }
      }
      if (i < text.length) {
        content += text[i]
        i++
      }
      continue
    }
    if (ch === '{') depth++
    else if (ch === '}') {
      depth--
      if (depth === 0) {
        i++
        break
      }
    }
    content += ch
    i++
  }
  return { content, next: i }
}

function splitTopLevel(text, sep) {
  const out = []
  let depth = 0
  let buf = ''
  for (let i = 0; i < text.length; i++) {
    const ch = text[i]
    if (ch === '(' || ch === '[' || ch === '{') depth++
    else if (ch === ')' || ch === ']' || ch === '}') depth--
    if (depth === 0 && ch === sep) {
      out.push(buf)
      buf = ''
    } else {
      buf += ch
    }
  }
  if (buf) out.push(buf)
  return out
}

function cssEscape(value) {
  return String(value).replace(/[^a-zA-Z0-9_-]/g, (c) => '\\' + c)
}

/**
 * Mount a template into `container`. Wires data-field two-way binding,
 * data-roll click handlers, scoped <style> injection and sandboxed <script>
 * execution. Returns an object with cleanup + helpers.
 *
 * Options:
 *  - container:        HTMLElement (required)
 *  - html:             full template HTML or body fragment (required)
 *  - scopeId:          unique id for this instance (required) e.g. "note-1"
 *  - fields:           initial field values (object)
 *  - onFieldChange:    (name, value) => void  — called whenever a field changes
 *                                               (user input OR vtt.setField)
 *  - readOnly:         boolean — if true, do not run scripts and skip field listeners
 *
 * @returns {{
 *   unmount: () => void,
 *   getFieldValue: (name: string) => string|boolean,
 *   getFieldElements: () => Element[],
 *   serialize: () => string,
 * }}
 */
export function mountTemplate({
  container,
  html,
  scopeId,
  fields = {},
  onFieldChange,
  readOnly = false,
}) {
  if (!container || !html) {
    return {
      unmount: () => {},
      getFieldValue: () => '',
      getFieldElements: () => [],
      serialize: () => '',
    }
  }

  const { body, styles, scripts } = parseTemplateAssets(html)

  container.setAttribute('data-vtt-scope', scopeId)
  container.innerHTML = body

  const cleanups = []
  const fieldListeners = []
  const mountListeners = []
  const destroyListeners = []
  let isApplyingFieldUpdate = false
  let unmounted = false

  // --- scoped styles ---
  styles.forEach((cssText) => {
    const styleEl = document.createElement('style')
    styleEl.setAttribute(INJECTED_ATTR, 'true')
    styleEl.setAttribute('data-vtt-scope-style', scopeId)
    styleEl.textContent = scopeCss(cssText, scopeId)
    container.appendChild(styleEl)
    cleanups.push(() => {
      if (styleEl.parentNode) styleEl.parentNode.removeChild(styleEl)
    })
  })

  // --- field bindings ---
  const fieldEls = [...container.querySelectorAll('[data-field]')]
  fieldEls.forEach((el) => {
    const name = el.getAttribute('data-field')
    if (!name) return
    const stored = Object.prototype.hasOwnProperty.call(fields, name) ? fields[name] : undefined

    if (el.type === 'checkbox') {
      el.checked = stored === true || stored === 'true' || stored === 'on'
    } else if (el.tagName === 'TEXTAREA') {
      el.value = stored ?? ''
    } else {
      el.value = stored ?? el.getAttribute('value') ?? ''
    }

    if (readOnly) return

    const handler = () => {
      const value = el.type === 'checkbox' ? el.checked : el.value
      try {
        onFieldChange && onFieldChange(name, value)
      } catch (err) {
        console.error(`[template:${scopeId}] onFieldChange callback failed`, err)
      }
      if (!isApplyingFieldUpdate) notifyScriptListeners(name, value)
    }
    const evt = el.type === 'checkbox' ? 'change' : 'input'
    el.addEventListener(evt, handler)
    cleanups.push(() => el.removeEventListener(evt, handler))
  })

  // --- helpers ---
  const getFieldValue = (name) => {
    const el = container.querySelector(`[data-field="${cssAttrEscape(name)}"]`)
    if (!el) return ''
    if (el.type === 'checkbox') return el.checked
    return el.value || ''
  }

  const setFieldValue = (name, value) => {
    const el = container.querySelector(`[data-field="${cssAttrEscape(name)}"]`)
    if (!el) {
      console.warn(`[template:${scopeId}] setField: unknown field "${name}"`)
      return
    }
    isApplyingFieldUpdate = true
    try {
      let normalized
      if (el.type === 'checkbox') {
        const bool = value === true || value === 'true' || value === 'on' || value === 1 || value === '1'
        if (el.checked === bool) {
          isApplyingFieldUpdate = false
          return
        }
        el.checked = bool
        normalized = bool
      } else {
        const next = value == null ? '' : String(value)
        if (el.value === next) {
          isApplyingFieldUpdate = false
          return
        }
        el.value = next
        normalized = next
      }
      try {
        onFieldChange && onFieldChange(name, normalized)
      } catch (err) {
        console.error(`[template:${scopeId}] onFieldChange callback failed`, err)
      }
    } finally {
      isApplyingFieldUpdate = false
    }
  }

  // --- dice roll handlers ---
  if (!readOnly) {
    container.querySelectorAll('[data-roll]').forEach((btn) => {
      const handler = (e) => {
        e.preventDefault()
        const expr = getEffectiveRollExpression(btn)
        const label = btn.getAttribute('data-roll-label') || ''
        executeDiceRoll(expr, label, getFieldValue)
      }
      btn.addEventListener('click', handler)
      cleanups.push(() => btn.removeEventListener('click', handler))
    })
  }

  // --- script sandbox ---
  function notifyScriptListeners(name, value) {
    if (unmounted) return
    fieldListeners.forEach((cb) => {
      try {
        cb(name, value)
      } catch (err) {
        console.error(`[template:${scopeId}] onFieldChange handler failed`, err)
      }
    })
  }

  if (!readOnly && scripts.length > 0) {
    const fieldsSnapshot = Object.freeze({ ...fields })
    const vtt = {
      scopeId,
      root: container,
      fields: fieldsSnapshot,
      getField: getFieldValue,
      setField: setFieldValue,
      onFieldChange: (cb) => {
        if (typeof cb === 'function') fieldListeners.push(cb)
      },
      onMount: (cb) => {
        if (typeof cb === 'function') mountListeners.push(cb)
      },
      onDestroy: (cb) => {
        if (typeof cb === 'function') destroyListeners.push(cb)
      },
    }
    Object.freeze(vtt)

    scripts.forEach((code, idx) => {
      try {
        const fn = new Function('vtt', '"use strict";\n' + code)
        fn(vtt)
      } catch (err) {
        console.error(`[template:${scopeId}] script #${idx} failed to run`, err)
      }
    })

    mountListeners.forEach((cb) => {
      try {
        cb()
      } catch (err) {
        console.error(`[template:${scopeId}] onMount handler failed`, err)
      }
    })
  }

  function unmount() {
    if (unmounted) return
    unmounted = true
    destroyListeners.forEach((cb) => {
      try {
        cb()
      } catch (err) {
        console.error(`[template:${scopeId}] onDestroy handler failed`, err)
      }
    })
    cleanups.forEach((fn) => {
      try {
        fn()
      } catch (err) {
        console.error(`[template:${scopeId}] cleanup failed`, err)
      }
    })
    fieldListeners.length = 0
    mountListeners.length = 0
    destroyListeners.length = 0
    if (container.getAttribute('data-vtt-scope') === scopeId) {
      container.removeAttribute('data-vtt-scope')
    }
  }

  return {
    unmount,
    getFieldValue,
    getFieldElements: () => [...container.querySelectorAll('[data-field]')],
    serialize: () => serializeTemplate({ container, originalStyles: styles, originalScripts: scripts }),
  }
}

function cssAttrEscape(value) {
  return String(value).replace(/(["\\])/g, '\\$1')
}

/**
 * Build an HTML string suitable for export: current field values inlined as
 * value=/checked attributes, plus the original (unscoped) styles and scripts
 * so the exported file behaves the same when opened standalone.
 */
function serializeTemplate({ container, originalStyles, originalScripts }) {
  const clone = container.cloneNode(true)
  clone.querySelectorAll(`[${INJECTED_ATTR}]`).forEach((n) => n.remove())
  clone.removeAttribute('data-vtt-scope')
  clone.querySelectorAll('[data-field]').forEach((el) => {
    if (el.tagName === 'INPUT' && el.getAttribute('type') === 'checkbox') {
      if (el.checked) el.setAttribute('checked', '')
      else el.removeAttribute('checked')
    } else if (el.tagName === 'TEXTAREA') {
      el.textContent = el.value || ''
    } else if (el.tagName === 'INPUT') {
      el.setAttribute('value', el.value || '')
    }
  })
  const bodyHtml = clone.innerHTML
  const stylesHtml = originalStyles.map((s) => `<style>\n${s}\n</style>`).join('\n')
  const scriptsHtml = originalScripts.map((s) => `<script>\n${s}\n</script>`).join('\n')
  return { bodyHtml, stylesHtml, scriptsHtml }
}
