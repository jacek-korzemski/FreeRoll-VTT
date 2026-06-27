import { useState, useCallback, useEffect, useMemo } from 'react'
import { API_BASE } from '../../../config'
import { t } from '../../lang'
import ConfirmModal from '../atoms/ConfirmModal'
import Breadcrumbs from '../atoms/Breadcrumbs'
import FolderList from '../molecules/FolderList'
import {
  collectClientTemplateUsage,
  getAssetServerUseScenes,
  getTemplateClientUseSources,
  isAssetInServerUse,
  isTemplateInClientUse,
} from '../../utils/assetUsage'

const TYPE_TOKEN = 'token'
const TYPE_MAP = 'map'
const TYPE_BACKGROUND = 'background'
const TYPE_TEMPLATE = 'template'
const TYPE_PAPER = 'paper'

const FOLDER_TYPES = new Set([TYPE_TOKEN, TYPE_MAP])

function deleteIdForAsset(type, asset) {
  if (type === TYPE_BACKGROUND) {
    return asset.filename || asset.id
  }
  return asset.id
}

function MaterialsDeleteSection({ onDeleted }) {
  const [selectedType, setSelectedType] = useState(TYPE_TOKEN)
  const [browsePath, setBrowsePath] = useState('')
  const [assets, setAssets] = useState([])
  const [folders, setFolders] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [serverUsage, setServerUsage] = useState(null)
  const [selectedIds, setSelectedIds] = useState(() => new Set())
  const [deleting, setDeleting] = useState(false)
  const [resultMessage, setResultMessage] = useState(null)
  const [confirmOpen, setConfirmOpen] = useState(false)

  const clientTemplateUsage = useMemo(() => collectClientTemplateUsage(), [assets, serverUsage])

  const fetchUsage = useCallback(async () => {
    try {
      const res = await fetch(`${API_BASE}?action=get-asset-usage`, { credentials: 'include' })
      const data = await res.json()
      if (data.success) {
        setServerUsage(data.usage || {})
      }
    } catch {
      /* usage info is optional for listing */
    }
  }, [])

  const fetchAssets = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      let nextAssets = []
      let nextFolders = []

      if (selectedType === TYPE_TOKEN || selectedType === TYPE_MAP) {
        const action = selectedType === TYPE_TOKEN ? 'list-tokens' : 'list-map'
        const pathParam = browsePath ? `&path=${encodeURIComponent(browsePath)}` : ''
        const res = await fetch(`${API_BASE}?action=${action}${pathParam}`, { credentials: 'include' })
        const data = await res.json()
        if (!data.success) throw new Error('list failed')
        nextAssets = data.files || []
        nextFolders = data.folders || []
      } else if (selectedType === TYPE_BACKGROUND) {
        const res = await fetch(`${API_BASE}?action=assets`, { credentials: 'include' })
        const data = await res.json()
        if (!data.success) throw new Error('list failed')
        nextAssets = (data.backgroundAssets || []).map((bg) => ({
          id: bg.filename || bg.id,
          name: bg.name,
          filename: bg.filename,
        }))
      } else if (selectedType === TYPE_TEMPLATE) {
        const res = await fetch(`${API_BASE}?action=list-templates`, { credentials: 'include' })
        const data = await res.json()
        if (!data.success) throw new Error('list failed')
        nextAssets = data.templates || []
      } else if (selectedType === TYPE_PAPER) {
        const res = await fetch(`${API_BASE}?action=list-papers`, { credentials: 'include' })
        const data = await res.json()
        if (!data.success) throw new Error('list failed')
        nextAssets = data.papers || []
      }

      setAssets(nextAssets)
      setFolders(nextFolders)
    } catch {
      setError(t('materialsDelete.loadError'))
      setAssets([])
      setFolders([])
    } finally {
      setLoading(false)
    }
  }, [selectedType, browsePath])

  useEffect(() => {
    fetchUsage()
  }, [fetchUsage])

  useEffect(() => {
    setSelectedIds(new Set())
    setResultMessage(null)
    fetchAssets()
  }, [fetchAssets])

  const handleTypeChange = useCallback((event) => {
    setSelectedType(event.target.value)
    setBrowsePath('')
    setSelectedIds(new Set())
    setResultMessage(null)
    setConfirmOpen(false)
  }, [])

  const getItemStatus = useCallback((asset) => {
    const id = deleteIdForAsset(selectedType, asset)

    if (selectedType === TYPE_TEMPLATE) {
      if (isTemplateInClientUse(id, clientTemplateUsage)) {
        return {
          inUse: true,
          reason: t('materialsDelete.inUseTemplate', {
            sources: getTemplateClientUseSources(id, clientTemplateUsage).join(', '),
          }),
        }
      }
      return { inUse: false }
    }

    if (isAssetInServerUse(selectedType, id, serverUsage)) {
      return {
        inUse: true,
        reason: t('materialsDelete.inUseScene', {
          scenes: getAssetServerUseScenes(selectedType, id, serverUsage).join(', '),
        }),
      }
    }

    return { inUse: false }
  }, [selectedType, serverUsage, clientTemplateUsage])

  const deletableAssets = useMemo(
    () => assets.filter((asset) => !getItemStatus(asset).inUse),
    [assets, getItemStatus]
  )

  const toggleSelection = useCallback((asset) => {
    const id = deleteIdForAsset(selectedType, asset)
    const status = getItemStatus(asset)
    if (status.inUse) return

    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }, [selectedType, getItemStatus])

  const selectAllDeletable = useCallback(() => {
    setSelectedIds(new Set(deletableAssets.map((asset) => deleteIdForAsset(selectedType, asset))))
  }, [deletableAssets, selectedType])

  const clearSelection = useCallback(() => {
    setSelectedIds(new Set())
  }, [])

  const selectedAssets = useMemo(
    () => assets.filter((asset) => selectedIds.has(deleteIdForAsset(selectedType, asset))),
    [assets, selectedIds, selectedType]
  )

  const handleDeleteRequest = useCallback(() => {
    if (selectedIds.size === 0) return
    setConfirmOpen(true)
  }, [selectedIds.size])

  const handleDeleteConfirm = useCallback(async () => {
    if (selectedIds.size === 0) return

    setDeleting(true)
    setResultMessage(null)
    setConfirmOpen(false)

    const items = Array.from(selectedIds).map((id) => ({
      type: selectedType,
      id,
    }))

    try {
      const res = await fetch(`${API_BASE}?action=delete-assets`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ items }),
      })
      const data = await res.json()

      const deletedCount = data.deleted?.length || 0
      const blockedCount = data.blocked?.length || 0
      const errorCount = data.errors?.length || 0

      if (deletedCount > 0) {
        setResultMessage(t('materialsDelete.success', { count: deletedCount }))
        setSelectedIds(new Set())
        await fetchUsage()
        await fetchAssets()
        onDeleted?.(selectedType)
        if (selectedType === TYPE_TEMPLATE) {
          window.dispatchEvent(new CustomEvent('vtt:templates-changed'))
        }
      }

      if (blockedCount > 0) {
        setResultMessage((prev) =>
          [prev, t('materialsDelete.blocked', { count: blockedCount })].filter(Boolean).join(' ')
        )
      }
      if (errorCount > 0 && deletedCount === 0) {
        setResultMessage(t('materialsDelete.failed'))
      }
    } catch {
      setResultMessage(t('materialsDelete.failed'))
    } finally {
      setDeleting(false)
    }
  }, [selectedIds, selectedType, fetchAssets, fetchUsage, onDeleted])

  const rootTitle =
    selectedType === TYPE_TOKEN
      ? t('sidebar.tokensRoot')
      : t('sidebar.mapElementsRoot')

  return (
    <div className="materials-delete-section">
      <div className="materials-delete-header">
        <span className="materials-delete-title">{t('materialsDelete.title')}</span>
      </div>

      <div className="upload-row">
        <label className="upload-label">{t('upload.typeLabel')}</label>
        <select className="upload-select" value={selectedType} onChange={handleTypeChange}>
          <option value={TYPE_TOKEN}>{t('upload.types.tokens')}</option>
          <option value={TYPE_MAP}>{t('upload.types.map')}</option>
          <option value={TYPE_BACKGROUND}>{t('upload.types.backgrounds')}</option>
          <option value={TYPE_TEMPLATE}>{t('upload.types.templates')}</option>
          <option value={TYPE_PAPER}>{t('upload.types.papers')}</option>
        </select>
      </div>

      {FOLDER_TYPES.has(selectedType) && (browsePath || folders.length > 0) && (
        <Breadcrumbs
          path={browsePath}
          onPathChange={setBrowsePath}
          rootIcon={selectedType === TYPE_TOKEN ? '🧍' : '🏠'}
          rootTitle={rootTitle}
        />
      )}

      {FOLDER_TYPES.has(selectedType) && (
        <FolderList path={browsePath} folders={folders} onPathChange={setBrowsePath} />
      )}

      {loading && <div className="materials-delete-loading">{t('app.loading')}</div>}
      {error && <div className="upload-message upload-message-error">{error}</div>}

      {!loading && !error && assets.length === 0 && (
        <p className="no-assets">{t('materialsDelete.empty')}</p>
      )}

      {!loading && assets.length > 0 && (
        <>
          <div className="materials-delete-toolbar">
            <button
              type="button"
              className="materials-delete-link-btn"
              onClick={selectAllDeletable}
              disabled={deletableAssets.length === 0}
            >
              {t('materialsDelete.selectAll')}
            </button>
            <button
              type="button"
              className="materials-delete-link-btn"
              onClick={clearSelection}
              disabled={selectedIds.size === 0}
            >
              {t('materialsDelete.clearSelection')}
            </button>
            <span className="materials-delete-count">
              {t('materialsDelete.selectedCount', { count: selectedIds.size })}
            </span>
          </div>

          <ul className="materials-delete-list">
            {assets.map((asset) => {
              const id = deleteIdForAsset(selectedType, asset)
              const status = getItemStatus(asset)
              const checked = selectedIds.has(id)

              return (
                <li
                  key={id}
                  className={`materials-delete-item ${status.inUse ? 'in-use' : ''} ${checked ? 'selected' : ''}`}
                >
                  <label className="materials-delete-item-label">
                    <input
                      type="checkbox"
                      checked={checked}
                      disabled={status.inUse || deleting}
                      onChange={() => toggleSelection(asset)}
                    />
                    <span className="materials-delete-item-name" title={asset.name}>
                      {asset.name || id}
                    </span>
                  </label>
                  {status.inUse && (
                    <span className="materials-delete-in-use" title={status.reason}>
                      {t('materialsDelete.inUseBadge')}
                    </span>
                  )}
                </li>
              )
            })}
          </ul>
        </>
      )}

      <div className="upload-actions">
        <button
          type="button"
          className="materials-delete-button"
          onClick={handleDeleteRequest}
          disabled={deleting || selectedIds.size === 0}
        >
          {deleting ? t('materialsDelete.deleting') : t('materialsDelete.deleteSelected')}
        </button>
      </div>

      {resultMessage && (
        <div className="upload-message upload-message-success">{resultMessage}</div>
      )}

      {confirmOpen && (
        <ConfirmModal
          title={t('materialsDelete.confirmTitle')}
          message={t('materialsDelete.confirmMessage', { count: selectedIds.size })}
          confirmLabel={t('materialsDelete.confirmOk')}
          cancelLabel={t('notes.templateCancel')}
          danger
          onConfirm={handleDeleteConfirm}
          onCancel={() => setConfirmOpen(false)}
        >
          <ul className="materials-delete-confirm-list">
            {selectedAssets.map((asset) => (
              <li key={deleteIdForAsset(selectedType, asset)}>
                {asset.name || deleteIdForAsset(selectedType, asset)}
              </li>
            ))}
          </ul>
        </ConfirmModal>
      )}
    </div>
  )
}

export default MaterialsDeleteSection
