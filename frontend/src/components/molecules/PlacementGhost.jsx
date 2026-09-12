import React, { useCallback, useState } from 'react'

function PlacementGhost({ asset, type, cell, cellSize, basePath, blocked = false }) {
  const [sizeBySrc, setSizeBySrc] = useState({})

  const rememberSize = useCallback((src, width, height) => {
    if (!src || width <= 0 || height <= 0) return
    setSizeBySrc(prev => {
      const cur = prev[src]
      if (cur?.width === width && cur?.height === height) return prev
      return { ...prev, [src]: { width, height } }
    })
  }, [])

  const handleImageLoad = useCallback((e) => {
    if (!asset?.src) return
    rememberSize(asset.src, e.target.naturalWidth, e.target.naturalHeight)
  }, [asset?.src, rememberSize])

  const handleImageRef = useCallback((img) => {
    if (!img || !asset?.src) return
    if (img.complete && img.naturalWidth > 0) {
      rememberSize(asset.src, img.naturalWidth, img.naturalHeight)
    }
  }, [asset?.src, rememberSize])

  if (!asset || !cell) return null

  const isToken = type === 'token'
  const centerX = cell.x * cellSize + cellSize / 2
  const centerY = cell.y * cellSize + cellSize / 2
  const naturalSize = sizeBySrc[asset.src]
  const footprintWidth = isToken ? cellSize : (naturalSize?.width || 0)
  const footprintHeight = isToken ? cellSize : (naturalSize?.height || 0)

  return (
    <div className={`placement-ghost ${isToken ? 'is-token' : 'is-map'} ${blocked ? 'blocked' : ''}`} aria-hidden>
      <div
        className="placement-ghost-anchor"
        style={{
          left: cell.x * cellSize,
          top: cell.y * cellSize,
          width: cellSize,
          height: cellSize
        }}
      />
      {!isToken && footprintWidth > 0 && footprintHeight > 0 && (
        <div
          className="placement-ghost-bounds"
          style={{
            left: centerX - footprintWidth / 2,
            top: centerY - footprintHeight / 2,
            width: footprintWidth,
            height: footprintHeight
          }}
        />
      )}
      <div
        className="placement-ghost-image"
        style={{ left: centerX, top: centerY }}
      >
        <img
          ref={handleImageRef}
          src={`${basePath}${asset.src}`}
          alt=""
          draggable={false}
          onLoad={isToken ? undefined : handleImageLoad}
          style={isToken ? { width: cellSize, height: cellSize } : undefined}
        />
      </div>
    </div>
  )
}

export default PlacementGhost
