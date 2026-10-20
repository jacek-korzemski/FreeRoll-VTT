import { useState, useCallback } from 'react'
import { t } from '../../lang'
import BackgroundSection from './BackgroundSection'

const SCENE_TYPES = ['tactical', 'combat', 'travel', 'narrative']

function EyeIcon({ open }) {
  return (
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
      <path
        d="M1.5 8S3.8 4.2 8 4.2 14.5 8 14.5 8 12.2 11.8 8 11.8 1.5 8 1.5 8z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
      />
      <circle cx="8" cy="8" r={open ? '2.1' : '1.1'} fill="currentColor" />
    </svg>
  )
}

function SceneManager({
  scenes,
  activeSceneId,
  hideHp,
  onFocusScene,
  onShowToPlayers,
  onCreateScene,
  onDeleteScene,
  onRenameScene,
  onDuplicateScene,
  onUpdateScene,
  backgroundAssets,
  globalBackgroundAssets = [],
  showGlobalBackgrounds = false,
  currentBackground,
  bgZoomStep,
  onBgZoomStepChange,
  onRemoveBackground,
  onNudgeBackground,
  onScaleBackground,
  onResetBackgroundPosition,
  onResetBackgroundScale,
  onResetBackgroundAll,
  onToggleGridHidden,
}) {
  const [editingId, setEditingId] = useState(null)
  const [editingName, setEditingName] = useState('')
  const [isCreating, setIsCreating] = useState(false)
  const [newSceneName, setNewSceneName] = useState('')

  const handleToggleEdit = useCallback((scene) => {
    if (editingId === scene.id) {
      setEditingId(null)
      setEditingName('')
      return
    }
    onFocusScene(scene.id)
    setEditingId(scene.id)
    setEditingName(scene.name)
  }, [editingId, onFocusScene])

  const commitName = useCallback(() => {
    const name = editingName.trim()
    if (editingId && name) {
      onRenameScene(editingId, name)
    }
  }, [editingId, editingName, onRenameScene])

  const handleNameKeyDown = useCallback((e) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      e.currentTarget.blur()
    } else if (e.key === 'Escape') {
      const scene = scenes.find((item) => item.id === editingId)
      setEditingName(scene?.name || '')
    }
  }, [editingId, scenes])

  const handleCreateScene = useCallback(() => {
    if (newSceneName.trim()) {
      onCreateScene(newSceneName.trim())
      setNewSceneName('')
      setIsCreating(false)
    }
  }, [newSceneName, onCreateScene])

  const handleCreateKeyDown = useCallback((e) => {
    if (e.key === 'Enter') {
      handleCreateScene()
    } else if (e.key === 'Escape') {
      setIsCreating(false)
      setNewSceneName('')
    }
  }, [handleCreateScene])

  return (
    <div className="scene-manager">
      <div className="scene-list">
        {scenes.map((scene, index) => {
          const type = SCENE_TYPES.includes(scene.type) ? scene.type : 'tactical'
          const isView = scene.id === activeSceneId
          const isEditing = editingId === scene.id
          const showBackground = type === 'tactical' || type === 'travel' || type === 'narrative' || type === 'combat'
          return (
            <div key={scene.id} className="scene-block">
              <div className={`scene-item ${isView ? 'active' : ''} ${scene.playerVisible ? 'player-visible' : ''}`}>
                <button
                  type="button"
                  className={`scene-visibility ${scene.playerVisible ? 'on' : ''}`}
                  aria-pressed={!!scene.playerVisible}
                  title={scene.playerVisible ? t('scenes.visibleToPlayers') : t('scenes.showToPlayers')}
                  onClick={() => {
                    if (!scene.playerVisible) onShowToPlayers(scene.id)
                  }}
                >
                  <EyeIcon open={!!scene.playerVisible} />
                </button>
                <span
                  className="scene-name"
                  onClick={() => {
                    if (!isView) onFocusScene(scene.id)
                  }}
                  title={isView ? t('scenes.current') : t('scenes.switchTo')}
                >
                  <span className="scene-number">{index + 1}.</span>
                  {scene.name}
                </span>
                <div className="scene-actions">
                  <button
                    type="button"
                    className="scene-action-btn"
                    onClick={() => handleToggleEdit(scene)}
                    title={t('scenes.edit')}
                  >
                    ✏️
                  </button>
                  <button
                    type="button"
                    className="scene-action-btn"
                    onClick={() => onDuplicateScene(scene.id)}
                    title={t('scenes.duplicate')}
                  >
                    📋
                  </button>
                  {scenes.length > 1 && (
                    <button
                      type="button"
                      className="scene-action-btn delete"
                      onClick={() => {
                        if (confirm(t('scenes.deleteConfirm', { name: scene.name }))) {
                          onDeleteScene(scene.id)
                        }
                      }}
                      title={t('scenes.delete')}
                    >
                      🗑️
                    </button>
                  )}
                </div>
              </div>

              {isEditing && (
                <div className="scene-edit">
                  <label className="scene-edit-field">
                    <span>{t('scenes.namePlaceholder')}</span>
                    <input
                      type="text"
                      className="scene-name-input scene-edit-name"
                      value={editingName}
                      onChange={(e) => setEditingName(e.target.value)}
                      onBlur={commitName}
                      onKeyDown={handleNameKeyDown}
                      maxLength={50}
                    />
                  </label>
                  <label className="scene-edit-field">
                    <span>{t('scenes.type')}</span>
                    <select
                      value={type}
                      onChange={(e) => onUpdateScene(scene.id, { type: e.target.value })}
                    >
                      {SCENE_TYPES.map((value) => (
                        <option key={value} value={value}>{t(`scenes.type_${value}`)}</option>
                      ))}
                    </select>
                  </label>
                  {type === 'combat' && isView && (
                    <label className="scene-edit-check">
                      <input
                        type="checkbox"
                        checked={!!hideHp}
                        onChange={(e) => onUpdateScene(scene.id, { hideHp: e.target.checked })}
                      />
                      <span>{t('scenes.hideHp')}</span>
                    </label>
                  )}
                  {showBackground && (
                    <div className="scene-edit-field">
                      <span>{t('scenes.background')}</span>
                      <BackgroundSection
                        embedded
                        showAdjust={type === 'tactical' && isView}
                        backgroundAssets={backgroundAssets}
                        globalBackgroundAssets={globalBackgroundAssets}
                        showGlobalBackgrounds={showGlobalBackgrounds}
                        currentBackground={isView ? currentBackground : null}
                        bgZoomStep={bgZoomStep}
                        onBgZoomStepChange={onBgZoomStepChange}
                        onPickBackground={(bg) => onUpdateScene(scene.id, {
                          background: {
                            src: bg.src,
                            name: bg.name,
                            width: bg.width,
                            height: bg.height,
                            offsetX: 0,
                            offsetY: 0,
                            scale: 1,
                            gridHidden: false,
                          },
                        })}
                        onRemoveBackground={isView ? onRemoveBackground : undefined}
                        onNudgeBackground={onNudgeBackground}
                        onScaleBackground={onScaleBackground}
                        onResetBackgroundPosition={onResetBackgroundPosition}
                        onResetBackgroundScale={onResetBackgroundScale}
                        onResetBackgroundAll={onResetBackgroundAll}
                        onToggleGridHidden={onToggleGridHidden}
                      />
                    </div>
                  )}
                </div>
              )}
            </div>
          )
        })}
      </div>

      {isCreating ? (
        <div className="scene-create-form">
          <input
            type="text"
            className="scene-name-input"
            placeholder={t('scenes.namePlaceholder')}
            value={newSceneName}
            onChange={(e) => setNewSceneName(e.target.value)}
            onKeyDown={handleCreateKeyDown}
            autoFocus
            maxLength={50}
          />
          <button type="button" className="scene-create-confirm" onClick={handleCreateScene}>
            ✓
          </button>
          <button
            type="button"
            className="scene-create-cancel"
            onClick={() => { setIsCreating(false); setNewSceneName('') }}
          >
            ✕
          </button>
        </div>
      ) : (
        <button
          type="button"
          className="scene-add-btn"
          onClick={() => setIsCreating(true)}
        >
          + {t('scenes.add')}
        </button>
      )}
    </div>
  )
}

export default SceneManager
