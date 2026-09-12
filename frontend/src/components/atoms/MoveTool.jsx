import { t } from '../../lang'

function MoveTool({ isActive, hasItems, onToggle }) {
  if (!hasItems) return null

  return (
    <div
      className={`move-tool ${isActive ? 'active' : ''}`}
      onClick={onToggle}
    >
      <span className="move-tool-icon">✥</span>
      <span className="move-tool-label">{t('sidebar.moveElements')}</span>
      {isActive && <span className="move-tool-active">✓</span>}
    </div>
  )
}

export default MoveTool
