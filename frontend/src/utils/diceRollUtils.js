/**
 * Shared dice roll parsing and execution for templates and macros.
 * Dispatches 'vtt:dice-roll' so App can add to history and sync.
 */
import { getTableItem } from './tableStorage'

/** Returns true if string contains dice notation ndx (e.g. 2d6, d20, 1d4 or 2d6+1) */
function isDiceNotation(str) {
  if (str == null || typeof str !== 'string') return false
  return /\d*d\d+/.test(str.trim())
}

export function parseRollExpression(expr, getFieldValue) {
  const diceRegex = /(\d*)d(\d+)/g
  const fieldRegex = /@(\w+)/g
  const conditionalRegex = /\+@(\w+)\?@(\w+)/g

  let modifier = 0
  const diceList = []

  const withoutConditionals = expr.replace(conditionalRegex, (_, valField, condField) => {
    const condVal = getFieldValue(condField)
    if (condVal === true || condVal === 'on' || condVal === 'true') {
      const val = parseInt(getFieldValue(valField), 10) || 0
      modifier += val
    }
    return ''
  })

  const withoutFields = withoutConditionals.replace(fieldRegex, (_, name) => {
    const raw = getFieldValue(name)
    const str = String(raw ?? '').trim()
    if (isDiceNotation(str)) {
      return str
    }
    const val = parseInt(str, 10) || 0
    modifier += val
    return ''
  })

  let match
  while ((match = diceRegex.exec(withoutFields)) !== null) {
    const count = parseInt(match[1], 10) || 1
    const sides = parseInt(match[2], 10)
    for (let i = 0; i < count; i++) {
      diceList.push({ type: `d${sides}`, sides })
    }
  }

  const remaining = withoutFields.replace(/(\d*)d(\d+)/g, '').replace(/\s/g, '')
  const numberMatches = remaining.match(/[+-]?\d+/g)
  if (numberMatches) {
    numberMatches.forEach((s) => { modifier += parseInt(s, 10) })
  }

  return { diceList, modifier }
}

/**
 * Build roll data object (for history/API). Does not dispatch.
 */
export function buildRollData(diceList, modifier, label, getFieldValue) {
  if (diceList.length === 0) return null

  const rolls = diceList.map(die => ({
    type: die.type,
    sides: die.sides,
    result: Math.floor(Math.random() * die.sides) + 1
  }))

  const total = rolls.reduce((sum, r) => sum + r.result, 0) + modifier
  const playerName = getTableItem('vtt_player_name') || 'Anonymous'

  let resolvedLabel = label || ''
  if (resolvedLabel && getFieldValue) {
    resolvedLabel = resolvedLabel.replace(/@(\w+)/g, (_, name) => {
      const v = getFieldValue(name)
      return (v === true || v === false) ? '' : (v || '')
    }).trim()
  }

  return {
    player: resolvedLabel ? `${playerName} (${resolvedLabel})` : playerName,
    type: 'standard',
    dice: rolls,
    modifier,
    total,
    timestamp: Date.now()
  }
}

const ROLL_CHECK_OPS = new Set(['lte', 'gte'])

function sameCellFieldInput(rollButton) {
  if (!rollButton || typeof rollButton.closest !== 'function') return null
  const td = rollButton.closest('td')
  return td ? td.querySelector('input[data-field]') : null
}

/** lte / gte from data-roll-check, or '' when the button has no success rule. */
export function readRollCheckOp(rollButton) {
  if (!rollButton || typeof rollButton.getAttribute !== 'function') return ''
  const op = (rollButton.getAttribute('data-roll-check') || '').trim()
  return ROLL_CHECK_OPS.has(op) ? op : ''
}

/**
 * Build effective roll expression from a roll button in a template cell.
 * - If data-roll is empty: use only the value of the input in the same cell (e.g. user typed "d4").
 * - If data-roll is set and same-cell input has value: use formula + "+" + input value (e.g. "d20" + "d4" → "d20+d4"),
 *   unless the formula already references that field via @fieldId (then parseRollExpression substitutes it).
 * - If data-roll-check is set, the same-cell field is the success target, not a modifier.
 */
export function getEffectiveRollExpression(rollButton) {
  if (!rollButton || typeof rollButton.closest !== 'function') return ''
  const expr = (rollButton.getAttribute('data-roll') || '').trim()
  const input = sameCellFieldInput(rollButton)
  const fieldId = (input && input.getAttribute('data-field')) || ''
  const userVal = input ? String(input.value || '').trim() : ''
  if (!expr) return userVal
  if (readRollCheckOp(rollButton)) return expr
  if (userVal && (!fieldId || !expr.includes('@' + fieldId))) return expr + '+' + userVal
  return expr
}

/**
 * Success rule for a template roll button.
 * Target is the integer in the same cell. Empty or non-numeric values skip the check.
 * @returns {{ op: 'lte'|'gte', target: number } | null}
 */
export function readRollCheckTarget(rollButton) {
  const op = readRollCheckOp(rollButton)
  if (!op) return null
  const input = sameCellFieldInput(rollButton)
  if (!input) return null
  const raw = String(input.value ?? '').trim()
  if (!/^[+-]?\d+$/.test(raw)) return null
  return { op, target: parseInt(raw, 10) }
}

/**
 * @param {number} total
 * @param {{ op: 'lte'|'gte', target: number } | null} spec
 * @returns {{ op: 'lte'|'gte', target: number, success: boolean } | null}
 */
export function evaluateRollCheck(total, spec) {
  if (!spec || !ROLL_CHECK_OPS.has(spec.op) || !Number.isFinite(spec.target)) return null
  const success = spec.op === 'lte' ? total <= spec.target : total >= spec.target
  return { op: spec.op, target: spec.target, success }
}

/**
 * Parse expression, roll dice, build rollData and dispatch 'vtt:dice-roll'.
 * rollCheckSpec is only set for template buttons that declare data-roll-check.
 */
export function executeDiceRoll(expr, label, getFieldValue, rollCheckSpec = null) {
  const { diceList, modifier } = parseRollExpression(expr, getFieldValue)
  const rollData = buildRollData(diceList, modifier, label, getFieldValue)
  if (!rollData) return

  const check = evaluateRollCheck(rollData.total, rollCheckSpec)
  if (check) rollData.check = check

  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('vtt:dice-roll', { detail: rollData }))
  }
}
