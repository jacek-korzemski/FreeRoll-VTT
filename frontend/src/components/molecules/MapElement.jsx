import { useCallback, useRef } from 'react'
import { getImageSrc } from '../../utils/sidebarHelpers'
import MapElementMenu from './MapElementMenu'

function clampScale(value) {
  const clamped = Math.min(3, Math.max(0.2, value))
  return Math.round(clamped * 10000) / 10000
}

function normalizeRotation(value) {
  const turns = ((value % 360) + 360) % 360
  return Math.round(turns * 10000) / 10000
}

function MapElement({
  element,
  cellSize,
  basePath,
  isEraserActive,
  isMoveToolActive,
  isRelocating = false,
  menuOpen = false,
  onElementClick,
  onUpdate,
  onRemove,
  onBeginMove,
}) {
  const centerX = element.x * cellSize + cellSize / 2
  const centerY = element.y * cellSize + cellSize / 2
  const interactive = isEraserActive || isMoveToolActive
  const scale = Number(element.scale ?? 1) || 1
  const rotation = Number(element.rotation ?? 0) || 0
  const scaleRef = useRef(scale)
  const rotationRef = useRef(rotation)
  scaleRef.current = scale
  rotationRef.current = rotation

  const handleClick = useCallback((e) => {
    if (!interactive) return
    if (e.target.closest('.map-radial-btn')) return
    e.preventDefault()
    e.stopPropagation()
    onElementClick?.(element, e)
  }, [interactive, onElementClick, element])

  const handleDoubleClick = useCallback((e) => {
    if (!isMoveToolActive || isEraserActive) return
    if (e.target.closest('.map-radial-btn:not(.map-radial-btn--center)')) return
    e.preventDefault()
    e.stopPropagation()
    onBeginMove?.(element)
  }, [isMoveToolActive, isEraserActive, onBeginMove, element])

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
  }, [element, onUpdate, onRemove, onBeginMove])

  const classes = [
    'map-element',
    isEraserActive && 'erasable',
    isMoveToolActive && 'movable',
    menuOpen && 'menu-open',
    isRelocating && 'relocating',
  ].filter(Boolean).join(' ')

  return (
    <div
      className={classes}
      style={{
        left: centerX,
        top: centerY
      }}
      onClick={handleClick}
      onDoubleClick={handleDoubleClick}
    >
      <img
        src={getImageSrc(element.src, basePath)}
        alt=""
        draggable={false}
        style={scale !== 1 || rotation !== 0 ? { transform: `rotate(${rotation}deg) scale(${scale})` } : undefined}
      />
      {menuOpen && <MapElementMenu onAction={handleAction} />}
    </div>
  )
}

export default MapElement
