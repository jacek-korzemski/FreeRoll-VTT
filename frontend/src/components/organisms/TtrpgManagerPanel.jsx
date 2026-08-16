import React, { useCallback, useEffect, useState } from 'react'
import { t } from '../../lang'
import {
  ttrpgProxy,
  ttrpgSetKey,
  ttrpgClearKey,
  ttrpgSelectCampaign,
  ttrpgUploadAsset,
  ttrpgUploadHandbook,
  abortTtrpgProxyRequests,
  unwrapList,
  unwrapItem,
} from '../../utils/ttrpgApi'

const CONTENT_TYPES = ['note', 'location', 'npc', 'item', 'quest']
const SUBTABS = ['campaign', 'contents', 'maps', 'trackers', 'sheets', 'handbooks']

function HtmlView({ html }) {
  if (!html) return <p className="ttrpg-muted">{t('ttrpg.emptyBody')}</p>
  return <div className="ttrpg-html" dangerouslySetInnerHTML={{ __html: html }} />
}

function LoadingSpinner({ label, inline = false }) {
  return (
    <div className={`ttrpg-loading${inline ? ' inline' : ''}`} role="status" aria-live="polite">
      <span className="ttrpg-spinner" aria-hidden="true" />
      <span>{label || t('ttrpg.loadingData')}</span>
    </div>
  )
}

function NeedCampaign() {
  return (
    <div className="ttrpg-pane">
      <p className="ttrpg-muted ttrpg-muted-center">{t('ttrpg.needCampaign')}</p>
    </div>
  )
}

function ErrorBanner({ error, onDismiss }) {
  if (!error) return null
  return (
    <div className="ttrpg-error" role="alert">
      <span>{error}</span>
      {onDismiss && (
        <button type="button" className="ttrpg-btn-ghost" onClick={onDismiss}>
          ×
        </button>
      )}
    </div>
  )
}

function proxyErrorMessage(res, fallbackKey = 'ttrpg.loadFailed') {
  if (!res) return t(fallbackKey)
  if (res.status === 401 || res.status === 403) return t('ttrpg.authFailed')
  if (res.error === 'Request timed out' || res.status === 0) return t('ttrpg.requestTimeout')
  return res.error || t(fallbackKey)
}

function ConnectForm({ apiBase, onConnected }) {
  const [baseUrl, setBaseUrl] = useState('http://localhost:8000')
  const [apiKey, setApiKey] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const data = await ttrpgSetKey(apiBase, { apiKey: apiKey.trim(), baseUrl: baseUrl.trim() })
      if (!data.success) {
        setError(data.error || t('ttrpg.connectFailed'))
        return
      }
      onConnected(data.ttrpgManager, data.version)
    } catch (err) {
      setError(err.message || t('ttrpg.connectFailed'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="ttrpg-connect-shell">
      <form className="ttrpg-connect" onSubmit={submit}>
        <h3>{t('ttrpg.connectTitle')}</h3>
        <p className="ttrpg-muted">{t('ttrpg.connectHint')}</p>
        <label className="ttrpg-field">
          <span>{t('ttrpg.baseUrl')}</span>
          <input
            type="url"
            value={baseUrl}
            onChange={(e) => setBaseUrl(e.target.value)}
            required
            autoComplete="off"
          />
        </label>
        <label className="ttrpg-field">
          <span>{t('ttrpg.apiKey')}</span>
          <input
            type="password"
            value={apiKey}
            onChange={(e) => setApiKey(e.target.value)}
            required
            autoComplete="off"
            placeholder="3|…"
          />
        </label>
        <ErrorBanner error={error} onDismiss={() => setError(null)} />
        {busy ? (
          <LoadingSpinner label={t('ttrpg.connecting')} inline />
        ) : (
          <button type="submit" className="ttrpg-btn" disabled={busy}>
            {t('ttrpg.connect')}
          </button>
        )}
      </form>
    </div>
  )
}

function CampaignPane({ apiBase, isGameMaster, campaignId, onCampaignChange, onStatus }) {
  const [campaigns, setCampaigns] = useState([])
  const [detail, setDetail] = useState(null)
  const [error, setError] = useState(null)
  const [form, setForm] = useState({ name: '', description: '', is_archived: false })
  const [creating, setCreating] = useState(false)
  const [listLoading, setListLoading] = useState(true)
  const [detailLoading, setDetailLoading] = useState(false)

  const load = useCallback(async () => {
    setListLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: 'gm/campaigns' })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setCampaigns(unwrapList(res))
    } finally {
      setListLoading(false)
    }
  }, [apiBase])

  const loadDetail = useCallback(async (id) => {
    if (!id) {
      setDetail(null)
      return
    }
    setDetailLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/campaigns/${id}` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      const item = unwrapItem(res)
      setDetail(item)
      if (item) {
        setForm({
          name: item.name || '',
          description: item.description || '',
          is_archived: !!item.is_archived,
        })
      }
    } finally {
      setDetailLoading(false)
    }
  }, [apiBase])

  useEffect(() => { load() }, [load])
  useEffect(() => { loadDetail(campaignId) }, [campaignId, loadDetail])

  const select = async (id) => {
    const num = id ? Number(id) : null
    if (isGameMaster) {
      const data = await ttrpgSelectCampaign(apiBase, num)
      if (!data.success) {
        setError(data.error || t('ttrpg.saveFailed'))
        return
      }
      onStatus(data.ttrpgManager, data.version)
    } else {
      onCampaignChange(num)
    }
  }

  const saveMeta = async () => {
    if (!campaignId || !isGameMaster) return
    const res = await ttrpgProxy(apiBase, {
      method: 'PUT',
      path: `gm/campaigns/${campaignId}`,
      json: {
        name: form.name,
        description: form.description,
        is_archived: form.is_archived,
      },
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    await load()
    await loadDetail(campaignId)
  }

  const createCampaign = async () => {
    if (!isGameMaster || !form.name.trim()) return
    const res = await ttrpgProxy(apiBase, {
      method: 'POST',
      path: 'gm/campaigns',
      json: { name: form.name.trim(), description: form.description || null },
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setCreating(false)
    await load()
    const item = unwrapItem(res)
    if (item?.id) await select(item.id)
  }

  return (
    <div className="ttrpg-pane">
      <ErrorBanner error={error} onDismiss={() => setError(null)} />
      {listLoading ? (
        <LoadingSpinner />
      ) : (
        <div className="ttrpg-toolbar center">
          <label className="ttrpg-field inline">
            <span>{t('ttrpg.campaign')}</span>
            <select
              value={campaignId || ''}
              onChange={(e) => select(e.target.value || null)}
              disabled={!isGameMaster}
            >
              <option value="">{t('ttrpg.selectCampaign')}</option>
              {campaigns.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}{c.is_archived ? ` (${t('ttrpg.archived')})` : ''}
                </option>
              ))}
            </select>
          </label>
          {isGameMaster && (
            <button type="button" className="ttrpg-btn" onClick={() => setCreating((v) => !v)}>
              {creating ? t('ttrpg.cancel') : t('ttrpg.newCampaign')}
            </button>
          )}
        </div>
      )}

      {creating && isGameMaster && (
        <div className="ttrpg-card">
          <label className="ttrpg-field">
            <span>{t('ttrpg.name')}</span>
            <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </label>
          <label className="ttrpg-field">
            <span>{t('ttrpg.description')}</span>
            <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} />
          </label>
          <button type="button" className="ttrpg-btn" onClick={createCampaign}>{t('ttrpg.create')}</button>
        </div>
      )}

      {detailLoading && <LoadingSpinner />}
      {!detailLoading && detail && (
        <div className="ttrpg-card">
          {isGameMaster ? (
            <>
              <label className="ttrpg-field">
                <span>{t('ttrpg.name')}</span>
                <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
              </label>
              <label className="ttrpg-field">
                <span>{t('ttrpg.description')}</span>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={4} />
              </label>
              <label className="ttrpg-check">
                <input
                  type="checkbox"
                  checked={form.is_archived}
                  onChange={(e) => setForm({ ...form, is_archived: e.target.checked })}
                />
                <span>{t('ttrpg.archived')}</span>
              </label>
              {detail.invite_code && (
                <p className="ttrpg-muted">{t('ttrpg.inviteCode')}: <code>{detail.invite_code}</code></p>
              )}
              <button type="button" className="ttrpg-btn" onClick={saveMeta}>{t('ttrpg.save')}</button>
            </>
          ) : (
            <>
              <h4>{detail.name}</h4>
              <p>{detail.description}</p>
            </>
          )}
        </div>
      )}
    </div>
  )
}

function ContentsPane({ apiBase, isGameMaster, campaignId }) {
  const [items, setItems] = useState([])
  const [typeFilter, setTypeFilter] = useState('')
  const [selected, setSelected] = useState(null)
  const [error, setError] = useState(null)
  const [editing, setEditing] = useState(null)
  const [listLoading, setListLoading] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)

  const load = useCallback(async () => {
    if (!campaignId) return
    setListLoading(true)
    try {
      const query = typeFilter ? { type: typeFilter } : undefined
      const res = await ttrpgProxy(apiBase, {
        method: 'GET',
        path: `gm/campaigns/${campaignId}/contents`,
        query,
      })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setItems(unwrapList(res))
    } finally {
      setListLoading(false)
    }
  }, [apiBase, campaignId, typeFilter])

  useEffect(() => { load() }, [load])

  const open = async (id) => {
    setDetailLoading(true)
    setEditing(null)
    try {
      const res = await ttrpgProxy(apiBase, {
        method: 'GET',
        path: `gm/campaigns/${campaignId}/contents/${id}`,
      })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setSelected(unwrapItem(res))
    } finally {
      setDetailLoading(false)
    }
  }

  const startCreate = () => {
    setEditing({
      type: 'note',
      title: '',
      content_html: '',
      visibility: 'players',
      status: '',
      priority: 0,
    })
    setSelected(null)
  }

  const startEdit = () => {
    if (!selected) return
    setEditing({
      type: selected.type,
      title: selected.title || '',
      content_html: selected.content_html || '',
      visibility: selected.visibility || 'players',
      status: selected.status || '',
      priority: selected.priority ?? 0,
    })
  }

  const save = async () => {
    if (!editing) return
    const json = {
      type: editing.type,
      title: editing.title,
      content_html: editing.content_html,
      visibility: editing.visibility,
      status: editing.status || null,
      priority: Number(editing.priority) || 0,
    }
    const res = selected
      ? await ttrpgProxy(apiBase, {
          method: 'PUT',
          path: `gm/campaigns/${campaignId}/contents/${selected.id}`,
          json,
        })
      : await ttrpgProxy(apiBase, {
          method: 'POST',
          path: `gm/campaigns/${campaignId}/contents`,
          json,
        })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setEditing(null)
    await load()
    const item = unwrapItem(res)
    if (item?.id) await open(item.id)
  }

  const remove = async () => {
    if (!selected || !window.confirm(t('ttrpg.confirmDelete'))) return
    const res = await ttrpgProxy(apiBase, {
      method: 'DELETE',
      path: `gm/campaigns/${campaignId}/contents/${selected.id}`,
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setSelected(null)
    await load()
  }

  const uploadCover = async (file) => {
    if (!file || !selected) return
    const up = await ttrpgUploadAsset(apiBase, campaignId, file)
    if (!up.success) {
      setError(proxyErrorMessage(up, 'ttrpg.saveFailed'))
      return
    }
    const asset = up.data?.data || up.data
    const res = await ttrpgProxy(apiBase, {
      method: 'PUT',
      path: `gm/campaigns/${campaignId}/contents/${selected.id}`,
      json: { cover_asset_id: asset?.id },
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    await open(selected.id)
  }

  if (!campaignId) return <NeedCampaign />

  return (
    <div className="ttrpg-pane ttrpg-split">
      <div className="ttrpg-list-col">
        <div className="ttrpg-toolbar">
          <select value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)}>
            <option value="">{t('ttrpg.allTypes')}</option>
            {CONTENT_TYPES.map((ty) => (
              <option key={ty} value={ty}>{t(`ttrpg.type.${ty}`)}</option>
            ))}
          </select>
          {isGameMaster && (
            <button type="button" className="ttrpg-btn" onClick={startCreate}>{t('ttrpg.add')}</button>
          )}
        </div>
        <ErrorBanner error={error} onDismiss={() => setError(null)} />
        {listLoading ? (
          <LoadingSpinner />
        ) : (
          <ul className="ttrpg-list">
            {items.map((item) => (
              <li key={item.id}>
                <button
                  type="button"
                  className={selected?.id === item.id ? 'active' : ''}
                  onClick={() => open(item.id)}
                >
                  <span className="ttrpg-badge">{item.type}</span> {item.title}
                </button>
              </li>
            ))}
            {items.length === 0 && <li className="ttrpg-muted">{t('ttrpg.empty')}</li>}
          </ul>
        )}
      </div>
      <div className="ttrpg-detail-col">
        {detailLoading ? (
          <LoadingSpinner />
        ) : editing && isGameMaster ? (
          <div className="ttrpg-card">
            <label className="ttrpg-field">
              <span>{t('ttrpg.typeLabel')}</span>
              <select value={editing.type} onChange={(e) => setEditing({ ...editing, type: e.target.value })}>
                {CONTENT_TYPES.map((ty) => (
                  <option key={ty} value={ty}>{t(`ttrpg.type.${ty}`)}</option>
                ))}
              </select>
            </label>
            <label className="ttrpg-field">
              <span>{t('ttrpg.title')}</span>
              <input value={editing.title} onChange={(e) => setEditing({ ...editing, title: e.target.value })} />
            </label>
            <label className="ttrpg-field">
              <span>{t('ttrpg.visibility')}</span>
              <select value={editing.visibility} onChange={(e) => setEditing({ ...editing, visibility: e.target.value })}>
                <option value="players">{t('ttrpg.visPlayers')}</option>
                <option value="gm_only">{t('ttrpg.visGm')}</option>
              </select>
            </label>
            <label className="ttrpg-field">
              <span>{t('ttrpg.bodyHtml')}</span>
              <textarea
                rows={10}
                value={editing.content_html}
                onChange={(e) => setEditing({ ...editing, content_html: e.target.value })}
              />
            </label>
            <div className="ttrpg-toolbar">
              <button type="button" className="ttrpg-btn" onClick={save}>{t('ttrpg.save')}</button>
              <button type="button" className="ttrpg-btn-ghost" onClick={() => setEditing(null)}>{t('ttrpg.cancel')}</button>
            </div>
          </div>
        ) : selected ? (
          <div className="ttrpg-card">
            <div className="ttrpg-toolbar">
              <h4>{selected.title}</h4>
              {isGameMaster && (
                <>
                  <button type="button" className="ttrpg-btn-accent" onClick={startEdit}>{t('ttrpg.edit')}</button>
                  <button type="button" className="ttrpg-btn-danger" onClick={remove}>{t('ttrpg.delete')}</button>
                  <label className="ttrpg-btn">
                    {t('ttrpg.uploadCover')}
                    <input
                      type="file"
                      accept="image/*"
                      hidden
                      onChange={(e) => {
                        const f = e.target.files?.[0]
                        if (f) uploadCover(f)
                        e.target.value = ''
                      }}
                    />
                  </label>
                </>
              )}
            </div>
            <p className="ttrpg-muted">
              {selected.type} · {selected.visibility}
              {selected.cover?.url && (
                <> · <a href={selected.cover.url} target="_blank" rel="noreferrer">{t('ttrpg.cover')}</a></>
              )}
            </p>
            <HtmlView html={selected.content_html} />
          </div>
        ) : (
          <p className="ttrpg-muted ttrpg-muted-center">{t('ttrpg.selectItem')}</p>
        )}
      </div>
    </div>
  )
}

function MapsPane({ apiBase, isGameMaster, campaignId }) {
  const [maps, setMaps] = useState([])
  const [map, setMap] = useState(null)
  const [pins, setPins] = useState([])
  const [error, setError] = useState(null)
  const [mapForm, setMapForm] = useState(null)
  const [pinForm, setPinForm] = useState(null)
  const [assetId, setAssetId] = useState('')
  const [listLoading, setListLoading] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)

  const loadMaps = useCallback(async () => {
    if (!campaignId) return
    setListLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/campaigns/${campaignId}/maps` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setMaps(unwrapList(res))
    } finally {
      setListLoading(false)
    }
  }, [apiBase, campaignId])

  useEffect(() => { loadMaps() }, [loadMaps])

  const openMap = async (id) => {
    setDetailLoading(true)
    setPinForm(null)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/campaigns/${campaignId}/maps/${id}` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      const item = unwrapItem(res)
      setMap(item)
      const pinsRes = await ttrpgProxy(apiBase, {
        method: 'GET',
        path: `gm/campaigns/${campaignId}/maps/${id}/pins`,
      })
      setPins(pinsRes.ok ? unwrapList(pinsRes) : (item?.pins || []))
    } finally {
      setDetailLoading(false)
    }
  }

  const createMap = async () => {
    if (!mapForm) return
    let imageAssetId = Number(assetId)
    if (!imageAssetId && mapForm.file) {
      const up = await ttrpgUploadAsset(apiBase, campaignId, mapForm.file)
      if (!up.success) {
        setError(proxyErrorMessage(up, 'ttrpg.saveFailed'))
        return
      }
      imageAssetId = (up.data?.data || up.data)?.id
    }
    if (!imageAssetId) {
      setError(t('ttrpg.needImage'))
      return
    }
    const res = await ttrpgProxy(apiBase, {
      method: 'POST',
      path: `gm/campaigns/${campaignId}/maps`,
      json: {
        image_asset_id: imageAssetId,
        title: mapForm.title,
        description: mapForm.description || null,
        visibility: mapForm.visibility || 'players',
      },
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setMapForm(null)
    setAssetId('')
    await loadMaps()
    const item = unwrapItem(res)
    if (item?.id) await openMap(item.id)
  }

  const savePin = async () => {
    if (!map || !pinForm) return
    const json = {
      title: pinForm.title,
      x: Number(pinForm.x),
      y: Number(pinForm.y),
      content_html: pinForm.content_html || '',
      color: pinForm.color || '#cc3333',
      icon: pinForm.icon || 'pin',
      visibility: pinForm.visibility || 'players',
      link_content_id: pinForm.link_content_id ? Number(pinForm.link_content_id) : null,
    }
    const res = pinForm.id
      ? await ttrpgProxy(apiBase, {
          method: 'PUT',
          path: `gm/campaigns/${campaignId}/maps/${map.id}/pins/${pinForm.id}`,
          json,
        })
      : await ttrpgProxy(apiBase, {
          method: 'POST',
          path: `gm/campaigns/${campaignId}/maps/${map.id}/pins`,
          json,
        })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setPinForm(null)
    await openMap(map.id)
  }

  const deletePin = async (id) => {
    if (!window.confirm(t('ttrpg.confirmDelete'))) return
    const res = await ttrpgProxy(apiBase, {
      method: 'DELETE',
      path: `gm/campaigns/${campaignId}/maps/${map.id}/pins/${id}`,
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    await openMap(map.id)
  }

  const deleteMap = async () => {
    if (!map || !window.confirm(t('ttrpg.confirmDelete'))) return
    const res = await ttrpgProxy(apiBase, {
      method: 'DELETE',
      path: `gm/campaigns/${campaignId}/maps/${map.id}`,
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setMap(null)
    setPins([])
    await loadMaps()
  }

  if (!campaignId) return <NeedCampaign />

  return (
    <div className="ttrpg-pane ttrpg-split">
      <div className="ttrpg-list-col">
        <div className="ttrpg-toolbar">
          {isGameMaster && (
            <button
              type="button"
              className="ttrpg-btn"
              onClick={() => setMapForm({ title: '', description: '', visibility: 'players', file: null })}
            >
              {t('ttrpg.add')}
            </button>
          )}
        </div>
        <ErrorBanner error={error} onDismiss={() => setError(null)} />
        {listLoading ? (
          <LoadingSpinner />
        ) : (
          <ul className="ttrpg-list">
            {maps.map((m) => (
              <li key={m.id}>
                <button type="button" className={map?.id === m.id ? 'active' : ''} onClick={() => openMap(m.id)}>
                  {m.title}
                </button>
              </li>
            ))}
            {maps.length === 0 && <li className="ttrpg-muted">{t('ttrpg.empty')}</li>}
          </ul>
        )}
      </div>
      <div className="ttrpg-detail-col">
        {detailLoading ? (
          <LoadingSpinner />
        ) : (
          <>
            {mapForm && isGameMaster && (
              <div className="ttrpg-card">
                <label className="ttrpg-field">
                  <span>{t('ttrpg.title')}</span>
                  <input value={mapForm.title} onChange={(e) => setMapForm({ ...mapForm, title: e.target.value })} />
                </label>
                <label className="ttrpg-field">
                  <span>{t('ttrpg.description')}</span>
                  <textarea value={mapForm.description} onChange={(e) => setMapForm({ ...mapForm, description: e.target.value })} rows={3} />
                </label>
                <label className="ttrpg-field">
                  <span>{t('ttrpg.mapImage')}</span>
                  <input
                    type="file"
                    accept="image/*"
                    onChange={(e) => setMapForm({ ...mapForm, file: e.target.files?.[0] || null })}
                  />
                </label>
                <label className="ttrpg-field">
                  <span>{t('ttrpg.orAssetId')}</span>
                  <input value={assetId} onChange={(e) => setAssetId(e.target.value)} placeholder="asset id" />
                </label>
                <div className="ttrpg-toolbar">
                  <button type="button" className="ttrpg-btn" onClick={createMap}>{t('ttrpg.create')}</button>
                  <button type="button" className="ttrpg-btn-ghost" onClick={() => setMapForm(null)}>{t('ttrpg.cancel')}</button>
                </div>
              </div>
            )}
            {map && (
              <div className="ttrpg-card">
                <div className="ttrpg-toolbar">
                  <h4>{map.title}</h4>
                  {isGameMaster && (
                    <>
                      <button
                        type="button"
                        className="ttrpg-btn"
                        onClick={() => setPinForm({ title: '', x: 0.5, y: 0.5, content_html: '', color: '#cc3333', visibility: 'players' })}
                      >
                        {t('ttrpg.addPin')}
                      </button>
                      <button type="button" className="ttrpg-btn-danger" onClick={deleteMap}>{t('ttrpg.delete')}</button>
                    </>
                  )}
                </div>
                {map.image?.url && (
                  <img className="ttrpg-map-img" src={map.image.url} alt={map.title} />
                )}
                <p className="ttrpg-muted">{map.description}</p>
                <h5>{t('ttrpg.pins')}</h5>
                <ul className="ttrpg-list">
                  {pins.map((p) => (
                    <li key={p.id}>
                      <button
                        type="button"
                        onClick={() => isGameMaster && setPinForm({ ...p })}
                      >
                        {p.title} ({Number(p.x).toFixed(2)}, {Number(p.y).toFixed(2)})
                      </button>
                      {isGameMaster && (
                        <button type="button" className="ttrpg-btn-ghost" onClick={() => deletePin(p.id)}>×</button>
                      )}
                    </li>
                  ))}
                </ul>
                {pinForm && isGameMaster && (
                  <div className="ttrpg-card nested">
                    <label className="ttrpg-field">
                      <span>{t('ttrpg.title')}</span>
                      <input value={pinForm.title} onChange={(e) => setPinForm({ ...pinForm, title: e.target.value })} />
                    </label>
                    <div className="ttrpg-row">
                      <label className="ttrpg-field">
                        <span>X (0–1)</span>
                        <input type="number" step="0.01" min="0" max="1" value={pinForm.x} onChange={(e) => setPinForm({ ...pinForm, x: e.target.value })} />
                      </label>
                      <label className="ttrpg-field">
                        <span>Y (0–1)</span>
                        <input type="number" step="0.01" min="0" max="1" value={pinForm.y} onChange={(e) => setPinForm({ ...pinForm, y: e.target.value })} />
                      </label>
                    </div>
                    <label className="ttrpg-field">
                      <span>{t('ttrpg.bodyHtml')}</span>
                      <textarea rows={4} value={pinForm.content_html || ''} onChange={(e) => setPinForm({ ...pinForm, content_html: e.target.value })} />
                    </label>
                    <div className="ttrpg-toolbar">
                      <button type="button" className="ttrpg-btn" onClick={savePin}>{t('ttrpg.save')}</button>
                      <button type="button" className="ttrpg-btn-ghost" onClick={() => setPinForm(null)}>{t('ttrpg.cancel')}</button>
                    </div>
                  </div>
                )}
              </div>
            )}
            {!map && !mapForm && (
              <p className="ttrpg-muted ttrpg-muted-center">{t('ttrpg.selectItem')}</p>
            )}
          </>
        )}
      </div>
    </div>
  )
}

function TrackersPane({ apiBase, isGameMaster, campaignId }) {
  const [trackers, setTrackers] = useState([])
  const [tracker, setTracker] = useState(null)
  const [entries, setEntries] = useState([])
  const [error, setError] = useState(null)
  const [form, setForm] = useState(null)
  const [entryForm, setEntryForm] = useState(null)
  const [listLoading, setListLoading] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)

  const load = useCallback(async () => {
    if (!campaignId) return
    setListLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/campaigns/${campaignId}/trackers` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setTrackers(unwrapList(res))
    } finally {
      setListLoading(false)
    }
  }, [apiBase, campaignId])

  useEffect(() => { load() }, [load])

  const open = async (id) => {
    setDetailLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/campaigns/${campaignId}/trackers/${id}` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      const item = unwrapItem(res)
      setTracker(item)
      const er = await ttrpgProxy(apiBase, {
        method: 'GET',
        path: `gm/campaigns/${campaignId}/trackers/${id}/entries`,
      })
      setEntries(er.ok ? unwrapList(er) : (item?.entries || []))
    } finally {
      setDetailLoading(false)
    }
  }

  const saveTracker = async () => {
    if (!form) return
    const json = {
      title: form.title,
      mode: form.mode || 'list',
      current_label: form.current_label || null,
      visibility: form.visibility || 'players',
    }
    const res = form.id
      ? await ttrpgProxy(apiBase, { method: 'PUT', path: `gm/campaigns/${campaignId}/trackers/${form.id}`, json })
      : await ttrpgProxy(apiBase, { method: 'POST', path: `gm/campaigns/${campaignId}/trackers`, json })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setForm(null)
    await load()
    const item = unwrapItem(res)
    if (item?.id) await open(item.id)
  }

  const saveEntry = async () => {
    if (!tracker || !entryForm) return
    const json = {
      at_label: entryForm.at_label || null,
      content_html: entryForm.content_html,
    }
    const res = entryForm.id
      ? await ttrpgProxy(apiBase, {
          method: 'PUT',
          path: `gm/campaigns/${campaignId}/trackers/${tracker.id}/entries/${entryForm.id}`,
          json,
        })
      : await ttrpgProxy(apiBase, {
          method: 'POST',
          path: `gm/campaigns/${campaignId}/trackers/${tracker.id}/entries`,
          json,
        })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setEntryForm(null)
    await open(tracker.id)
  }

  const removeTracker = async () => {
    if (!tracker || !window.confirm(t('ttrpg.confirmDelete'))) return
    const res = await ttrpgProxy(apiBase, {
      method: 'DELETE',
      path: `gm/campaigns/${campaignId}/trackers/${tracker.id}`,
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setTracker(null)
    setEntries([])
    await load()
  }

  if (!campaignId) return <NeedCampaign />

  return (
    <div className="ttrpg-pane ttrpg-split">
      <div className="ttrpg-list-col">
        <div className="ttrpg-toolbar">
          {isGameMaster && (
            <button
              type="button"
              className="ttrpg-btn"
              onClick={() => setForm({ title: '', mode: 'list', current_label: '', visibility: 'players' })}
            >
              {t('ttrpg.add')}
            </button>
          )}
        </div>
        <ErrorBanner error={error} onDismiss={() => setError(null)} />
        {listLoading ? (
          <LoadingSpinner />
        ) : (
          <ul className="ttrpg-list">
            {trackers.map((tr) => (
              <li key={tr.id}>
                <button type="button" className={tracker?.id === tr.id ? 'active' : ''} onClick={() => open(tr.id)}>
                  {tr.title}
                  {tr.current_label ? ` — ${tr.current_label}` : ''}
                </button>
              </li>
            ))}
            {trackers.length === 0 && <li className="ttrpg-muted">{t('ttrpg.empty')}</li>}
          </ul>
        )}
      </div>
      <div className="ttrpg-detail-col">
        {detailLoading ? (
          <LoadingSpinner />
        ) : (
          <>
            {form && isGameMaster && (
              <div className="ttrpg-card">
                <label className="ttrpg-field">
                  <span>{t('ttrpg.title')}</span>
                  <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
                </label>
                <label className="ttrpg-field">
                  <span>{t('ttrpg.currentLabel')}</span>
                  <input value={form.current_label || ''} onChange={(e) => setForm({ ...form, current_label: e.target.value })} />
                </label>
                <label className="ttrpg-field">
                  <span>{t('ttrpg.mode')}</span>
                  <select value={form.mode || 'list'} onChange={(e) => setForm({ ...form, mode: e.target.value })}>
                    <option value="list">list</option>
                    <option value="calendar">calendar</option>
                  </select>
                </label>
                <div className="ttrpg-toolbar">
                  <button type="button" className="ttrpg-btn" onClick={saveTracker}>{t('ttrpg.save')}</button>
                  <button type="button" className="ttrpg-btn-ghost" onClick={() => setForm(null)}>{t('ttrpg.cancel')}</button>
                </div>
              </div>
            )}
            {tracker && (
              <div className="ttrpg-card">
                <div className="ttrpg-toolbar">
                  <h4>{tracker.title}</h4>
                  {isGameMaster && (
                    <>
                      <button type="button" className="ttrpg-btn-accent" onClick={() => setForm({ ...tracker })}>{t('ttrpg.edit')}</button>
                      <button type="button" className="ttrpg-btn" onClick={() => setEntryForm({ at_label: '', content_html: '' })}>{t('ttrpg.addEntry')}</button>
                      <button type="button" className="ttrpg-btn-danger" onClick={removeTracker}>{t('ttrpg.delete')}</button>
                    </>
                  )}
                </div>
                <p className="ttrpg-muted">{t('ttrpg.currentLabel')}: {tracker.current_label || '—'}</p>
                <ul className="ttrpg-list">
                  {entries.map((en) => (
                    <li key={en.id}>
                      <div>
                        <strong>{en.at_label || '—'}</strong>
                        <HtmlView html={en.content_html} />
                      </div>
                      {isGameMaster && (
                        <button type="button" className="ttrpg-btn-ghost" onClick={() => setEntryForm({ ...en })}>{t('ttrpg.edit')}</button>
                      )}
                    </li>
                  ))}
                </ul>
                {entryForm && isGameMaster && (
                  <div className="ttrpg-card nested">
                    <label className="ttrpg-field">
                      <span>{t('ttrpg.atLabel')}</span>
                      <input value={entryForm.at_label || ''} onChange={(e) => setEntryForm({ ...entryForm, at_label: e.target.value })} />
                    </label>
                    <label className="ttrpg-field">
                      <span>{t('ttrpg.bodyHtml')}</span>
                      <textarea rows={5} value={entryForm.content_html || ''} onChange={(e) => setEntryForm({ ...entryForm, content_html: e.target.value })} />
                    </label>
                    <div className="ttrpg-toolbar">
                      <button type="button" className="ttrpg-btn" onClick={saveEntry}>{t('ttrpg.save')}</button>
                      <button type="button" className="ttrpg-btn-ghost" onClick={() => setEntryForm(null)}>{t('ttrpg.cancel')}</button>
                    </div>
                  </div>
                )}
              </div>
            )}
            {!tracker && !form && (
              <p className="ttrpg-muted ttrpg-muted-center">{t('ttrpg.selectItem')}</p>
            )}
          </>
        )}
      </div>
    </div>
  )
}

function SheetsPane({ apiBase, isGameMaster, campaignId }) {
  const [sheets, setSheets] = useState([])
  const [sheet, setSheet] = useState(null)
  const [error, setError] = useState(null)
  const [form, setForm] = useState(null)
  const [pin, setPin] = useState('')
  const [listLoading, setListLoading] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)

  const load = useCallback(async () => {
    if (!campaignId) return
    setListLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/campaigns/${campaignId}/sheets` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setSheets(unwrapList(res))
    } finally {
      setListLoading(false)
    }
  }, [apiBase, campaignId])

  useEffect(() => { load() }, [load])

  const open = async (id) => {
    if (!isGameMaster) {
      const meta = sheets.find((s) => s.id === id)
      setSheet(meta || { id, name: t('ttrpg.sheetLocked') })
      return
    }
    setDetailLoading(true)
    setForm(null)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/campaigns/${campaignId}/sheets/${id}` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setSheet(unwrapItem(res))
    } finally {
      setDetailLoading(false)
    }
  }

  const save = async () => {
    if (!form || !isGameMaster) return
    if (!form.id) {
      const res = await ttrpgProxy(apiBase, {
        method: 'POST',
        path: `gm/campaigns/${campaignId}/sheets`,
        json: {
          owner_user_id: Number(form.owner_user_id),
          name: form.name,
          content_html: form.content_html || '',
          pin: form.pin,
        },
      })
      if (!res.ok) {
        setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
        return
      }
      setForm(null)
      await load()
      const item = unwrapItem(res)
      if (item?.id) await open(item.id)
      return
    }
    const res = await ttrpgProxy(apiBase, {
      method: 'PUT',
      path: `gm/campaigns/${campaignId}/sheets/${form.id}`,
      json: {
        name: form.name,
        content_html: form.content_html || '',
      },
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setForm(null)
    await open(form.id)
  }

  const resetPin = async () => {
    if (!sheet || !pin) return
    const res = await ttrpgProxy(apiBase, {
      method: 'PUT',
      path: `gm/campaigns/${campaignId}/sheets/${sheet.id}/pin`,
      json: { pin },
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setPin('')
  }

  const remove = async () => {
    if (!sheet || !window.confirm(t('ttrpg.confirmDelete'))) return
    const res = await ttrpgProxy(apiBase, {
      method: 'DELETE',
      path: `gm/campaigns/${campaignId}/sheets/${sheet.id}`,
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setSheet(null)
    await load()
  }

  if (!campaignId) return <NeedCampaign />

  return (
    <div className="ttrpg-pane ttrpg-split">
      <div className="ttrpg-list-col">
        <div className="ttrpg-toolbar">
          {isGameMaster && (
            <button
              type="button"
              className="ttrpg-btn"
              onClick={() => setForm({ name: '', owner_user_id: '', content_html: '', pin: '1234' })}
            >
              {t('ttrpg.add')}
            </button>
          )}
        </div>
        <ErrorBanner error={error} onDismiss={() => setError(null)} />
        {listLoading ? (
          <LoadingSpinner />
        ) : (
          <ul className="ttrpg-list">
            {sheets.map((s) => (
              <li key={s.id}>
                <button type="button" className={sheet?.id === s.id ? 'active' : ''} onClick={() => open(s.id)}>
                  {s.name}
                </button>
              </li>
            ))}
            {sheets.length === 0 && <li className="ttrpg-muted">{t('ttrpg.empty')}</li>}
          </ul>
        )}
      </div>
      <div className="ttrpg-detail-col">
        {detailLoading ? (
          <LoadingSpinner />
        ) : (
          <>
            {form && isGameMaster && (
              <div className="ttrpg-card">
                <label className="ttrpg-field">
                  <span>{t('ttrpg.name')}</span>
                  <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                </label>
                {!form.id && (
                  <>
                    <label className="ttrpg-field">
                      <span>{t('ttrpg.ownerUserId')}</span>
                      <input value={form.owner_user_id} onChange={(e) => setForm({ ...form, owner_user_id: e.target.value })} />
                    </label>
                    <label className="ttrpg-field">
                      <span>{t('ttrpg.pin')}</span>
                      <input value={form.pin} onChange={(e) => setForm({ ...form, pin: e.target.value })} />
                    </label>
                  </>
                )}
                <label className="ttrpg-field">
                  <span>{t('ttrpg.bodyHtml')}</span>
                  <textarea rows={10} value={form.content_html || ''} onChange={(e) => setForm({ ...form, content_html: e.target.value })} />
                </label>
                <div className="ttrpg-toolbar">
                  <button type="button" className="ttrpg-btn" onClick={save}>{t('ttrpg.save')}</button>
                  <button type="button" className="ttrpg-btn-ghost" onClick={() => setForm(null)}>{t('ttrpg.cancel')}</button>
                </div>
              </div>
            )}
            {sheet && !form && (
              <div className="ttrpg-card">
                <div className="ttrpg-toolbar">
                  <h4>{sheet.name}</h4>
                  {isGameMaster && (
                    <>
                      <button type="button" className="ttrpg-btn-accent" onClick={() => setForm({ ...sheet })}>{t('ttrpg.edit')}</button>
                      <button type="button" className="ttrpg-btn-danger" onClick={remove}>{t('ttrpg.delete')}</button>
                    </>
                  )}
                </div>
                {isGameMaster ? (
                  <>
                    <HtmlView html={sheet.content_html} />
                    <div className="ttrpg-toolbar">
                      <input
                        type="text"
                        placeholder={t('ttrpg.newPin')}
                        value={pin}
                        onChange={(e) => setPin(e.target.value)}
                      />
                      <button type="button" className="ttrpg-btn-accent" onClick={resetPin}>{t('ttrpg.resetPin')}</button>
                    </div>
                  </>
                ) : (
                  <p className="ttrpg-muted">{t('ttrpg.sheetPlayerHint')}</p>
                )}
              </div>
            )}
            {!sheet && !form && (
              <p className="ttrpg-muted ttrpg-muted-center">{t('ttrpg.selectItem')}</p>
            )}
          </>
        )}
      </div>
    </div>
  )
}

function HandbookPdfModal({ preview, onClose }) {
  useEffect(() => {
    if (!preview?.url) return undefined
    const onKey = (e) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [preview?.url, onClose])

  if (!preview?.url) return null

  const label = preview.title
    ? `${preview.title}${preview.page ? ` — ${t('ttrpg.page')} ${preview.page}` : ''}`
    : t('ttrpg.openPreview')

  return (
    <div
      className="ttrpg-pdf-modal-overlay"
      role="dialog"
      aria-modal="true"
      onClick={onClose}
    >
      <div className="ttrpg-pdf-modal" onClick={(e) => e.stopPropagation()}>
        <div className="ttrpg-pdf-modal-header">
          <h3>{label}</h3>
          <div className="ttrpg-toolbar">
            <a
              className="ttrpg-btn-accent"
              href={preview.url}
              target="_blank"
              rel="noreferrer"
            >
              {t('ttrpg.openInTab')}
            </a>
            <button type="button" className="ttrpg-btn-ghost" onClick={onClose}>
              {t('ttrpg.closePreview')}
            </button>
          </div>
        </div>
        <iframe
          className="ttrpg-pdf-frame"
          src={preview.url}
          title={label}
        />
      </div>
    </div>
  )
}

function HandbooksPane({ apiBase, isGameMaster, campaignId }) {
  const [books, setBooks] = useState([])
  const [book, setBook] = useState(null)
  const [error, setError] = useState(null)
  const [query, setQuery] = useState('')
  const [results, setResults] = useState([])
  const [searched, setSearched] = useState(false)
  const [uploadTitle, setUploadTitle] = useState('')
  const [preview, setPreview] = useState(null)
  const [previewBusy, setPreviewBusy] = useState(false)
  const [listLoading, setListLoading] = useState(true)
  const [detailLoading, setDetailLoading] = useState(false)
  const [searchLoading, setSearchLoading] = useState(false)
  const [uploading, setUploading] = useState(false)

  const load = useCallback(async () => {
    setListLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: 'gm/handbooks' })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setBooks(unwrapList(res))
    } finally {
      setListLoading(false)
    }
  }, [apiBase])

  useEffect(() => { load() }, [load])

  const bookTitle = useCallback((id) => {
    const found = books.find((b) => b.id === id)
    return found?.title || `#${id}`
  }, [books])

  const openMeta = async (id) => {
    setDetailLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, { method: 'GET', path: `gm/handbooks/${id}` })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        return
      }
      setBook(unwrapItem(res))
    } finally {
      setDetailLoading(false)
    }
  }

  const search = async () => {
    const ids = books.filter((b) => b.status === 'ready').map((b) => b.id)
    if (!query.trim() || ids.length === 0) return
    setSearched(true)
    setSearchLoading(true)
    try {
      const res = await ttrpgProxy(apiBase, {
        method: 'POST',
        path: 'gm/handbooks/search',
        json: { query: query.trim(), handbook_ids: ids, limit: 30 },
      })
      if (!res.ok) {
        setError(proxyErrorMessage(res))
        setResults([])
        return
      }
      const body = res.data
      setResults(Array.isArray(body?.data) ? body.data : (Array.isArray(body) ? body : []))
    } finally {
      setSearchLoading(false)
    }
  }

  const openPreview = async (handbookId, pageNumber) => {
    if (!handbookId) return
    setPreviewBusy(true)
    setError(null)
    try {
      const queryParams = { expires_in: 600 }
      if (pageNumber != null && pageNumber !== '') {
        queryParams.page = Number(pageNumber)
      }
      const res = await ttrpgProxy(apiBase, {
        method: 'GET',
        path: `gm/handbooks/${handbookId}/preview-link`,
        query: queryParams,
      })
      if (!res.ok) {
        setError(proxyErrorMessage(res, 'ttrpg.previewFailed'))
        return
      }
      const item = unwrapItem(res)
      const url = item?.url
      if (!url) {
        setError(t('ttrpg.previewFailed'))
        return
      }
      setPreview({
        url,
        title: bookTitle(handbookId),
        page: item.page ?? pageNumber ?? null,
      })
    } finally {
      setPreviewBusy(false)
    }
  }

  const closePreview = useCallback(() => {
    setPreview(null)
  }, [])

  const upload = async (file) => {
    if (!file || !uploadTitle.trim()) {
      setError(t('ttrpg.needTitle'))
      return
    }
    setUploading(true)
    setError(null)
    try {
      const up = await ttrpgUploadHandbook(apiBase, {
        file,
        title: uploadTitle.trim(),
        language: 'pl',
        campaignId: campaignId || null,
      })
      if (!up.success) {
        setError(proxyErrorMessage(up, 'ttrpg.saveFailed'))
        return
      }
      setUploadTitle('')
      const created = unwrapItem({ data: up.data })
      await load()
      if (created?.id) {
        await openMeta(created.id)
      }
    } finally {
      setUploading(false)
    }
  }

  const rename = async () => {
    if (!book || !isGameMaster) return
    const title = window.prompt(t('ttrpg.title'), book.title)
    if (!title) return
    const res = await ttrpgProxy(apiBase, {
      method: 'PUT',
      path: `gm/handbooks/${book.id}`,
      json: { title },
    })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    await load()
    await openMeta(book.id)
  }

  const remove = async () => {
    if (!book || !window.confirm(t('ttrpg.confirmDelete'))) return
    const res = await ttrpgProxy(apiBase, { method: 'DELETE', path: `gm/handbooks/${book.id}` })
    if (!res.ok) {
      setError(proxyErrorMessage(res, 'ttrpg.saveFailed'))
      return
    }
    setBook(null)
    await load()
  }

  return (
    <div className="ttrpg-pane ttrpg-split">
      <div className="ttrpg-list-col">
        <div className="ttrpg-toolbar">
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t('ttrpg.searchPlaceholder')}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault()
                search()
              }
            }}
          />
          <button
            type="button"
            className="ttrpg-btn-accent"
            onClick={search}
            disabled={previewBusy || searchLoading || listLoading}
          >
            {searchLoading ? t('ttrpg.searching') : t('ttrpg.search')}
          </button>
        </div>
        {isGameMaster && (
          <div className="ttrpg-card nested">
            <input
              value={uploadTitle}
              onChange={(e) => setUploadTitle(e.target.value)}
              placeholder={t('ttrpg.title')}
              disabled={uploading}
            />
            {uploading ? (
              <LoadingSpinner label={t('ttrpg.uploading')} inline />
            ) : (
              <label className="ttrpg-btn">
                {t('ttrpg.uploadPdf')}
                <input
                  type="file"
                  accept="application/pdf"
                  hidden
                  onChange={(e) => {
                    const f = e.target.files?.[0]
                    if (f) upload(f)
                    e.target.value = ''
                  }}
                />
              </label>
            )}
          </div>
        )}
        <ErrorBanner error={error} onDismiss={() => setError(null)} />
        {listLoading ? (
          <LoadingSpinner />
        ) : (
          <ul className="ttrpg-list">
            {books.map((b) => (
              <li key={b.id}>
                <button type="button" className={book?.id === b.id ? 'active' : ''} onClick={() => openMeta(b.id)}>
                  <span className="ttrpg-badge">{b.status}</span>
                  {b.title}
                </button>
              </li>
            ))}
            {books.length === 0 && <li className="ttrpg-muted">{t('ttrpg.empty')}</li>}
          </ul>
        )}
      </div>
      <div className="ttrpg-detail-col">
        {detailLoading ? (
          <LoadingSpinner />
        ) : (
          <>
            {book && (
              <div className="ttrpg-card">
                <div className="ttrpg-toolbar">
                  <h4>{book.title}</h4>
                  {isGameMaster && (
                    <>
                      <button type="button" className="ttrpg-btn-accent" onClick={rename}>{t('ttrpg.edit')}</button>
                      <button type="button" className="ttrpg-btn-danger" onClick={remove}>{t('ttrpg.delete')}</button>
                    </>
                  )}
                </div>
                <p className="ttrpg-muted">
                  {book.original_name} · {book.page_count} {t('ttrpg.pages')} · {book.status}
                </p>
              </div>
            )}

            {searchLoading && <LoadingSpinner label={t('ttrpg.searching')} />}

            {previewBusy && (
              <LoadingSpinner label={t('ttrpg.loadingData')} inline />
            )}

            {!searched && !searchLoading && (
              <p className="ttrpg-muted ttrpg-muted-center">{t('ttrpg.searchEmpty')}</p>
            )}

            {searched && !searchLoading && (
              <div className="ttrpg-card">
                <h5>{t('ttrpg.searchResults')}</h5>
                {results.length === 0 ? (
                  <p className="ttrpg-muted">{t('ttrpg.noSearchHits')}</p>
                ) : (
                  <ul className="ttrpg-list">
                    {results.map((r, i) => {
                      const excerpt = r.excerpt || r.snippet || r.content || ''
                      const title = r.handbook_title || bookTitle(r.handbook_id)
                      return (
                        <li key={`${r.handbook_id}-${r.page_number}-${i}`}>
                          <button
                            type="button"
                            className="ttrpg-search-hit"
                            disabled={previewBusy}
                            onClick={() => openPreview(r.handbook_id, r.page_number)}
                          >
                            <span className="ttrpg-search-hit-meta">
                              <strong>{title}</strong>
                              {' — '}
                              {t('ttrpg.page')} {r.page_number ?? '?'}
                            </span>
                            {excerpt ? (
                              <span
                                className="ttrpg-search-hit-excerpt"
                                dangerouslySetInnerHTML={{ __html: excerpt }}
                              />
                            ) : (
                              <span className="ttrpg-muted">{t('ttrpg.emptyBody')}</span>
                            )}
                          </button>
                        </li>
                      )
                    })}
                  </ul>
                )}
              </div>
            )}
          </>
        )}
      </div>
      <HandbookPdfModal preview={preview} onClose={closePreview} />
    </div>
  )
}

function TtrpgManagerPanel({
  apiBase,
  isGameMaster = false,
  ttrpgManager = { configured: false, baseUrl: null, campaignId: null },
  onStatusChange,
}) {
  const configured = !!ttrpgManager?.configured
  const [subTab, setSubTab] = useState('contents')
  const [localCampaignId, setLocalCampaignId] = useState(ttrpgManager?.campaignId ?? null)
  const [disconnecting, setDisconnecting] = useState(false)

  useEffect(() => {
    setLocalCampaignId(ttrpgManager?.campaignId ?? null)
  }, [ttrpgManager?.campaignId])

  const handleStatus = (status, version) => {
    if (onStatusChange) onStatusChange(status, version)
  }

  const disconnect = async () => {
    if (!window.confirm(t('ttrpg.confirmDisconnect'))) return
    // Stop hanging Manager proxy calls so PHP/local clear-key can run immediately.
    abortTtrpgProxyRequests()
    setDisconnecting(true)
    try {
      const data = await ttrpgClearKey(apiBase)
      if (data.success) handleStatus(data.ttrpgManager, data.version)
    } finally {
      setDisconnecting(false)
    }
  }

  const disconnectButton = isGameMaster ? (
    <button
      type="button"
      className="ttrpg-btn-danger"
      onClick={disconnect}
      disabled={disconnecting}
    >
      {disconnecting ? t('ttrpg.loadingData') : t('ttrpg.disconnect')}
    </button>
  ) : null

  if (!configured) {
    if (!isGameMaster) {
      return (
        <div className="ttrpg-panel">
          <p className="ttrpg-muted ttrpg-muted-center">{t('ttrpg.waitingGm')}</p>
        </div>
      )
    }
    return (
      <div className="ttrpg-panel">
        <ConnectForm apiBase={apiBase} onConnected={handleStatus} />
      </div>
    )
  }

  const campaignId = localCampaignId

  return (
    <div className="ttrpg-panel">
      <div className="ttrpg-header">
        <div className="ttrpg-subtabs">
          {SUBTABS.map((id) => (
            <button
              key={id}
              type="button"
              className={subTab === id ? 'active' : ''}
              onClick={() => setSubTab(id)}
            >
              {t(`ttrpg.tabs.${id}`)}
            </button>
          ))}
        </div>
        {disconnectButton}
      </div>

      {subTab === 'campaign' && (
        <CampaignPane
          apiBase={apiBase}
          isGameMaster={isGameMaster}
          campaignId={campaignId}
          onCampaignChange={setLocalCampaignId}
          onStatus={handleStatus}
        />
      )}
      {subTab === 'contents' && (
        <ContentsPane apiBase={apiBase} isGameMaster={isGameMaster} campaignId={campaignId} />
      )}
      {subTab === 'maps' && (
        <MapsPane apiBase={apiBase} isGameMaster={isGameMaster} campaignId={campaignId} />
      )}
      {subTab === 'trackers' && (
        <TrackersPane apiBase={apiBase} isGameMaster={isGameMaster} campaignId={campaignId} />
      )}
      {subTab === 'sheets' && (
        <SheetsPane apiBase={apiBase} isGameMaster={isGameMaster} campaignId={campaignId} />
      )}
      {subTab === 'handbooks' && (
        <HandbooksPane apiBase={apiBase} isGameMaster={isGameMaster} campaignId={campaignId} />
      )}
    </div>
  )
}

export default TtrpgManagerPanel
