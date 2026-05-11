import { t } from '../lang'

export function formatRoll(roll) {
  if (roll.type === 'l5r') {
    return t('l5r.rollResult', {
      success: roll.totals.success,
      opportunity: roll.totals.opportunity,
      strife: roll.totals.strife
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
    return `✓${roll.totals.success} 🌀${roll.totals.opportunity} 💢${roll.totals.strife}`
  }
  return `= ${roll.total}`
}
