import { useCallback, useMemo, useRef } from 'react'
import { t } from '../../lang'
import { executeDiceRoll } from '../../utils/diceRollUtils'
import { hydrateAdventureHtml } from '../../utils/adventureContent'
import AdventureImageZoom from './AdventureImageZoom'

function AdventurePreviewBox({
  title,
  html,
  assetBase,
  knownIds,
  left,
  top,
  zIndex,
  onClose,
  onOpen,
  onMove,
  onFocus,
  onOpenLink,
}) {
  const dragRef = useRef(null)
  const bodyRef = useRef(null)
  const previewRoot = useCallback(() => bodyRef.current, [])
  const knownKey = knownIds.join(',')
  const safeHtml = useMemo(
    () => hydrateAdventureHtml(html || '', assetBase, new Set(knownIds), t('adventure.missing')),
    [html, assetBase, knownKey, knownIds]
  )

  const handleClick = (event) => {
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
      if (id) onOpenLink(id, event)
    }
  }

  const handlePointerDown = (event) => {
    if (event.target.closest('button')) return
    onFocus()
    dragRef.current = { x: event.clientX, y: event.clientY, left, top }
    event.currentTarget.setPointerCapture(event.pointerId)
  }

  const handlePointerMove = (event) => {
    if (!dragRef.current) return
    onMove(
      dragRef.current.left + (event.clientX - dragRef.current.x),
      dragRef.current.top + (event.clientY - dragRef.current.y)
    )
  }

  const handlePointerUp = () => {
    dragRef.current = null
  }

  return (
    <div
      className="adventure-box"
      style={{ left, top, zIndex }}
      onMouseDown={onFocus}
    >
      <div
        className="adventure-box-title"
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={handlePointerUp}
        onPointerCancel={handlePointerUp}
      >
        <span className="adventure-box-title-text">{title}</span>
        <button type="button" className="adventure-box-open" onClick={onOpen} title={t('adventure.openEntry')}>
          <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
            <path fill="currentColor" d="M2 3.5A1.5 1.5 0 0 1 3.5 2H8v1.5H3.5v9h9V8H14v4.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 12.5v-9z" />
            <path fill="currentColor" d="M9 2h5v5h-1.5V4.56L8.03 9.03 6.97 7.97l4.47-4.47H9V2z" />
          </svg>
        </button>
        <button type="button" onClick={onClose} title={t('adventure.closeBox')}>×</button>
      </div>
      <div
        ref={bodyRef}
        className="adventure-box-body"
        onClick={handleClick}
        dangerouslySetInnerHTML={{ __html: safeHtml }}
      />
      <AdventureImageZoom getRoot={previewRoot} />
    </div>
  )
}

export default AdventurePreviewBox
