import { t } from '../../lang'

const CENTER = { id: 'move', icon: '✥', labelKey: 'sidebar.mapElementMove' }

const RING = [
  { id: 'rotateCw', angle: -54, icon: '↻', labelKey: 'sidebar.mapElementRotateCw' },
  { id: 'grow', angle: 18, icon: '+', labelKey: 'sidebar.mapElementGrow' },
  { id: 'delete', angle: 90, icon: '🗑️', labelKey: 'sidebar.mapElementDelete', danger: true },
  { id: 'shrink', angle: 162, icon: '−', labelKey: 'sidebar.mapElementShrink' },
  { id: 'rotateCcw', angle: 234, icon: '↺', labelKey: 'sidebar.mapElementRotateCcw' },
]

const RADIUS = 58

function MapElementMenu({ onAction }) {
  return (
    <div
      className="map-radial"
      onMouseDown={(e) => e.stopPropagation()}
      onClick={(e) => e.stopPropagation()}
      onTouchStart={(e) => e.stopPropagation()}
    >
      <div className="map-radial-backdrop" />
      <RadialButton action={CENTER} x={0} y={0} delay={0} center onAction={onAction} />
      {RING.map((action, index) => {
        const rad = (action.angle * Math.PI) / 180
        return (
          <RadialButton
            key={action.id}
            action={action}
            x={Math.cos(rad) * RADIUS}
            y={Math.sin(rad) * RADIUS}
            delay={(index + 1) * 40}
            onAction={onAction}
          />
        )
      })}
    </div>
  )
}

function RadialButton({ action, x, y, delay, center = false, onAction }) {
  const label = t(action.labelKey)
  return (
    <button
      type="button"
      className={[
        'map-radial-btn',
        center && 'map-radial-btn--center',
        action.danger && 'map-radial-btn--danger',
      ].filter(Boolean).join(' ')}
      style={{
        '--radial-x': `${x}px`,
        '--radial-y': `${y}px`,
        animationDelay: `${delay}ms`,
      }}
      title={label}
      aria-label={label}
      onMouseDown={(e) => e.stopPropagation()}
      onTouchStart={(e) => e.stopPropagation()}
      onDoubleClick={(e) => e.stopPropagation()}
      onClick={(e) => {
        e.preventDefault()
        e.stopPropagation()
        onAction(action.id)
      }}
    >
      {action.icon}
    </button>
  )
}

export default MapElementMenu
