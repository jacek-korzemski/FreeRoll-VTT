import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import ConfirmModal from '../atoms/ConfirmModal'
import AdventureEditor from '../molecules/AdventureEditor'
import AdventurePreviewBox from '../molecules/AdventurePreviewBox'
import { t } from '../../lang'
import { getTableItem, setTableItem } from '../../utils/tableStorage'

const TABS_KEY = 'vtt_adventure_tabs'

async function adventureFetch(apiBase, action, { method = 'GET', body } = {}) {
  const response = await fetch(`${apiBase}?action=${action}`, {
    method,
    credentials: 'include',
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
    body: body ? JSON.stringify(body) : undefined,
  })
  let data = {}
  try {
    data = await response.json()
  } catch {
    data = {}
  }
  if (!response.ok || data.success === false) {
    const error = new Error(data.error || 'Request failed')
    error.status = response.status
    throw error
  }
  return data
}

async function readAdventureImport(response) {
  const text = await response.text()
  try {
    return JSON.parse(text)
  } catch {
    const start = text.indexOf('{')
    const end = text.lastIndexOf('}')
    if (start >= 0 && end > start) {
      try {
        return JSON.parse(text.slice(start, end + 1))
      } catch {
        return {}
      }
    }
    return {}
  }
}

function readTabs() {
  try {
    const parsed = JSON.parse(getTableItem(TABS_KEY) || 'null')
    if (!parsed || !Array.isArray(parsed.tabIds)) return { tabIds: [], activeId: null }
    return {
      tabIds: parsed.tabIds.map((id) => Number(id)).filter((id) => id > 0),
      activeId: Number(parsed.activeId) || null,
    }
  } catch {
    return { tabIds: [], activeId: null }
  }
}

function AdventurePanel({ apiBase, assetBase, selectedAsset, selectedType, onAssetsChanged }) {
  const [entries, setEntries] = useState([])
  const [tabs, setTabs] = useState([])
  const [activeId, setActiveId] = useState(null)
  const [htmlById, setHtmlById] = useState({})
  const [status, setStatus] = useState('idle')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [renamingId, setRenamingId] = useState(null)
  const [draftTitle, setDraftTitle] = useState('')
  const [boxesByTab, setBoxesByTab] = useState({})
  const stageRef = useRef(null)
  const listRef = useRef(null)
  const dragIdRef = useRef(null)
  const dropHintRef = useRef(null)
  const suppressClickRef = useRef(false)
  const zRef = useRef(4)
  const boxSeq = useRef(1)
  const saveTimer = useRef(null)
  const pendingSave = useRef(null)
  const htmlRef = useRef({})
  const importRef = useRef(null)
  const [ioBusy, setIoBusy] = useState(false)
  const [clearOpen, setClearOpen] = useState(false)

  const knownIds = useMemo(() => new Set(entries.map((entry) => String(entry.id))), [entries])

  const flushSave = useCallback(async () => {
    const job = pendingSave.current
    if (!job) return true
    pendingSave.current = null
    setStatus('saving')
    try {
      const payload = { id: job.id, title: job.title }
      if (job.html !== undefined) payload.bodyHtml = job.html
      const data = await adventureFetch(apiBase, 'adventure-save', { method: 'POST', body: payload })
      setEntries((prev) => prev.map((entry) => (
        entry.id === data.entry.id
          ? { ...entry, title: data.entry.title, sortOrder: data.entry.sortOrder, updatedAt: data.entry.updatedAt }
          : entry
      )))
      setStatus(pendingSave.current ? 'dirty' : 'saved')
      return true
    } catch (err) {
      setStatus('error')
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.saveError'))
      return false
    }
  }, [apiBase])

  const queueSave = useCallback((id, title, html) => {
    pendingSave.current = { id, title, html }
    setStatus('dirty')
    clearTimeout(saveTimer.current)
    saveTimer.current = setTimeout(() => { flushSave() }, 700)
  }, [flushSave])

  useEffect(() => () => {
    clearTimeout(saveTimer.current)
    const job = pendingSave.current
    if (!job) return
    const payload = { id: job.id, title: job.title }
    if (job.html !== undefined) payload.bodyHtml = job.html
    fetch(`${apiBase}?action=adventure-save`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      keepalive: true,
    }).catch(() => {})
  }, [apiBase])

  const rememberHtml = useCallback((id, html) => {
    htmlRef.current[id] = html
    setHtmlById((prev) => (prev[id] === html ? prev : { ...prev, [id]: html }))
  }, [])

  const ensureHtml = useCallback(async (id) => {
    if (Object.prototype.hasOwnProperty.call(htmlRef.current, id)) return htmlRef.current[id]
    const data = await adventureFetch(apiBase, `adventure-entry&id=${id}`)
    rememberHtml(id, data.entry.bodyHtml || '')
    return data.entry.bodyHtml || ''
  }, [apiBase, rememberHtml])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError('')
    adventureFetch(apiBase, 'adventure-list')
      .then(async (data) => {
        if (cancelled) return
        const list = data.entries || []
        setEntries(list)
        const saved = readTabs()
        const valid = new Set(list.map((entry) => entry.id))
        let tabIds = saved.tabIds.filter((id) => valid.has(id))
        let nextActive = valid.has(saved.activeId) ? saved.activeId : null
        if (tabIds.length === 0 && list[0]) {
          tabIds = [list[0].id]
          nextActive = list[0].id
        }
        if (!nextActive && tabIds[0]) nextActive = tabIds[0]
        if (nextActive && !tabIds.includes(nextActive)) tabIds = [...tabIds, nextActive]
        setTabs(tabIds)
        setActiveId(nextActive)
        if (nextActive) await ensureHtml(nextActive)
      })
      .catch((err) => {
        if (cancelled) return
        setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.loadError'))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => { cancelled = true }
  }, [apiBase, ensureHtml])

  useEffect(() => {
    if (loading) return
    setTableItem(TABS_KEY, JSON.stringify({ tabIds: tabs, activeId }))
  }, [tabs, activeId, loading])

  useEffect(() => {
    if (!activeId || Object.prototype.hasOwnProperty.call(htmlRef.current, activeId)) return
    ensureHtml(activeId).catch((err) => {
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.loadError'))
    })
  }, [activeId, ensureHtml])

  const openTab = (id) => {
    setTabs((prev) => (prev.includes(id) ? prev : [...prev, id]))
    setActiveId(id)
  }

  const closeTab = (id) => {
    setTabs((prev) => {
      const next = prev.filter((tabId) => tabId !== id)
      if (activeId === id) setActiveId(next[next.length - 1] || null)
      return next
    })
    setBoxesByTab((prev) => {
      if (!prev[id]) return prev
      const next = { ...prev }
      delete next[id]
      return next
    })
  }

  const handleHtmlChange = (html) => {
    if (!activeId) return
    rememberHtml(activeId, html)
    const title = entries.find((entry) => entry.id === activeId)?.title || t('adventure.untitled')
    queueSave(activeId, title, html)
  }

  const exportAdventure = async () => {
    if (ioBusy) return
    setIoBusy(true)
    setError('')
    try {
      clearTimeout(saveTimer.current)
      const saved = await flushSave()
      if (!saved) return
      const response = await fetch(`${apiBase}?action=adventure-export`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tabs: { tabIds: tabs, activeId } }),
      })
      const type = response.headers.get('content-type') || ''
      if (!response.ok || type.includes('application/json')) {
        let data = {}
        try {
          data = await response.json()
        } catch {
          data = {}
        }
        const error = new Error(data.error || 'Request failed')
        error.status = response.status
        error.code = data.code
        throw error
      }
      const blob = await response.blob()
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = 'adventure.zip'
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch (err) {
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.exportError'))
    } finally {
      setIoBusy(false)
    }
  }

  const importAdventure = async (file) => {
    if (!file || ioBusy) return
    if (!window.confirm(t('adventure.importConfirm'))) return
    setIoBusy(true)
    setError('')
    clearTimeout(saveTimer.current)
    pendingSave.current = null
    try {
      const body = new FormData()
      body.append('file', file)
      const response = await fetch(`${apiBase}?action=adventure-import`, {
        method: 'POST',
        credentials: 'include',
        body,
      })
      const data = await readAdventureImport(response)
      if (!response.ok || data.success !== true || !Array.isArray(data.entries)) {
        const error = new Error(data.error || 'Request failed')
        error.status = response.status
        error.code = data.code
        throw error
      }
      const list = data.entries || []
      htmlRef.current = {}
      setHtmlById({})
      setBoxesByTab({})
      setEntries(list)
      setRenamingId(null)
      const nextTabs = Array.isArray(data.tabs?.tabIds)
        ? data.tabs.tabIds.map((id) => Number(id)).filter((id) => id > 0)
        : []
      let nextActive = Number(data.tabs?.activeId) || null
      if (!nextTabs.length && list[0]) {
        nextTabs.push(list[0].id)
        nextActive = list[0].id
      }
      if (nextActive && !nextTabs.includes(nextActive)) nextTabs.push(nextActive)
      setTabs(nextTabs)
      setActiveId(nextActive)
      setStatus('saved')
      if (nextActive) await ensureHtml(nextActive)
      if (typeof onAssetsChanged === 'function') onAssetsChanged()
    } catch (err) {
      if (err.status === 403) setError(t('adventure.forbidden'))
      else if (err.code === 'quota_exceeded') setError(t('adventure.importQuota'))
      else if (err.code === 'bad_package') setError(t('adventure.importBad'))
      else setError(t('adventure.importError'))
    } finally {
      setIoBusy(false)
    }
  }

  const createEntry = async () => {
    setError('')
    try {
      const data = await adventureFetch(apiBase, 'adventure-create', {
        method: 'POST',
        body: { title: t('adventure.untitled') },
      })
      const entry = data.entry
      setEntries((prev) => [...prev, {
        id: entry.id,
        title: entry.title,
        sortOrder: entry.sortOrder,
        updatedAt: entry.updatedAt,
      }])
      rememberHtml(entry.id, entry.bodyHtml || '')
      openTab(entry.id)
      setRenamingId(entry.id)
      setDraftTitle(entry.title)
    } catch (err) {
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.saveError'))
    }
  }

  const startRename = (entry) => {
    setRenamingId(entry.id)
    setDraftTitle(entry.title)
  }

  const commitRename = (id) => {
    const title = draftTitle.trim() || t('adventure.untitled')
    setRenamingId(null)
    setEntries((prev) => prev.map((entry) => (entry.id === id ? { ...entry, title } : entry)))
    const html = Object.prototype.hasOwnProperty.call(htmlRef.current, id) ? htmlRef.current[id] : undefined
    queueSave(id, title, html)
  }

  const removeEntry = async (entry) => {
    if (!window.confirm(t('adventure.deleteConfirm'))) return
    setError('')
    try {
      await adventureFetch(apiBase, 'adventure-delete', { method: 'POST', body: { id: entry.id } })
      setEntries((prev) => prev.filter((item) => item.id !== entry.id))
      setTabs((prev) => prev.filter((id) => id !== entry.id))
      if (activeId === entry.id) {
        const rest = tabs.filter((id) => id !== entry.id)
        setActiveId(rest[rest.length - 1] || null)
      }
      delete htmlRef.current[entry.id]
      setHtmlById((prev) => {
        const next = { ...prev }
        delete next[entry.id]
        return next
      })
      setBoxesByTab((prev) => {
        const next = {}
        Object.entries(prev).forEach(([tabId, list]) => {
          if (Number(tabId) === entry.id) return
          const kept = list.filter((box) => box.entryId !== entry.id)
          if (kept.length) next[tabId] = kept
        })
        return next
      })
    } catch (err) {
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.saveError'))
    }
  }

  const clearAdventure = async () => {
    setClearOpen(false)
    setError('')
    clearTimeout(saveTimer.current)
    pendingSave.current = null
    try {
      await adventureFetch(apiBase, 'adventure-clear', { method: 'POST', body: {} })
      htmlRef.current = {}
      setHtmlById({})
      setBoxesByTab({})
      setEntries([])
      setTabs([])
      setActiveId(null)
      setRenamingId(null)
      setStatus('saved')
    } catch (err) {
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.saveError'))
    }
  }

  const clearDropMarks = () => {
    dropHintRef.current?.li.classList.remove('drop-before', 'drop-after')
    dropHintRef.current = null
    listRef.current?.querySelectorAll('.dragging').forEach((item) => item.classList.remove('dragging'))
  }

  const markDrop = (li, place) => {
    const prev = dropHintRef.current
    if (prev?.li === li && prev.place === place) return
    prev?.li.classList.remove('drop-before', 'drop-after')
    li.classList.remove('drop-before', 'drop-after')
    li.classList.add(place === 'before' ? 'drop-before' : 'drop-after')
    dropHintRef.current = { li, place }
  }

  const scrollListWhileDragging = (clientY) => {
    const list = listRef.current
    if (!list) return
    const rect = list.getBoundingClientRect()
    if (clientY < rect.top + 28) list.scrollTop -= 14
    else if (clientY > rect.bottom - 28) list.scrollTop += 14
  }

  const reorderEntry = async (fromId, toId, place) => {
    const from = entries.findIndex((entry) => entry.id === fromId)
    if (from < 0 || fromId === toId) return
    const next = entries.slice()
    const [item] = next.splice(from, 1)
    let insertAt = next.findIndex((entry) => entry.id === toId)
    if (insertAt < 0) return
    if (place === 'after') insertAt += 1
    if (insertAt === from) return
    next.splice(insertAt, 0, item)
    setEntries(next)
    try {
      const data = await adventureFetch(apiBase, 'adventure-reorder', {
        method: 'POST',
        body: { ids: next.map((entry) => entry.id) },
      })
      setEntries(data.entries || next)
    } catch (err) {
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.saveError'))
    }
  }

  const placeBox = (entryId, event, tabId) => {
    if (tabId == null) return
    const stage = stageRef.current
    const rect = stage ? stage.getBoundingClientRect() : { left: 0, top: 0, width: 800, height: 600 }
    const width = Math.min(420, Math.max(220, rect.width - 16))
    let left = event.clientX - rect.left + 12
    let top = event.clientY - rect.top + 12
    if (left + width > rect.width - 8) left = Math.max(8, rect.width - width - 8)
    if (top + 280 > rect.height - 8) top = Math.max(8, rect.height - 300)
    setBoxesByTab((prev) => {
      const list = prev[tabId] || []
      if (list.some((box) => box.entryId === entryId)) {
        zRef.current += 1
        return {
          ...prev,
          [tabId]: list.map((box) => (box.entryId === entryId ? { ...box, zIndex: zRef.current } : box)),
        }
      }
      const stack = list.length * 16
      zRef.current += 1
      return {
        ...prev,
        [tabId]: [...list, {
          uid: boxSeq.current++,
          entryId,
          left: left + stack,
          top: top + stack,
          zIndex: zRef.current,
        }],
      }
    })
  }

  const openPreview = async (entryId, event) => {
    const tabId = activeId
    if (tabId == null || !knownIds.has(String(entryId))) return
    try {
      await ensureHtml(entryId)
      placeBox(entryId, event, tabId)
    } catch (err) {
      setError(err.status === 403 ? t('adventure.forbidden') : t('adventure.loadError'))
    }
  }

  const clampBox = (left, top) => {
    const stage = stageRef.current
    if (!stage) return { left, top }
    return {
      left: Math.min(Math.max(8, left), Math.max(8, stage.clientWidth - 80)),
      top: Math.min(Math.max(8, top), Math.max(8, stage.clientHeight - 40)),
    }
  }

  const activeLoaded = activeId != null && Object.prototype.hasOwnProperty.call(htmlById, activeId)
  const statusLabel = status === 'saving'
    ? t('adventure.saving')
    : status === 'saved'
      ? t('adventure.saved')
      : status === 'error'
        ? t('adventure.saveError')
        : ''

  return (
    <div className="adventure-panel">
      <aside className="adventure-list">
        <div className="adventure-list-header">
          <h2>{t('adventure.title')}</h2>
          <button type="button" onClick={createEntry} title={t('adventure.newEntry')}>+</button>
          <div className="adventure-list-actions">
            <button type="button" onClick={exportAdventure} disabled={ioBusy || loading} title={t('adventure.export')}>
              {t('adventure.export')}
            </button>
            <button type="button" onClick={() => importRef.current?.click()} disabled={ioBusy || loading} title={t('adventure.import')}>
              {t('adventure.import')}
            </button>
            <input
              ref={importRef}
              type="file"
              accept=".zip,application/zip"
              hidden
              onChange={(event) => {
                const file = event.target.files?.[0]
                event.target.value = ''
                if (file) importAdventure(file)
              }}
            />
          </div>
        </div>
        {loading && <p className="adventure-status">{t('adventure.loading')}</p>}
        {!loading && entries.length === 0 && <p className="adventure-status">{t('adventure.empty')}</p>}
        <div
          className="adventure-list-scroll"
          ref={listRef}
          onDragOver={(event) => {
            if (dragIdRef.current == null) return
            event.preventDefault()
            scrollListWhileDragging(event.clientY)
          }}
        >
        <ul>
          {entries.map((entry) => (
            <li
              key={entry.id}
              className={entry.id === activeId ? 'active' : ''}
              onDragOver={(event) => {
                if (dragIdRef.current == null) return
                event.preventDefault()
                event.dataTransfer.dropEffect = 'move'
                const rect = event.currentTarget.getBoundingClientRect()
                const place = event.clientY < rect.top + rect.height / 2 ? 'before' : 'after'
                markDrop(event.currentTarget, place)
                scrollListWhileDragging(event.clientY)
              }}
              onDrop={(event) => {
                event.preventDefault()
                const fromId = dragIdRef.current
                const place = dropHintRef.current?.li === event.currentTarget
                  ? dropHintRef.current.place
                  : 'before'
                clearDropMarks()
                dragIdRef.current = null
                if (fromId != null) reorderEntry(fromId, entry.id, place)
              }}
            >
              {renamingId === entry.id ? (
                <input
                  className="adventure-rename"
                  value={draftTitle}
                  autoFocus
                  onChange={(event) => setDraftTitle(event.target.value)}
                  onBlur={() => commitRename(entry.id)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') event.currentTarget.blur()
                    if (event.key === 'Escape') {
                      setRenamingId(null)
                    }
                  }}
                />
              ) : (
                <button
                  type="button"
                  className="adventure-list-name"
                  draggable
                  onClick={() => {
                    if (suppressClickRef.current) return
                    openTab(entry.id)
                  }}
                  onDoubleClick={() => startRename(entry)}
                  onDragStart={(event) => {
                    dragIdRef.current = entry.id
                    suppressClickRef.current = true
                    event.dataTransfer.effectAllowed = 'move'
                    event.dataTransfer.setData('text/plain', String(entry.id))
                    event.currentTarget.closest('li')?.classList.add('dragging')
                  }}
                  onDragEnd={() => {
                    dragIdRef.current = null
                    clearDropMarks()
                    setTimeout(() => { suppressClickRef.current = false }, 0)
                  }}
                  title={entry.title}
                >
                  {entry.title}
                </button>
              )}
              <button type="button" onClick={() => startRename(entry)} title={t('adventure.rename')}>✎</button>
              <button type="button" onClick={() => removeEntry(entry)} title={t('adventure.delete')}>×</button>
            </li>
          ))}
        </ul>
        <div className="adventure-list-clear">
          <hr />
          <button
            type="button"
            onClick={() => setClearOpen(true)}
            disabled={loading || entries.length === 0}
          >
            {t('adventure.clear')}
          </button>
        </div>
        </div>
      </aside>
      <div className="adventure-stage" ref={stageRef}>
        <div className="adventure-tabs">
          {tabs.map((id) => {
            const entry = entries.find((item) => item.id === id)
            if (!entry) return null
            return (
              <div key={id} className={`adventure-tab${id === activeId ? ' active' : ''}`}>
                <button type="button" onClick={() => setActiveId(id)}>{entry.title}</button>
                <button type="button" onClick={() => closeTab(id)} title={t('adventure.closeTab')}>×</button>
              </div>
            )
          })}
          {statusLabel && <span className={`adventure-save adventure-save--${status}`}>{statusLabel}</span>}
        </div>
        {error && <p className="adventure-error">{error}</p>}
        <div className="adventure-editor-wrap">
          {activeLoaded && (
            <AdventureEditor
              key={activeId}
              entryId={activeId}
              initialHtml={htmlById[activeId] || ''}
              assetBase={assetBase}
              entries={entries}
              knownIds={knownIds}
              selectedAsset={selectedAsset}
              selectedType={selectedType}
              onHtmlChange={handleHtmlChange}
              onOpenPreview={openPreview}
            />
          )}
        </div>
        {(boxesByTab[activeId] || []).map((box) => {
          const entry = entries.find((item) => item.id === box.entryId)
          if (!entry) return null
          const tabId = activeId
          return (
            <AdventurePreviewBox
              key={box.uid}
              title={entry.title}
              html={htmlById[box.entryId] || ''}
              assetBase={assetBase}
              knownIds={[...knownIds]}
              left={box.left}
              top={box.top}
              zIndex={box.zIndex}
              onClose={() => setBoxesByTab((prev) => ({
                ...prev,
                [tabId]: (prev[tabId] || []).filter((item) => item.uid !== box.uid),
              }))}
              onOpen={() => openTab(box.entryId)}
              onFocus={() => {
                zRef.current += 1
                const nextZ = zRef.current
                setBoxesByTab((prev) => ({
                  ...prev,
                  [tabId]: (prev[tabId] || []).map((item) => (
                    item.uid === box.uid ? { ...item, zIndex: nextZ } : item
                  )),
                }))
              }}
              onMove={(left, top) => {
                const clamped = clampBox(left, top)
                setBoxesByTab((prev) => ({
                  ...prev,
                  [tabId]: (prev[tabId] || []).map((item) => (
                    item.uid === box.uid ? { ...item, left: clamped.left, top: clamped.top } : item
                  )),
                }))
              }}
              onOpenLink={openPreview}
            />
          )
        })}
      </div>
      {clearOpen && (
        <ConfirmModal
          title={t('adventure.clearTitle')}
          message={t('adventure.clearConfirm')}
          confirmLabel={t('adventure.clearAction')}
          cancelLabel={t('notes.templateCancel')}
          danger
          onConfirm={clearAdventure}
          onCancel={() => setClearOpen(false)}
        />
      )}
    </div>
  )
}

export default AdventurePanel
