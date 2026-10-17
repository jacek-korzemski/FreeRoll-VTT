import { useCallback, useEffect, useRef, useState } from 'react'
import SimpleWYSIWYG from '../atoms/SimpleWYSIWYG'
import { t } from '../../lang'
import { executeDiceRoll } from '../../utils/diceRollUtils'
import { clearActiveAssetDrag, getActiveAssetDrag, getImageSrc } from '../../utils/sidebarHelpers'
import { decorateDiceIn, hydrateAdventureHtml, serializeAdventureHtml } from '../../utils/adventureContent'
import AdventureImageZoom from './AdventureImageZoom'

function rangeFromPoint(x, y) {
  if (document.caretRangeFromPoint) return document.caretRangeFromPoint(x, y)
  const pos = document.caretPositionFromPoint?.(x, y)
  if (!pos) return null
  const range = document.createRange()
  range.setStart(pos.offsetNode, pos.offset)
  range.collapse(true)
  return range
}

function AdventureEditor({
  entryId,
  initialHtml,
  assetBase,
  entries,
  knownIds,
  selectedAsset,
  selectedType,
  onHtmlChange,
  onOpenPreview,
}) {
  const editorRef = useRef(null)
  const savedRangeRef = useRef(null)
  const linkMenuRef = useRef(null)
  const onHtmlChangeRef = useRef(onHtmlChange)
  const [linkMenu, setLinkMenu] = useState(null)
  const [hydrated] = useState(() => hydrateAdventureHtml(
    initialHtml,
    assetBase,
    knownIds,
    t('adventure.missing')
  ))

  useEffect(() => {
    onHtmlChangeRef.current = onHtmlChange
  }, [onHtmlChange])

  const readSerialized = useCallback(() => {
    const el = editorRef.current?.getElement()
    return el ? serializeAdventureHtml(el) : ''
  }, [])

  const publish = useCallback(() => {
    onHtmlChangeRef.current(readSerialized())
  }, [readSerialized])

  const decorateTimerRef = useRef(null)

  const decorate = useCallback((skipNearCaret) => {
    const el = editorRef.current?.getElement()
    if (!el) return
    if (decorateDiceIn(el, { skipNearCaret })) publish()
  }, [publish])

  const handleChange = useCallback(() => {
    publish()
    clearTimeout(decorateTimerRef.current)
    decorateTimerRef.current = setTimeout(() => decorate(true), 600)
  }, [decorate, publish])

  useEffect(() => () => clearTimeout(decorateTimerRef.current), [])

  const rememberSelection = useCallback(() => {
    const editor = editorRef.current?.getElement()
    const selection = window.getSelection()
    if (!editor || !selection || selection.rangeCount === 0) return
    const range = selection.getRangeAt(0)
    if (editor.contains(range.commonAncestorContainer)) {
      savedRangeRef.current = range.cloneRange()
    }
  }, [])

  useEffect(() => {
    const onSelectionChange = () => rememberSelection()
    document.addEventListener('selectionchange', onSelectionChange)
    return () => document.removeEventListener('selectionchange', onSelectionChange)
  }, [rememberSelection])

  useEffect(() => {
    const el = editorRef.current?.getElement()
    if (!el) return
    el.querySelectorAll('a.adv-entry-link').forEach((anchor) => {
      const id = anchor.getAttribute('data-entry-id')
      if (!knownIds.has(String(id))) {
        anchor.replaceWith(document.createTextNode(t('adventure.missing')))
      }
    })
  }, [knownIds])

  useEffect(() => {
    if (!linkMenu) return
    const close = (event) => {
      if (event.target.closest?.('.adventure-link-toggle')) return
      if (!linkMenuRef.current?.contains(event.target)) setLinkMenu(null)
    }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [linkMenu])

  const placeNode = (node, clientX, clientY) => {
    const editor = editorRef.current?.getElement()
    if (!editor) return
    editor.focus()
    const selection = window.getSelection()
    let range = clientX != null ? rangeFromPoint(clientX, clientY) : null
    if (!range || !editor.contains(range.commonAncestorContainer)) {
      if (savedRangeRef.current) {
        range = savedRangeRef.current
      } else {
        range = document.createRange()
        range.selectNodeContents(editor)
        range.collapse(false)
      }
    }
    range.deleteContents()
    range.insertNode(node)
    range.setStartAfter(node)
    range.collapse(true)
    selection?.removeAllRanges()
    selection?.addRange(range)
    savedRangeRef.current = range.cloneRange()
    publish()
  }

  const buildImage = (asset, type) => {
    const img = document.createElement('img')
    img.className = 'adv-ref'
    img.setAttribute('data-src', asset.src)
    img.setAttribute('data-asset-type', type || 'map')
    img.alt = asset.name || ''
    img.src = getImageSrc(asset.src, assetBase)
    img.setAttribute('contenteditable', 'false')
    return img
  }

  const handleEditorClick = (event) => {
    const roll = event.target.closest?.('.adv-roll')
    if (roll) {
      event.preventDefault()
      const expr = roll.getAttribute('data-roll') || ''
      if (expr) executeDiceRoll(expr, '', () => '')
      return
    }
    const link = event.target.closest?.('.adv-entry-link')
    if (link) {
      event.preventDefault()
      const id = Number(link.getAttribute('data-entry-id'))
      if (id) onOpenPreview(id, event)
    }
  }

  const handleDragOver = (event) => {
    const drag = getActiveAssetDrag()
    const types = event.dataTransfer?.types ? Array.from(event.dataTransfer.types) : []
    if (drag || types.includes('application/json')) {
      event.preventDefault()
      event.dataTransfer.dropEffect = 'copy'
    }
  }

  const handleDrop = (event) => {
    const drag = getActiveAssetDrag()
    let asset = drag?.asset
    let type = drag?.type
    if (!asset) {
      try {
        const parsed = JSON.parse(event.dataTransfer.getData('application/json') || '')
        asset = parsed
        type = parsed?.type
      } catch {
        asset = null
      }
    }
    clearActiveAssetDrag()
    if (!asset?.src) return
    event.preventDefault()
    placeNode(buildImage(asset, type), event.clientX, event.clientY)
  }

  const openLinkMenu = () => {
    const range = savedRangeRef.current
    const text = range && !range.collapsed ? range.toString().trim() : ''
    setLinkMenu(text ? 'list' : 'empty')
  }

  const insertLink = (targetId) => {
    const editor = editorRef.current?.getElement()
    const range = savedRangeRef.current
    if (!editor || !range || range.collapsed) {
      setLinkMenu('empty')
      return
    }
    const text = range.toString()
    if (!text.trim()) {
      setLinkMenu('empty')
      return
    }
    const anchor = document.createElement('a')
    anchor.href = '#'
    anchor.className = 'adv-entry-link'
    anchor.setAttribute('data-entry-id', String(targetId))
    anchor.setAttribute('contenteditable', 'false')
    anchor.textContent = text
    range.deleteContents()
    range.insertNode(anchor)
    const selection = window.getSelection()
    const after = document.createRange()
    after.setStartAfter(anchor)
    after.collapse(true)
    selection?.removeAllRanges()
    selection?.addRange(after)
    savedRangeRef.current = after.cloneRange()
    setLinkMenu(null)
    publish()
  }

  const insertSelected = () => {
    if (!selectedAsset?.src) return
    placeNode(buildImage(selectedAsset, selectedType || 'map'))
  }

  const canInsert = Boolean(selectedAsset?.src && (selectedType === 'map' || selectedType === 'token' || selectedType === 'background'))

  const editorRoot = useCallback(() => editorRef.current?.getElement() || null, [])

  return (
    <>
    <AdventureImageZoom getRoot={editorRoot} />
    <SimpleWYSIWYG
      ref={editorRef}
      className="adventure-wysiwyg"
      height="100%"
      placeholder={t('adventure.placeholder')}
      initialContent={hydrated}
      onChange={handleChange}
      onEditorClick={handleEditorClick}
      onEditorDragOver={handleDragOver}
      onEditorDrop={handleDrop}
      onEditorBlur={() => decorate(false)}
      toolbarExtra={(
        <>
          <div className="swysiwyg-toolbar-group adventure-link-wrap" ref={linkMenuRef}>
            <button
              type="button"
              className={`adventure-link-toggle${linkMenu ? ' active' : ''}`}
              onMouseDown={(event) => event.preventDefault()}
              onClick={openLinkMenu}
              title={t('adventure.link')}
            >
              {t('adventure.link')}
            </button>
            {linkMenu && (
              <div className="adventure-link-menu">
                {linkMenu === 'empty' && <p>{t('adventure.linkEmpty')}</p>}
                {linkMenu === 'list' && entries.map((entry) => (
                  <button
                    key={entry.id}
                    type="button"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => insertLink(entry.id)}
                  >
                    {entry.title}
                  </button>
                ))}
              </div>
            )}
          </div>
          <div className="swysiwyg-toolbar-group">
            <button
              type="button"
              className="adventure-insert-asset"
              onMouseDown={(event) => event.preventDefault()}
              onClick={insertSelected}
              disabled={!canInsert}
              title={canInsert ? t('adventure.insertAsset') : t('adventure.insertAssetEmpty')}
            >
              {t('adventure.insertAsset')}
            </button>
          </div>
        </>
      )}
    />
    </>
  )
}

export default AdventureEditor
