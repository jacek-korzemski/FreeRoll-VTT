import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { t } from '../../lang'
import FolderList from '../molecules/FolderList'
import AssetGrid from '../molecules/AssetGrid'

function assetListQuery(source, path) {
  const sourceQuery = source === 'global' ? '&source=global' : ''
  const pathQuery = path ? `&path=${encodeURIComponent(path)}` : ''
  return `${sourceQuery}${pathQuery}`
}

function parseHp(value) {
  if (value === '' || value == null) return null
  const number = Number(value)
  if (!Number.isFinite(number)) return null
  return Math.max(0, Math.min(99999, Math.round(number)))
}

function CombatantCreateModal({
  side,
  basePath,
  apiBase,
  globalAssets,
  onClose,
  onConfirm,
}) {
  const [name, setName] = useState('')
  const [hp, setHp] = useState('')
  const [maxHp, setMaxHp] = useState('')
  const [notes, setNotes] = useState('')
  const [path, setPath] = useState('')
  const [source, setSource] = useState('table')
  const [folders, setFolders] = useState([])
  const [files, setFiles] = useState([])
  const [loading, setLoading] = useState(true)
  const [selected, setSelected] = useState(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    fetch(`${apiBase}?action=list-tokens${assetListQuery(source, path)}`, { credentials: 'include' })
      .then((res) => res.json())
      .then((data) => {
        if (cancelled || !data.success) return
        setFolders(data.folders || [])
        setFiles(data.files || [])
      })
      .catch(() => {
        if (!cancelled) {
          setFolders([])
          setFiles([])
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [apiBase, path, source])

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const submit = (event) => {
    event.preventDefault()
    if (!selected?.src) return
    onConfirm({
      side,
      assetId: selected.id,
      src: selected.src,
      name: name.trim(),
      notes,
      hp: parseHp(hp),
      maxHp: parseHp(maxHp),
    })
    onClose()
  }

  return createPortal(
    <div className="note-template-modal" onClick={onClose} role="presentation">
      <div
        className="note-template-modal-content combatant-create-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="combatant-create-title"
        onClick={(event) => event.stopPropagation()}
      >
        <h3 id="combatant-create-title">
          {side === 'player' ? t('scenes.addPlayerTitle') : t('scenes.addEnemyTitle')}
        </h3>
        <form className="combatant-create-form" onSubmit={submit}>
          <label>
            {t('scenes.characterName')}
            <input
              className="combat-name-input"
              type="text"
              maxLength={80}
              value={name}
              placeholder={t('scenes.characterName')}
              onChange={(event) => setName(event.target.value)}
            />
          </label>
          <div className="combat-hp-nums">
            <label>
              {t('scenes.hpCurrent')}
              <input
                type="number"
                min="0"
                max="99999"
                value={hp}
                aria-label={t('scenes.hpCurrent')}
                onChange={(event) => setHp(event.target.value)}
              />
            </label>
            <span>/</span>
            <label>
              {t('scenes.hpMax')}
              <input
                type="number"
                min="0"
                max="99999"
                value={maxHp}
                aria-label={t('scenes.hpMax')}
                onChange={(event) => setMaxHp(event.target.value)}
              />
            </label>
          </div>
          <label>
            {t('scenes.notes')}
            <textarea
              className="combat-notes"
              rows={3}
              maxLength={2000}
              value={notes}
              placeholder={t('scenes.notes')}
              onChange={(event) => setNotes(event.target.value)}
            />
          </label>
          <div className="combatant-create-browser">
            <p className="board-hint">{t('scenes.pickToken')}</p>
            {globalAssets && (
              <div className="asset-source-switch" role="tablist">
                <button
                  type="button"
                  role="tab"
                  aria-selected={source !== 'global'}
                  className={source !== 'global' ? 'active' : ''}
                  onClick={() => {
                    setPath('')
                    setSource('table')
                  }}
                >
                  {t('sidebar.assetSourceTable')}
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={source === 'global'}
                  className={source === 'global' ? 'active' : ''}
                  onClick={() => {
                    setPath('')
                    setSource('global')
                  }}
                >
                  {t('sidebar.assetSourceGlobal')}
                </button>
              </div>
            )}
            {loading ? <p className="note-template-loading">{t('app.loading')}</p> : null}
            <FolderList path={path} folders={folders} onPathChange={setPath} />
            <AssetGrid
              assets={files}
              selectedAsset={selected}
              selectedType="token"
              basePath={basePath}
              onSelectAsset={(asset) => setSelected(asset)}
              assetType="token"
              showEmptyMessage={!loading && files.length === 0 && folders.length === 0}
            />
          </div>
          <div className="note-template-modal-footer board-modal-footer">
            <button type="button" className="note-template-cancel" onClick={onClose}>
              {t('scenes.cancel')}
            </button>
            <button type="submit" className="board-modal-primary" disabled={!selected?.src}>
              {side === 'player' ? t('scenes.addPlayer') : t('scenes.addEnemy')}
            </button>
          </div>
        </form>
      </div>
    </div>,
    document.body,
  )
}

export default CombatantCreateModal
