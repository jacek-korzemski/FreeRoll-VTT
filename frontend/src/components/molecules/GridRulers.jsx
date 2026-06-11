import React, { useCallback, useLayoutEffect, useMemo, useState } from 'react'
import { t } from '../../lang'

function labelStepForZoom(pxPerCell) {
  if (pxPerCell < 12) return 10
  if (pxPerCell < 24) return 5
  if (pxPerCell < 40) return 2
  return 1
}

function viewportReadout(scrollLeft, scrollTop, clientW, clientH, zoomLevel, cellSize, gridSize, background) {
  const gx = (scrollLeft + clientW / 2) / zoomLevel
  const gy = (scrollTop + clientH / 2) / zoomLevel
  const sceneCellX = Math.floor(gx / cellSize)
  const sceneCellY = Math.floor(gy / cellSize)
  const scx = Math.max(0, Math.min(gridSize - 1, sceneCellX))
  const scy = Math.max(0, Math.min(gridSize - 1, sceneCellY))

  let mapPart = null
  if (background && background.width > 0 && background.height > 0) {
    const offsetX = background.offsetX ?? 0
    const offsetY = background.offsetY ?? 0
    const scale = background.scale ?? 1
    const imgW = background.width
    const imgH = background.height
    const drawW = imgW * scale
    const drawH = imgH * scale
    const onArt = gx >= offsetX && gy >= offsetY && gx < offsetX + drawW && gy < offsetY + drawH
    const gw = Math.floor(imgW / cellSize)
    const gh = Math.floor(imgH / cellSize)
    if (!onArt || gw < 1 || gh < 1) {
      mapPart = 'off'
    } else {
      const ix = (gx - offsetX) / scale
      const iy = (gy - offsetY) / scale
      const mcx = Math.max(0, Math.min(gw - 1, Math.floor(ix / cellSize)))
      const mcy = Math.max(0, Math.min(gh - 1, Math.floor(iy / cellSize)))
      mapPart = { mcx, mcy }
    }
  }

  return { scx, scy, mapPart }
}

/**
 * Corner cell + horizontal and vertical rulers synced to grid-container scroll/zoom.
 */
function GridRulers({ scrollContainerRef, zoomLevel, background, cellSize, gridSize }) {
  const [layout, setLayout] = useState({
    scrollLeft: 0,
    scrollTop: 0,
    scrollWidth: 0,
    scrollHeight: 0,
    clientWidth: 0,
    clientHeight: 0,
  })

  const syncScroll = useCallback(() => {
    const el = scrollContainerRef?.current
    if (!el) return
    setLayout(prev => {
      const next = {
        scrollLeft: el.scrollLeft,
        scrollTop: el.scrollTop,
        scrollWidth: el.scrollWidth,
        scrollHeight: el.scrollHeight,
        clientWidth: el.clientWidth,
        clientHeight: el.clientHeight,
      }
      if (
        prev.scrollLeft === next.scrollLeft
        && prev.scrollTop === next.scrollTop
        && prev.scrollWidth === next.scrollWidth
        && prev.scrollHeight === next.scrollHeight
        && prev.clientWidth === next.clientWidth
        && prev.clientHeight === next.clientHeight
      ) {
        return prev
      }
      return next
    })
  }, [scrollContainerRef])

  useLayoutEffect(() => {
    let stopped = false
    let rafId = 0
    let ro = null
    let el = null

    const tick = () => {
      if (stopped) return
      syncScroll()
      rafId = requestAnimationFrame(tick)
    }

    const bind = () => {
      el = scrollContainerRef?.current
      if (!el) {
        rafId = requestAnimationFrame(bind)
        return
      }

      syncScroll()
      ro = new ResizeObserver(syncScroll)
      ro.observe(el)
      el.addEventListener('scroll', syncScroll, { passive: true })
      window.addEventListener('resize', syncScroll)
      rafId = requestAnimationFrame(tick)
    }

    bind()

    return () => {
      stopped = true
      cancelAnimationFrame(rafId)
      ro?.disconnect()
      if (el) {
        el.removeEventListener('scroll', syncScroll)
      }
      window.removeEventListener('resize', syncScroll)
    }
  }, [scrollContainerRef, syncScroll, zoomLevel, background])

  const pxPerCell = cellSize * zoomLevel
  const step = labelStepForZoom(pxPerCell)

  const trackW = Math.max(layout.scrollWidth, gridSize * cellSize * zoomLevel)
  const trackH = Math.max(layout.scrollHeight, gridSize * cellSize * zoomLevel)

  const readout = useMemo(
    () =>
      viewportReadout(
        layout.scrollLeft,
        layout.scrollTop,
        layout.clientWidth,
        layout.clientHeight,
        zoomLevel,
        cellSize,
        gridSize,
        background
      ),
    [layout, zoomLevel, cellSize, gridSize, background]
  )

  const mapLine =
    readout.mapPart == null
      ? ''
      : readout.mapPart === 'off'
        ? t('grid.rulerOffArt')
        : `${t('grid.rulerMap')}: ${readout.mapPart.mcx},${readout.mapPart.mcy}`

  const cornerTitle = [ `${t('grid.rulerScene')}: ${readout.scx},${readout.scy}`, mapLine ].filter(Boolean).join(' · ')

  const horizTicks = []
  for (let i = 0; i <= gridSize; i++) {
    horizTicks.push(
      <div
        key={`h-${i}`}
        className="map-ruler-tick"
        style={{ left: i * cellSize * zoomLevel }}
      />
    )
  }

  const horizLabels = []
  for (let i = 0; i < gridSize; i += step) {
    const cx = (i + 0.5) * cellSize * zoomLevel
    horizLabels.push(
      <span
        key={`hl-${i}`}
        className="map-ruler-label"
        style={{ left: cx }}
      >
        {i}
      </span>
    )
  }

  const vertTicks = []
  for (let i = 0; i <= gridSize; i++) {
    vertTicks.push(
      <div
        key={`v-${i}`}
        className="map-ruler-tick"
        style={{ top: i * cellSize * zoomLevel }}
      />
    )
  }

  const vertLabels = []
  for (let i = 0; i < gridSize; i += step) {
    const cy = (i + 0.5) * cellSize * zoomLevel
    vertLabels.push(
      <span
        key={`vl-${i}`}
        className="map-ruler-label map-ruler-label--vertical"
        style={{ top: cy }}
      >
        {i}
      </span>
    )
  }

  return (
    <>
      <div className="map-ruler-corner" title={cornerTitle} aria-hidden="true" />

      <div className="map-ruler-h">
        <div
          className="map-ruler-h-track"
          style={{
            width: trackW,
            transform: `translate3d(${-layout.scrollLeft}px, 0, 0)`,
          }}
        >
          {horizTicks}
          {horizLabels}
        </div>
      </div>

      <div className="map-ruler-v">
        <div
          className="map-ruler-v-track"
          style={{
            height: trackH,
            transform: `translate3d(0, ${-layout.scrollTop}px, 0)`,
          }}
        >
          {vertTicks}
          {vertLabels}
        </div>
      </div>
    </>
  )
}

export default GridRulers
