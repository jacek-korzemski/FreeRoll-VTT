import { useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { currentLanguage, t } from '../../lang'
import { getTableItem, setTableItem } from '../../utils/tableStorage'
import { getImageSrc } from '../../utils/sidebarHelpers'

const VIEW_KEY = 'vtt_handed_cards_view'
const VIEWS = ['cards', 'table', 'images']

function cardTitle(card) {
  const title = (card.title || '').trim()
  return title || t('handedCards.unnamed')
}

function readRect(node) {
  const rect = node.getBoundingClientRect()
  if (rect.width < 8 || rect.height < 8) {
    const width = 200
    const height = 140
    return {
      top: Math.max(16, window.innerHeight - height - 72),
      left: Math.max(16, (window.innerWidth - width) / 2),
      width,
      height,
    }
  }
  return {
    top: rect.top,
    left: rect.left,
    width: rect.width,
    height: rect.height,
  }
}

function stageDest() {
  const margin = 28
  const width = Math.min(920, window.innerWidth - margin * 2)
  const height = Math.min(Math.round(window.innerHeight * 0.86), window.innerHeight - margin * 2)
  return {
    top: Math.max(12, (window.innerHeight - height) / 2),
    left: Math.max(12, (window.innerWidth - width) / 2),
    width,
    height,
  }
}

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function HandedCardStage({ card, imageSrc, origin, source, onClose }) {
  const reduced = prefersReducedMotion()
  const [present, setPresent] = useState(reduced)
  const [revealed, setRevealed] = useState(reduced)
  const [closing, setClosing] = useState(false)
  const [box, setBox] = useState(() => (reduced ? stageDest() : origin))
  const cardRef = useRef(null)
  const closeRef = useRef(null)
  const finished = useRef(false)
  const onCloseRef = useRef(onClose)
  onCloseRef.current = onClose

  const finish = () => {
    if (finished.current) return
    finished.current = true
    onCloseRef.current()
  }

  useEffect(() => {
    const node = source
    node?.classList.add('is-source-hidden')
    return () => node?.classList.remove('is-source-hidden')
  }, [source])

  useEffect(() => {
    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = previous
    }
  }, [])

  useEffect(() => {
    if (reduced) {
      closeRef.current?.focus()
      return undefined
    }
    const fadeTimer = window.setTimeout(() => {
      setPresent(true)
      setBox(stageDest())
    }, 90)
    return () => window.clearTimeout(fadeTimer)
  }, [reduced])

  useEffect(() => {
    if (!present || revealed || closing) return undefined
    const timer = window.setTimeout(() => setRevealed(true), 290)
    return () => window.clearTimeout(timer)
  }, [present, revealed, closing])

  useEffect(() => {
    if (!revealed) return undefined
    closeRef.current?.focus()
    return undefined
  }, [revealed])

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'Escape') beginClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  })

  useEffect(() => {
    if (!closing) return undefined
    const returnTimer = window.setTimeout(() => {
      setPresent(false)
      setBox(origin)
    }, 160)
    const fallback = window.setTimeout(finish, 520)
    return () => {
      window.clearTimeout(returnTimer)
      window.clearTimeout(fallback)
    }
  }, [closing, origin])

  const beginClose = () => {
    if (finished.current || closing) return
    if (reduced) {
      finish()
      return
    }
    setRevealed(false)
    setClosing(true)
  }

  const onTransitionEnd = (event) => {
    if (event.target !== cardRef.current || event.propertyName !== 'top') return
    if (closing) {
      finish()
      return
    }
    if (present) setRevealed(true)
  }

  const title = cardTitle(card)
  const boxStyle = {
    top: `${box.top}px`,
    left: `${box.left}px`,
    width: `${box.width}px`,
    height: `${box.height}px`,
  }

  return createPortal(
    <div
      className={`handed-stage${present ? ' is-open' : ''}`}
      onClick={beginClose}
      role="presentation"
    >
      <article
        ref={cardRef}
        className={`handed-stage-card${present || closing ? ' is-present' : ''}${revealed ? ' is-revealed' : ''}${imageSrc ? ' has-image' : ''}`}
        style={boxStyle}
        role="dialog"
        aria-modal="true"
        aria-labelledby="handed-stage-title"
        onClick={(event) => event.stopPropagation()}
        onTransitionEnd={onTransitionEnd}
      >
        <div className="handed-stage-sheet">
          {imageSrc ? (
            <div className="handed-stage-figure">
              <img src={imageSrc} alt="" />
            </div>
          ) : null}
          <div className="handed-stage-copy">
            <p className="handed-stage-kicker">#{card.id}</p>
            <h2 id="handed-stage-title">{title}</h2>
            <div className="handed-stage-body">{card.body || ''}</div>
          </div>
        </div>
        <button
          ref={closeRef}
          type="button"
          className="handed-stage-close"
          onClick={beginClose}
          aria-label={t('handedCards.closePreview')}
        >
          ×
        </button>
      </article>
    </div>,
    document.body,
  )
}

function HandedCardsPanel({ cards = [], basePath = '' }) {
  const [view, setView] = useState(() => {
    const saved = getTableItem(VIEW_KEY)
    return VIEWS.includes(saved) ? saved : 'cards'
  })
  const [sort, setSort] = useState({ key: 'id', dir: 'asc' })
  const [stage, setStage] = useState(null)
  const [imageSrc, setImageSrc] = useState('')

  useEffect(() => {
    setTableItem(VIEW_KEY, view)
  }, [view])

  useEffect(() => {
    if (!imageSrc) return undefined
    const onKey = (event) => {
      if (event.key === 'Escape') setImageSrc('')
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [imageSrc])

  const sorted = useMemo(() => {
    const list = cards.slice()
    const dir = sort.dir === 'asc' ? 1 : -1
    list.sort((a, b) => {
      if (sort.key === 'id') return (Number(a.id) - Number(b.id)) * dir
      const av = sort.key === 'title' ? (a.title || '') : (a.body || '')
      const bv = sort.key === 'title' ? (b.title || '') : (b.body || '')
      return av.localeCompare(bv, currentLanguage, { sensitivity: 'base' }) * dir
    })
    return list
  }, [cards, sort])

  const openStage = (card, node) => {
    setImageSrc('')
    setStage({ card, origin: readRect(node), source: node })
  }

  const toggleSort = (key) => {
    setSort((prev) => (
      prev.key === key
        ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' }
        : { key, dir: 'asc' }
    ))
  }

  const sortLabel = (key) => {
    if (sort.key !== key) return ''
    return sort.dir === 'asc' ? ' ↑' : ' ↓'
  }

  return (
    <section className="handed-panel">
      <header className="handed-header">
        <div className="handed-header-title">
          <h2>{t('handedCards.title')}</h2>
          {cards.length > 0 ? <span className="handed-count">{cards.length}</span> : null}
        </div>
        {cards.length > 0 ? (
          <div className="handed-views" role="group" aria-label={t('handedCards.title')}>
            {VIEWS.map((id) => (
              <button
                key={id}
                type="button"
                aria-pressed={view === id}
                onClick={() => setView(id)}
              >
                {t(`handedCards.view${id.charAt(0).toUpperCase()}${id.slice(1)}`)}
              </button>
            ))}
          </div>
        ) : null}
      </header>

      {cards.length === 0 ? (
        <p className="handed-empty">{t('handedCards.empty')}</p>
      ) : (
        <div className="handed-scroll">
          {view === 'cards' ? (
            <div className="handed-grid">
              {sorted.map((card) => {
                const src = card.src ? getImageSrc(card.src, basePath) : ''
                return (
                  <button
                    key={card.id}
                    type="button"
                    className="handed-face"
                    onClick={(event) => openStage(card, event.currentTarget)}
                  >
                    <span className="handed-number">#{card.id}</span>
                    <span className="handed-face-art">
                      {src ? <img src={src} alt="" draggable={false} /> : <span>{t('handedCards.noImage')}</span>}
                    </span>
                    <strong>{cardTitle(card)}</strong>
                    <p>{card.body || ''}</p>
                  </button>
                )
              })}
            </div>
          ) : null}

          {view === 'table' ? (
            <div className="handed-table-wrap">
              <table className="handed-table">
                <thead>
                  <tr>
                    <th aria-sort={sort.key === 'id' ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                      <button type="button" onClick={() => toggleSort('id')}>
                        {t('handedCards.colNumber')}{sortLabel('id')}
                      </button>
                    </th>
                    <th aria-sort={sort.key === 'title' ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                      <button type="button" onClick={() => toggleSort('title')}>
                        {t('handedCards.colName')}{sortLabel('title')}
                      </button>
                    </th>
                    <th aria-sort={sort.key === 'body' ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                      <button type="button" onClick={() => toggleSort('body')}>
                        {t('handedCards.colDescription')}{sortLabel('body')}
                      </button>
                    </th>
                    <th>{t('handedCards.colImage')}</th>
                  </tr>
                </thead>
                <tbody>
                  {sorted.map((card) => {
                    const src = card.src ? getImageSrc(card.src, basePath) : ''
                    return (
                      <tr key={card.id}>
                        <td className="handed-col-id">{card.id}</td>
                        <td>
                          <button
                            type="button"
                            className="handed-name"
                            onClick={(event) => openStage(card, event.currentTarget.closest('tr'))}
                          >
                            {cardTitle(card)}
                          </button>
                        </td>
                        <td>
                          <p className="handed-desc">{card.body || '—'}</p>
                        </td>
                        <td>
                          <button
                            type="button"
                            className="handed-thumb-btn"
                            disabled={!src}
                            aria-label={t('handedCards.showImage')}
                            onClick={() => {
                              if (src) setImageSrc(src)
                            }}
                          >
                            {src ? <img src={src} alt="" draggable={false} /> : <span />}
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : null}

          {view === 'images' ? (
            <div className="handed-art-grid">
              {sorted.map((card) => {
                const src = card.src ? getImageSrc(card.src, basePath) : ''
                const title = cardTitle(card)
                return (
                  <button
                    key={card.id}
                    type="button"
                    className="handed-art"
                    aria-label={title}
                    onClick={(event) => openStage(card, event.currentTarget)}
                  >
                    <span className="handed-number">#{card.id}</span>
                    {src ? <img src={src} alt="" draggable={false} /> : <span className="handed-art-fallback">{title}</span>}
                  </button>
                )
              })}
            </div>
          ) : null}
        </div>
      )}

      {stage ? (
        <HandedCardStage
          key={stage.card.id}
          card={stage.card}
          imageSrc={stage.card.src ? getImageSrc(stage.card.src, basePath) : ''}
          origin={stage.origin}
          source={stage.source}
          onClose={() => setStage(null)}
        />
      ) : null}

      {imageSrc ? createPortal(
        <div className="handed-image-lightbox" onClick={() => setImageSrc('')} role="presentation">
          <img src={imageSrc} alt="" />
        </div>,
        document.body,
      ) : null}
    </section>
  )
}

export default HandedCardsPanel
