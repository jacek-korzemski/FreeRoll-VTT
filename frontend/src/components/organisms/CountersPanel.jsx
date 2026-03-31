import React, { useState, useEffect, useCallback, useRef, useMemo } from 'react'
import { t } from '../../lang'
import { getOrCreateClientId } from '../../utils/clientId'
import {
  timerDisplaySeconds,
  formatTimerMmSs,
  SYNC_DRIFT_THRESHOLD_SEC,
  timerAnchorKey,
} from '../../utils/counterTimer'

const STORAGE_PRIVATE = 'vtt_counters_private'

function loadPrivateCounters() {
  try {
    const raw = localStorage.getItem(STORAGE_PRIVATE)
    if (!raw) return []
    const arr = JSON.parse(raw)
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}

function savePrivateCounters(list) {
  try {
    localStorage.setItem(STORAGE_PRIVATE, JSON.stringify(list))
  } catch { /* ignore */ }
}

function emptyManual(id) {
  return {
    id,
    type: 'manual',
    title: '',
    notes: '',
    value: 0,
  }
}

function emptyTimer(id, direction, durationSec) {
  const now = Math.floor(Date.now() / 1000)
  if (direction === 'down') {
    return {
      id,
      type: 'timer',
      title: '',
      notes: '',
      direction: 'down',
      endsAt: now + durationSec,
      initialDurationSec: durationSec,
    }
  }
  return {
    id,
    type: 'timer',
    title: '',
    notes: '',
    direction: 'up',
    startedAt: now,
    durationSec,
  }
}

function CounterNotes({ notes, expanded, onToggle, readOnly, onChange, onNotesBlur }) {
  return (
    <div className="counters-notes-block">
      <button type="button" className="counters-notes-toggle" onClick={onToggle}>
        {expanded ? '▼' : '▶'} {t('counters.notes')}
      </button>
      {expanded && (
        readOnly ? (
          <div className="counters-notes-readonly">{notes || '—'}</div>
        ) : (
          <textarea
            className="counters-notes-input"
            value={notes}
            onChange={(e) => onChange(e.target.value)}
            onBlur={() => onNotesBlur?.()}
            rows={3}
            placeholder={t('counters.notesPlaceholder')}
          />
        )
      )}
    </div>
  )
}

export default function CountersPanel({
  sharedCounters = [],
  serverNow,
  isGameMaster = false,
  apiBase,
  onCountersMutation,
}) {
  const clientId = useMemo(() => getOrCreateClientId(), [])
  const [privateList, setPrivateList] = useState(() => loadPrivateCounters())
  const [tick, setTick] = useState(0)
  const [serverOffsetSec, setServerOffsetSec] = useState(0)
  const lastAnchorsRef = useRef({})
  const [createOpen, setCreateOpen] = useState(false)
  const [createType, setCreateType] = useState('manual')
  const [createDirection, setCreateDirection] = useState('down')
  const [createDurationMin, setCreateDurationMin] = useState(5)
  const [editTimer, setEditTimer] = useState(null)

  useEffect(() => {
    const id = setInterval(() => setTick((x) => x + 1), 1000)
    return () => clearInterval(id)
  }, [])

  useEffect(() => {
    if (serverNow == null || typeof serverNow !== 'number') return
    const clientNowSec = Math.floor(Date.now() / 1000)
    let newOffset = serverNow - clientNowSec
    let shouldSet = false
    for (const c of sharedCounters) {
      if (c.type !== 'timer') continue
      const ak = timerAnchorKey(c)
      const prev = lastAnchorsRef.current[c.id]
      if (prev !== ak) {
        lastAnchorsRef.current[c.id] = ak
        shouldSet = true
        newOffset = serverNow - clientNowSec
        break
      }
      const dl = timerDisplaySeconds(c, clientNowSec + serverOffsetSec)
      const ds = timerDisplaySeconds(c, serverNow)
      if (Math.abs(dl - ds) > SYNC_DRIFT_THRESHOLD_SEC) {
        shouldSet = true
        newOffset = serverNow - clientNowSec
        break
      }
    }
    if (shouldSet && newOffset !== serverOffsetSec) {
      setServerOffsetSec(newOffset)
    }
  }, [serverNow, sharedCounters, serverOffsetSec])

  const persistPrivate = useCallback((updater) => {
    setPrivateList((prev) => {
      const next = typeof updater === 'function' ? updater(prev) : updater
      savePrivateCounters(next)
      return next
    })
  }, [])

  const isOwner = (c) => c.ownerId === clientId

  const canEditShared = (c) => isOwner || isGameMaster

  const effectiveNowShared = () => Math.floor(Date.now() / 1000) + serverOffsetSec

  const post = useCallback(
    async (action, body) => {
      const res = await fetch(`${apiBase}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ ...body, clientId }),
      })
      const data = await res.json()
      if (data.success && onCountersMutation) {
        onCountersMutation(action, body, data)
      }
      return data
    },
    [apiBase, clientId, onCountersMutation]
  )

  const addPrivateManual = () => {
    const id = crypto.randomUUID()
    persistPrivate((prev) => [...prev, emptyManual(id)])
  }

  const addPrivateTimer = () => {
    const id = crypto.randomUUID()
    const sec = Math.max(1, createDurationMin * 60)
    persistPrivate((prev) => [...prev, emptyTimer(id, createDirection, sec)])
    setCreateOpen(false)
  }

  const addPrivateManualFromModal = () => {
    const id = crypto.randomUUID()
    persistPrivate((prev) => [...prev, emptyManual(id)])
    setCreateOpen(false)
  }

  const updatePrivate = (id, patch) => {
    persistPrivate((prev) => prev.map((c) => (c.id === id ? { ...c, ...patch } : c)))
  }

  const removePrivate = (id) => {
    persistPrivate((prev) => prev.filter((c) => c.id !== id))
  }

  const shareCounter = async (c) => {
    if (c.type === 'manual') {
      const data = await post('counter-add', {
        id: c.id,
        type: 'manual',
        title: c.title,
        notes: c.notes,
        value: c.value,
      })
      if (data.success) {
        removePrivate(c.id)
      }
    } else {
      const now = Math.floor(Date.now() / 1000)
      let payload = {
        id: c.id,
        type: 'timer',
        title: c.title,
        notes: c.notes,
        direction: c.direction,
      }
      if (c.direction === 'down') {
        const remaining = Math.max(0, Math.floor((c.endsAt || now) - now))
        payload.durationSec = remaining > 0 ? remaining : 60
        const init =
          Number(c.initialDurationSec) > 0 ? c.initialDurationSec : payload.durationSec
        payload.initialDurationSec = Math.max(1, Math.min(604800, init))
      } else {
        payload.durationSec = c.durationSec || 60
        payload.direction = 'up'
      }
      const data = await post('counter-add', payload)
      if (data.success) {
        removePrivate(c.id)
      }
    }
  }

  const unshareCounter = async (c) => {
    const data = await post('counter-delete', { id: c.id })
    if (data.success) {
      const { ownerId, ...rest } = c
      persistPrivate((prev) => [...prev, rest])
    }
  }

  const deleteSharedCounter = async (c) => {
    await post('counter-delete', { id: c.id })
  }

  const bumpManualShared = async (c, delta) => {
    const v = (c.value || 0) + delta
    await post('counter-update', { id: c.id, value: v })
  }

  const setManualSharedValue = async (c, value) => {
    const n = parseInt(String(value).trim(), 10)
    if (Number.isNaN(n)) return
    await post('counter-update', { id: c.id, value: n })
  }

  const openEditTimer = (c) => {
    const now = effectiveNowShared()
    if (c.direction === 'down') {
      const rem = timerDisplaySeconds(c, now)
      const initial =
        Number(c.initialDurationSec) > 0 ? c.initialDurationSec : rem
      setEditTimer({
        ...c,
        editRemainingMin: Math.floor(rem / 60),
        editRemainingSec: rem % 60,
        editInitialMin: Math.floor(initial / 60),
        editInitialSec: initial % 60,
        editDirection: c.direction,
      })
    } else {
      const dur = c.durationSec || 300
      setEditTimer({
        ...c,
        editRemainingMin: Math.floor(dur / 60),
        editRemainingSec: dur % 60,
        editDirection: c.direction,
      })
    }
  }

  const saveEditTimer = async () => {
    if (!editTimer) return
    const now = effectiveNowShared()
    const c = editTimer
    const dir = c.editDirection === 'up' ? 'up' : 'down'
    if (dir === 'down') {
      const rem = Math.max(0, (c.editRemainingMin || 0) * 60 + Math.min(59, c.editRemainingSec || 0))
      const initial = Math.max(
        1,
        (c.editInitialMin || 0) * 60 + Math.min(59, c.editInitialSec || 0)
      )
      await post('counter-update', {
        id: c.id,
        direction: 'down',
        endsAt: now + rem,
        initialDurationSec: initial,
        title: c.title,
        notes: c.notes,
      })
    } else {
      const dur = Math.max(1, (c.editRemainingMin || 0) * 60 + Math.min(59, c.editRemainingSec || 0))
      await post('counter-update', {
        id: c.id,
        direction: 'up',
        startedAt: now,
        durationSec: dur,
        title: c.title,
        notes: c.notes,
      })
    }
    setEditTimer(null)
  }

  const resetEditTimer = async () => {
    if (!editTimer) return
    const c = editTimer
    const dir = c.editDirection === 'up' ? 'up' : 'down'
    const now = effectiveNowShared()
    if (dir === 'down') {
      const initial = Math.max(
        1,
        (c.editInitialMin || 0) * 60 + Math.min(59, c.editInitialSec || 0)
      )
      await post('counter-update', {
        id: c.id,
        direction: 'down',
        endsAt: now + initial,
        initialDurationSec: initial,
      })
    } else {
      const dur = Math.max(1, (c.editRemainingMin || 0) * 60 + Math.min(59, c.editRemainingSec || 0))
      await post('counter-update', {
        id: c.id,
        direction: 'up',
        startedAt: now,
        durationSec: dur,
      })
    }
    setEditTimer(null)
  }

  const deleteEditTimer = async () => {
    if (!editTimer) return
    await post('counter-delete', { id: editTimer.id })
    setEditTimer(null)
  }

  return (
    <div className="counters-panel">
      <div className="counters-toolbar">
        <button type="button" className="counters-add-btn" onClick={() => setCreateOpen(true)}>
          {t('counters.add')}
        </button>
      </div>

      {createOpen && (
        <div className="counters-modal-overlay">
          <div className="counters-modal" role="dialog" aria-modal="true" aria-labelledby="counters-create-title">
            <h3 id="counters-create-title" className="counters-modal-title">{t('counters.createTitle')}</h3>
            <label className="counters-field">
              <span>{t('counters.type')}</span>
              <select value={createType} onChange={(e) => setCreateType(e.target.value)}>
                <option value="manual">{t('counters.typeManual')}</option>
                <option value="timer">{t('counters.typeTimer')}</option>
              </select>
            </label>
            {createType === 'timer' && (
              <>
                <label className="counters-field">
                  <span>{t('counters.direction')}</span>
                  <select value={createDirection} onChange={(e) => setCreateDirection(e.target.value)}>
                    <option value="down">{t('counters.directionDown')}</option>
                    <option value="up">{t('counters.directionUp')}</option>
                  </select>
                </label>
                <label className="counters-field">
                  <span>{t('counters.durationMinutes')}</span>
                  <input
                    type="number"
                    min={1}
                    value={createDurationMin}
                    onChange={(e) => setCreateDurationMin(Math.max(1, parseInt(e.target.value, 10) || 1))}
                  />
                </label>
              </>
            )}
            <div className="counters-modal-actions">
              <button type="button" onClick={() => setCreateOpen(false)}>
                {t('counters.cancel')}
              </button>
              {createType === 'manual' ? (
                <button type="button" className="primary" onClick={addPrivateManualFromModal}>
                  {t('counters.create')}
                </button>
              ) : (
                <button type="button" className="primary" onClick={addPrivateTimer}>
                  {t('counters.create')}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {editTimer && (
        <div className="counters-modal-overlay">
          <div className="counters-modal" role="dialog" aria-modal="true" aria-labelledby="counters-edit-timer-title">
            <h3 id="counters-edit-timer-title" className="counters-modal-title">{t('counters.editTimer')}</h3>
            <label className="counters-field">
              <span>{t('counters.direction')}</span>
              <select
                value={editTimer.editDirection}
                onChange={(e) => {
                  const v = e.target.value === 'up' ? 'up' : 'down'
                  setEditTimer((x) => {
                    if (v === 'down' && typeof x.editInitialMin !== 'number') {
                      const rem =
                        (x.editRemainingMin || 0) * 60 + Math.min(59, x.editRemainingSec || 0)
                      const init = Math.max(1, rem)
                      return {
                        ...x,
                        editDirection: v,
                        editInitialMin: Math.floor(init / 60),
                        editInitialSec: init % 60,
                      }
                    }
                    return { ...x, editDirection: v }
                  })
                }}
              >
                <option value="down">{t('counters.directionDown')}</option>
                <option value="up">{t('counters.directionUp')}</option>
              </select>
            </label>
            <label className="counters-field">
              <span>
                {editTimer.editDirection === 'down'
                  ? t('counters.remainingTime')
                  : t('counters.totalTime')}
              </span>
              <div className="counters-time-inputs">
                <input
                  type="number"
                  min={0}
                  value={editTimer.editRemainingMin}
                  onChange={(e) =>
                    setEditTimer((x) => ({ ...x, editRemainingMin: Math.max(0, parseInt(e.target.value, 10) || 0) }))
                  }
                />
                <span>m</span>
                <input
                  type="number"
                  min={0}
                  max={59}
                  value={editTimer.editRemainingSec}
                  onChange={(e) =>
                    setEditTimer((x) => ({ ...x, editRemainingSec: Math.min(59, Math.max(0, parseInt(e.target.value, 10) || 0)) }))
                  }
                />
                <span>s</span>
              </div>
            </label>
            {editTimer.editDirection === 'down' && (
              <label className="counters-field">
                <span>{t('counters.initialDuration')}</span>
                <div className="counters-time-inputs">
                  <input
                    type="number"
                    min={0}
                    value={editTimer.editInitialMin}
                    onChange={(e) =>
                      setEditTimer((x) => ({ ...x, editInitialMin: Math.max(0, parseInt(e.target.value, 10) || 0) }))
                    }
                  />
                  <span>m</span>
                  <input
                    type="number"
                    min={0}
                    max={59}
                    value={editTimer.editInitialSec}
                    onChange={(e) =>
                      setEditTimer((x) => ({ ...x, editInitialSec: Math.min(59, Math.max(0, parseInt(e.target.value, 10) || 0)) }))
                    }
                  />
                  <span>s</span>
                </div>
                <span className="counters-field-hint">{t('counters.initialDurationHint')}</span>
              </label>
            )}
            <div className="counters-modal-actions">
              <button type="button" onClick={() => setEditTimer(null)}>
                {t('counters.cancel')}
              </button>
              <button type="button" onClick={resetEditTimer}>
                {t('counters.reset')}
              </button>
              {canEditShared(editTimer) && (
                <button type="button" className="danger" onClick={deleteEditTimer}>
                  {t('counters.delete')}
                </button>
              )}
              <button type="button" className="primary" onClick={saveEditTimer}>
                {t('counters.save')}
              </button>
            </div>
          </div>
        </div>
      )}

      <ul className="counters-list">
        {privateList.map((c) => (
          <li key={c.id} className="counters-item counters-item-private">
            <PrivateCounterRow
              c={c}
              tick={tick}
              onUpdate={updatePrivate}
              onRemove={removePrivate}
              onShare={() => shareCounter(c)}
            />
          </li>
        ))}
        {sharedCounters.map((c) => (
          <li key={c.id} className="counters-item">
            <SharedCounterRow
              c={c}
              tick={tick}
              canEdit={canEditShared(c)}
              readOnly={!canEditShared(c)}
              displayNowSec={effectiveNowShared()}
              onUnshare={() => unshareCounter(c)}
              onDeleteServer={() => deleteSharedCounter(c)}
              isOwner={isOwner(c)}
              onBumpManual={(d) => bumpManualShared(c, d)}
              onSetManual={(v) => setManualSharedValue(c, v)}
              onEditTimer={() => openEditTimer(c)}
              onTitleNotes={(title, notes) => post('counter-update', { id: c.id, title, notes })}
            />
          </li>
        ))}
      </ul>

      {privateList.length === 0 && sharedCounters.length === 0 && (
        <p className="counters-empty">{t('counters.empty')}</p>
      )}
    </div>
  )
}

function PrivateCounterRow({ c, tick, onUpdate, onRemove, onShare }) {
  const [notesOpen, setNotesOpen] = useState(false)
  void tick
  const nowSec = Math.floor(Date.now() / 1000)
  const display =
    c.type === 'timer' ? formatTimerMmSs(timerDisplaySeconds(c, nowSec)) : String(c.value ?? 0)

  return (
    <div className="counters-row">
      <div className="counters-row-head">
        <input
          className="counters-title-input"
          value={c.title}
          onChange={(e) => onUpdate(c.id, { title: e.target.value })}
          placeholder={t('counters.titlePlaceholder')}
        />
        <span className="counters-badge-private">{t('counters.private')}</span>
        <button type="button" className="counters-icon-btn" title={t('counters.share')} onClick={onShare}>
          {t('counters.share')}
        </button>
        <button type="button" className="counters-icon-btn danger" title={t('counters.remove')} onClick={() => onRemove(c.id)}>
          ×
        </button>
      </div>
      <div className="counters-row-body">
        {c.type === 'manual' ? (
          <div className="counters-manual-controls">
            <button type="button" onClick={() => onUpdate(c.id, { value: (c.value || 0) - 1 })}>−</button>
            <input
              type="number"
              className="counters-value-input"
              value={c.value ?? 0}
              onChange={(e) => onUpdate(c.id, { value: parseInt(e.target.value, 10) || 0 })}
            />
            <button type="button" onClick={() => onUpdate(c.id, { value: (c.value || 0) + 1 })}>+</button>
          </div>
        ) : (
          <div className="counters-timer-display">{display}</div>
        )}
        <CounterNotes
          notes={c.notes || ''}
          expanded={notesOpen}
          onToggle={() => setNotesOpen((x) => !x)}
          readOnly={false}
          onChange={(v) => onUpdate(c.id, { notes: v })}
        />
      </div>
    </div>
  )
}

function SharedCounterRow({
  c,
  tick,
  canEdit,
  readOnly,
  displayNowSec,
  onUnshare,
  onDeleteServer,
  isOwner,
  onBumpManual,
  onSetManual,
  onEditTimer,
  onTitleNotes,
}) {
  const [notesOpen, setNotesOpen] = useState(false)
  const [title, setTitle] = useState(c.title || '')
  const [notes, setNotes] = useState(c.notes || '')
  const [manualVal, setManualVal] = useState(String(c.value ?? 0))

  void tick
  useEffect(() => {
    setTitle(c.title || '')
    setNotes(c.notes || '')
    setManualVal(String(c.value ?? 0))
  }, [c.title, c.notes, c.value, c.id])

  const display =
    c.type === 'timer' ? formatTimerMmSs(timerDisplaySeconds(c, displayNowSec)) : String(c.value ?? 0)

  const flushMeta = () => {
    if (canEdit) onTitleNotes(title, notes)
  }

  return (
    <div className="counters-row">
      <div className="counters-row-head">
        {canEdit ? (
          <input
            className="counters-title-input"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            onBlur={flushMeta}
            placeholder={t('counters.titlePlaceholder')}
          />
        ) : (
          <span className="counters-title-readonly">{c.title || t('counters.untitled')}</span>
        )}
        <span className="counters-badge-shared">{t('counters.shared')}</span>
        {canEdit && (
          <>
            {isOwner && (
              <button type="button" className="counters-icon-btn" title={t('counters.unshare')} onClick={onUnshare}>
                {t('counters.unshare')}
              </button>
            )}
            {c.type === 'timer' && (
              <button type="button" className="counters-icon-btn" title={t('counters.editTimer')} onClick={onEditTimer}>
                ✎
              </button>
            )}
            <button type="button" className="counters-icon-btn danger" title={t('counters.delete')} onClick={onDeleteServer}>
              ×
            </button>
          </>
        )}
      </div>
      <div className="counters-row-body">
        {c.type === 'manual' ? (
          <div className="counters-manual-controls">
            {canEdit ? (
              <>
                <button type="button" onClick={() => onBumpManual(-1)}>−</button>
                <input
                  type="number"
                  className="counters-value-input"
                  value={manualVal}
                  onChange={(e) => setManualVal(e.target.value)}
                  onBlur={() => onSetManual(manualVal)}
                />
                <button type="button" onClick={() => onBumpManual(1)}>+</button>
              </>
            ) : (
              <span className="counters-readonly-value">{c.value ?? 0}</span>
            )}
          </div>
        ) : (
          <div className="counters-timer-display">{display}</div>
        )}
        <CounterNotes
          notes={canEdit ? notes : c.notes || ''}
          expanded={notesOpen}
          onToggle={() => setNotesOpen((x) => !x)}
          readOnly={readOnly || !canEdit}
          onChange={(v) => setNotes(v)}
          onNotesBlur={canEdit ? flushMeta : undefined}
        />
      </div>
    </div>
  )
}
