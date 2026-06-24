/**
 * Helpers for rendering stored L5R rolls (history / snackbar).
 * Rolls saved with per-die faceId show dice images; older rolls fall back to totals.
 */

export function l5rRollHasDiceFaces(roll) {
  if (roll?.type !== 'l5r' || !Array.isArray(roll.dice) || roll.dice.length === 0) return false
  return roll.dice.every((die) => {
    const faceId = Number(die?.faceId)
    if (!Number.isFinite(faceId) || faceId < 1) return false
    const max = die?.type === 'skill' ? 12 : 6
    return faceId <= max
  })
}

/** Legacy summary when dice faces were not stored (old backend / rolls.json). */
export function formatL5RLegacyTotal(roll) {
  const totals = roll?.totals
  if (!totals) return ''
  return `✓${totals.success ?? 0} 🌀${totals.opportunity ?? 0} 💢${totals.strife ?? 0}`
}
