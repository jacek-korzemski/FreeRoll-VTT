/**
 * L5R 5e character sheet importer.
 *
 * Parses a CSV/TSV export of a single player's tab from the shared Google Sheets
 * character workbook and maps it onto the `data-field` schema used by
 * backend/assets/templates/l5r_5e.html.
 *
 * The sheet is a sparse, merged-cell grid, so values are located by anchoring on
 * their text labels and reading a neighbouring cell (right / below) rather than by
 * fixed coordinates. Table columns (armor / weapons) are detected from the header
 * row so minor layout shifts between exports are tolerated.
 */

const norm = (s) => String(s ?? '').replace(/\s+/g, ' ').trim()
const isNum = (v) => /^-?\d+$/.test(v)
const intOf = (v) => {
  const m = String(v).match(/-?\d+/)
  return m ? parseInt(m[0], 10) : 0
}

/** Parse delimited text (auto-detect TSV vs CSV) into a 2D array of strings. */
export function parseDelimited(text) {
  const delim = text.indexOf('\t') !== -1 ? '\t' : ','
  const rows = []
  let row = []
  let field = ''
  let inQuotes = false
  let i = 0
  const pushField = () => {
    row.push(field)
    field = ''
  }
  const pushRow = () => {
    rows.push(row)
    row = []
  }
  while (i < text.length) {
    const ch = text[i]
    if (inQuotes) {
      if (ch === '"') {
        if (text[i + 1] === '"') {
          field += '"'
          i += 2
          continue
        }
        inQuotes = false
        i++
        continue
      }
      field += ch
      i++
      continue
    }
    if (ch === '"') {
      inQuotes = true
      i++
      continue
    }
    if (ch === delim) {
      pushField()
      i++
      continue
    }
    if (ch === '\r') {
      i++
      continue
    }
    if (ch === '\n') {
      pushField()
      pushRow()
      i++
      continue
    }
    field += ch
    i++
  }
  pushField()
  if (row.length > 1 || row[0] !== '') pushRow()
  return rows
}

const cell = (g, r, c) => norm(g?.[r]?.[c])

function findCell(grid, label, startRow = 0) {
  const target = label.toLowerCase()
  for (let r = startRow; r < grid.length; r++) {
    for (let c = 0; c < grid[r].length; c++) {
      if (cell(grid, r, c).toLowerCase() === target) return { r, c }
    }
  }
  return null
}

function colInRow(grid, r, matcher) {
  const row = grid[r] || []
  for (let c = 0; c < row.length; c++) {
    const v = cell(grid, r, c)
    if (typeof matcher === 'string' ? v.toLowerCase() === matcher.toLowerCase() : matcher.test(v)) {
      return c
    }
  }
  return -1
}

function readRight(grid, r, c, maxCols, pred) {
  for (let cc = c + 1; cc <= c + maxCols; cc++) {
    const v = cell(grid, r, cc)
    if (v && (!pred || pred(v))) return v
  }
  return ''
}

function readBelow(grid, r, c, maxRows, pred) {
  for (let rr = r + 1; rr <= r + maxRows; rr++) {
    const v = cell(grid, rr, c)
    if (v && (!pred || pred(v))) return v
  }
  return ''
}

// --- field configuration --------------------------------------------------

const IDENTITY = [
  ['player_name', 'Player Name'],
  ['character_name', 'Character Name'],
  ['clan', 'Clan'],
  ['family', 'Family'],
  ['school', 'School'],
  ['roles', 'Roles'],
  ['heritage', 'Heritage'],
  ['titles', 'Titles'],
  ['giri', 'Giri'],
  ['ninjo', 'Ninjō'],
  ['personality', 'Personality, Habits & Quirks'],
  ['look', 'Look'],
  ['equipment', 'Equipment'],
  ['notes', 'Notes'],
  ['relationships', 'Relationships'],
]

const SKILLS = [
  ['skill_aesthetics', 'Aesthetics'],
  ['skill_composition', 'Composition'],
  ['skill_design', 'Design'],
  ['skill_smithing', 'Smithing'],
  ['skill_fitness', 'Fitness'],
  ['skill_martial_melee', 'Martial Arts [Melee]'],
  ['skill_martial_ranged', 'Martial Arts [Ranged]'],
  ['skill_martial_unarmed', 'Martial Arts [Unarmed]'],
  ['skill_meditation', 'Meditation'],
  ['skill_tactics', 'Tactics'],
  ['skill_culture', 'Culture'],
  ['skill_government', 'Government'],
  ['skill_medicine', 'Medicine'],
  ['skill_sentiment', 'Sentiment'],
  ['skill_theology', 'Theology'],
  ['skill_command', 'Command'],
  ['skill_courtesy', 'Courtesy'],
  ['skill_games', 'Games'],
  ['skill_performance', 'Performance'],
  ['skill_commerce', 'Commerce'],
  ['skill_labor', 'Labor'],
  ['skill_seafaring', 'Seafaring'],
  ['skill_skulduggery', 'Skulduggery'],
  ['skill_survival', 'Survival'],
]

const KNOWN_LABELS = new Set(
  [...IDENTITY, ...SKILLS]
    .map(([, label]) => label.toLowerCase())
    .concat(['skills', 'aproaches', 'approaches', 'school ability', 'void points', 'starting wealth'])
)
const notLabel = (v) => !KNOWN_LABELS.has(v.toLowerCase())

// --- table extraction -----------------------------------------------------

function extractRows(grid, header, columns, { stopRow, max = 8 } = {}) {
  const out = []
  const limit = stopRow ?? grid.length
  for (let rr = header.r + 1; rr < limit && out.length < max; rr++) {
    const name = cell(grid, rr, columns.name)
    if (!name || name === '-') continue
    const entry = {}
    for (const [key, col] of Object.entries(columns)) {
      if (col < 0) continue
      entry[key] = cell(grid, rr, col)
    }
    out.push(entry)
  }
  return out
}

function extractTechniques(grid, max = 6) {
  const out = []
  for (let r = 0; r < grid.length && out.length < max; r++) {
    for (let c = 0; c < grid[r].length && out.length < max; c++) {
      if (cell(grid, r, c).toLowerCase() !== 'technique') continue
      // A real technique card has a "Type" label directly below the "Technique" cell.
      // This rejects the lone "Technique" row in the XP-cost/curriculum table.
      if (cell(grid, r + 1, c).toLowerCase() !== 'type') continue
      const name = readRight(grid, r, c, 10, (v) => v !== '-')
      if (!name || name === '-') continue

      // Card body ends at the next "Technique" cell in the same column.
      let blockEnd = grid.length
      for (let rr = r + 1; rr < grid.length; rr++) {
        if (cell(grid, rr, c).toLowerCase() === 'technique') {
          blockEnd = rr
          break
        }
      }

      const valueBelowLabel = (matcher) => {
        for (let rr = r; rr < blockEnd; rr++) {
          const v = cell(grid, rr, c)
          if (typeof matcher === 'string' ? v.toLowerCase() === matcher : matcher.test(v)) {
            return readBelow(grid, rr, c, 2, (x) => x !== '-')
          }
        }
        return ''
      }

      out.push({
        name,
        type: readRight(grid, r + 1, c, 10, (v) => v !== '-'),
        rank: (() => {
          const rc = colInRow(grid, r + 1, /^rank$/i)
          return rc >= 0 ? readRight(grid, r + 1, rc, 6, isNum) : ''
        })(),
        ring: readRight(grid, r + 2, c, 10, (v) => v !== '-'),
        activation: valueBelowLabel(/^activation$/i),
        effects: valueBelowLabel(/^effects$/i),
        opportunities: valueBelowLabel(/^opportunities$/i),
      })
    }
  }
  return out
}

function gatherAdvantages(grid, headerLabel) {
  const header = findCell(grid, headerLabel)
  if (!header) return ''
  const lines = []
  for (const offset of [2, 9, 16, 23]) {
    const r = header.r + offset
    const name = cell(grid, r, header.c)
    if (!name || name === '-') continue
    const desc = cell(grid, r, header.c + 9)
    const category = cell(grid, r + 4, header.c)
    let line = name
    if (category && notLabel(category)) line += ` [${category}]`
    if (desc) line += ` — ${desc}`
    lines.push(line)
  }
  return lines.join('\n')
}

// --- main -----------------------------------------------------------------

export function parseL5RCharacterSheet(text) {
  const grid = parseDelimited(text)
  const fields = {}
  const stats = { identity: 0, skills: 0, weapons: 0, armor: 0, techniques: 0 }

  const setIf = (key, value) => {
    const v = norm(value)
    if (v) fields[key] = v
  }

  // Identity
  for (const [key, label] of IDENTITY) {
    const c = findCell(grid, label)
    if (!c) continue
    const value = readBelow(grid, c.r, c.c, 2, notLabel)
    if (value) {
      fields[key] = value
      stats.identity++
    }
  }

  // Rings (value sits two rows below the ring name; Void sits to the right)
  for (const [key, label] of [
    ['ring_air', 'Air'],
    ['ring_earth', 'Earth'],
    ['ring_fire', 'Fire'],
    ['ring_water', 'Water'],
  ]) {
    const c = findCell(grid, label)
    if (c) {
      const v = readBelow(grid, c.r, c.c, 3, isNum)
      if (v) fields[key] = String(intOf(v))
    }
  }
  const voidCell = findCell(grid, 'Void')
  if (voidCell) {
    const v = readRight(grid, voidCell.r, voidCell.c, 6, isNum)
    if (v) fields.ring_void = String(intOf(v))
  }

  // Pools / honor / glory / status / school rank / XP
  for (const [key, label] of [
    ['honor', 'Honor'],
    ['glory', 'Glory'],
    ['status', 'Status'],
    ['xp_total', 'Total XP'],
    ['xp_spent', 'Spent XP'],
    ['xp_saved', 'Saved XP'],
  ]) {
    const c = findCell(grid, label)
    if (c) setIf(key, readBelow(grid, c.r, c.c, 2, isNum))
  }

  const rankCell = findCell(grid, 'Rank')
  if (rankCell) setIf('school_rank', readBelow(grid, rankCell.r, rankCell.c, 3, isNum))

  // Money (best-effort: starting wealth like "8 koku")
  const wealth = findCell(grid, 'Starting wealth')
  if (wealth) {
    const v = readBelow(grid, wealth.r, wealth.c, 3, (x) => /\d/.test(x))
    if (v) setIf('money_koku', String(intOf(v)))
  }

  // School ability
  const sa = findCell(grid, 'School Ability')
  if (sa) {
    setIf('school_ability_name', readRight(grid, sa.r, sa.c, 10, notLabel))
    setIf('school_ability_text', readBelow(grid, sa.r, sa.c, 2, notLabel))
  }

  // Skills (rank is the first number to the right of the skill name)
  for (const [key, label] of SKILLS) {
    const c = findCell(grid, label)
    if (!c) continue
    const v = readRight(grid, c.r, c.c, 12, isNum)
    if (v && intOf(v) > 0) {
      fields[key] = String(intOf(v))
      stats.skills++
    }
  }

  // Armor table
  const armorHeader = findCell(grid, 'Armor')
  const weaponHeader = findCell(grid, 'Weapon')
  if (armorHeader) {
    const armors = extractRows(grid, armorHeader, {
      name: armorHeader.c,
      resistance: colInRow(grid, armorHeader.r, /^resistance$/i),
      qualities: colInRow(grid, armorHeader.r, /^qualities$/i),
      extra: colInRow(grid, armorHeader.r, /^additional/i),
    }, { stopRow: weaponHeader?.r, max: 4 })
    armors.forEach((a, i) => {
      const n = i + 1
      setIf(`armor${n}_name`, a.name)
      setIf(`armor${n}_resistance`, a.resistance)
      setIf(`armor${n}_qualities`, [a.qualities, a.extra].filter(Boolean).join(' — '))
      stats.armor++
    })
  }

  // Weapon table (stop before the technique cards that follow it)
  if (weaponHeader) {
    const techStart = findCell(grid, 'Technique', weaponHeader.r + 1)
    const weapons = extractRows(grid, weaponHeader, {
      name: weaponHeader.c,
      range: colInRow(grid, weaponHeader.r, /^range$/i),
      damage: colInRow(grid, weaponHeader.r, /^damage/i),
      grips: colInRow(grid, weaponHeader.r, /^grips$/i),
      qualities: colInRow(grid, weaponHeader.r, /^qualities$/i),
      extra: colInRow(grid, weaponHeader.r, /^additional/i),
    }, { stopRow: techStart?.r, max: 6 })
    weapons.forEach((w, i) => {
      const n = i + 1
      setIf(`weapon${n}_name`, w.name)
      setIf(`weapon${n}_range`, w.range)
      setIf(`weapon${n}_damage`, w.damage)
      setIf(`weapon${n}_qualities`, [w.qualities, w.grips, w.extra].filter(Boolean).join(' · '))
      stats.weapons++
    })
  }

  // Techniques
  extractTechniques(grid, 6).forEach((tech, i) => {
    const n = i + 1
    setIf(`tech${n}_name`, tech.name)
    setIf(`tech${n}_type`, tech.type)
    setIf(`tech${n}_rank`, tech.rank)
    setIf(`tech${n}_ring`, tech.ring)
    setIf(`tech${n}_activation`, tech.activation)
    setIf(`tech${n}_effects`, tech.effects)
    setIf(`tech${n}_opportunities`, tech.opportunities)
    stats.techniques++
  })

  // Advantages / disadvantages (best-effort)
  setIf('distinctions', gatherAdvantages(grid, 'Distinctions'))
  setIf('passions', gatherAdvantages(grid, 'Passions'))
  setIf('adversities', gatherAdvantages(grid, 'Adversities'))
  setIf('anxieties', gatherAdvantages(grid, 'Anxieties'))

  return { fields, stats }
}

export default parseL5RCharacterSheet
