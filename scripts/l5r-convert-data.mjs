/**
 * Dev-time converter: turns the Google Sheets HTML exports of the L5R game-data
 * tables into structured JSON consumed by the in-app Compendium and card pickers.
 *
 * Usage:
 *   node scripts/l5r-convert-data.mjs ["temp/Karty Postaci - Krabi Upór"]
 *
 * Reads the 13 compendium tables (Weapons, Armors, Techniques, Schools, Clans,
 * Families, Titles, Distinctions, Passions, Adversities, Anxieties, Inversions,
 * Curriculum) and writes frontend/src/assets/l5r/data/*.json (committed).
 * Player character-sheet exports in the same folder are ignored.
 */

import fs from 'node:fs'
import path from 'node:path'

const SRC_DIR = process.argv[2] || 'temp/Karty Postaci - Krabi Upór'
const OUT_DIR = 'frontend/src/assets/l5r/data'

function decodeCell(html) {
  return html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&#39;/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&nbsp;/g, ' ')
    .replace(/[ \t]+/g, ' ')
    .replace(/\n{2,}/g, '\n')
    .trim()
}

function parseRows(html) {
  const rows = []
  const trRe = /<tr[^>]*>([\s\S]*?)<\/tr>/gi
  let tr
  while ((tr = trRe.exec(html))) {
    const tdRe = /<td[^>]*>([\s\S]*?)<\/td>/gi
    const cells = []
    let td
    while ((td = tdRe.exec(tr[1]))) cells.push(decodeCell(td[1]))
    if (cells.length) rows.push(cells)
  }
  return rows
}

const oneLine = (s) => (s || '').replace(/\s*\n\s*/g, ' ').replace(/\s+/g, ' ').trim()
const ringOf = (s) => (s || '').split(';')[0].trim()
const traitsOf = (s) => {
  const parts = (s || '').split(';')
  return parts.slice(1).join(';').replace(/\s*\n\s*/g, ', ').replace(/\s+/g, ' ').trim()
}
const isClanHeader = (c) =>
  c[0] && c[0] === c[0].toUpperCase() && c[0] !== '-' && c.slice(1).every((x) => !x)
const titleCase = (s) => s.charAt(0) + s.slice(1).toLowerCase()

const advantage = (c) => ({
  name: c[0],
  ring: ringOf(c[1]),
  traits: traitsOf(c[1]),
  description: oneLine(c[2]),
  check: oneLine(c[3]),
  source: c[4] || '',
})

function curriculumRow(c) {
  const ranks = []
  for (let start = 2; start + 6 < c.length; start += 7) {
    const rank = {
      skillGroup: c[start] || '',
      skills: [c[start + 1], c[start + 2], c[start + 3]].filter(Boolean),
      techniqueGroup: c[start + 4] || '',
      techniques: [c[start + 5], c[start + 6]].filter(Boolean),
    }
    const hasContent = rank.skillGroup || rank.skills.length || rank.techniqueGroup || rank.techniques.length
    ranks.push(hasContent ? rank : null)
  }
  return { name: c[0], ranks: ranks.filter(Boolean) }
}

const TABLES = [
  {
    out: 'weapons',
    file: 'Weapons.html',
    map: (c) => ({
      name: c[0],
      range: c[1] || '',
      damage: c[2] || '',
      grips: oneLine(c[3]),
      qualities: oneLine(c[4]),
      description: oneLine(c[5]),
      source: c[6] || '',
    }),
  },
  {
    out: 'armors',
    file: 'Armors.html',
    map: (c) => ({
      name: c[0],
      resistance: c[1] || '',
      qualities: oneLine(c[2]),
      description: oneLine(c[3]),
      source: c[4] || '',
    }),
  },
  {
    out: 'techniques',
    file: 'Techniques.html',
    isHeader: (c) => /^!!!/.test(c[0] || ''),
    map: (c) => ({
      name: c[0],
      rank: c[1] || '',
      type: c[2] || '',
      ring: c[3] || '',
      activation: oneLine(c[4]),
      effects: oneLine(c[5]),
      opportunities: oneLine(c[6]),
      restriction: oneLine(c[7]),
      source: c[8] || '',
    }),
  },
  {
    out: 'schools',
    file: 'Schools.html',
    section: (c) => (isClanHeader(c) ? titleCase(c[0]) : null),
    map: (c, clan) => ({
      name: c[0],
      clan: clan || '',
      role: c[1] || '',
      abilityName: c[2] || '',
      abilityText: oneLine(c[3]),
      techniques: oneLine(c[4]),
      masteryName: c[5] || '',
      masteryAbility: oneLine(c[6]),
      source: c[7] || '',
    }),
  },
  {
    out: 'clans',
    file: 'Clans.html',
    map: (c) => ({ name: c[0], status: c[1] || '', source: c[2] || '' }),
  },
  {
    out: 'families',
    file: 'Families.html',
    section: (c) => (isClanHeader(c) ? titleCase(c[0]) : null),
    map: (c, clan) => ({
      name: c[0],
      wealth: c[1] || '',
      glory: c[2] || '',
      clan: c[3] || clan || '',
      source: c[4] || '',
    }),
  },
  {
    out: 'titles',
    file: 'Titles.html',
    map: (c) => ({
      name: c[0],
      abilityName: c[1] || '',
      abilityText: oneLine(c[2]),
      xp: c[3] || '',
      skillGroup: c[4] || '',
      skills: [c[5], c[6], c[7]].filter(Boolean),
      techniques: [c[8], c[9], c[10]].filter(Boolean),
      source: c[12] || '',
    }),
  },
  { out: 'distinctions', file: 'Distinctions.html', map: advantage },
  { out: 'passions', file: 'Passions.html', map: advantage },
  { out: 'adversities', file: 'Adversities.html', map: advantage },
  { out: 'anxieties', file: 'Anxieties.html', map: advantage },
  {
    out: 'inversions',
    file: 'Inversions.html',
    isHeader: (c) => /^!!!/.test(c[0] || ''),
    map: (c) => ({
      name: c[0],
      rank: c[1] || '',
      type: c[2] || '',
      ring: c[3] || '',
      activation: oneLine(c[4]),
      effects: oneLine(c[5]),
      opportunities: oneLine(c[6]),
      magnitude: oneLine(c[7]),
      source: c[8] || '',
    }),
  },
  {
    out: 'curriculum',
    file: 'Curriculum.html',
    isHeader: (c) => c[0] === '-' || /skill group/i.test(c[2] || ''),
    map: curriculumRow,
  },
]

function convert() {
  if (!fs.existsSync(SRC_DIR)) {
    console.error(`Source folder not found: ${SRC_DIR}`)
    process.exit(1)
  }
  fs.mkdirSync(OUT_DIR, { recursive: true })

  const manifest = []
  for (const table of TABLES) {
    const filePath = path.join(SRC_DIR, table.file)
    if (!fs.existsSync(filePath)) {
      console.warn(`! Missing ${table.file} — skipping`)
      continue
    }
    const rows = parseRows(fs.readFileSync(filePath, 'utf8'))
    const entries = []
    let section = null
    for (const c of rows) {
      if (table.section) {
        const s = table.section(c)
        if (s) {
          section = s
          continue
        }
      }
      if (table.isHeader && table.isHeader(c)) continue
      const name = (c[0] || '').trim()
      if (!name || name === '-' || /^!!!/.test(name)) continue
      entries.push(table.map(c, section))
    }
    const outPath = path.join(OUT_DIR, `${table.out}.json`)
    fs.writeFileSync(outPath, JSON.stringify(entries, null, 2) + '\n', 'utf8')
    console.log(`✓ ${table.out.padEnd(13)} ${String(entries.length).padStart(4)} entries -> ${outPath}`)
    manifest.push({ key: table.out, count: entries.length })
  }

  fs.writeFileSync(path.join(OUT_DIR, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n', 'utf8')
  console.log(`\nDone. ${manifest.length} categories written to ${OUT_DIR}`)
}

convert()
