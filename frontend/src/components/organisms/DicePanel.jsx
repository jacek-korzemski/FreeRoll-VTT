import React, { useState, useEffect, useCallback, lazy, Suspense } from 'react'
import { t } from '../../lang'
import { ENABLE_L5R } from '../../../config'
import { parseRollExpression } from '../../utils/diceRollUtils'
import { formatRoll, formatTotal } from '../../utils/diceFormat'
import { l5rRollHasDiceFaces } from '../../utils/l5rDiceDisplay'
import { useNotesTemplate } from '../../contexts/NotesTemplateContext'

// Build-time literal: when EnableL5r is off this folds to false and the L5R panel
// (plus its dice images) is dead-code-eliminated from the bundle.
const L5R_BUILD = import.meta.env.VITE_ENABLE_L5R === 'true'
const L5RDicePanel = L5R_BUILD ? lazy(() => import('../molecules/L5RDicePanel')) : null
const L5RRollDice = L5R_BUILD ? lazy(() => import('../molecules/L5RRollDice')) : null

const DICE_TYPES = [
  { type: 'd4', sides: 4, color: '#e74c3c' },
  { type: 'd6', sides: 6, color: '#3498db' },
  { type: 'd8', sides: 8, color: '#2ecc71' },
  { type: 'd10', sides: 10, color: '#9b59b6' },
  { type: 'd12', sides: 12, color: '#f39c12' },
  { type: 'd20', sides: 20, color: '#e94560' },
  { type: 'd100', sides: 100, color: '#1abc9c' },
]

const MACRO_STORAGE_KEY = 'vtt_macros'

function loadMacros() {
  try {
    const raw = localStorage.getItem(MACRO_STORAGE_KEY)
    if (!raw) return []
    const data = JSON.parse(raw)
    return Array.isArray(data) ? data : []
  } catch {
    return []
  }
}

function DicePanel({ isOpen, onToggle, rollHistory, onRoll, pendingL5RRoll, onL5RRollConsumed, isGameMaster, onClearHistory }) {
  const [mode, setMode] = useState('standard')
  const [selectedDice, setSelectedDice] = useState([])
  const [modifier, setModifier] = useState(0)
  const [playerName, setPlayerName] = useState('')
  const [isRolling, setIsRolling] = useState(false)
  const [macros, setMacros] = useState([])
  const { getFieldValue: ctxGetFieldValue } = useNotesTemplate() || {}

  // A pending L5R roll (from a character-sheet button) switches the panel to L5R mode.
  useEffect(() => {
    if (!ENABLE_L5R) return
    if (pendingL5RRoll?.token) setMode('l5r')
  }, [pendingL5RRoll])

  useEffect(() => {
    const saved = localStorage.getItem('vtt_player_name')
    if (saved) setPlayerName(saved)
  }, [])

  useEffect(() => {
    setMacros(loadMacros())
  }, [])

  useEffect(() => {
    if (typeof window === 'undefined') return
    const handler = () => {
      const list = loadMacros()
      setMacros(list)
      if (list.length === 0 && mode === 'macros') {
        setMode('standard')
      }
    }
    window.addEventListener('vtt:macros-changed', handler)
    return () => window.removeEventListener('vtt:macros-changed', handler)
  }, [mode])

  useEffect(() => {
    if (isOpen) {
      setMacros(loadMacros())
    }
  }, [isOpen])

  useEffect(() => {
    if (!ENABLE_L5R && mode === 'l5r') {
      setMode('standard')
    }
  }, [mode])

  const handleNameChange = useCallback((e) => {
    const name = e.target.value
    setPlayerName(name)
    localStorage.setItem('vtt_player_name', name)
  }, [])

  const addDie = useCallback((dieType) => {
    setSelectedDice(prev => [...prev, {
      ...dieType,
      id: Date.now() + Math.random()
    }])
  }, [])

  const removeDie = useCallback((id) => {
    setSelectedDice(prev => prev.filter(d => d.id !== id))
  }, [])

  const clearDice = useCallback(() => {
    setSelectedDice([])
    setModifier(0)
  }, [])

  const applyMacro = useCallback((macro) => {
    if (!macro || !macro.expression) return

    const getFieldValue = macro.sourceNoteId && ctxGetFieldValue
      ? (fieldName) => ctxGetFieldValue(macro.sourceNoteId, fieldName)
      : () => ''

    const { diceList, modifier: exprModifier } = parseRollExpression(macro.expression, getFieldValue)

    if (diceList.length === 0) {
      return
    }

    const withIds = diceList.map(die => ({
      ...die,
      id: Date.now() + Math.random()
    }))

    setSelectedDice(withIds)
    setModifier(exprModifier)
  }, [ctxGetFieldValue])

  const rollDice = useCallback(() => {
    if (selectedDice.length === 0) return

    setIsRolling(true)

    setTimeout(() => {
      const rolls = selectedDice.map(die => ({
        type: die.type,
        sides: die.sides,
        result: Math.floor(Math.random() * die.sides) + 1
      }))

      const total = rolls.reduce((sum, r) => sum + r.result, 0) + modifier

      const rollData = {
        player: playerName || 'Anonymous',
        type: 'standard',
        dice: rolls,
        modifier: modifier,
        total: total,
        timestamp: Date.now()
      }

      onRoll(rollData)
      setIsRolling(false)
    }, 500)
  }, [selectedDice, modifier, playerName, onRoll])

  const timeAgo = (timestamp) => {
    const seconds = Math.floor((Date.now() - timestamp) / 1000)
    if (seconds < 60) return t('dice.now')
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}${t('dice.minutesAgo')}`
    const hours = Math.floor(minutes / 60)
    return `${hours}${t('dice.hoursAgo')}`
  }

  return (
    <>
      <button 
        className={`dice-panel-toggle ${isOpen ? 'open' : ''}`}
        onClick={onToggle}
        title={t('dice.title')}
      >
        🎲
      </button>

      <div className={`dice-panel ${isOpen ? 'open' : ''}`}>
        <div className="dice-panel-header">
          <h2>🎲 {t('dice.title')}</h2>
        </div>

        <div className="dice-panel-content">
        <div className="dice-player-name">
          <input
            type="text"
            placeholder={t('dice.playerPlaceholder')}
            value={playerName}
            onChange={handleNameChange}
            maxLength={20}
          />
        </div>

        <div className="dice-mode-switch">
          <button 
            className={mode === 'standard' ? 'active' : ''}
            onClick={() => setMode('standard')}
          >
            {t('dice.standardMode')}
          </button>
          {macros.length > 0 && (
            <button
              className={mode === 'macros' ? 'active' : ''}
              onClick={() => setMode('macros')}
            >
              Makra
            </button>
          )}
          {ENABLE_L5R && (
            <button
              className={mode === 'l5r' ? 'active' : ''}
              onClick={() => setMode('l5r')}
            >
              L5R
            </button>
          )}
        </div>

        {(mode === 'standard' || mode === 'macros') && (
          <>
            <div className="dice-types">
              {mode === 'standard' && DICE_TYPES.map(die => (
                <button
                  key={die.type}
                  className="dice-type-btn"
                  style={{ '--dice-color': die.color }}
                  onClick={() => addDie(die)}
                  title={`${t('dice.addDie').split(' ')[0]} ${die.type}`}
                >
                  {die.type}
                </button>
              ))}
              {mode === 'macros' && (
                <>
                  {macros.length === 0 ? (
                    <p className="dice-placeholder">{t('macros.noMacros')}</p>
                  ) : (
                    <div className="dice-macros">
                      {macros
                        .slice()
                        .sort((a, b) => {
                          const aName = (a.name || a.expression || '').toLowerCase()
                          const bName = (b.name || b.expression || '').toLowerCase()
                          return aName.localeCompare(bName)
                        })
                        .map(macro => (
                        <button
                          key={macro.id}
                          className="dice-macro-btn"
                          onClick={() => applyMacro(macro)}
                        >
                          {macro.icon && <span className="dice-macro-icon">{macro.icon}</span>}
                          <span className="dice-macro-label">{macro.name || macro.expression}</span>
                        </button>
                      ))}
                    </div>
                  )}
                </>
              )}
            </div>

            <div className="dice-selected">
              {selectedDice.length === 0 ? (
                <p className="dice-placeholder">{t('dice.addDie')}</p>
              ) : (
                <div className="dice-pool">
                  {selectedDice.map(die => (
                    <span
                      key={die.id}
                      className="die-chip"
                      style={{ '--dice-color': DICE_TYPES.find(d => d.type === die.type)?.color }}
                      onClick={() => removeDie(die.id)}
                    >
                      {die.type}
                    </span>
                  ))}
                  {modifier !== 0 && (
                    <span className="dice-pool-modifier">
                      {modifier > 0 ? `+${modifier}` : modifier}
                    </span>
                  )}
                </div>
              )}
            </div>

            <div className="dice-modifier">
              <label>{t('dice.modifier')}</label>
              <div className="modifier-controls">
                <button onClick={() => setModifier(m => m - 1)}>−</button>
                <input
                  type="number"
                  value={modifier}
                  onChange={(e) => setModifier(parseInt(e.target.value) || 0)}
                />
                <button onClick={() => setModifier(m => m + 1)}>+</button>
              </div>
            </div>

            <div className="dice-actions">
              <button 
                className="roll-btn"
                onClick={rollDice}
                disabled={selectedDice.length === 0 || isRolling}
              >
                {isRolling ? `🎲 ${t('dice.rolling')}` : `🎲 ${t('dice.roll')}`}
              </button>
              <button 
                className="clear-btn"
                onClick={clearDice}
                disabled={selectedDice.length === 0 && modifier === 0}
              >
                ✕
              </button>
            </div>
          </>
        )}

        {ENABLE_L5R && mode === 'l5r' && L5RDicePanel && (
          <Suspense fallback={<div className="l5r-dice-loading">…</div>}>
            <L5RDicePanel
              playerName={playerName}
              onRoll={onRoll}
              prefill={pendingL5RRoll}
              onPrefillConsumed={onL5RRollConsumed}
            />
          </Suspense>
        )}

        <div className="dice-history">
          <div className="dice-history-header">
            <h3>{t('dice.history')}</h3>
            {isGameMaster && (
              <button
                type="button"
                className="dice-history-clear-btn"
                onClick={onClearHistory}
                disabled={rollHistory.length === 0}
              >
                🗑️ {t('dice.clearHistory')}
              </button>
            )}
          </div>
          <div className="history-list">
            {rollHistory.length === 0 ? (
              <p className="history-empty">{t('dice.historyEmpty')}</p>
            ) : (
              rollHistory.map((roll, idx) => {
                const formula = formatRoll(roll)
                const total = formatTotal(roll)
                const showL5RDice = roll.type === 'l5r' && l5rRollHasDiceFaces(roll) && L5RRollDice
                return (
                <div key={roll.id || idx} className={`history-item ${roll.type === 'l5r' ? 'l5r-roll' : ''}`}>
                  <div className="history-header">
                    <span className="history-player">{roll.player}</span>
                    {roll.type === 'l5r' && <span className="history-badge">L5R</span>}
                    <span className="history-time">{timeAgo(roll.timestamp)}</span>
                  </div>
                  {showL5RDice ? (
                    <>
                      {formula && <div className="history-formula">{formula}</div>}
                      <div className="history-dice">
                        <Suspense fallback={total ? <div className="history-total">{total}</div> : null}>
                          <L5RRollDice dice={roll.dice} />
                        </Suspense>
                      </div>
                    </>
                  ) : (
                    <>
                      {formula && <div className="history-formula">{formula}</div>}
                      {total && <div className="history-total">{total}</div>}
                    </>
                  )}
                </div>
                )
              })
            )}
          </div>
        </div>
        </div>
      </div>
    </>
  )
}

export default DicePanel
