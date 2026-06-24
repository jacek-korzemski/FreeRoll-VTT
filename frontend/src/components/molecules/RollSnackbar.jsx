import { useCallback, useEffect, useRef, useState, lazy, Suspense } from 'react'
import { t } from '../../lang'
import { ENABLE_L5R } from '../../../config'
import { formatRoll, formatTotal } from '../../utils/diceFormat'
import { l5rRollHasDiceFaces } from '../../utils/l5rDiceDisplay'

const L5R_BUILD = import.meta.env.VITE_ENABLE_L5R === 'true'
const L5RRollDice = L5R_BUILD ? lazy(() => import('./L5RRollDice')) : null

function RollSnackbar({ roll, onClose }) {
  const [isPaused, setIsPaused] = useState(false)
  const onCloseRef = useRef(onClose)

  useEffect(() => {
    onCloseRef.current = onClose
  }, [onClose])

  const handleTogglePause = useCallback(() => {
    setIsPaused(p => !p)
  }, [])

  const handleClose = useCallback(() => {
    onCloseRef.current?.()
  }, [])

  const handleAnimationEnd = useCallback((e) => {
    if (e.animationName === 'roll-snackbar-countdown') {
      onCloseRef.current?.()
    }
  }, [])

  const isL5R = roll.type === 'l5r'
  const showL5RDice = ENABLE_L5R && isL5R && l5rRollHasDiceFaces(roll) && L5RRollDice
  const formula = formatRoll(roll)
  const total = formatTotal(roll)

  return (
    <div
      className={`roll-snackbar ${isL5R ? 'l5r' : ''} ${isPaused ? 'paused' : ''}`}
      role="status"
      aria-live="polite"
    >
      <div className="roll-snackbar-body">
        <div className="roll-snackbar-header">
          <span className="roll-snackbar-player">{roll.player || 'Anonymous'}</span>
          {isL5R && <span className="roll-snackbar-badge">L5R</span>}
        </div>
        {formula && <div className="roll-snackbar-formula">{formula}</div>}
        {showL5RDice ? (
          <div className="roll-snackbar-dice">
            <Suspense fallback={total ? <div className="roll-snackbar-total">{total}</div> : null}>
              <L5RRollDice dice={roll.dice} />
            </Suspense>
          </div>
        ) : (
          total && <div className="roll-snackbar-total">{total}</div>
        )}
      </div>
      <div className="roll-snackbar-actions">
        <button
          type="button"
          className="roll-snackbar-btn pause"
          onClick={handleTogglePause}
          title={isPaused ? t('dice.snackbarResume') : t('dice.snackbarPause')}
          aria-label={isPaused ? t('dice.snackbarResume') : t('dice.snackbarPause')}
        >
          {isPaused ? '▶' : '⏸'}
        </button>
        <button
          type="button"
          className="roll-snackbar-btn close"
          onClick={handleClose}
          title={t('dice.snackbarClose')}
          aria-label={t('dice.snackbarClose')}
        >
          ✕
        </button>
      </div>
      <div
        className="roll-snackbar-progress"
        onAnimationEnd={handleAnimationEnd}
      />
    </div>
  )
}

export default RollSnackbar
