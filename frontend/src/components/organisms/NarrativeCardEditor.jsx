import { useEffect, useState } from 'react'
import { API_BASE } from '../../../config'
import { t } from '../../lang'
import { getImageSrc } from '../../utils/sidebarHelpers'
import FolderList from '../molecules/FolderList'
import AssetGrid from '../molecules/AssetGrid'

const KINDS = [
  { id: 'map', labelKey: 'scenes.imageMap' },
  { id: 'background', labelKey: 'scenes.imageBackground' },
  { id: 'token', labelKey: 'scenes.imageToken' },
]

function listUrl(kind, source, path) {
  const sourceQuery = source === 'global' ? '&source=global' : ''
  if (kind === 'background') {
    return `${API_BASE}?action=assets${sourceQuery}`
  }
  const action = kind === 'map' ? 'list-map' : 'list-tokens'
  const pathQuery = path ? `&path=${encodeURIComponent(path)}` : ''
  return `${API_BASE}?action=${action}${sourceQuery}${pathQuery}`
}

function NarrativeCardEditor({ basePath, globalAssets = false, onCancel, onSaved }) {
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [kind, setKind] = useState('token')
  const [source, setSource] = useState('table')
  const [path, setPath] = useState('')
  const [folders, setFolders] = useState([])
  const [files, setFiles] = useState([])
  const [loading, setLoading] = useState(true)
  const [selected, setSelected] = useState(null)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState(false)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    fetch(listUrl(kind, source, path), { credentials: 'include' })
      .then((res) => res.json())
      .then((data) => {
        if (cancelled || !data.success) return
        if (kind === 'background') {
          setFolders([])
          setFiles(Array.isArray(data.backgroundAssets) ? data.backgroundAssets : [])
          return
        }
        setFolders(Array.isArray(data.folders) ? data.folders : [])
        setFiles(Array.isArray(data.files) ? data.files : [])
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
  }, [kind, source, path])

  const canSave = !saving && !!(title.trim() || body.trim() || selected?.src)

  const save = (addToScene) => {
    if (!canSave) return
    setSaving(true)
    setSaveError(false)
    fetch(`${API_BASE}?action=card-library-save`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        src: selected?.src || '',
        title: title.trim(),
        body,
      }),
    })
      .then((res) => res.json().then((data) => {
        if (!res.ok || !data.success || !data.card) throw new Error(data.error || 'save failed')
        return data.card
      }))
      .then((card) => onSaved(card, addToScene))
      .catch(() => {
        setSaveError(true)
        setSaving(false)
      })
  }

  const imageSrc = selected?.src ? getImageSrc(selected.src, basePath) : ''

  return (
    <div className="card-editor">
      <div className="card-editor-kinds" role="tablist">
        {KINDS.map((item) => (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={kind === item.id}
            className={kind === item.id ? 'active' : ''}
            onClick={() => {
              setKind(item.id)
              setPath('')
            }}
          >
            {t(item.labelKey)}
          </button>
        ))}
      </div>
      <div className="card-editor-body">
        <div className="card-editor-fields">
          <div className="card-editor-preview">
            {imageSrc ? (
              <img src={imageSrc} alt="" />
            ) : (
              <span>{t('scenes.libraryNoImage')}</span>
            )}
          </div>
          {selected?.src ? (
            <button type="button" className="card-library-delete" onClick={() => setSelected(null)}>
              {t('scenes.libraryClearImage')}
            </button>
          ) : null}
          <label>
            {t('scenes.cardTitle')}
            <input
              type="text"
              maxLength={120}
              value={title}
              placeholder={t('scenes.cardTitle')}
              onChange={(event) => setTitle(event.target.value)}
            />
          </label>
          <label>
            {t('scenes.cardBody')}
            <textarea
              rows={6}
              maxLength={4000}
              value={body}
              placeholder={t('scenes.cardBody')}
              onChange={(event) => setBody(event.target.value)}
            />
          </label>
        </div>
        <div className="card-editor-picker">
          {globalAssets ? (
            <div className="asset-source-switch" role="tablist">
              <button
                type="button"
                role="tab"
                aria-selected={source !== 'global'}
                className={source !== 'global' ? 'active' : ''}
                onClick={() => {
                  setSource('table')
                  setPath('')
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
                  setSource('global')
                  setPath('')
                }}
              >
                {t('sidebar.assetSourceGlobal')}
              </button>
            </div>
          ) : null}
          {loading ? <p className="note-template-loading">{t('app.loading')}</p> : null}
          {kind !== 'background' ? (
            <FolderList path={path} folders={folders} onPathChange={setPath} />
          ) : null}
          <AssetGrid
            assets={files}
            selectedAsset={selected?.kind === kind ? selected : null}
            selectedType={kind}
            basePath={basePath}
            assetType={kind}
            onSelectAsset={(asset) => setSelected({ ...asset, kind })}
            showEmptyMessage={!loading && files.length === 0 && folders.length === 0}
          />
        </div>
      </div>
      {saveError ? <p className="note-template-error">{t('scenes.librarySaveError')}</p> : null}
      <div className="note-template-modal-footer board-modal-footer card-editor-footer">
        <button type="button" className="note-template-cancel" onClick={onCancel} disabled={saving}>
          {t('scenes.cancel')}
        </button>
        <button type="button" className="note-template-cancel" disabled={!canSave} onClick={() => save(false)}>
          {t('scenes.librarySave')}
        </button>
        <button type="button" className="board-modal-primary" disabled={!canSave} onClick={() => save(true)}>
          {t('scenes.librarySaveAdd')}
        </button>
      </div>
    </div>
  )
}

export default NarrativeCardEditor
