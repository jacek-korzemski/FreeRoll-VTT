import { t } from '../../lang'
import BackgroundNudgeControls from '../atoms/BackgroundNudgeControls'
import BackgroundZoomControls from '../atoms/BackgroundZoomControls'

function BackgroundAdjust({
  currentBackground,
  bgZoomStep,
  onBgZoomStepChange,
  onNudgeBackground,
  onScaleBackground,
  onResetBackgroundPosition,
  onResetBackgroundScale,
  onToggleGridHidden,
}) {
  const gridHidden = currentBackground.gridHidden ?? false

  return (
    <div className="background-adjust">
      <div className="background-adjust-section">
        <button
          type="button"
          className={`bg-grid-toggle-btn ${gridHidden ? 'active' : ''}`}
          onClick={onToggleGridHidden}
        >
          {gridHidden ? t('sidebar.backgroundShowGrid') : t('sidebar.backgroundHideGrid')}
        </button>
      </div>
      <BackgroundNudgeControls
        currentBackground={currentBackground}
        onNudgeBackground={onNudgeBackground}
        onResetBackgroundPosition={onResetBackgroundPosition}
      />
      <BackgroundZoomControls
        currentBackground={currentBackground}
        bgZoomStep={bgZoomStep}
        onBgZoomStepChange={onBgZoomStepChange}
        onScaleBackground={onScaleBackground}
        onResetBackgroundScale={onResetBackgroundScale}
      />
    </div>
  )
}

export default BackgroundAdjust
