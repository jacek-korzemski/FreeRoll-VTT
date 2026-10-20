import { useRef } from 'react'
import { t } from '../../lang'
import CollapsibleSection from '../atoms/CollapsibleSection'
import { clearActiveAssetDrag, handleAssetDragStart, isCurrentBackground } from '../../utils/sidebarHelpers'
import BackgroundAdjust from '../molecules/BackgroundAdjust'

function BackgroundSection({
  backgroundAssets,
  currentBackground,
  bgZoomStep,
  onBgZoomStepChange,
  onSetBackground,
  onPickBackground,
  onRemoveBackground,
  onNudgeBackground,
  onScaleBackground,
  onResetBackgroundPosition,
  onResetBackgroundScale,
  onResetBackgroundAll,
  onToggleGridHidden,
  onDeselectAsset,
  embedded = false,
  showAdjust = true,
}) {
  const draggedRef = useRef(false)

  const pickBackground = onPickBackground || onSetBackground

  const body = (
    <>
      {currentBackground && (
        <div className="current-background">
          <span className="current-bg-label">{t('sidebar.backgroundActive')}</span>
          <span className="current-bg-name">{currentBackground.name}</span>
          {showAdjust && (
            <button 
              className="remove-bg-btn" 
              onClick={onResetBackgroundAll}
              title={t('sidebar.backgroundResetAll') || 'Reset background position and zoom'}
            >
              ⟲
            </button>
          )}
          <button 
            className="remove-bg-btn" 
            onClick={onRemoveBackground}
            title={t('sidebar.backgroundRemove')}
          >
            ✕
          </button>
        </div>
      )}
      
      <div className="background-list">
        {backgroundAssets.length === 0 && (
          <p className="no-assets">
            {t('sidebar.backgroundsEmpty')}<br/>
            <code>backend/assets/backgrounds/</code>
          </p>
        )}
        {backgroundAssets.map(bg => (
          <div
            key={bg.id}
            className={`background-item ${isCurrentBackground(bg, currentBackground) ? 'active' : ''}`}
            draggable
            onDragStart={(event) => {
              draggedRef.current = true
              handleAssetDragStart(bg, 'background', event)
            }}
            onDragEnd={() => clearActiveAssetDrag()}
            onClick={() => {
              if (draggedRef.current) {
                draggedRef.current = false
                return
              }
              if (!isCurrentBackground(bg, currentBackground)) pickBackground(bg)
            }}
            title={`${bg.filename} (${bg.gridWidth}×${bg.gridHeight})`}
          >
            <span className="bg-icon">🗺️</span>
            <span className="bg-name">{bg.name}</span>
            <span className="bg-size">{bg.gridWidth}×{bg.gridHeight}</span>
            {isCurrentBackground(bg, currentBackground) && <span className="bg-active">✓</span>}
          </div>
        ))}
      </div>

      {showAdjust && currentBackground && (
        <BackgroundAdjust
          currentBackground={currentBackground}
          bgZoomStep={bgZoomStep}
          onBgZoomStepChange={onBgZoomStepChange}
          onNudgeBackground={onNudgeBackground}
          onScaleBackground={onScaleBackground}
          onResetBackgroundPosition={onResetBackgroundPosition}
          onResetBackgroundScale={onResetBackgroundScale}
          onToggleGridHidden={onToggleGridHidden}
        />
      )}
    </>
  )

  if (embedded) {
    return <div className="scene-background-editor">{body}</div>
  }

  return (
    <CollapsibleSection 
      title={t('sidebar.backgrounds')} 
      icon="🖼️" 
      defaultOpen={false} 
      onToggle={onDeselectAsset}
    >
      {body}
    </CollapsibleSection>
  )
}

export default BackgroundSection
