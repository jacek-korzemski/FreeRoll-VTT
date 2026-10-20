import { useEffect, useRef, useState } from 'react'
import { t } from '../../lang'

function clampPercent(value) {
  const number = Number(value)
  if (!Number.isFinite(number)) return 0
  return Math.round(Math.max(0, Math.min(100, number)) * 100) / 100
}

function clampTurn(value) {
  const number = parseInt(value, 10)
  if (!Number.isFinite(number)) return 1
  return Math.max(1, Math.min(999, number))
}

function defaultPosition(combatant, indexOnSide) {
  const hasX = Number.isFinite(Number(combatant.x))
  const hasY = Number.isFinite(Number(combatant.y))
  if (hasX && hasY) return { x: clampPercent(combatant.x), y: clampPercent(combatant.y) }
  return {
    x: combatant.side === 'player' ? 62 : 8,
    y: Math.min(68, 12 + indexOnSide * 28),
  }
}

function TurnBanner({ turn, isGameMaster, onSetTurn }) {
  const [current, setCurrent] = useState(turn)
  const [focused, setFocused] = useState(false)

  useEffect(() => {
    if (!focused) setCurrent(turn)
  }, [focused, turn])

  const commit = (nextTurn) => {
    const savedTurn = clampTurn(nextTurn)
    setCurrent(savedTurn)
    setFocused(false)
    if (savedTurn !== turn) onSetTurn(savedTurn)
  }

  if (!isGameMaster) {
    return (
      <div className="combat-turn">
        {t('scenes.turn', { turn })}
      </div>
    )
  }

  return (
    <div className="combat-turn">
      <button type="button" onClick={() => commit(Number(current) - 1)} aria-label="-">−</button>
      <span>{t('scenes.turnLabel')}</span>
      <input
        type="number"
        min="1"
        max="999"
        value={current}
        onFocus={() => setFocused(true)}
        onChange={(event) => setCurrent(event.target.value)}
        onBlur={() => commit(current)}
      />
      <button type="button" onClick={() => commit(Number(current) + 1)} aria-label="+">+</button>
    </div>
  )
}

function CombatantUnit({
  combatant,
  indexOnSide,
  hideHp,
  isGameMaster,
  stageRef,
  open,
  onToggle,
  onUpdate,
  onRemove,
}) {
  const [name, setName] = useState(combatant.name || '')
  const [notes, setNotes] = useState(combatant.notes || '')
  const [hp, setHp] = useState(combatant.hp ?? '')
  const [maxHp, setMaxHp] = useState(combatant.maxHp ?? '')
  const [nameFocused, setNameFocused] = useState(false)
  const [notesFocused, setNotesFocused] = useState(false)
  const [hpFocused, setHpFocused] = useState(false)
  const [live, setLive] = useState(null)
  const dragRef = useRef(null)
  const fallback = defaultPosition(combatant, indexOnSide)
  const x = live ? live.x : fallback.x
  const y = live ? live.y : fallback.y

  useEffect(() => {
    if (!nameFocused) setName(combatant.name || '')
  }, [combatant.name, nameFocused])

  useEffect(() => {
    if (!notesFocused) setNotes(combatant.notes || '')
  }, [combatant.notes, notesFocused])

  useEffect(() => {
    if (!hpFocused) {
      setHp(combatant.hp ?? '')
      setMaxHp(combatant.maxHp ?? '')
    }
  }, [combatant.hp, combatant.maxHp, hpFocused])

  useEffect(() => {
    const onMove = (event) => {
      const drag = dragRef.current
      const stage = stageRef.current
      if (!drag || !stage) return
      const rect = stage.getBoundingClientRect()
      if (!rect.width || !rect.height) return
      const next = {
        x: clampPercent(drag.x + ((event.clientX - drag.clientX) / rect.width) * 100),
        y: clampPercent(drag.y + ((event.clientY - drag.clientY) / rect.height) * 100),
      }
      if (Math.abs(event.clientX - drag.clientX) > 3 || Math.abs(event.clientY - drag.clientY) > 3) {
        drag.moved = true
      }
      drag.next = next
      setLive(next)
    }
    const onUp = () => {
      const drag = dragRef.current
      dragRef.current = null
      if (!drag) return
      setLive(null)
      if (!drag.moved) {
        onToggle(combatant.id)
        return
      }
      if (drag.next && (drag.next.x !== drag.x || drag.next.y !== drag.y)) {
        onUpdate(combatant.id, { x: drag.next.x, y: drag.next.y })
      }
    }
    window.addEventListener('pointermove', onMove)
    window.addEventListener('pointerup', onUp)
    return () => {
      window.removeEventListener('pointermove', onMove)
      window.removeEventListener('pointerup', onUp)
    }
  }, [combatant.id, onToggle, onUpdate, stageRef])

  const commitName = () => {
    setNameFocused(false)
    if ((combatant.name || '') !== name) onUpdate(combatant.id, { name })
  }

  const commitNotes = () => {
    setNotesFocused(false)
    if ((combatant.notes || '') !== notes) onUpdate(combatant.id, { notes })
  }

  const commitHp = () => {
    setHpFocused(false)
    const nextHp = hp === '' ? null : Number(hp)
    const nextMax = maxHp === '' ? null : Number(maxHp)
    if (nextHp !== (combatant.hp ?? null) || nextMax !== (combatant.maxHp ?? null)) {
      onUpdate(combatant.id, { hp: nextHp, maxHp: nextMax })
    }
  }

  const startDrag = (event) => {
    if (event.button !== 0) return
    if (event.target.closest('input, textarea, button')) return
    if (!isGameMaster) {
      onToggle(combatant.id)
      return
    }
    event.preventDefault()
    dragRef.current = { x, y, clientX: event.clientX, clientY: event.clientY, moved: false }
    setLive({ x, y })
  }

  const hpValue = Number(hp)
  const maxValue = Number(maxHp)
  const ratio = maxValue > 0 && Number.isFinite(hpValue)
    ? Math.max(0, Math.min(1, hpValue / maxValue))
    : 0

  return (
    <article
      className={`combat-unit combat-unit-${combatant.side === 'player' ? 'player' : 'enemy'}${open ? ' is-open' : ''}${isGameMaster ? '' : ' is-locked'}`}
      aria-expanded={open}
      style={{ left: `${x}%`, top: `${y}%` }}
      onPointerDown={startDrag}
    >
      {combatant.src ? (
        <img className="combat-portrait" src={combatant.src} alt="" draggable={false} />
      ) : (
        <div className="combat-portrait combat-portrait-empty" />
      )}
      {open && (
      <div className="combat-plate">
        {isGameMaster ? (
          <input
            className="combat-name-input"
            type="text"
            maxLength={80}
            value={name}
            placeholder={t('scenes.characterName')}
            onFocus={() => setNameFocused(true)}
            onChange={(event) => setName(event.target.value)}
            onBlur={commitName}
          />
        ) : (
          <h3 className="combat-name">{combatant.name || t('scenes.unnamed')}</h3>
        )}
        {!hideHp && (
          <div className="combat-hp">
            <div className="combat-hp-bar" aria-hidden="true">
              <span style={{ width: `${ratio * 100}%` }} />
            </div>
            <div className="combat-hp-nums">
              <input
                type="number"
                min="0"
                max="99999"
                value={hp}
                readOnly={!isGameMaster}
                aria-label={t('scenes.hpCurrent')}
                onFocus={() => setHpFocused(true)}
                onChange={(event) => setHp(event.target.value)}
                onBlur={commitHp}
              />
              <span>/</span>
              <input
                type="number"
                min="0"
                max="99999"
                value={maxHp}
                readOnly={!isGameMaster}
                aria-label={t('scenes.hpMax')}
                onFocus={() => setHpFocused(true)}
                onChange={(event) => setMaxHp(event.target.value)}
                onBlur={commitHp}
              />
            </div>
          </div>
        )}
        {isGameMaster && (
          <textarea
            className="combat-notes"
            rows={2}
            maxLength={2000}
            value={notes}
            placeholder={t('scenes.notes')}
            onFocus={() => setNotesFocused(true)}
            onChange={(event) => setNotes(event.target.value)}
            onBlur={commitNotes}
          />
        )}
        {!isGameMaster && combatant.notes ? (
          <p className="combat-notes-read">{combatant.notes}</p>
        ) : null}
        {isGameMaster && (
          <button type="button" className="combat-remove" onClick={() => onRemove(combatant.id)}>
            {t('scenes.remove')}
          </button>
        )}
      </div>
      )}
    </article>
  )
}

function CombatBoard({
  combatants,
  hideHp,
  background,
  turn,
  isGameMaster,
  selectedAsset,
  selectedType,
  basePath,
  onAdd,
  onUpdate,
  onRemove,
  onSetTurn,
}) {
  const stageRef = useRef(null)
  const [openId, setOpenId] = useState(null)
  const toggleUnit = (id) => setOpenId((prev) => (prev === id ? null : id))
  const withSrc = combatants.map((combatant) => ({
    ...combatant,
    src: combatant.src ? `${basePath}${combatant.src}` : '',
  }))
  const sideIndex = { enemy: 0, player: 0 }
  const canAdd = !!(selectedAsset && selectedType === 'token')
  const backgroundStyle = background?.src
    ? { backgroundImage: `url(${basePath}${background.src})` }
    : undefined

  return (
    <div className="combat-board" ref={stageRef}>
      <div className="combat-scene-bg" style={backgroundStyle} />
      <div className="combat-toolbar">
        {isGameMaster ? (
          <div className="combat-add combat-add-enemy">
            <button type="button" disabled={!canAdd} onClick={() => onAdd('enemy')}>
              {t('scenes.addEnemy')}
            </button>
            {!canAdd && <p className="board-hint">{t('scenes.selectToken')}</p>}
          </div>
        ) : <span />}
        <TurnBanner turn={turn} isGameMaster={isGameMaster} onSetTurn={onSetTurn} />
        {isGameMaster ? (
          <div className="combat-add combat-add-player">
            <button type="button" disabled={!canAdd} onClick={() => onAdd('player')}>
              {t('scenes.addPlayer')}
            </button>
          </div>
        ) : <span />}
      </div>
      {withSrc.length === 0 && <p className="combat-empty board-hint">{t('scenes.noCombatants')}</p>}
      {withSrc.map((combatant) => {
        const side = combatant.side === 'player' ? 'player' : 'enemy'
        const indexOnSide = sideIndex[side]
        sideIndex[side] += 1
        return (
          <CombatantUnit
            key={combatant.id}
            combatant={combatant}
            indexOnSide={indexOnSide}
            hideHp={hideHp}
            isGameMaster={isGameMaster}
            stageRef={stageRef}
            open={openId === combatant.id}
            onToggle={toggleUnit}
            onUpdate={onUpdate}
            onRemove={onRemove}
          />
        )
      })}
    </div>
  )
}

export default CombatBoard
