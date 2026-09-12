import React, { useCallback } from 'react'

function MapElement({ element, cellSize, basePath, isEraserActive, isMoveToolActive, onElementClick }) {
  const centerX = element.x * cellSize + cellSize / 2
  const centerY = element.y * cellSize + cellSize / 2
  const interactive = isEraserActive || isMoveToolActive

  const handleClick = useCallback((e) => {
    if (!interactive) return
    e.preventDefault()
    e.stopPropagation()
    onElementClick?.(element, e)
  }, [interactive, onElementClick, element])

  return (
    <div
      className={`map-element ${isEraserActive ? 'erasable' : ''} ${isMoveToolActive ? 'movable' : ''}`}
      style={{
        left: centerX,
        top: centerY
      }}
      onClick={handleClick}
    >
      <img
        src={`${basePath}${element.src}`}
        alt=""
        draggable={false}
      />
    </div>
  )
}

export default MapElement
