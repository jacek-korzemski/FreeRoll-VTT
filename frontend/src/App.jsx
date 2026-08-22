import { useState, useEffect, useCallback, useRef, startTransition } from 'react'
import { Sidebar, Grid, DicePanel, BottomPanel, RollSnackbarContainer } from './components'
import DebugOverlay from './components/atoms/DebugOverlay'
import { 
  createEmptyFog, 
  createRevealedFog, 
  encodeToBase64, 
  decodeFromBase64,
} from './utils/fogBitmap'
import { ASSET_BASE, API_BASE, ENABLE_L5R } from '../config'
import { t } from './lang'

const DEBUG_MODE = new URLSearchParams(window.location.search).has('debug')

function App() {
  const [scenes, setScenes] = useState([])
  const [bottomPanelTab, setBottomPanelTab] = useState(null)
  const [activeSceneId, setActiveSceneId] = useState(null)
  const [background, setBackground] = useState(null)
  const [mapElements, setMapElements] = useState([])
  const [tokens, setTokens] = useState([])
  const [mapPath, setMapPath] = useState('')
  const [mapFolders, setMapFolders] = useState([])
  const [mapFiles, setMapFiles] = useState([])
  const [tokenPath, setTokenPath] = useState('')
  const [tokenFolders, setTokenFolders] = useState([])
  const [tokenFiles, setTokenFiles] = useState([])
  const [mapListLoading, setMapListLoading] = useState(false)
  const [tokenListLoading, setTokenListLoading] = useState(false)
  const [backgroundAssets, setBackgroundAssets] = useState([])
  const [selectedAsset, setSelectedAsset] = useState(null)
  const [selectedType, setSelectedType] = useState(null)
  const [isEraserActive, setIsEraserActive] = useState(false)  
  const [version, setVersion] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [fogOfWar, setFogOfWar] = useState({ enabled: false, data: null })
  const [fogBitmap, setFogBitmap] = useState(() => createEmptyFog())
  const [fogEditMode, setFogEditMode] = useState(false)
  const [fogRevealMode, setFogRevealMode] = useState(true)
  const [fogBrushSize, setFogBrushSize] = useState(3)
  const [fogGmOpacity, setFogGmOpacity] = useState(false)
  const [dicePanelOpen, setDicePanelOpen] = useState(false)
  const [rollHistory, setRollHistory] = useState([])
  const [rollsHydrated, setRollsHydrated] = useState(false)
  const [pendingL5RRoll, setPendingL5RRoll] = useState(null)
  const [sidebarOpen, setSidebarOpen] = useState(() => 
    typeof window !== 'undefined' && window.innerWidth >= 576
  )
  const [zoomLevel, setZoomLevel] = useState(1)
  const [pingMode, setPingMode] = useState(false)
  const [pingAnimation, setPingAnimation] = useState(null)
  const [activePing, setActivePing] = useState(null)
  const lastPingTimestampRef = useRef(0)
  const versionRef = useRef(0)
  const activeSceneIdRef = useRef(null)
  const fogEditModeRef = useRef(false)
  const pollInFlightRef = useRef(false)
  const gridContainerRef = useRef(null)
  const isInitialMapFetch = useRef(true)
  const isInitialTokenFetch = useRef(true)
  const mapNavigationForwardRef = useRef(false)
  const tokenNavigationForwardRef = useRef(false)
  const [isGameMaster, setIsGameMaster] = useState(false)
  const [apiStatus, setApiStatus] = useState('ok')
  const [apiFlashTrigger, setApiFlashTrigger] = useState(0)
  const [isTokenEraserActive, setIsTokenEraserActive] = useState(false)
  const [sharedCounters, setSharedCounters] = useState([])
  const [serverNow, setServerNow] = useState(null)
  const [ttrpgManager, setTtrpgManager] = useState({
    configured: false,
    baseUrl: null,
    campaignId: null,
  })

  const fogUpdateTimeoutRef = useRef(null)
  const backgroundUpdateTimeoutRef = useRef(null)
  const backgroundRemovedRef = useRef(false)

  versionRef.current = version
  activeSceneIdRef.current = activeSceneId
  fogEditModeRef.current = fogEditMode

  // Sprawdź rolę użytkownika na początku
  useEffect(() => {
    // W trybie deweloperskim sprawdź localStorage dla łatwego przełączania roli
    const devGm = localStorage.getItem('dev_gm') === '1'
    const gmParam = devGm ? '&gm=1' : ''
    
    fetch(`${API_BASE}?action=auth${gmParam}`, { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setIsGameMaster(data.isGameMaster || false)
        }
      })
      .catch(console.error)
  }, [])

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 575.98px)')
    const handler = () => {
      if (mq.matches) {
        setSidebarOpen(false)
        setDicePanelOpen(false)
      }
    }
    mq.addEventListener('change', handler)
    return () => mq.removeEventListener('change', handler)
  }, [])

  const handleZoomChange = useCallback((newZoom) => {
    const clamped = Math.max(0.4, Math.min(1.4, newZoom))
    setZoomLevel(Math.round(clamped * 100) / 100)
  }, [])

  const updateSceneState = useCallback((sceneData) => {
    if (!sceneData) return
    setBackground(sceneData.background || null)
    setMapElements(sceneData.mapElements || [])
    setTokens(sceneData.tokens || [])
    setFogOfWar(sceneData.fogOfWar || { enabled: false, data: null })
    setFogBitmap(decodeFromBase64(sceneData.fogOfWar?.data))
  }, [])

  const handleCountersMutation = useCallback((action, body, data) => {
    if (typeof data.version === 'number') {
      setVersion(data.version)
    }
    if (action === 'counter-add' && data.counter) {
      setSharedCounters((prev) => {
        const rest = prev.filter((c) => c.id !== data.counter.id)
        return [...rest, data.counter]
      })
    } else if (action === 'counter-update' && data.counter) {
      setSharedCounters((prev) => prev.map((c) => (c.id === data.counter.id ? data.counter : c)))
    } else if (action === 'counter-delete' && data.success) {
      const id = body?.id
      if (id) {
        setSharedCounters((prev) => prev.filter((c) => c.id !== id))
      }
    }
  }, [])

  const handleTtrpgStatusChange = useCallback((status, nextVersion) => {
    if (status && typeof status === 'object') {
      setTtrpgManager({
        configured: !!status.configured,
        baseUrl: status.baseUrl ?? null,
        campaignId: status.campaignId ?? null,
      })
    }
    if (typeof nextVersion === 'number') {
      setVersion(nextVersion)
    }
  }, [])

  const handleSendPing = useCallback((x, y) => {
    fetch(`${API_BASE}?action=send-ping`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ x, y })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setActivePing(data.ping)
          setVersion(data.version)
          setPingMode(false)
        }
      })
      .catch(console.error)
  }, [])

  const handleTogglePing = useCallback(() => {
    const newPingMode = !pingMode
    setPingMode(newPingMode)
    
    if (newPingMode) {
      setSelectedAsset(null)
      setSelectedType(null)
      setIsEraserActive(false)
      setFogEditMode(false)
    }
  }, [pingMode])

  const handleClearPing = useCallback(() => {
    fetch(`${API_BASE}?action=clear-ping`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setActivePing(null)
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  const scrollToPoint = useCallback((cellX, cellY) => {
    if (!gridContainerRef.current) return
    
    const container = gridContainerRef.current
    const cellSize = 64 * zoomLevel
    
    // Oblicz pozycję docelową (środek ekranu na danej komórce)
    const targetX = cellX * cellSize + cellSize / 2 - container.clientWidth / 2
    const targetY = cellY * cellSize + cellSize / 2 - container.clientHeight / 2
    
    // Smooth scroll
    container.scrollTo({
      left: Math.max(0, targetX),
      top: Math.max(0, targetY),
      behavior: 'smooth'
    })
    
    // Pokaż animację pinga
    setPingAnimation({ x: cellX, y: cellY, timestamp: Date.now() })
    
    // Ukryj animację po 2 sekundach
    setTimeout(() => {
      setPingAnimation(null)
    }, 2000)
  }, [zoomLevel])

  const applyPingFromSync = useCallback((ping) => {
    if (ping) {
      setActivePing(ping)
      if (ping.timestamp > lastPingTimestampRef.current) {
        lastPingTimestampRef.current = ping.timestamp
        scrollToPoint(ping.x, ping.y)
      }
    } else {
      setActivePing(null)
    }
  }, [scrollToPoint])
  
  useEffect(() => {
    fetch(API_BASE + '?action=assets', { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setBackgroundAssets(data.backgroundAssets || [])
        }
      })
      .catch(console.error)
  }, [])

  const refreshBackgroundAssets = useCallback(() => {
    fetch(API_BASE + '?action=assets', { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setBackgroundAssets(data.backgroundAssets || [])
        }
      })
      .catch(console.error)
  }, [])

  useEffect(() => {
    const isForward = mapNavigationForwardRef.current
    if (!isInitialMapFetch.current && isForward) setMapListLoading(true)
    isInitialMapFetch.current = false
    const q = mapPath ? `&path=${encodeURIComponent(mapPath)}` : ''
    fetch(`${API_BASE}?action=list-map${q}`, { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          startTransition(() => {
            setMapFolders(data.folders || [])
            setMapFiles(data.files || [])
          })
        }
        setMapListLoading(false)
      })
      .catch(() => setMapListLoading(false))
  }, [mapPath])

  const refreshMapAssets = useCallback(() => {
    const q = mapPath ? `&path=${encodeURIComponent(mapPath)}` : ''
    fetch(`${API_BASE}?action=list-map${q}`, { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          startTransition(() => {
            setMapFolders(data.folders || [])
            setMapFiles(data.files || [])
          })
        }
      })
      .catch(console.error)
  }, [mapPath])

  useEffect(() => {
    const isForward = tokenNavigationForwardRef.current
    if (!isInitialTokenFetch.current && isForward) setTokenListLoading(true)
    isInitialTokenFetch.current = false
    const q = tokenPath ? `&path=${encodeURIComponent(tokenPath)}` : ''
    fetch(`${API_BASE}?action=list-tokens${q}`, { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          startTransition(() => {
            setTokenFolders(data.folders || [])
            setTokenFiles(data.files || [])
          })
        }
        setTokenListLoading(false)
      })
      .catch(() => setTokenListLoading(false))
  }, [tokenPath])

  const refreshTokenAssets = useCallback(() => {
    const q = tokenPath ? `&path=${encodeURIComponent(tokenPath)}` : ''
    fetch(`${API_BASE}?action=list-tokens${q}`, { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          startTransition(() => {
            setTokenFolders(data.folders || [])
            setTokenFiles(data.files || [])
          })
        }
      })
      .catch(console.error)
  }, [tokenPath])

  
useEffect(() => {
  fetch(API_BASE + '?action=state', { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const sync = data.data || {}
        setScenes(sync.scenes || [])
        setActiveSceneId(sync.activeSceneId)
        updateSceneState(sync.scene)
        setVersion(sync.version || 0)
        setSharedCounters(sync.counters || [])
        if (typeof sync.serverNow === 'number') {
          setServerNow(sync.serverNow)
        }
        if (sync.ttrpgManager) {
          setTtrpgManager({
            configured: !!sync.ttrpgManager.configured,
            baseUrl: sync.ttrpgManager.baseUrl ?? null,
            campaignId: sync.ttrpgManager.campaignId ?? null,
          })
        }
        applyPingFromSync(sync.ping ?? null)
        if (Array.isArray(sync.rolls)) {
          setRollHistory(sync.rolls)
          setRollsHydrated(true)
        }
      }
      setIsLoading(false)
    })
    .catch(err => {
      console.error(err)
      setIsLoading(false)
    })
}, [updateSceneState, applyPingFromSync])

  
  useEffect(() => {
    const onSuccess = () => {
      setApiStatus('ok')
      setApiFlashTrigger(t => t + 1)
    }
    const onError = () => {
      setApiStatus('error')
      setApiFlashTrigger(t => t + 1)
    }

    const poll = () => {
      if (pollInFlightRef.current) return
      pollInFlightRef.current = true

      fetch(`${API_BASE}?action=check&version=${versionRef.current}`, { credentials: 'include' })
        .then(res => {
          if (res.status === 204) {
            onSuccess()
            return null
          }
          if (!res.ok) {
            onError()
            return null
          }
          return res.json()
        })
        .then(data => {
          if (!data) return
          if (!data.success) {
            onError()
            return
          }
          onSuccess()
          if (!data.hasChanges || !data.data) return

          const sync = data.data
          if (typeof sync.serverNow === 'number') {
            setServerNow(sync.serverNow)
          }
          if (sync.ttrpgManager && typeof sync.ttrpgManager === 'object') {
            setTtrpgManager({
              configured: !!sync.ttrpgManager.configured,
              baseUrl: sync.ttrpgManager.baseUrl ?? null,
              campaignId: sync.ttrpgManager.campaignId ?? null,
            })
          }
          setScenes(sync.scenes || [])
          if (Array.isArray(sync.counters)) {
            setSharedCounters(sync.counters)
          }
          if (sync.activeSceneId !== activeSceneIdRef.current) {
            setActiveSceneId(sync.activeSceneId)
            updateSceneState(sync.scene)
            setSelectedAsset(null)
            setSelectedType(null)
            setIsEraserActive(false)
            setFogEditMode(false)
          } else if (!fogEditModeRef.current) {
            updateSceneState(sync.scene)
          }
          if (typeof sync.version === 'number') {
            setVersion(sync.version)
          }
          applyPingFromSync(sync.ping ?? null)
          if (Array.isArray(sync.rolls)) {
            setRollHistory(sync.rolls)
            setRollsHydrated(true)
          }
        })
        .catch(err => {
          onError()
          console.error(err)
        })
        .finally(() => {
          pollInFlightRef.current = false
        })
    }

    const interval = setInterval(poll, 2000)
    return () => clearInterval(interval)
  }, [updateSceneState, applyPingFromSync])

  const handleSwitchScene = useCallback((sceneId) => {
    fetch(`${API_BASE}?action=switch-scene`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: sceneId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setActiveSceneId(sceneId)
          updateSceneState(data.scene)
          setVersion(data.version)
          // Reset narzędzi
          setSelectedAsset(null)
          setSelectedType(null)
          setIsEraserActive(false)
          setFogEditMode(false)
        }
      })
      .catch(console.error)
  }, [updateSceneState])

  const handleCreateScene = useCallback((name) => {
    fetch(`${API_BASE}?action=create-scene`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ name })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setScenes(prev => [...prev, data.scene])
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  const refreshPapers = useCallback(() => {
    fetch(`${API_BASE}?action=list-papers`, { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        // Odświeżenie listy po stronie PdfPanel jest trudniejsze bez kontekstu,
        // więc na razie ta funkcja może zostać wykorzystana w przyszłości.
        // Obecnie nie modyfikujemy lokalnego stanu tutaj.
        return data
      })
      .catch(console.error)
  }, [])

  const handleDeleteScene = useCallback((sceneId) => {
    fetch(`${API_BASE}?action=delete-scene`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: sceneId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setScenes(prev => prev.filter(s => s.id !== sceneId))
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  const handleRenameScene = useCallback((sceneId, name) => {
    fetch(`${API_BASE}?action=rename-scene`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: sceneId, name })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setScenes(prev => prev.map(s => 
            s.id === sceneId ? { ...s, name } : s
          ))
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  const handleDuplicateScene = useCallback((sceneId) => {
    fetch(`${API_BASE}?action=duplicate-scene`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: sceneId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setScenes(prev => [...prev, data.scene])
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  const handleDiceRoll = useCallback((rollData) => {
    setRollHistory(prev => [{ ...rollData, id: Date.now().toString() }, ...prev].slice(0, 20))
    
    fetch(`${API_BASE}?action=roll`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(rollData)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  const handleClearRollHistory = useCallback(() => {
    if (!confirm(t('dice.clearHistoryConfirm'))) return

    fetch(`${API_BASE}?action=clear-rolls`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setRollHistory([])
          if (typeof data.version === 'number') {
            setVersion(data.version)
          }
        }
      })
      .catch(console.error)
  }, [])

  useEffect(() => {
    const handler = (e) => handleDiceRoll(e.detail)
    window.addEventListener('vtt:dice-roll', handler)
    return () => window.removeEventListener('vtt:dice-roll', handler)
  }, [handleDiceRoll])

  // L5R roll requests from character-sheet buttons open the dice panel pre-filled.
  useEffect(() => {
    if (!ENABLE_L5R) return
    const handler = (e) => {
      setPendingL5RRoll({ ...e.detail, token: Date.now() })
      setDicePanelOpen(true)
    }
    window.addEventListener('vtt:l5r-roll', handler)
    return () => window.removeEventListener('vtt:l5r-roll', handler)
  }, [])
  
  const handleSelectAsset = useCallback((asset, type) => {
    setIsEraserActive(false)
    setFogEditMode(false)
    setPingMode(false) 
    
    if (selectedAsset?.id === asset.id && selectedType === type) {
      setSelectedAsset(null)
      setSelectedType(null)
    } else {
      setSelectedAsset(asset)
      setSelectedType(type)
    }
  }, [selectedAsset, selectedType])

  const handleToggleEraser = useCallback(() => {
    setFogEditMode(false)  
    setPingMode(false) 
    
    if (isEraserActive) {
      setIsEraserActive(false)
    } else {
      setIsEraserActive(true)
      setSelectedAsset(null)
      setSelectedType(null)
    }
  }, [isEraserActive])

  const handleToggleTokenEraser = useCallback(() => {
    setFogEditMode(false)
    setPingMode(false)
    
    setIsTokenEraserActive(prev => {
      const next = !prev
      if (next) {
        setIsEraserActive(false)
        setSelectedAsset(null)
        setSelectedType(null)
      }
      return next
    })
  }, [])

  
  const scheduleBackgroundSave = useCallback((bgConfig) => {
    if (backgroundUpdateTimeoutRef.current) {
      clearTimeout(backgroundUpdateTimeoutRef.current)
    }

    backgroundUpdateTimeoutRef.current = setTimeout(() => {
      // Sprawdź czy tło nie zostało usunięte w międzyczasie
      if (backgroundRemovedRef.current) {
        return // Tło zostało usunięte, nie zapisuj
      }

      fetch(`${API_BASE}?action=set-background`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          src: bgConfig.src,
          name: bgConfig.name,
          width: bgConfig.width,
          height: bgConfig.height,
          offsetX: bgConfig.offsetX ?? 0,
          offsetY: bgConfig.offsetY ?? 0,
          scale: bgConfig.scale ?? 1,
          gridHidden: bgConfig.gridHidden ?? false
        })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success && !backgroundRemovedRef.current) {
            setBackground(data.background)
            setVersion(data.version)
          }
        })
        .catch(console.error)
    }, 5000)
  }, [])

  const handleSetBackground = useCallback((bg) => {
    // Wyczyść flagę usunięcia i anuluj pending updates
    backgroundRemovedRef.current = false
    if (backgroundUpdateTimeoutRef.current) {
      clearTimeout(backgroundUpdateTimeoutRef.current)
      backgroundUpdateTimeoutRef.current = null
    }

    // Ustawienie nowego tła – brak debounce, żeby gracze od razu je zobaczyli
    const initialConfig = {
      src: bg.src,
      name: bg.name,
      width: bg.width,
      height: bg.height,
      offsetX: 0,
      offsetY: 0,
      scale: 1
    }

    fetch(`${API_BASE}?action=set-background`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(initialConfig)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setBackground(data.background)
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  const handleNudgeBackground = useCallback((dx, dy) => {
    setBackground(prev => {
      if (!prev) return prev
      const next = {
        ...prev,
        offsetX: (prev.offsetX ?? 0) + dx,
        offsetY: (prev.offsetY ?? 0) + dy
      }
      scheduleBackgroundSave(next)
      return next
    })
  }, [scheduleBackgroundSave])

  const handleScaleBackground = useCallback((delta) => {
    setBackground(prev => {
      if (!prev) return prev
      const currentScale = prev.scale ?? 1
      const nextScale = Math.max(0.5, Math.min(3, currentScale + delta))
      const next = {
        ...prev,
        scale: nextScale
      }
      scheduleBackgroundSave(next)
      return next
    })
  }, [scheduleBackgroundSave])

  const handleResetBackgroundPosition = useCallback(() => {
    setBackground(prev => {
      if (!prev) return prev
      const next = {
        ...prev,
        offsetX: 0,
        offsetY: 0
      }
      scheduleBackgroundSave(next)
      return next
    })
  }, [scheduleBackgroundSave])

  const handleResetBackgroundScale = useCallback(() => {
    setBackground(prev => {
      if (!prev) return prev
      const next = {
        ...prev,
        scale: 1
      }
      scheduleBackgroundSave(next)
      return next
    })
  }, [scheduleBackgroundSave])

  const handleResetBackgroundAll = useCallback(() => {
    setBackground(prev => {
      if (!prev) return prev
      const next = {
        ...prev,
        offsetX: 0,
        offsetY: 0,
        scale: 1
      }
      scheduleBackgroundSave(next)
      return next
    })
  }, [scheduleBackgroundSave])

  const handleToggleGridHidden = useCallback(() => {
    setBackground(prev => {
      if (!prev) return prev
      const next = {
        ...prev,
        gridHidden: !prev.gridHidden
      }

      fetch(`${API_BASE}?action=set-background`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          src: next.src,
          name: next.name,
          width: next.width,
          height: next.height,
          offsetX: next.offsetX ?? 0,
          offsetY: next.offsetY ?? 0,
          scale: next.scale ?? 1,
          gridHidden: next.gridHidden
        })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            setBackground(data.background)
            setVersion(data.version)
          }
        })
        .catch(console.error)

      return next
    })
  }, [])

  
  const handleRemoveBackground = useCallback(() => {
    // Ustaw flagę że tło zostało usunięte i anuluj pending updates
    backgroundRemovedRef.current = true
    if (backgroundUpdateTimeoutRef.current) {
      clearTimeout(backgroundUpdateTimeoutRef.current)
      backgroundUpdateTimeoutRef.current = null
    }

    fetch(`${API_BASE}?action=remove-background`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setBackground(null)
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  
  const isOccupiedByToken = useCallback((x, y) => {
    return tokens.some(token => token.x === x && token.y === y)
  }, [tokens])

  const isOccupiedByMapElement = useCallback((x, y) => {
    return mapElements.some(element => element.x === x && element.y === y)
  }, [mapElements])

  
  const handleDeselectAsset = useCallback(() => {
    setSelectedAsset(null)
    setSelectedType(null)
  }, [])

  const placeAssetAt = useCallback((asset, type, x, y, deselectAfterPlace = false) => {
    if (type === 'token' && isOccupiedByToken(x, y)) return
    if (type === 'map' && isOccupiedByMapElement(x, y)) return
    const action = type === 'map' ? 'add-map-element' : 'add-token'
    return fetch(`${API_BASE}?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ assetId: asset.id, src: asset.src, x, y })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (type === 'map') {
            setMapElements(prev => [...prev, data.element])
          } else {
            setTokens(prev => [...prev, data.token])
          }
          setVersion(data.version)
          if (deselectAfterPlace) {
            setSelectedAsset(null)
            setSelectedType(null)
          }
        }
      })
      .catch(console.error)
  }, [isOccupiedByToken, isOccupiedByMapElement])

  const handleCellClick = useCallback((x, y) => {
    if (isEraserActive) return
    if (!selectedAsset || !selectedType) return
    placeAssetAt(selectedAsset, selectedType, x, y, false)
  }, [selectedAsset, selectedType, isEraserActive, placeAssetAt])

  const handleDropOnGrid = useCallback((asset, type, x, y) => {
    placeAssetAt(asset, type, x, y, true)
  }, [placeAssetAt])

  
  const handleTokenMove = useCallback((tokenId, newX, newY) => {
    const isOccupied = tokens.some(t => t.id !== tokenId && t.x === newX && t.y === newY)
    if (isOccupied) return

    setTokens(prev => prev.map(t => 
      t.id === tokenId ? { ...t, x: newX, y: newY } : t
    ))

    fetch(`${API_BASE}?action=move-token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: tokenId, x: newX, y: newY })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [tokens])

  const handleTokenUpdate = useCallback((tokenId, updates) => {
    setTokens(prev => prev.map(t => 
      t.id === tokenId ? { ...t, ...updates } : t
    ))

    fetch(`${API_BASE}?action=update-token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: tokenId, ...updates })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [])

  
  const handleRemoveMapElement = useCallback((elementId) => {
    fetch(`${API_BASE}?action=remove-map-element`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: elementId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setMapElements(prev => {
            const filtered = prev.filter(e => e.id !== elementId)
            // Automatycznie wyłącz gumkę jeśli usunęliśmy ostatni element
            if (filtered.length === 0 && isEraserActive) {
              setIsEraserActive(false)
            }
            return filtered
          })
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [isEraserActive])

  
  const handleRemoveToken = useCallback((tokenId) => {
    fetch(`${API_BASE}?action=remove-token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: tokenId })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          try {
            localStorage.removeItem(TOKEN_NOTE_STORAGE_PREFIX + tokenId)
          } catch (_) {}
          setTokens(prev => {
            const next = prev.filter(t => t.id !== tokenId)
            if (next.length === 0 && isTokenEraserActive) {
              setIsTokenEraserActive(false)
            }
            return next
          })
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, [isTokenEraserActive])

  const TOKEN_NOTE_STORAGE_PREFIX = 'vtt_token_note_'

  const handleDuplicateToken = useCallback((token) => {
    const candidates = [
      { x: token.x + 1, y: token.y },
      { x: token.x - 1, y: token.y },
      { x: token.x, y: token.y + 1 },
      { x: token.x, y: token.y - 1 }
    ]
    const free = candidates.find(({ x, y }) => !isOccupiedByToken(x, y))
    if (!free) {
      return
    }
    fetch(`${API_BASE}?action=add-token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        assetId: token.assetId,
        src: token.src,
        x: free.x,
        y: free.y
      })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.token) {
          setTokens(prev => [...prev, data.token])
          setVersion(data.version)
          try {
            const raw = localStorage.getItem(TOKEN_NOTE_STORAGE_PREFIX + token.id)
            if (raw) {
              localStorage.setItem(TOKEN_NOTE_STORAGE_PREFIX + data.token.id, raw)
            }
          } catch (_) {}
          const updates = {}
          if (token.size != null) updates.size = token.size
          if (token.upperLabel != null) updates.upperLabel = token.upperLabel
          if (token.lowerLabel != null) updates.lowerLabel = token.lowerLabel
          if (Object.keys(updates).length > 0) {
            fetch(`${API_BASE}?action=update-token`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'include',
              body: JSON.stringify({ id: data.token.id, ...updates })
            })
              .then(r => r.json())
              .then(d => {
                if (d.success) {
                  setVersion(d.version)
                  setTokens(prev => prev.map(t => t.id === data.token.id ? { ...t, ...updates } : t))
                }
              })
              .catch(console.error)
          }
        }
      })
      .catch(console.error)
  }, [isOccupiedByToken])

  
  const handleClear = useCallback(() => {
    if (!confirm(t('sidebar.clearMapConfirm'))) return

    fetch(`${API_BASE}?action=clear`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          try {
            tokens.forEach(token => localStorage.removeItem(TOKEN_NOTE_STORAGE_PREFIX + token.id))
          } catch (_) {}
          setBackground(null)
          setMapElements([])
          setTokens([])
          setVersion(data.version)
          setSelectedAsset(null)
          setSelectedType(null)
          setIsEraserActive(false)
          setFogEditMode(false)
          setFogBitmap(createEmptyFog())
        }
      })
      .catch(console.error)
  }, [tokens])

  const handleToggleFog = useCallback((enabled) => {
    fetch(`${API_BASE}?action=toggle-fog`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ enabled })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setFogOfWar(prev => ({ ...prev, enabled: data.enabled }))
          setVersion(data.version)
        }
      })
      .catch(console.error)
  }, []);

  const handleFogBitmapChange = useCallback((newBitmap) => {
  setFogBitmap(newBitmap)
  
  
  if (fogUpdateTimeoutRef.current) {
    clearTimeout(fogUpdateTimeoutRef.current)
  }
  
  fogUpdateTimeoutRef.current = setTimeout(() => {
    const base64 = encodeToBase64(newBitmap)
    fetch(`${API_BASE}?action=update-fog`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ data: base64 })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setVersion(data.version)
        }
      })
      .catch(console.error)
    }, 300)
  }, []);

  const handleFogRevealAll = useCallback(() => {
    const newBitmap = createRevealedFog()
    handleFogBitmapChange(newBitmap)
  }, [handleFogBitmapChange])

  const handleFogHideAll = useCallback(() => {
    const newBitmap = createEmptyFog()
    handleFogBitmapChange(newBitmap)
  }, [handleFogBitmapChange])

  
  const handleToggleFogEdit = useCallback(() => {
    const newEditMode = !fogEditMode
    setFogEditMode(newEditMode)
    setPingMode(false) 
    
    
    if (newEditMode) {
      setSelectedAsset(null)
      setSelectedType(null)
      setIsEraserActive(false)
    }
  }, [fogEditMode])

  
  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        handleDeselectAsset()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [handleDeselectAsset])

  useEffect(() => {
    const preventSelection = (e) => {
      if (e.target && typeof e.target.closest === 'function' && e.target.closest('.grid-container')) {
        e.preventDefault()
      }
    }
    
    document.addEventListener('selectstart', preventSelection)
    return () => document.removeEventListener('selectstart', preventSelection)
  }, [])

  useEffect(() => {
    return () => {
      if (fogUpdateTimeoutRef.current) {
        clearTimeout(fogUpdateTimeoutRef.current)
      }
      if (backgroundUpdateTimeoutRef.current) {
        clearTimeout(backgroundUpdateTimeoutRef.current)
      }
    }
  }, [])

  if (isLoading) {
    return <div className="loading">{t('app.loading')}</div>
  }

  return (
    <div className="app">
      {/* Toggle sidebar button */}
      <button 
        className={`sidebar-toggle ${sidebarOpen ? 'open' : ''}`}
        onClick={() => setSidebarOpen(prev => !prev)}
        title={sidebarOpen ? 'Zwiń panel' : 'Rozwiń panel'}
      >
        {sidebarOpen ? '◀' : '▶'}
      </button>

      <Sidebar
        isOpen={sidebarOpen}
        isGameMaster={isGameMaster}
        apiStatus={apiStatus}
        apiFlashTrigger={apiFlashTrigger}
        mapPath={mapPath}
        mapFolders={mapFolders}
        mapFiles={mapFiles}
        mapListLoading={mapListLoading}
        mapNavigationForwardRef={mapNavigationForwardRef}
        onMapPathChange={(newPath) => {
          const prevSegs = (mapPath || '').split('/').filter(Boolean).length
          const newSegs = (newPath || '').split('/').filter(Boolean).length
          mapNavigationForwardRef.current = newSegs > prevSegs
          setMapPath(newPath)
        }}
        tokenPath={tokenPath}
        tokenFolders={tokenFolders}
        tokenFiles={tokenFiles}
        tokenListLoading={tokenListLoading}
        tokenNavigationForwardRef={tokenNavigationForwardRef}
        onTokenPathChange={(newPath) => {
          const prevSegs = (tokenPath || '').split('/').filter(Boolean).length
          const newSegs = (newPath || '').split('/').filter(Boolean).length
          tokenNavigationForwardRef.current = newSegs > prevSegs
          setTokenPath(newPath)
        }}
        backgroundAssets={backgroundAssets}
        currentBackground={background}
        selectedAsset={selectedAsset}
        selectedType={selectedType}
        isEraserActive={isEraserActive}
        hasMapElements={mapElements.length > 0}
        isTokenEraserActive={isTokenEraserActive}
        hasTokens={tokens.length > 0}
        fogOfWar={fogOfWar}
        fogEditMode={fogEditMode}
        fogRevealMode={fogRevealMode}
        fogBrushSize={fogBrushSize}
        fogGmOpacity={fogGmOpacity}
        onToggleFog={handleToggleFog}
        onToggleFogEdit={handleToggleFogEdit}
        onSetFogRevealMode={setFogRevealMode}
        onSetFogBrushSize={setFogBrushSize}
        onSetFogGmOpacity={setFogGmOpacity}
        onFogRevealAll={handleFogRevealAll}
        onFogHideAll={handleFogHideAll}
        onSelectAsset={handleSelectAsset}
        onToggleEraser={handleToggleEraser}
        onToggleTokenEraser={handleToggleTokenEraser}
        onSetBackground={handleSetBackground}
        onRemoveBackground={handleRemoveBackground}
        onNudgeBackground={handleNudgeBackground}
        onScaleBackground={handleScaleBackground}
        onResetBackgroundPosition={handleResetBackgroundPosition}
        onResetBackgroundScale={handleResetBackgroundScale}
        onResetBackgroundAll={handleResetBackgroundAll}
        onToggleGridHidden={handleToggleGridHidden}
        onClear={handleClear}
        basePath={ASSET_BASE}
        zoomLevel={zoomLevel}
        onZoomChange={handleZoomChange}
        scenes={scenes}
        activeSceneId={activeSceneId}
        onSwitchScene={handleSwitchScene}
        onCreateScene={handleCreateScene}
        onDeleteScene={handleDeleteScene}
        onRenameScene={handleRenameScene}
        onDuplicateScene={handleDuplicateScene}
        pingMode={pingMode}
        onTogglePing={handleTogglePing}
        activePing={activePing}
        onClearPing={handleClearPing}
        onDeselectAsset={handleDeselectAsset}
        onRefreshMapAssets={refreshMapAssets}
        onRefreshTokenAssets={refreshTokenAssets}
        onRefreshBackgroundAssets={refreshBackgroundAssets}
        onRefreshPapers={refreshPapers}
      />
      
      <main className="main-content">
        <Grid
          ref={gridContainerRef}
          isGameMaster={isGameMaster}
          background={background}
          mapElements={mapElements}
          tokens={tokens}
          selectedAsset={selectedAsset}
          selectedType={selectedType}
          isEraserActive={isEraserActive}
          isTokenEraserActive={isTokenEraserActive}
          fogBitmap={fogBitmap}
          fogEnabled={fogOfWar.enabled}
          fogEditMode={fogEditMode}
          fogRevealMode={fogRevealMode}
          fogBrushSize={fogBrushSize}
          fogGmOpacity={fogGmOpacity}
          onFogBitmapChange={handleFogBitmapChange}
          onCellClick={handleCellClick}
          onTokenMove={handleTokenMove}
          onTokenUpdate={handleTokenUpdate}
          onRemoveMapElement={handleRemoveMapElement}
          onRemoveToken={handleRemoveToken}
          onDuplicateToken={handleDuplicateToken}
          onDropPlace={handleDropOnGrid}
          onDeselectPlacement={handleDeselectAsset}
          basePath={ASSET_BASE}
          zoomLevel={zoomLevel}
          pingMode={pingMode}
          pingAnimation={pingAnimation}
          onSendPing={handleSendPing}
        />
      </main>

      <DicePanel
        isOpen={dicePanelOpen}
        onToggle={() => setDicePanelOpen(prev => !prev)}
        rollHistory={rollHistory}
        onRoll={handleDiceRoll}
        pendingL5RRoll={pendingL5RRoll}
        onL5RRollConsumed={() => setPendingL5RRoll(null)}
        isGameMaster={isGameMaster}
        onClearHistory={handleClearRollHistory}
      />

      <RollSnackbarContainer
        rollHistory={rollHistory}
        dicePanelOpen={dicePanelOpen}
        rollsHydrated={rollsHydrated}
      />

      <BottomPanel
        activeTab={bottomPanelTab}
        onTabChange={setBottomPanelTab}
        sharedCounters={sharedCounters}
        serverNow={serverNow}
        isGameMaster={isGameMaster}
        apiBase={API_BASE}
        onCountersMutation={handleCountersMutation}
        ttrpgManager={ttrpgManager}
        onTtrpgStatusChange={handleTtrpgStatusChange}
      />

      {DEBUG_MODE && <DebugOverlay />}
    </div>
  )
}

export default App