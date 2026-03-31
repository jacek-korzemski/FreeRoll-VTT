/** Displayed remaining/elapsed seconds for a timer counter (integer seconds). */
export function timerDisplaySeconds(counter, nowSec) {
  if (!counter || counter.type !== 'timer') return 0
  const dir = counter.direction === 'up' ? 'up' : 'down'
  if (dir === 'down') {
    const end = Number(counter.endsAt)
    if (!Number.isFinite(end)) return 0
    return Math.max(0, Math.floor(end - nowSec))
  }
  const start = Number(counter.startedAt)
  const dur = Number(counter.durationSec)
  if (!Number.isFinite(start) || !Number.isFinite(dur)) return 0
  const elapsed = Math.max(0, Math.floor(nowSec - start))
  return Math.min(dur, elapsed)
}

export function formatTimerMmSs(totalSec) {
  const s = Math.max(0, Math.floor(totalSec))
  const m = Math.floor(s / 60)
  const r = s % 60
  return `${m}:${r.toString().padStart(2, '0')}`
}

export const SYNC_DRIFT_THRESHOLD_SEC = 10

/** Serialize timer anchor fields for change detection */
export function timerAnchorKey(counter) {
  if (!counter || counter.type !== 'timer') return ''
  if (counter.direction === 'up') {
    return `up:${counter.startedAt}:${counter.durationSec}`
  }
  return `down:${counter.endsAt}`
}
