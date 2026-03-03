import { t } from '../../lang'

/**
 * Generic eraser tool used for map elements and tokens.
 * Backwards-compatible props for existing map element usage:
 * - isEraserActive, hasMapElements, onToggleEraser
 * New generic props:
 * - isActive, hasItems, onToggle, labelKey
 */
function EraserTool({
  isEraserActive,
  hasMapElements,
  onToggleEraser,
  isActive,
  hasItems,
  onToggle,
  labelKey = 'sidebar.eraser',
}) {
  const active = typeof isActive === 'boolean' ? isActive : !!isEraserActive
  const hasAny = typeof hasItems === 'boolean' ? hasItems : !!hasMapElements
  const handleToggle = onToggle || onToggleEraser

  if (!hasAny) return null

  return (
    <div 
      className={`eraser-tool ${active ? 'active' : ''}`}
      onClick={handleToggle}
    >
      <span className="eraser-icon">🧹</span>
      <span className="eraser-label">{t(labelKey)}</span>
      {active && <span className="eraser-active">✓</span>}
    </div>
  )
}

export default EraserTool
