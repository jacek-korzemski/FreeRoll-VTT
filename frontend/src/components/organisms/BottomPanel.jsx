import React, { useState, useCallback, useEffect, Suspense, lazy, useMemo } from 'react'
import NotesPanel from './NotesPanel'
import { NotesTemplateProvider } from '../../contexts/NotesTemplateContext'
import { t } from '../../lang'
import { ENABLE_L5R } from '../../../config'

const PdfPanel = lazy(() => import('./PdfPanel'))
const MacroEditor = lazy(() => import('./MacroEditor'))
const CountersPanel = lazy(() => import('./CountersPanel'))
const TtrpgManagerPanel = lazy(() => import('./TtrpgManagerPanel'))

// Build-time literal: the compendium tab (and the JSON data chunks it pulls in)
// are only emitted into the bundle when EnableL5r is set at build time.
const L5R_BUILD = import.meta.env.VITE_ENABLE_L5R === 'true'
const CompendiumPanel = L5R_BUILD ? lazy(() => import('./CompendiumPanel')) : null

const STORAGE_KEY = 'vtt_bottom_panel_height'
const MIN_HEIGHT_PERCENT = 30
const MAX_HEIGHT_PERCENT = 90
const DEFAULT_HEIGHT_PERCENT = 50

const BASE_PANELS = [
  { id: 'notes', icon: '📝', titleKey: 'notes.title' },
  { id: 'pdf', icon: '📄', titleKey: 'pdf.title' },
  { id: 'macros', icon: '⚡', titleKey: 'macros.title' },
  { id: 'counters', icon: '🔢', titleKey: 'counters.title' },
  ...(ENABLE_L5R ? [{ id: 'compendium', icon: '📚', titleKey: 'l5r.compendium' }] : []),
]

function BottomPanel({
  activeTab,
  onTabChange,
  sharedCounters = [],
  serverNow = null,
  isGameMaster = false,
  apiBase = '',
  onCountersMutation,
  ttrpgManager = { configured: false, baseUrl: null, campaignId: null },
  onTtrpgStatusChange,
}) {
  const [heightPercent, setHeightPercent] = useState(DEFAULT_HEIGHT_PERCENT)
  const [isResizing, setIsResizing] = useState(false)
  const [mountedTabs, setMountedTabs] = useState(new Set())
  const isOpen = activeTab !== null
  const configured = !!ttrpgManager?.configured

  const visiblePanels = useMemo(() => {
    const panels = [...BASE_PANELS]
    if (configured) {
      panels.push({ id: 'ttrpg', icon: '🎲', titleKey: 'ttrpg.managerTitle' })
    } else if (isGameMaster) {
      panels.push({ id: 'ttrpg', icon: '💥', titleKey: 'ttrpg.connectTab' })
    }
    return panels
  }, [configured, isGameMaster])

  useEffect(() => {
    if (activeTab && !mountedTabs.has(activeTab)) {
      setMountedTabs(prev => new Set([...prev, activeTab]))
    }
  }, [activeTab, mountedTabs])

  useEffect(() => {
    if (activeTab === 'ttrpg' && !configured && !isGameMaster) {
      onTabChange(null)
    }
  }, [activeTab, configured, isGameMaster, onTabChange])

  useEffect(() => {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      try {
        const config = JSON.parse(saved)
        if (config.heightPercent) setHeightPercent(config.heightPercent)
      } catch { /* ignore */ }
    } else {
      const legacy = localStorage.getItem('vtt_notes_config')
      if (legacy) {
        try {
          const config = JSON.parse(legacy)
          if (config.heightPercent) setHeightPercent(config.heightPercent)
        } catch { /* ignore */ }
      }
    }
  }, [])

  const saveHeight = useCallback((height) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ heightPercent: height }))
  }, [])

  const handleResizeStart = useCallback((e) => {
    // Obsługa zarówno myszy jak i dotyku
    if (e.type === 'touchstart') {
      // Na mobilkach zapobiegamy scrollowaniu strony podczas zmiany rozmiaru
      e.preventDefault()
    }
    setIsResizing(true)
  }, [])

  useEffect(() => {
    if (!isResizing) return

    const handleMouseMove = (e) => {
      const vh = window.innerHeight
      const newHeight = ((vh - e.clientY) / vh) * 100
      setHeightPercent(Math.min(MAX_HEIGHT_PERCENT, Math.max(MIN_HEIGHT_PERCENT, newHeight)))
    }

    const handleTouchMove = (e) => {
      if (e.touches.length === 0) return
      const vh = window.innerHeight
      const newHeight = ((vh - e.touches[0].clientY) / vh) * 100
      setHeightPercent(Math.min(MAX_HEIGHT_PERCENT, Math.max(MIN_HEIGHT_PERCENT, newHeight)))
    }

    const handleMouseUp = () => {
      setIsResizing(false)
      saveHeight(heightPercent)
    }

    const handleTouchEnd = () => {
      setIsResizing(false)
      saveHeight(heightPercent)
    }

    document.addEventListener('mousemove', handleMouseMove)
    document.addEventListener('mouseup', handleMouseUp)
    document.addEventListener('touchmove', handleTouchMove, { passive: false })
    document.addEventListener('touchend', handleTouchEnd)
    document.addEventListener('touchcancel', handleTouchEnd)

    return () => {
      document.removeEventListener('mousemove', handleMouseMove)
      document.removeEventListener('mouseup', handleMouseUp)
      document.removeEventListener('touchmove', handleTouchMove)
      document.removeEventListener('touchend', handleTouchEnd)
      document.removeEventListener('touchcancel', handleTouchEnd)
    }
  }, [isResizing, heightPercent, saveHeight])

  const handleTabClick = useCallback((tabId) => {
    if (!isOpen) {
      onTabChange(tabId)
    } else if (activeTab !== tabId) {
      onTabChange(tabId)
    }
  }, [isOpen, activeTab, onTabChange])

  return (
    <>
      <div
        className={`bottom-panel ${isOpen ? 'open' : ''} ${isResizing ? 'resizing' : ''}`}
        style={{ height: isOpen ? `${heightPercent}vh` : '0' }}
      >
        <div className="bottom-panel-toolbar">
          <div className="bottom-panel-tabs">
            {visiblePanels.map(panel => (
              <button
                key={panel.id}
                className={`bottom-panel-tab ${activeTab === panel.id ? 'active' : ''}`}
                onClick={() => handleTabClick(panel.id)}
                title={t(panel.titleKey)}
              >
                {panel.icon}
              </button>
            ))}
          </div>
          {isOpen && (
            <button
              className="bottom-panel-close"
              onClick={() => onTabChange(null)}
              title={t('notes.close')}
            >
              ▼
            </button>
          )}
        </div>

        <div
          className={`bottom-panel-resize-handle ${isResizing ? 'active' : ''}`}
          onMouseDown={handleResizeStart}
          onTouchStart={handleResizeStart}
        >
          <div className="bottom-panel-resize-bar" />
        </div>

        <div className="bottom-panel-content">
          <NotesTemplateProvider>
            {(mountedTabs.has('notes') || mountedTabs.has('macros')) && (
              <div className={`bottom-panel-tab-pane ${activeTab !== 'notes' ? 'hidden' : ''}`}>
                <NotesPanel />
              </div>
            )}
            {mountedTabs.has('pdf') && (
              <div className={`bottom-panel-tab-pane ${activeTab !== 'pdf' ? 'hidden' : ''}`}>
                <Suspense fallback={<div className="pdf-placeholder">{t('pdf.loading')}</div>}>
                  <PdfPanel />
                </Suspense>
              </div>
            )}
            {mountedTabs.has('macros') && (
              <div className={`bottom-panel-tab-pane ${activeTab !== 'macros' ? 'hidden' : ''}`}>
                <Suspense fallback={<div className="macro-editor-placeholder">{t('macros.loading')}</div>}>
                  <MacroEditor />
                </Suspense>
              </div>
            )}
          </NotesTemplateProvider>
          {mountedTabs.has('counters') && (
            <div className={`bottom-panel-tab-pane ${activeTab !== 'counters' ? 'hidden' : ''}`}>
              <Suspense fallback={<div className="counters-placeholder">{t('app.loading')}</div>}>
                <CountersPanel
                  sharedCounters={sharedCounters}
                  serverNow={serverNow}
                  isGameMaster={isGameMaster}
                  apiBase={apiBase}
                  onCountersMutation={onCountersMutation}
                />
              </Suspense>
            </div>
          )}
          {ENABLE_L5R && CompendiumPanel && mountedTabs.has('compendium') && (
            <div className={`bottom-panel-tab-pane ${activeTab !== 'compendium' ? 'hidden' : ''}`}>
              <Suspense fallback={<div className="compendium-placeholder">{t('l5r.loading')}</div>}>
                <CompendiumPanel />
              </Suspense>
            </div>
          )}
          {mountedTabs.has('ttrpg') && (configured || isGameMaster) && (
            <div className={`bottom-panel-tab-pane ${activeTab !== 'ttrpg' ? 'hidden' : ''}`}>
              <Suspense fallback={<div className="ttrpg-placeholder">{t('ttrpg.loading')}</div>}>
                <TtrpgManagerPanel
                  apiBase={apiBase}
                  isGameMaster={isGameMaster}
                  ttrpgManager={ttrpgManager}
                  onStatusChange={onTtrpgStatusChange}
                />
              </Suspense>
            </div>
          )}
        </div>
      </div>

      {isResizing && <div className="bottom-panel-resize-overlay" />}
    </>
  )
}

export default BottomPanel
