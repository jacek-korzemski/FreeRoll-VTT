import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { getImageSrc } from '../../utils/sidebarHelpers'
import MapElementMenu from '../molecules/MapElementMenu'

const PIECE_SIZE = 64

function clampScale(value) {
  const clamped = Math.min(3, Math.max(0.2, value))
  return Math.round(clamped * 10000) / 10000
}

function normalizeRotation(value) {
  const turns = ((value % 360) + 360) % 360
  return Math.round(turns * 10000) / 10000
}

function clientToImage(viewport, clientX, clientY, zoom, offset) {
  const rect = viewport.getBoundingClientRect()
  const scale = zoom || 1
  return {
    x: Math.round((clientX - rect.left + viewport.scrollLeft - offset.x) / scale),
    y: Math.round((clientY - rect.top + viewport.scrollTop - offset.y) / scale),
  }
}

function visualBounds(x, y, naturalW, naturalH, scale, rotation) {
  const hw = (naturalW * scale) / 2
  const hh = (naturalH * scale) / 2
  const rad = (rotation * Math.PI) / 180
  const cos = Math.abs(Math.cos(rad))
  const sin = Math.abs(Math.sin(rad))
  const boundW = hw * cos + hh * sin
  const boundH = hw * sin + hh * cos
  const cx = x + naturalW / 2
  const cy = y + naturalH / 2
  return {
    x: cx - boundW,
    y: cy - boundH,
    width: boundW * 2,
    height: boundH * 2,
  }
}

function TravelMapPiece({
  element,
  imageSrc,
  left,
  top,
  width,
  height,
  isEraserActive,
  isMoveToolActive,
  menuOpen,
  isRelocating,
  onToggleMenu,
  onUpdate,
  onRemove,
  onBeginMove,
  onSize,
}) {
  const scale = Number(element.scale ?? 1) || 1
  const rotation = Number(element.rotation ?? 0) || 0
  const scaleRef = useRef(scale)
  const rotationRef = useRef(rotation)
  scaleRef.current = scale
  rotationRef.current = rotation
  const transform = scale !== 1 || rotation !== 0
    ? `rotate(${rotation}deg) scale(${scale})`
    : undefined

  const handleAction = useCallback((action) => {
    if (action === 'grow' || action === 'shrink') {
      const next = clampScale(scaleRef.current * (action === 'grow' ? 1.2 : 0.8))
      scaleRef.current = next
      onUpdate?.(element.id, { scale: next })
      return
    }
    if (action === 'rotateCw' || action === 'rotateCcw') {
      const next = normalizeRotation(rotationRef.current + (action === 'rotateCw' ? 45 : -45))
      rotationRef.current = next
      onUpdate?.(element.id, { rotation: next })
      return
    }
    if (action === 'delete') {
      onRemove?.(element.id)
      return
    }
    if (action === 'move') {
      onBeginMove?.(element)
    }
  }, [element, onBeginMove, onRemove, onUpdate])

  if (!width || !height) {
    return (
      <img
        src={imageSrc}
        alt=""
        draggable={false}
        style={{ position: 'absolute', width: 0, height: 0, opacity: 0 }}
        onLoad={(event) => onSize?.(element.src, event.currentTarget.naturalWidth, event.currentTarget.naturalHeight)}
      />
    )
  }

  const classes = [
    'travel-map-piece',
    isEraserActive && 'erasable',
    isMoveToolActive && 'movable',
    menuOpen && 'menu-open',
    isRelocating && 'relocating',
  ].filter(Boolean).join(' ')

  return (
    <div
      className={classes}
      data-id={element.id}
      data-kind="map"
      style={{ left, top, width, height }}
      onClick={(event) => {
        if (!isMoveToolActive || isEraserActive) return
        if (event.target.closest('.map-radial')) return
        event.stopPropagation()
        onToggleMenu?.(element.id)
      }}
      onDoubleClick={(event) => {
        if (!isMoveToolActive || isEraserActive) return
        if (event.target.closest('.map-radial-btn:not(.map-radial-btn--center)')) return
        event.preventDefault()
        event.stopPropagation()
        onBeginMove?.(element)
      }}
    >
      <img
        src={imageSrc}
        alt=""
        draggable={false}
        style={transform ? { transform } : undefined}
        onLoad={(event) => onSize?.(element.src, event.currentTarget.naturalWidth, event.currentTarget.naturalHeight)}
      />
      {menuOpen && <MapElementMenu onAction={handleAction} />}
    </div>
  )
}

function TravelBoard({
  background,
  mapElements,
  tokens,
  selectedAsset,
  selectedType,
  isEraserActive,
  isTokenEraserActive,
  isMoveToolActive = false,
  relocatingMapElement = null,
  onPlace,
  onTokenMove,
  onUpdateMapElement,
  onBeginMapElementMove,
  onRemoveMapElement,
  onRemoveToken,
  onDeselectPlacement,
  basePath,
  zoomLevel,
}) {
  const viewportRef = useRef(null)
  const dragRef = useRef(null)
  const sizesRef = useRef({})
  const [natural, setNatural] = useState({ w: 0, h: 0 })
  const [sizes, setSizes] = useState({})
  const [dragPos, setDragPos] = useState(null)
  const [viewportSize, setViewportSize] = useState({ w: 0, h: 0 })
  const [openMenuId, setOpenMenuId] = useState(null)
  const [pointer, setPointer] = useState(null)

  const rememberSize = useCallback((src, width, height) => {
    if (!src || width <= 0 || height <= 0) return
    const current = sizesRef.current[src]
    if (current?.width === width && current?.height === height) return
    const next = { ...sizesRef.current, [src]: { width, height } }
    sizesRef.current = next
    setSizes(next)
  }, [])

  useEffect(() => {
    setNatural({
      w: background?.width || 0,
      h: background?.height || 0,
    })
  }, [background?.src, background?.width, background?.height])

  useEffect(() => {
    const node = viewportRef.current
    if (!node) return undefined
    const measure = () => {
      setViewportSize({ w: node.clientWidth, h: node.clientHeight })
    }
    measure()
    const observer = new ResizeObserver(measure)
    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    if (!isMoveToolActive) setOpenMenuId(null)
  }, [isMoveToolActive])

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'Escape') setOpenMenuId(null)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const ghostAsset = relocatingMapElement
    ? relocatingMapElement
    : (!isMoveToolActive && !isEraserActive && selectedType === 'map' ? selectedAsset : null)

  useEffect(() => {
    const sources = new Set()
    mapElements.forEach((element) => {
      if (element?.src) sources.add(element.src)
    })
    if (ghostAsset?.src) sources.add(ghostAsset.src)
    sources.forEach((src) => {
      if (sizesRef.current[src]) return
      const img = new Image()
      img.onload = () => rememberSize(src, img.naturalWidth, img.naturalHeight)
      img.src = getImageSrc(src, basePath)
    })
  }, [basePath, ghostAsset, mapElements, rememberSize])

  const imgW = natural.w || background?.width || 0
  const imgH = natural.h || background?.height || 0
  const zoom = zoomLevel || 1

  const layout = useMemo(() => {
    const scaledW = imgW * zoom
    const scaledH = imgH * zoom
    const baseW = viewportSize.w > 0 ? Math.max(scaledW, viewportSize.w) : scaledW
    const baseH = viewportSize.h > 0 ? Math.max(scaledH, viewportSize.h) : scaledH
    const offsetX = Math.max(0, (baseW - scaledW) / 2)
    const offsetY = Math.max(0, (baseH - scaledH) / 2)
    let worldW = baseW
    let worldH = baseH
    const extend = (x, y, width, height) => {
      worldW = Math.max(worldW, offsetX + (x + width) * zoom)
      worldH = Math.max(worldH, offsetY + (y + height) * zoom)
    }
    tokens.forEach((token) => {
      const live = dragPos && dragPos.id === token.id
      extend(
        live ? dragPos.x : (token.x || 0),
        live ? dragPos.y : (token.y || 0),
        PIECE_SIZE,
        PIECE_SIZE
      )
    })
    mapElements.forEach((element) => {
      const size = sizes[element.src]
      if (!size?.width || !size?.height) {
        extend(element.x || 0, element.y || 0, 0, 0)
        return
      }
      const bounds = visualBounds(
        element.x || 0,
        element.y || 0,
        size.width,
        size.height,
        Number(element.scale ?? 1) || 1,
        Number(element.rotation ?? 0) || 0
      )
      extend(bounds.x, bounds.y, bounds.width, bounds.height)
    })
    return { worldW, worldH, offsetX, offsetY }
  }, [dragPos, imgH, imgW, mapElements, sizes, tokens, viewportSize.h, viewportSize.w, zoom])

  const offset = useMemo(() => ({ x: layout.offsetX, y: layout.offsetY }), [layout.offsetX, layout.offsetY])

  const topLeftFor = useCallback((type, src, centerX, centerY) => {
    if (type !== 'map') {
      return {
        x: Math.max(0, Math.round(centerX)),
        y: Math.max(0, Math.round(centerY)),
      }
    }
    const size = sizesRef.current[src] || { width: 0, height: 0 }
    return {
      x: Math.max(0, Math.round(centerX - (size.width || 0) / 2)),
      y: Math.max(0, Math.round(centerY - (size.height || 0) / 2)),
    }
  }, [])

  const canPlace = !isMoveToolActive && !isEraserActive && !isTokenEraserActive
    && !!(selectedAsset && (selectedType === 'token' || selectedType === 'map'))

  const endDrag = useCallback((clientX, clientY) => {
    const drag = dragRef.current
    dragRef.current = null
    setDragPos(null)
    if (!drag || !viewportRef.current) return
    const imagePos = clientToImage(viewportRef.current, clientX, clientY, zoom, offset)
    if (drag.kind === 'pan') {
      if (!drag.moved && canPlace) {
        const point = topLeftFor(selectedType, selectedAsset.src, imagePos.x, imagePos.y)
        onPlace(selectedAsset, selectedType, point.x, point.y)
      }
      return
    }
    const x = Math.max(0, Math.round(imagePos.x - drag.dx))
    const y = Math.max(0, Math.round(imagePos.y - drag.dy))
    if (drag.kind === 'token') onTokenMove(drag.id, x, y)
  }, [canPlace, offset, onPlace, onTokenMove, selectedAsset, selectedType, topLeftFor, zoom])

  useEffect(() => {
    const onMove = (event) => {
      const drag = dragRef.current
      if (!drag || !viewportRef.current) return
      if (drag.kind === 'pan') {
        const dx = event.clientX - drag.x
        const dy = event.clientY - drag.y
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) drag.moved = true
        viewportRef.current.scrollLeft = drag.scrollLeft - dx
        viewportRef.current.scrollTop = drag.scrollTop - dy
        return
      }
      const imagePos = clientToImage(viewportRef.current, event.clientX, event.clientY, zoom, offset)
      setDragPos({
        id: drag.id,
        x: Math.max(0, Math.round(imagePos.x - drag.dx)),
        y: Math.max(0, Math.round(imagePos.y - drag.dy)),
      })
    }
    const onUp = (event) => {
      if (!dragRef.current) return
      endDrag(event.clientX, event.clientY)
    }
    window.addEventListener('pointermove', onMove)
    window.addEventListener('pointerup', onUp)
    return () => {
      window.removeEventListener('pointermove', onMove)
      window.removeEventListener('pointerup', onUp)
    }
  }, [endDrag, offset, zoom])

  const handlePointerDown = useCallback((event) => {
    if (event.button !== 0) return
    if (event.target.closest?.('.map-radial')) return
    const piece = event.target.closest?.('[data-kind]')
    if (piece?.dataset.kind === 'token') {
      event.preventDefault()
      const id = piece.dataset.id
      if (isTokenEraserActive) {
        onRemoveToken(id)
        return
      }
      const token = tokens.find((item) => item.id === id)
      if (!token || !viewportRef.current) return
      const imagePos = clientToImage(viewportRef.current, event.clientX, event.clientY, zoom, offset)
      dragRef.current = {
        kind: 'token',
        id,
        dx: imagePos.x - (token.x || 0),
        dy: imagePos.y - (token.y || 0),
      }
      setDragPos({ id, x: token.x || 0, y: token.y || 0 })
      return
    }
    if (piece?.dataset.kind === 'map') {
      if (isEraserActive) {
        event.preventDefault()
        setOpenMenuId(null)
        onRemoveMapElement(piece.dataset.id)
        return
      }
      if (isMoveToolActive) return
    }
    if (!viewportRef.current) return
    setOpenMenuId(null)
    event.preventDefault()
    dragRef.current = {
      kind: 'pan',
      x: event.clientX,
      y: event.clientY,
      scrollLeft: viewportRef.current.scrollLeft,
      scrollTop: viewportRef.current.scrollTop,
      moved: false,
    }
  }, [isEraserActive, isMoveToolActive, isTokenEraserActive, offset, onRemoveMapElement, onRemoveToken, tokens, zoom])

  const handleContextMenu = useCallback((event) => {
    if (event.target.closest?.('.map-radial')) {
      event.preventDefault()
      return
    }
    const piece = event.target.closest?.('[data-kind]')
    if (!piece) {
      event.preventDefault()
      onDeselectPlacement?.()
      return
    }
    event.preventDefault()
    if (piece.dataset.kind === 'token') onRemoveToken(piece.dataset.id)
    if (piece.dataset.kind === 'map') onRemoveMapElement(piece.dataset.id)
  }, [onDeselectPlacement, onRemoveMapElement, onRemoveToken])

  const handleDrop = useCallback((event) => {
    event.preventDefault()
    if (relocatingMapElement || !viewportRef.current || !onPlace) return
    try {
      const raw = event.dataTransfer.getData('application/json')
      if (!raw) return
      const payload = JSON.parse(raw)
      if (!payload?.id || (payload.type !== 'token' && payload.type !== 'map')) return
      const imagePos = clientToImage(viewportRef.current, event.clientX, event.clientY, zoom, offset)
      const point = topLeftFor(payload.type, payload.src, imagePos.x, imagePos.y)
      onPlace(
        { id: payload.id, src: payload.src, name: payload.name },
        payload.type,
        point.x,
        point.y
      )
    } catch (_) {
      // ignore invalid drop data
    }
  }, [offset, onPlace, relocatingMapElement, topLeftFor, zoom])

  const handlePointerMove = useCallback((event) => {
    if (!ghostAsset) return
    setPointer({ x: event.clientX, y: event.clientY })
  }, [ghostAsset])

  const tokenStyle = (token) => {
    const live = dragPos && dragPos.id === token.id
    const x = live ? dragPos.x : (token.x || 0)
    const y = live ? dragPos.y : (token.y || 0)
    const size = PIECE_SIZE * zoom
    return {
      left: layout.offsetX + x * zoom,
      top: layout.offsetY + y * zoom,
      width: size,
      height: size,
    }
  }

  const ghostSize = ghostAsset?.src ? sizes[ghostAsset.src] : null
  const ghostScale = Number(ghostAsset?.scale ?? 1) || 1
  const ghostRotation = Number(ghostAsset?.rotation ?? 0) || 0
  const ghostTransform = ghostScale !== 1 || ghostRotation !== 0
    ? `rotate(${ghostRotation}deg) scale(${ghostScale})`
    : undefined
  const placing = canPlace || !!relocatingMapElement

  return (
    <div
      ref={viewportRef}
      className={`travel-board${placing ? ' placing' : ''}${isMoveToolActive ? ' move-mode' : ''}`}
      onPointerDown={handlePointerDown}
      onPointerMove={handlePointerMove}
      onDragStart={(event) => event.preventDefault()}
      onContextMenu={handleContextMenu}
      onDragOver={(event) => {
        event.preventDefault()
        event.dataTransfer.dropEffect = 'copy'
      }}
      onDrop={handleDrop}
    >
      <div
        className="travel-world"
        style={{ width: layout.worldW, height: layout.worldH }}
      >
        {background?.src ? (
          <img
            className="travel-map-image"
            src={getImageSrc(background.src, basePath)}
            alt=""
            draggable={false}
            style={{
              left: layout.offsetX,
              top: layout.offsetY,
              width: Math.max(imgW, 1) * zoom,
              height: Math.max(imgH, 1) * zoom,
            }}
            onLoad={(event) => {
              const nextW = event.currentTarget.naturalWidth || 0
              const nextH = event.currentTarget.naturalHeight || 0
              if (nextW && nextH) setNatural({ w: nextW, h: nextH })
            }}
          />
        ) : null}
        {mapElements.map((element) => {
          const size = sizes[element.src]
          const naturalW = size?.width || 0
          const naturalH = size?.height || 0
          return (
            <TravelMapPiece
              key={element.id}
              element={element}
              imageSrc={getImageSrc(element.src, basePath)}
              left={layout.offsetX + (element.x || 0) * zoom}
              top={layout.offsetY + (element.y || 0) * zoom}
              width={naturalW ? naturalW * zoom : 0}
              height={naturalH ? naturalH * zoom : 0}
              isEraserActive={isEraserActive}
              isMoveToolActive={isMoveToolActive}
              menuOpen={isMoveToolActive && openMenuId === element.id}
              isRelocating={relocatingMapElement?.id === element.id}
              onToggleMenu={(id) => setOpenMenuId((prev) => (prev === id ? null : id))}
              onUpdate={onUpdateMapElement}
              onRemove={onRemoveMapElement}
              onBeginMove={onBeginMapElementMove}
              onSize={rememberSize}
            />
          )
        })}
        {tokens.map((token) => (
          <img
            key={token.id}
            className="travel-piece travel-token"
            data-id={token.id}
            data-kind="token"
            src={getImageSrc(token.src, basePath)}
            alt=""
            draggable={false}
            style={tokenStyle(token)}
          />
        ))}
      </div>
      {ghostAsset?.src && pointer && ghostSize?.width && ghostSize?.height ? createPortal(
        <div
          className="travel-placement-ghost"
          style={{
            left: pointer.x,
            top: pointer.y,
            width: ghostSize.width * zoom,
            height: ghostSize.height * zoom,
          }}
        >
          <img
            src={getImageSrc(ghostAsset.src, basePath)}
            alt=""
            draggable={false}
            style={ghostTransform ? { transform: ghostTransform } : undefined}
          />
        </div>,
        document.body,
      ) : null}
    </div>
  )
}

export default TravelBoard
