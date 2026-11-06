import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { API_BASE } from '../../../config'
import { t } from '../../lang'
import { getImageSrc } from '../../utils/sidebarHelpers'

function excerpt(body) {
  const text = (body || '').replace(/\s+/g, ' ').trim()
  if (text.length <= 140) return text
  return `${text.slice(0, 137)}…`
}

function NarrativeCardLibraryModal({ basePath, onClose, onAdd }) {
  const [cards, setCards] = useState([])
  const [selectedId, setSelectedId] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)

  useEffect(() => {
    let cancelled = false
    fetch(`${API_BASE}?action=card-library-list`, { credentials: 'include' })
      .then((res) => res.json())
      .then((data) => {
        if (cancelled) return
        if (!data.success) {
          setError(true)
          return
        }
        setCards(Array.isArray(data.cards) ? data.cards : [])
      })
      .catch(() => {
        if (!cancelled) setError(true)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const selected = cards.find((card) => card.id === selectedId) || null

  const removeCard = (id) => {
    setCards((prev) => prev.filter((card) => card.id !== id))
    setSelectedId((prev) => (prev === id ? null : prev))
    fetch(`${API_BASE}?action=card-library-delete`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    }).catch(() => {})
  }

  return createPortal(
    <div className="note-template-modal" onClick={onClose} role="presentation">
      <div
        className="note-template-modal-content card-library-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="card-library-title"
        onClick={(event) => event.stopPropagation()}
      >
        <h3 id="card-library-title">{t('scenes.libraryTitle')}</h3>
        {loading ? <p className="note-template-loading">{t('app.loading')}</p> : null}
        {!loading && error ? <p className="note-template-error">{t('scenes.libraryError')}</p> : null}
        {!loading && !error && cards.length === 0 ? (
          <p className="note-template-empty">{t('scenes.libraryEmpty')}</p>
        ) : null}
        {!loading && !error && cards.length > 0 ? (
          <div className="card-library-grid">
            {cards.map((card) => {
              const title = (card.title || '').trim() || t('scenes.unnamed')
              const selectedTile = card.id === selectedId
              return (
                <div
                  key={card.id}
                  className={`card-library-tile${selectedTile ? ' is-selected' : ''}`}
                >
                  <button
                    type="button"
                    className="card-library-pick"
                    aria-pressed={selectedTile}
                    onClick={() => setSelectedId(card.id)}
                  >
                    {card.src ? (
                      <img src={getImageSrc(card.src, basePath)} alt="" draggable={false} />
                    ) : (
                      <div className="card-library-image-empty" />
                    )}
                    <strong>{title}</strong>
                    <p>{excerpt(card.body)}</p>
                  </button>
                  <button
                    type="button"
                    className="card-library-delete"
                    onClick={() => removeCard(card.id)}
                  >
                    {t('scenes.libraryDelete')}
                  </button>
                </div>
              )
            })}
          </div>
        ) : null}
        <div className="note-template-modal-footer board-modal-footer">
          <button type="button" className="note-template-cancel" onClick={onClose}>
            {t('scenes.cancel')}
          </button>
          <button
            type="button"
            className="board-modal-primary"
            disabled={!selected}
            onClick={() => {
              if (!selected) return
              onAdd({
                src: selected.src || '',
                title: selected.title || '',
                body: selected.body || '',
              })
              onClose()
            }}
          >
            {t('scenes.libraryAdd')}
          </button>
        </div>
      </div>
    </div>,
    document.body,
  )
}

export default NarrativeCardLibraryModal
