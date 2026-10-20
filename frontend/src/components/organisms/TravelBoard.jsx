import { useCallback, useEffect, useMemo, useRef, useState } from 'react'

const PIECE_SIZE = 64

function clientToImage(viewport, clientX, clientY, zoom, offset) {
  const rect = viewport.getBoundingClientRect()
  const scale = zoom || 1
  return {
    x: Math.round((clientX - rect.left + viewport.scrollLeft - offset.x) / scale),
    y: Math.round((clientY - rect.top + viewport.scrollTop - offset.y) / scale),
  }
}

function TravelBoard({
  background,
  mapElements,
  tokens,
  selectedAsset,
  selectedType,
  isEraserActive,
  isTokenEraserActive,
  onPlace,
  onTokenMove,
  onMapElementMove,
  onRemoveMapElement,
  onRemoveToken,
  onDeselectPlacement,
  basePath,
  zoomLevel,
}) {
  const viewportRef = useRef(null)
  const dragRef = useRef(null)
  const [natural, setNatural] = useState({ w: 0, h: 0 })
  const [dragPos, setDragPos] = useState(null)
  const [viewportSize, setViewportSize] = useState({ w: 0, h: 0 })

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
      const live = dragPos && dragPos.kind === 'token' && dragPos.id === token.id
      extend(
        live ? dragPos.x : (token.x || 0),
        live ? dragPos.y : (token.y || 0),
        PIECE_SIZE * (token.size || 1),
        PIECE_SIZE * (token.size || 1)
      )
    })
    mapElements.forEach((element) => {
      const live = dragPos && dragPos.kind === 'map' && dragPos.id === element.id
      extend(
        live ? dragPos.x : (element.x || 0),
        live ? dragPos.y : (element.y || 0),
        PIECE_SIZE,
        PIECE_SIZE
      )
    })
    return { worldW, worldH, offsetX, offsetY }
  }, [dragPos, imgH, imgW, mapElements, tokens, viewportSize.h, viewportSize.w, zoom])

  const offset = useMemo(() => ({ x: layout.offsetX, y: layout.offsetY }), [layout.offsetX, layout.offsetY])

  const endDrag = useCallback((clientX, clientY) => {
    const drag = dragRef.current
    dragRef.current = null
    setDragPos(null)
    if (!drag || !viewportRef.current) return
    const imagePos = clientToImage(viewportRef.current, clientX, clientY, zoom, offset)
    if (drag.kind === 'pan') {
      if (!drag.moved && selectedAsset && (selectedType === 'token' || selectedType === 'map')) {
        onPlace(selectedAsset, selectedType, Math.max(0, imagePos.x), Math.max(0, imagePos.y))
      }
      return
    }
    const x = Math.max(0, Math.round(imagePos.x - drag.dx))
    const y = Math.max(0, Math.round(imagePos.y - drag.dy))
    if (drag.kind === 'token') onTokenMove(drag.id, x, y)
    if (drag.kind === 'map') onMapElementMove(drag.id, x, y)
  }, [offset, onMapElementMove, onPlace, onTokenMove, selectedAsset, selectedType, zoom])

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
        kind: drag.kind,
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
    event.preventDefault()
    const piece = event.target.closest?.('.travel-piece')
    if (piece) {
      const id = piece.dataset.id
      const kind = piece.dataset.kind
      if (kind === 'token' && isTokenEraserActive) {
        onRemoveToken(id)
        return
      }
      if (kind === 'map' && isEraserActive) {
        onRemoveMapElement(id)
        return
      }
      const token = kind === 'token' ? tokens.find((item) => item.id === id) : null
      const element = kind === 'map' ? mapElements.find((item) => item.id === id) : null
      const origin = token || element
      if (!origin || !viewportRef.current) return
      const imagePos = clientToImage(viewportRef.current, event.clientX, event.clientY, zoom, offset)
      dragRef.current = {
        kind,
        id,
        dx: imagePos.x - (origin.x || 0),
        dy: imagePos.y - (origin.y || 0),
      }
      setDragPos({ id, kind, x: origin.x || 0, y: origin.y || 0 })
      return
    }
    if (!viewportRef.current) return
    dragRef.current = {
      kind: 'pan',
      x: event.clientX,
      y: event.clientY,
      scrollLeft: viewportRef.current.scrollLeft,
      scrollTop: viewportRef.current.scrollTop,
      moved: false,
    }
  }, [isEraserActive, isTokenEraserActive, mapElements, offset, onRemoveMapElement, onRemoveToken, tokens, zoom])

  const handleContextMenu = useCallback((event) => {
    const piece = event.target.closest?.('.travel-piece')
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
    if (!viewportRef.current || !onPlace) return
    try {
      const raw = event.dataTransfer.getData('application/json')
      if (!raw) return
      const payload = JSON.parse(raw)
      if (!payload?.id || (payload.type !== 'token' && payload.type !== 'map')) return
      const imagePos = clientToImage(viewportRef.current, event.clientX, event.clientY, zoom, offset)
      onPlace(
        { id: payload.id, src: payload.src, name: payload.name },
        payload.type,
        Math.max(0, imagePos.x),
        Math.max(0, imagePos.y)
      )
    } catch (_) {
      // ignore invalid drop data
    }
  }, [offset, onPlace, zoom])

  const pieceStyle = (item, kind) => {
    const live = dragPos && dragPos.id === item.id && dragPos.kind === kind
    const x = live ? dragPos.x : (item.x || 0)
    const y = live ? dragPos.y : (item.y || 0)
    const size = kind === 'token' ? PIECE_SIZE * (item.size || 1) : PIECE_SIZE
    return {
      left: layout.offsetX + x * zoom,
      top: layout.offsetY + y * zoom,
      width: size * zoom,
      height: size * zoom,
    }
  }

  return (
    <div
      ref={viewportRef}
      className={`travel-board ${selectedAsset ? 'placing' : ''}`}
      onPointerDown={handlePointerDown}
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
            src={`${basePath}${background.src}`}
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
        {mapElements.map((element) => (
          <img
            key={element.id}
            className="travel-piece travel-map-element"
            data-id={element.id}
            data-kind="map"
            src={`${basePath}${element.src}`}
            alt=""
            draggable={false}
            style={pieceStyle(element, 'map')}
          />
        ))}
        {tokens.map((token) => (
          <img
            key={token.id}
            className="travel-piece travel-token"
            data-id={token.id}
            data-kind="token"
            src={`${basePath}${token.src}`}
            alt=""
            draggable={false}
            style={pieceStyle(token, 'token')}
          />
        ))}
      </div>
    </div>
  )
}

export default TravelBoard
