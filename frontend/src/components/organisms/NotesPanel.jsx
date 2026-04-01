import React, { useState, useCallback, useEffect } from 'react'
import NoteEditor from '../molecules/NoteEditor'
import { useNotesTemplate } from '../../contexts/NotesTemplateContext'
import { readNoteStorageData } from '../../utils/noteTemplateMeta'
import { t } from '../../lang'

const STORAGE_KEY_CONFIG = 'vtt_notes_config'

function normalizeConfig(raw) {
  const editorIds =
    raw?.editorIds && Array.isArray(raw.editorIds) && raw.editorIds.length > 0
      ? raw.editorIds
      : ['1']
  let primaryId =
    raw?.primaryId && editorIds.includes(raw.primaryId) ? raw.primaryId : editorIds[0]
  let secondaryId =
    raw?.secondaryId && editorIds.includes(raw.secondaryId) ? raw.secondaryId : null
  if (secondaryId === primaryId) {
    secondaryId = editorIds.find((id) => id !== primaryId) ?? null
  }
  let splitView = !!raw?.splitView && editorIds.length > 1
  const sidebarOpen = raw?.sidebarOpen !== false
  if (splitView && !secondaryId && editorIds.length >= 2) {
    secondaryId = editorIds.find((id) => id !== primaryId) ?? null
  }
  if (!secondaryId) splitView = false
  return { editorIds, primaryId, secondaryId, splitView, sidebarOpen }
}

function listLabel(noteId, sources) {
  const fromCtx = sources?.find((s) => s.noteId === noteId)?.title?.trim()
  if (fromCtx) return fromCtx
  const stored = readNoteStorageData(noteId)?.title?.trim()
  if (stored) return stored
  return t('notes.listUntitled')
}

function NotesPanel() {
  const { setNoteOrder, sources } = useNotesTemplate() || {}

  const [editorIds, setEditorIds] = useState(['1'])
  const [primaryId, setPrimaryId] = useState('1')
  const [secondaryId, setSecondaryId] = useState(null)
  const [splitView, setSplitView] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(true)
  const [splitAllowed, setSplitAllowed] = useState(true)
  const [expandedNoteId, setExpandedNoteId] = useState(null)

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 640px)')
    const apply = () => {
      const narrow = mq.matches
      setSplitAllowed(!narrow)
      if (narrow) setSplitView(false)
    }
    apply()
    mq.addEventListener('change', apply)
    return () => mq.removeEventListener('change', apply)
  }, [])

  useEffect(() => {
    if (!splitView || !splitAllowed) {
      setExpandedNoteId(null)
    }
  }, [splitView, splitAllowed])

  useEffect(() => {
    const saved = localStorage.getItem(STORAGE_KEY_CONFIG)
    if (saved) {
      try {
        const parsed = JSON.parse(saved)
        const n = normalizeConfig(parsed)
        setEditorIds(n.editorIds)
        setPrimaryId(n.primaryId)
        setSecondaryId(n.secondaryId)
        setSplitView(n.splitView)
        setSidebarOpen(n.sidebarOpen)
        const migrated = JSON.stringify({
          editorIds: n.editorIds,
          primaryId: n.primaryId,
          secondaryId: n.secondaryId,
          splitView: n.splitView,
          sidebarOpen: n.sidebarOpen,
        })
        if (migrated !== saved) {
          localStorage.setItem(STORAGE_KEY_CONFIG, migrated)
        }
      } catch {
        // ignore
      }
    }
  }, [])

  useEffect(() => {
    setNoteOrder?.(editorIds)
  }, [editorIds, setNoteOrder])

  const persistToDisk = useCallback((next) => {
    localStorage.setItem(STORAGE_KEY_CONFIG, JSON.stringify(next))
  }, [])

  const applyConfig = useCallback(
    (next) => {
      persistToDisk(next)
      setEditorIds(next.editorIds)
      setPrimaryId(next.primaryId)
      setSecondaryId(next.secondaryId)
      setSplitView(next.splitView)
      setSidebarOpen(next.sidebarOpen)
    },
    [persistToDisk]
  )

  const selectPrimary = useCallback(
    (id) => {
      if (!editorIds.includes(id)) return
      let sec = secondaryId
      if (sec === id) {
        sec = editorIds.find((eid) => eid !== id) ?? null
      }
      const nextSplit = splitView && splitAllowed && !!sec && sec !== id
      const narrow =
        typeof window !== 'undefined' && window.matchMedia('(max-width: 640px)').matches
      applyConfig({
        editorIds,
        primaryId: id,
        secondaryId: nextSplit ? sec : null,
        splitView: nextSplit,
        sidebarOpen: narrow ? false : sidebarOpen,
      })
    },
    [editorIds, secondaryId, splitView, splitAllowed, sidebarOpen, applyConfig]
  )

  const assignToLeftSlot = useCallback(
    (id) => {
      if (!editorIds.includes(id)) return
      if (primaryId === id) {
        setExpandedNoteId(null)
        return
      }
      const np = id
      let ns = secondaryId
      if (ns === np) {
        ns = primaryId !== np ? primaryId : editorIds.find((x) => x !== np) ?? null
      }
      if (ns === np) {
        ns = editorIds.find((x) => x !== np) ?? null
      }
      applyConfig({
        editorIds,
        primaryId: np,
        secondaryId: ns,
        splitView: true,
        sidebarOpen,
      })
      setExpandedNoteId(null)
    },
    [editorIds, primaryId, secondaryId, sidebarOpen, applyConfig]
  )

  const assignToRightSlot = useCallback(
    (id) => {
      if (!editorIds.includes(id)) return
      if (secondaryId === id) {
        setExpandedNoteId(null)
        return
      }
      const ns = id
      let np = primaryId
      if (np === ns) {
        np = secondaryId !== ns ? secondaryId : editorIds.find((x) => x !== ns) ?? null
      }
      if (np === ns) {
        np = editorIds.find((x) => x !== ns) ?? null
      }
      applyConfig({
        editorIds,
        primaryId: np,
        secondaryId: ns,
        splitView: true,
        sidebarOpen,
      })
      setExpandedNoteId(null)
    },
    [editorIds, primaryId, secondaryId, sidebarOpen, applyConfig]
  )

  const handleNoteRowClick = useCallback(
    (id) => {
      if (splitView && splitAllowed) {
        setExpandedNoteId((prev) => (prev === id ? null : id))
        return
      }
      selectPrimary(id)
    },
    [splitView, splitAllowed, selectPrimary]
  )

  const handleAddNotepad = useCallback(() => {
    const newId = Date.now().toString()
    const newIds = [...editorIds, newId]
    let sec = secondaryId
    if (splitView && splitAllowed) {
      if (!sec || !newIds.includes(sec)) {
        sec = newIds.find((eid) => eid !== newId) ?? null
      }
    } else {
      sec = null
    }
    const nextSplit = splitView && splitAllowed && !!sec && sec !== newId
    applyConfig({
      editorIds: newIds,
      primaryId: newId,
      secondaryId: nextSplit ? sec : null,
      splitView: nextSplit,
      sidebarOpen,
    })
  }, [editorIds, secondaryId, splitView, splitAllowed, sidebarOpen, applyConfig])

  const removeNotepadInternal = useCallback(
    (id) => {
      if (editorIds.length <= 1) return
      const newIds = editorIds.filter((eid) => eid !== id)
      let np = primaryId === id ? newIds[0] : primaryId
      let ns = secondaryId
      if (secondaryId === id) {
        ns = newIds.find((i) => i !== np) ?? null
      }
      if (ns === np) ns = newIds.find((i) => i !== np) ?? null
      const nextSplit = splitView && splitAllowed && !!ns && ns !== np
      applyConfig({
        editorIds: newIds,
        primaryId: np,
        secondaryId: nextSplit ? ns : null,
        splitView: nextSplit,
        sidebarOpen,
      })
      localStorage.removeItem(`vtt_notes_${id}`)
      setExpandedNoteId((ex) => (ex === id ? null : ex))
    },
    [editorIds, primaryId, secondaryId, splitView, splitAllowed, sidebarOpen, applyConfig]
  )

  const handleRemoveFromSidebar = useCallback(
    (e, id) => {
      e.stopPropagation()
      if (editorIds.length <= 1) return
      if (!confirm(t('notes.removeConfirm'))) return
      removeNotepadInternal(id)
    },
    [editorIds, removeNotepadInternal]
  )

  const handleRemoveFromEditor = useCallback(
    (id) => {
      removeNotepadInternal(id)
    },
    [removeNotepadInternal]
  )

  const handleSplitToggle = useCallback(
    (enabled) => {
      if (!splitAllowed && enabled) return
      setExpandedNoteId(null)
      if (!enabled) {
        applyConfig({
          editorIds,
          primaryId,
          secondaryId: null,
          splitView: false,
          sidebarOpen,
        })
        return
      }
      const other = editorIds.find((i) => i !== primaryId) ?? null
      applyConfig({
        editorIds,
        primaryId,
        secondaryId: other,
        splitView: !!other,
        sidebarOpen,
      })
    },
    [splitAllowed, editorIds, primaryId, sidebarOpen, applyConfig]
  )

  const toggleSidebar = useCallback(() => {
    applyConfig({
      editorIds,
      primaryId,
      secondaryId,
      splitView,
      sidebarOpen: !sidebarOpen,
    })
  }, [applyConfig, editorIds, primaryId, secondaryId, splitView, sidebarOpen])

  const visibleIds =
    splitView && splitAllowed && secondaryId && secondaryId !== primaryId
      ? [primaryId, secondaryId]
      : [primaryId]

  return (
    <div className="notes-panel">
      <div className={`notes-sidebar ${sidebarOpen ? 'open' : 'closed'}`}>
        <div className="notes-sidebar-header">
          <span className="notes-sidebar-title">📝 {t('notes.title')}</span>
        </div>
        <div className="notes-sidebar-list">
          {editorIds.map((id) => {
            const showPicker = splitView && splitAllowed && expandedNoteId === id
            const isSingleActive = !splitView && primaryId === id
            const isLeft = splitView && splitAllowed && primaryId === id
            const isRight = splitView && splitAllowed && secondaryId === id
            return (
              <div key={id} className="notes-item-wrap">
                <button
                  type="button"
                  className={`notes-item${isSingleActive ? ' active' : ''}${
                    isLeft ? ' is-left-slot' : ''
                  }${isRight ? ' is-right-slot' : ''}${showPicker ? ' is-slot-picker-parent' : ''}`}
                  onClick={() => handleNoteRowClick(id)}
                  title={listLabel(id, sources)}
                >
                  <span className="notes-item-icon">📋</span>
                  <span className="notes-item-name">{listLabel(id, sources)}</span>
                  {splitView && splitAllowed && (
                    <span className="notes-item-slot-badges" aria-hidden="true">
                      {isLeft && <span className="notes-slot-badge notes-slot-badge--left">L</span>}
                      {isRight && <span className="notes-slot-badge notes-slot-badge--right">R</span>}
                    </span>
                  )}
                  {editorIds.length > 1 && (
                    <span
                      className="notes-item-remove"
                      onClick={(e) => handleRemoveFromSidebar(e, id)}
                      title={t('notes.remove')}
                      role="presentation"
                    >
                      ✕
                    </span>
                  )}
                </button>
                {showPicker && (
                  <div className="notes-item-slot-picker">
                    <button
                      type="button"
                      className="notes-slot-btn notes-slot-btn--left"
                      onClick={(e) => {
                        e.stopPropagation()
                        assignToLeftSlot(id)
                      }}
                    >
                      {t('notes.slotLeft')}
                    </button>
                    <button
                      type="button"
                      className="notes-slot-btn notes-slot-btn--right"
                      onClick={(e) => {
                        e.stopPropagation()
                        assignToRightSlot(id)
                      }}
                    >
                      {t('notes.slotRight')}
                    </button>
                  </div>
                )}
              </div>
            )
          })}
        </div>
        <button type="button" className="notes-add-btn notes-sidebar-add" onClick={handleAddNotepad}>
          + {t('notes.addEditor')}
        </button>

        {splitAllowed && editorIds.length >= 2 && (
          <div className="notes-sidebar-split">
            <div className="notes-sidebar-split-label">{t('notes.viewMode')}</div>
            <div className="notes-sidebar-split-toggle">
              <button
                type="button"
                className={!splitView ? 'active' : ''}
                onClick={() => handleSplitToggle(false)}
              >
                {t('notes.viewSingle')}
              </button>
              <button
                type="button"
                className={splitView ? 'active' : ''}
                onClick={() => handleSplitToggle(true)}
              >
                {t('notes.viewSplit')}
              </button>
            </div>
            {splitView && (
              <p className="notes-split-pick-hint">{t('notes.splitPickHint')}</p>
            )}
          </div>
        )}
        {!splitAllowed && editorIds.length >= 2 && (
          <p className="notes-split-narrow-hint">{t('notes.splitNarrowHint')}</p>
        )}
      </div>

      <button
        type="button"
        className={`notes-sidebar-toggle ${sidebarOpen ? 'open' : ''}`}
        onClick={toggleSidebar}
        title={sidebarOpen ? t('pdf.collapseSidebar') : t('pdf.expandSidebar')}
      >
        {sidebarOpen ? '◀' : '▶'}
      </button>

      <div className="notes-main-area">
        <div
          className={
            visibleIds.length > 1 ? 'notes-editors-split' : 'notes-editors-single'
          }
        >
          {visibleIds.map((id) => (
            <NoteEditor
              key={id}
              id={id}
              noteIndex={editorIds.indexOf(id) + 1}
              onRemove={handleRemoveFromEditor}
              canRemove={editorIds.length > 1}
            />
          ))}
        </div>
      </div>
    </div>
  )
}

export default NotesPanel
