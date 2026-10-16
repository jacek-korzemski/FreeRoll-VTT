import { t } from '../lang'
import { l5rRollHasDiceFaces, formatL5RLegacyTotal } from './l5rDiceDisplay'

export function formatRoll(roll) {
  if (roll.type === 'l5r') {
    if (l5rRollHasDiceFaces(roll)) {
      return roll.label || ''
    }
    return t('l5r.rollResult', {
      success: roll.totals?.success ?? 0,
      opportunity: roll.totals?.opportunity ?? 0,
      strife: roll.totals?.strife ?? 0
    })
  }

  const diceCounts = {}
  ;(roll.dice || []).forEach(d => {
    diceCounts[d.type] = diceCounts[d.type] || []
    diceCounts[d.type].push(d.result)
  })

  const parts = Object.entries(diceCounts).map(([type, results]) =>
    `${results.length}${type} [${results.join(', ')}]`
  )

  let formula = parts.join(' + ')
  if (roll.modifier && roll.modifier !== 0) {
    formula += roll.modifier > 0 ? ` + ${roll.modifier}` : ` - ${Math.abs(roll.modifier)}`
  }

  return formula
}

export function formatTotal(roll) {
  if (roll.type === 'l5r') {
    if (l5rRollHasDiceFaces(roll)) return ''
    return formatL5RLegacyTotal(roll)
  }
  return `= ${roll.total}`
}

/** "Sukces ≤ 12" when the roll carries a template success check. Empty otherwise. */
export function formatRollOutcome(roll) {
  const check = roll?.check
  if (!check || (check.op !== 'lte' && check.op !== 'gte')) return ''
  const target = Number(check.target)
  if (!Number.isFinite(target)) return ''
  const cmp = check.op === 'lte' ? '≤' : '≥'
  const label = check.success ? t('dice.checkSuccess') : t('dice.checkFailure')
  return `${label} ${cmp} ${target}`
}
