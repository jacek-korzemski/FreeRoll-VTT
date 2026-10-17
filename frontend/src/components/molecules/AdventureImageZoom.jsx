import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { t } from '../../lang'

function clipRect(img, root) {
  const rect = img.getBoundingClientRect()
  const clip = root.closest('.swysiwyg-editor-container, .adventure-box-body') || root
  const bounds = clip.getBoundingClientRect()
  const top = Math.max(rect.top, bounds.top)
  const left = Math.max(rect.left, bounds.left)
  const right = Math.min(rect.right, bounds.right)
  const bottom = Math.min(rect.bottom, bounds.bottom)
  if (right - left < 28 || bottom - top < 28) return null
  return { top, left, right, bottom }
}

function AdventureImageZoom({ getRoot }) {
  const [img, setImg] = useState(null)
  const [spot, setSpot] = useState(null)
  const [open, setOpen] = useState(null)

  useEffect(() => {
    const root = getRoot()
    if (!root) return undefined

    const show = (next) => {
      setImg((prev) => (prev === next ? prev : next))
    }

    const onOver = (event) => {
      const next = event.target.closest?.('img.adv-ref')
      if (!next || !root.contains(next)) return
      show(next)
    }

    const onOut = (event) => {
      if (event.relatedTarget?.closest?.('.adventure-zoom')) return
      const next = event.relatedTarget?.closest?.('img.adv-ref')
      if (next && root.contains(next)) return
      const leaving = event.target.closest?.('img.adv-ref')
      if (!leaving) return
      show(null)
    }

    root.addEventListener('mouseover', onOver)
    root.addEventListener('mouseout', onOut)
    return () => {
      root.removeEventListener('mouseover', onOver)
      root.removeEventListener('mouseout', onOut)
    }
  }, [getRoot])

  useEffect(() => {
    if (!img) {
      setSpot(null)
      return undefined
    }
    const place = () => {
      const root = getRoot()
      if (!img.isConnected || !root) {
        setImg(null)
        return
      }
      setSpot(clipRect(img, root))
    }
    place()
    window.addEventListener('scroll', place, true)
    window.addEventListener('resize', place)
    return () => {
      window.removeEventListener('scroll', place, true)
      window.removeEventListener('resize', place)
    }
  }, [img, getRoot])

  useEffect(() => {
    if (!open) return undefined
    const onKey = (event) => {
      if (event.key === 'Escape') setOpen(null)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  const button = spot && !open ? createPortal(
    <button
      type="button"
      className="adventure-zoom"
      style={{
        top: Math.min(spot.top + 6, spot.bottom - 32),
        left: Math.max(spot.left + 4, spot.right - 34),
      }}
      title={t('adventure.zoom')}
      onMouseDown={(event) => event.preventDefault()}
      onMouseLeave={(event) => {
        if (event.relatedTarget === img) return
        setImg(null)
      }}
      onClick={() => setOpen({ src: img.currentSrc || img.src, alt: img.alt || '' })}
    >
      <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
        <path fill="currentColor" d="M2 6V2h4v1.5H3.5V6H2zm8-4h4v4h-1.5V3.5H10V2zM13.5 10H15v4h-4v-1.5h2.5V10zM2 10h1.5v2.5H6V14H2v-4z" />
      </svg>
    </button>,
    document.body,
  ) : null

  const lightbox = open ? createPortal(
    <div className="adventure-lightbox" onClick={() => setOpen(null)} role="presentation">
      <img src={open.src} alt={open.alt} />
    </div>,
    document.body,
  ) : null

  return (
    <>
      {button}
      {lightbox}
    </>
  )
}

export default AdventureImageZoom
