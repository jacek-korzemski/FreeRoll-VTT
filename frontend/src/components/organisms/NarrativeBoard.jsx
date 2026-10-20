import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { t } from '../../lang'

function NarrativeCard({
  card,
  imageSrc,
  isGameMaster,
  canMoveUp,
  canMoveDown,
  onUpdate,
  onRemove,
  onMove,
}) {
  const [title, setTitle] = useState(card.title || '')
  const [body, setBody] = useState(card.body || '')
  const [focused, setFocused] = useState(false)
  const imageRef = useRef(null)
  const [natural, setNatural] = useState({ w: 0, h: 0, src: '' })
  const [lightbox, setLightbox] = useState(false)
  const zoomable = natural.src === imageSrc && (natural.w > 320 || natural.h > 320)

  const rememberSize = (node) => {
    if (!node || !imageSrc) return
    setNatural({
      w: node.naturalWidth || 0,
      h: node.naturalHeight || 0,
      src: imageSrc,
    })
  }

  useEffect(() => {
    if (!focused) {
      setTitle(card.title || '')
      setBody(card.body || '')
    }
  }, [card.title, card.body, focused])

  useEffect(() => {
    setLightbox(false)
    const node = imageRef.current
    if (node?.complete) rememberSize(node)
  }, [imageSrc])

  useEffect(() => {
    if (!lightbox) return undefined
    const onKey = (event) => {
      if (event.key === 'Escape') setLightbox(false)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [lightbox])

  const commit = () => {
    setFocused(false)
    if (title !== (card.title || '') || body !== (card.body || '')) {
      onUpdate(card.id, { title, body })
    }
  }

  return (
    <article
      className={`narrative-card ${card.visible ? '' : 'is-hidden'}`}
      data-id={card.id}
    >
      {imageSrc ? (
        <img
          ref={imageRef}
          className={`narrative-card-image${zoomable ? ' is-zoomable' : ''}`}
          src={imageSrc}
          alt=""
          draggable={false}
          onLoad={(event) => rememberSize(event.currentTarget)}
          onClick={() => {
            if (zoomable) setLightbox(true)
          }}
        />
      ) : (
        <div className="narrative-card-image narrative-card-image-empty" />
      )}
      {lightbox && imageSrc ? createPortal(
        <div className="adventure-lightbox" onClick={() => setLightbox(false)} role="presentation">
          <img src={imageSrc} alt="" />
        </div>,
        document.body,
      ) : null}
      <div className={`narrative-card-copy${isGameMaster ? '' : ' is-read'}`}>
        {isGameMaster ? (
          <>
            <input
              className="narrative-title"
              type="text"
              maxLength={120}
              value={title}
              placeholder={t('scenes.cardTitle')}
              onFocus={() => setFocused(true)}
              onChange={(event) => setTitle(event.target.value)}
              onBlur={commit}
            />
            <textarea
              className="narrative-body"
              rows={6}
              maxLength={4000}
              value={body}
              placeholder={t('scenes.cardBody')}
              onFocus={() => setFocused(true)}
              onChange={(event) => setBody(event.target.value)}
              onBlur={commit}
            />
            <div className="narrative-card-actions">
              <button type="button" disabled={!canMoveUp} onClick={() => onMove(card.id, -1)}>
                {t('scenes.moveUp')}
              </button>
              <button type="button" disabled={!canMoveDown} onClick={() => onMove(card.id, 1)}>
                {t('scenes.moveDown')}
              </button>
              <button
                type="button"
                onClick={() => onUpdate(card.id, { visible: !card.visible })}
              >
                {card.visible ? t('scenes.hideCard') : t('scenes.showCard')}
              </button>
              <button type="button" className="narrative-remove" onClick={() => onRemove(card.id)}>
                {t('scenes.remove')}
              </button>
            </div>
          </>
        ) : (
          <>
            <h3 className="narrative-title narrative-title-read">{card.title}</h3>
            <p className="narrative-body-read">{card.body}</p>
          </>
        )}
      </div>
    </article>
  )
}

function NarrativeBoard({
  cards,
  background,
  isGameMaster,
  selectedAsset,
  selectedType,
  basePath,
  onAdd,
  onUpdate,
  onRemove,
  onReorder,
}) {
  const [hideAll, setHideAll] = useState(false)
  const listRef = useRef(null)
  const positionsRef = useRef(null)
  const canAddSelection = !!(
    selectedAsset?.src
    && (selectedType === 'token' || selectedType === 'map')
  )
  const backgroundStyle = background?.src
    ? { backgroundImage: `url(${basePath}${background.src})` }
    : undefined

  useLayoutEffect(() => {
    const first = positionsRef.current
    positionsRef.current = null
    if (!first || !listRef.current) return undefined
    const nodes = listRef.current.querySelectorAll('.narrative-card')
    nodes.forEach((node) => {
      const prev = first.get(node.dataset.id)
      if (!prev) return
      const dy = prev.top - node.getBoundingClientRect().top
      if (!dy) return
      node.style.transition = 'none'
      node.style.transform = `translateY(${dy}px)`
      node.getBoundingClientRect()
      node.style.transition = 'transform 220ms ease'
      node.style.transform = ''
    })
    return undefined
  }, [cards])

  const moveCard = (cardId, direction) => {
    const index = cards.findIndex((card) => card.id === cardId)
    const next = index + direction
    if (index < 0 || next < 0 || next >= cards.length) return
    if (listRef.current) {
      const first = new Map()
      listRef.current.querySelectorAll('.narrative-card').forEach((node) => {
        first.set(node.dataset.id, node.getBoundingClientRect())
      })
      positionsRef.current = first
    }
    const order = cards.slice()
    const [moved] = order.splice(index, 1)
    order.splice(next, 0, moved)
    onReorder(order)
  }

  return (
    <div className="narrative-board">
      <div className="narrative-scene-bg" style={backgroundStyle} />
      <div className="narrative-scroll">
        <div className="narrative-toolbar">
          <button type="button" onClick={() => setHideAll((prev) => !prev)}>
            {hideAll ? t('scenes.showCards') : t('scenes.hideAll')}
          </button>
          {isGameMaster && (
            <button
              type="button"
              disabled={!canAddSelection}
              onClick={() => onAdd({
                src: selectedAsset.src,
                title: selectedAsset.name || '',
              })}
            >
              {t('scenes.addCard')}
            </button>
          )}
          {isGameMaster && !canAddSelection && (
            <span className="board-hint">{t('scenes.addCardHint')}</span>
          )}
        </div>
        {hideAll ? null : (
          <>
            {cards.length === 0 && <p className="board-hint">{t('scenes.noCards')}</p>}
            <div className="narrative-list" ref={listRef}>
              {cards.map((card, index) => (
                <NarrativeCard
                  key={card.id}
                  card={card}
                  imageSrc={card.src ? `${basePath}${card.src}` : ''}
                  isGameMaster={isGameMaster}
                  canMoveUp={index > 0}
                  canMoveDown={index < cards.length - 1}
                  onUpdate={onUpdate}
                  onRemove={onRemove}
                  onMove={moveCard}
                />
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  )
}

export default NarrativeBoard
