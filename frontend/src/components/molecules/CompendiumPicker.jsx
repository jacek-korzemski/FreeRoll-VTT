import React, { useState, useEffect, useMemo } from 'react'
import { t } from '../../lang'

const LOADERS = {
  weapon: () => import('../../assets/l5r/data/weapons.json'),
  armor: () => import('../../assets/l5r/data/armors.json'),
  technique: () => import('../../assets/l5r/data/techniques.json'),
}

const CATEGORY_LABEL_KEY = { weapon: 'cat_weapons', armor: 'cat_armors', technique: 'cat_techniques' }

const MAX_RESULTS = 100

function CompendiumPicker({ category, onSelect, onClose }) {
  const [data, setData] = useState([])
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    const loader = LOADERS[category]
    if (!loader) {
      setLoading(false)
      return
    }
    setLoading(true)
    loader()
      .then((m) => {
        if (!cancelled) setData(m.default || [])
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
    return data.filter((e) => JSON.stringify(e).toLowerCase().includes(q))
  }, [data, query])

  const shown = filtered.slice(0, MAX_RESULTS)

  const subtitle = (e) =>
    category === 'weapon'
      ? [e.range && `Range ${e.range}`, e.damage].filter(Boolean).join(' · ')
      : category === 'armor'
        ? e.resistance
        : [e.type, e.rank && `Rank ${e.rank}`, e.ring].filter(Boolean).join(' · ')

  const detail = (e) =>
    category === 'weapon' ? e.qualities : category === 'armor' ? e.qualities : e.activation

  return (
    <div
      className="note-template-modal l5r-picker-modal"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose()
      }}
    >
      <div className="note-template-modal-content">
        <h3>{t('l5r.pickerTitle', { category: t(`l5r.${CATEGORY_LABEL_KEY[category] || 'cat_weapons'}`) })}</h3>
        <input
          type="text"
          className="l5r-comp-search"
          placeholder={t('l5r.search')}
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          autoFocus
        />
        <div className="l5r-picker-list">
          {loading && <div className="l5r-comp-loading">{t('l5r.loading')}</div>}
          {!loading && shown.length === 0 && <div className="l5r-comp-empty">{t('l5r.empty')}</div>}
          {!loading &&
            shown.map((e, i) => (
              <button
                key={`${e.name}-${i}`}
                type="button"
                className="l5r-picker-item"
                onClick={() => onSelect(e)}
              >
                <span className="l5r-comp-name">{e.name}</span>
                {subtitle(e) && <span className="l5r-comp-subtitle">{subtitle(e)}</span>}
                {detail(e) && <span className="l5r-picker-detail">{detail(e)}</span>}
              </button>
            ))}
        </div>
        <div className="note-template-modal-footer">
          <button onClick={onClose} className="note-template-cancel">
            {t('l5r.cancel')}
          </button>
        </div>
      </div>
    </div>
  )
}

export default CompendiumPicker
