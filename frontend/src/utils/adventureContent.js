import { getImageSrc } from './sidebarHelpers'

const DICE_RE = /(?<![\w/])(\d*d\d+(?:\s*[+-]\s*(?:\d*d\d+|\d+))*)/gi

function textOffset(root, node, offset) {
  const range = document.createRange()
  range.selectNodeContents(root)
  range.setEnd(node, offset)
  return range.toString().length
}

function nodeTextStart(root, node) {
  const range = document.createRange()
  range.selectNodeContents(root)
  range.setEnd(node, 0)
  return range.toString().length
}

function restoreCaret(root, offset) {
  if (offset == null) return
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  let remaining = offset
  let node = walker.nextNode()
  while (node) {
    const len = node.nodeValue?.length || 0
    if (remaining <= len) {
      const range = document.createRange()
      const parent = node.parentElement
      if (parent?.classList.contains('adv-roll')) {
        if (remaining <= 0) range.setStartBefore(parent)
        else range.setStartAfter(parent)
      } else {
        range.setStart(node, remaining)
      }
      range.collapse(true)
      const selection = window.getSelection()
      selection?.removeAllRanges()
      selection?.addRange(range)
      return
    }
    remaining -= len
    node = walker.nextNode()
  }
}

/**
 * Wrap dice notation in non-editable chips. Text length stays the same.
 * When skipNearCaret is set, a match the caret is still inside is left alone.
 */
export function decorateDiceIn(root, { skipNearCaret = false } = {}) {
  if (!root) return false
  let caret = null
  const selection = window.getSelection()
  if (selection && selection.rangeCount > 0 && root.contains(selection.anchorNode)) {
    try {
      caret = textOffset(root, selection.anchorNode, selection.anchorOffset)
    } catch {
      caret = null
    }
  }

  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      const parent = node.parentElement
      if (!parent) return NodeFilter.FILTER_REJECT
      if (parent.closest('.adv-roll, .adv-entry-link, .adv-ref, script, style')) {
        return NodeFilter.FILTER_REJECT
      }
      DICE_RE.lastIndex = 0
      if (!DICE_RE.test(node.nodeValue || '')) return NodeFilter.FILTER_REJECT
      return NodeFilter.FILTER_ACCEPT
    },
  })

  const nodes = []
  let current = walker.nextNode()
  while (current) {
    nodes.push(current)
    current = walker.nextNode()
  }

  let changed = false
  nodes.forEach((node) => {
    const text = node.nodeValue || ''
    const startBase = nodeTextStart(root, node)
    DICE_RE.lastIndex = 0
    const frag = document.createDocumentFragment()
    let last = 0
    let match = DICE_RE.exec(text)
    let wrapped = false
    while (match) {
      const expr = match[1]
      const start = startBase + match.index
      const end = start + expr.length
      const caretInside = skipNearCaret && caret != null && caret > start && caret < end
      if (caretInside) {
        match = DICE_RE.exec(text)
        continue
      }
      if (match.index > last) {
        frag.appendChild(document.createTextNode(text.slice(last, match.index)))
      }
      const span = document.createElement('span')
      span.className = 'adv-roll'
      span.setAttribute('data-roll', expr.replace(/\s+/g, ''))
      span.setAttribute('contenteditable', 'false')
      span.textContent = expr
      frag.appendChild(span)
      last = match.index + match[0].length
      wrapped = true
      match = DICE_RE.exec(text)
    }
    if (!wrapped) return
    if (last < text.length) {
      frag.appendChild(document.createTextNode(text.slice(last)))
    }
    node.parentNode.replaceChild(frag, node)
    changed = true
  })

  if (changed) restoreCaret(root, caret)
  return changed
}

export function serializeAdventureHtml(root) {
  if (!root) return ''
  const clone = root.cloneNode(true)
  clone.querySelectorAll('img').forEach((img) => {
    const rel = img.getAttribute('data-src')
    if (rel) img.setAttribute('src', rel)
  })
  return clone.innerHTML
}

function adventureAssetRef(_rel, src) {
  const raw = src || ''
  const globalAt = raw.indexOf('/vtt/global/')
  if (globalAt !== -1) return raw.slice(globalAt)
  const at = raw.indexOf('backend/assets/')
  return at === -1 ? '' : raw.slice(at)
}

export function hydrateAdventureHtml(html, assetBase, knownIds, missingLabel) {
  const holder = document.createElement('div')
  holder.innerHTML = html || ''

  holder.querySelectorAll('img').forEach((img) => {
    const rel = img.getAttribute('data-src') || ''
    const src = img.getAttribute('src') || ''
    const relative = rel && !/^[a-z][a-z0-9+.-]*:/i.test(rel)
      ? rel
      : adventureAssetRef('', src)
    if (!relative) return
    img.setAttribute('data-src', relative)
    img.src = getImageSrc(relative, assetBase)
    img.classList.add('adv-ref')
    img.setAttribute('contenteditable', 'false')
  })

  holder.querySelectorAll('a[data-entry-id]').forEach((anchor) => {
    const id = anchor.getAttribute('data-entry-id')
    if (knownIds && !knownIds.has(String(id))) {
      anchor.replaceWith(document.createTextNode(missingLabel || ''))
      return
    }
    anchor.classList.add('adv-entry-link')
    anchor.setAttribute('href', '#')
    anchor.setAttribute('contenteditable', 'false')
  })

  decorateDiceIn(holder)
  return holder.innerHTML
}
