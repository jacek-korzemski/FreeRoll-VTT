import React, { useState, useEffect, useMemo } from 'react'
import { t } from '../../lang'

// One lazy chunk per data file: only the selected category's JSON is fetched.
const LOADERS = {
  weapons: () => import('../../assets/l5r/data/weapons.json'),
  armors: () => import('../../assets/l5r/data/armors.json'),
  techniques: () => import('../../assets/l5r/data/techniques.json'),
  schools: () => import('../../assets/l5r/data/schools.json'),
  clans: () => import('../../assets/l5r/data/clans.json'),
  families: () => import('../../assets/l5r/data/families.json'),
  titles: () => import('../../assets/l5r/data/titles.json'),
  distinctions: () => import('../../assets/l5r/data/distinctions.json'),
  passions: () => import('../../assets/l5r/data/passions.json'),
  adversities: () => import('../../assets/l5r/data/adversities.json'),
  anxieties: () => import('../../assets/l5r/data/anxieties.json'),
  inversions: () => import('../../assets/l5r/data/inversions.json'),
  curriculum: () => import('../../assets/l5r/data/curriculum.json'),
}

const CATEGORIES = Object.keys(LOADERS)

const KEY_LABELS = {
  grips: 'Grips',
  qualities: 'Qualities',
  description: 'Description',
  source: 'Source',
  resistance: 'Resistance',
  activation: 'Activation',
  effects: 'Effects',
  opportunities: 'Opportunities',
  restriction: 'Restriction',
  magnitude: 'Magnitude',
  abilityName: 'Ability',
  abilityText: '',
  techniques: 'Techniques',
  masteryName: 'Mastery',
  masteryAbility: '',
  skillGroup: 'Skill group',
  skills: 'Skills',
  check: 'When',
}

const advantageDisplay = {
  subtitle: (e) => [e.ring, e.traits].filter(Boolean).join(' · '),
  fields: ['description', 'check', 'source'],
}

const DISPLAY = {
  weapons: {
    subtitle: (e) => [e.range && `Range ${e.range}`, e.damage].filter(Boolean).join(' · '),
    fields: ['grips', 'qualities', 'description', 'source'],
  },
  armors: {
    subtitle: (e) => e.resistance,
    fields: ['qualities', 'description', 'source'],
  },
  techniques: {
    subtitle: (e) => [e.type, e.rank && `Rank ${e.rank}`, e.ring].filter(Boolean).join(' · '),
    fields: ['activation', 'effects', 'opportunities', 'restriction', 'source'],
  },
  inversions: {
    subtitle: (e) => [e.type, e.rank && `Rank ${e.rank}`, e.ring].filter(Boolean).join(' · '),
    fields: ['activation', 'effects', 'opportunities', 'magnitude', 'source'],
  },
  schools: {
    subtitle: (e) => [e.clan, e.role].filter(Boolean).join(' · '),
    fields: ['abilityName', 'abilityText', 'techniques', 'masteryName', 'masteryAbility', 'source'],
  },
  clans: {
    subtitle: (e) => (e.status ? `Status ${e.status}` : ''),
    fields: ['source'],
  },
  families: {
    subtitle: (e) => [e.clan, e.wealth, e.glory && `Glory ${e.glory}`].filter(Boolean).join(' · '),
    fields: ['source'],
  },
  titles: {
    subtitle: (e) => (e.xp ? `XP ${e.xp}` : ''),
    fields: ['abilityName', 'abilityText', 'skillGroup', 'skills', 'techniques', 'source'],
  },
  distinctions: advantageDisplay,
  passions: advantageDisplay,
  adversities: advantageDisplay,
  anxieties: advantageDisplay,
}

const MAX_RESULTS = 200

function FieldLine({ fieldKey, value }) {
  if (value === undefined || value === null || value === '') return null
  const text = Array.isArray(value) ? value.join(', ') : String(value)
  if (!text) return null
  const label = KEY_LABELS[fieldKey]
  return (
    <div className="l5r-comp-field">
      {label ? <strong>{label}: </strong> : null}
      {text}
    </div>
  )
}

function CurriculumEntry({ entry }) {
  const ranks = Array.isArray(entry.ranks) ? entry.ranks : []
  if (ranks.length === 0) return null

  return (
    <div className="l5r-comp-curriculum">
      {ranks.map((rank, i) => {
        if (!rank) return null
        const skills = Array.isArray(rank.skills) ? rank.skills : []
        const techniques = Array.isArray(rank.techniques) ? rank.techniques : []
        const hasTechniques = rank.techniqueGroup || techniques.length > 0

        return (
          <div key={i} className="l5r-comp-rank">
            <strong>Rank {i + 1}:</strong>{' '}
            {[rank.skillGroup, ...skills].filter(Boolean).join(', ')}
            {hasTechniques && (
              <div className="l5r-comp-rank-tech">
                {[rank.techniqueGroup, ...techniques].filter(Boolean).join(', ')}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}

function CompendiumPanel() {
  const [category, setCategory] = useState('weapons')
  const [query, setQuery] = useState('')
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setData([])

    LOADERS[category]()
      .then((mod) => {
        if (!cancelled) setData(mod.default || [])
      })
      .catch(() => {
        if (!cancelled) setData([])
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [category])

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    if (!q) return data
    return data.filter((entry) => JSON.stringify(entry).toLowerCase().includes(q))
  }, [data, query])

  const shown = filtered.slice(0, MAX_RESULTS)
  const display = DISPLAY[category]

  return (
    <div className="l5r-compendium">
      <div className="l5r-comp-toolbar">
        <div className="l5r-comp-categories">
          {CATEGORIES.map((key) => (
            <button
              key={key}
              className={`l5r-comp-cat ${category === key ? 'active' : ''}`}
              onClick={() => {
                setCategory(key)
                setQuery('')
                setLoading(true)
                setData([])
              }}
            >
              {t(`l5r.cat_${key}`)}
            </button>
          ))}
        </div>
        <input
          type="text"
          className="l5r-comp-search"
          placeholder={t('l5r.search')}
          value={query}
          onChange={(e) => setQuery(e.target.value)}
        />
      </div>

      <div className="l5r-comp-results">
        {loading && <div className="l5r-comp-loading">{t('l5r.loading')}</div>}
        {!loading && shown.length === 0 && <div className="l5r-comp-empty">{t('l5r.empty')}</div>}
        {!loading && (
          <div className="l5r-comp-count">{filtered.length}</div>
        )}
        {!loading &&
          shown.map((entry, i) => (
            <div key={`${entry.name}-${i}`} className="l5r-comp-entry">
              <div className="l5r-comp-entry-head">
                <span className="l5r-comp-name">{entry.name}</span>
                {display?.subtitle?.(entry) && (
                  <span className="l5r-comp-subtitle">{display.subtitle(entry)}</span>
                )}
              </div>
              {category === 'curriculum' ? (
                <CurriculumEntry entry={entry} />
              ) : (
                <div className="l5r-comp-entry-body">
                  {display?.fields?.map((f) => (
                    <FieldLine key={f} fieldKey={f} value={entry[f]} />
                  ))}
                </div>
              )}
            </div>
          ))}
        {!loading && filtered.length > MAX_RESULTS && (
          <div className="l5r-comp-more">+{filtered.length - MAX_RESULTS}…</div>
        )}
      </div>
    </div>
  )
}

export default CompendiumPanel
