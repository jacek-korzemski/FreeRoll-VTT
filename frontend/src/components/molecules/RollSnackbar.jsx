import { useCallback, useEffect, useRef, useState } from 'react'
import { t } from '../../lang'
import { formatRoll, formatTotal } from '../../utils/diceFormat'

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
        <div className="roll-snackbar-formula">{formatRoll(roll)}</div>
        <div className="roll-snackbar-total">{formatTotal(roll)}</div>
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
