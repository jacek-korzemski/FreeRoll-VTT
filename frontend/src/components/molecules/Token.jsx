import React, { useCallback, useState, useRef, useEffect } from 'react'
import { t } from '../../lang'

function Token({ token, cellSize, isDragging, dragPosition, onDragStart, basePath, onTokenUpdate, onRemoveToken, isTokenEraserActive = false, onTokenErase }) {
  const [isHovered, setIsHovered] = useState(false)
  const [isEditMode, setIsEditMode] = useState(false)
  const [isDeleteConfirmMode, setIsDeleteConfirmMode] = useState(false)
  const [editSize, setEditSize] = useState(token.size ?? 1)
  const [editUpperLabel, setEditUpperLabel] = useState(token.upperLabel ?? '')
  const [editLowerLabel, setEditLowerLabel] = useState(token.lowerLabel ?? '')
  
  const dragStartPosRef = useRef(null)
  const dragStartTimeRef = useRef(null)
  const longPressTimerRef = useRef(null)
  const isLongPressRef = useRef(false)
  const hasMovedRef = useRef(false)

  useEffect(() => {
    setEditSize(token.size ?? 1)
    setEditUpperLabel(token.upperLabel ?? '')
    setEditLowerLabel(token.lowerLabel ?? '')
    setIsEditMode(false)
    setIsDeleteConfirmMode(false)
  }, [token.id, token.size, token.upperLabel, token.lowerLabel])

  const handleMouseEnter = useCallback(() => {
    if (!isDragging && !isEditMode && !isDeleteConfirmMode) {
      setIsHovered(true)
    }
  }, [isDragging, isEditMode, isDeleteConfirmMode])

  const handleMouseLeave = useCallback(() => {
    setIsHovered(false)
  }, [])

  const handleGearClick = useCallback((e) => {
    e.preventDefault()
    e.stopPropagation()
    setIsEditMode(true)
    setIsDeleteConfirmMode(false)
    setIsHovered(false)
  }, [])

  const handleTrashClick = useCallback((e) => {
    e.preventDefault()
    e.stopPropagation()
    setIsDeleteConfirmMode(true)
    setIsEditMode(false)
    setIsHovered(false)
  }, [])

  const handleSave = useCallback(() => {
    if (onTokenUpdate) {
      onTokenUpdate(token.id, {
        size: editSize,
        upperLabel: editUpperLabel || null,
        lowerLabel: editLowerLabel || null
      })
    }
    setIsEditMode(false)
    setIsDeleteConfirmMode(false)
  }, [token.id, editSize, editUpperLabel, editLowerLabel, onTokenUpdate])

  const handleCancel = useCallback(() => {
    setEditSize(token.size ?? 1)
    setEditUpperLabel(token.upperLabel ?? '')
    setEditLowerLabel(token.lowerLabel ?? '')
    setIsEditMode(false)
    setIsDeleteConfirmMode(false)
  }, [token.size, token.upperLabel, token.lowerLabel])

  const handleDeleteConfirm = useCallback(() => {
    if (onRemoveToken) {
      onRemoveToken(token.id)
    }
    setIsDeleteConfirmMode(false)
    setIsEditMode(false)
  }, [onRemoveToken, token.id])

  const handleDeleteCancel = useCallback(() => {
    setIsDeleteConfirmMode(false)
  }, [])

  const handleIncreaseSize = useCallback((e) => {
    e.preventDefault()
    e.stopPropagation()
    setEditSize(prev => Math.min(prev * 1.2, 3))
  }, [])

  const handleDecreaseSize = useCallback((e) => {
    e.preventDefault()
    e.stopPropagation()
    setEditSize(prev => Math.max(prev * 0.8, 0.2))
  }, [])

  const handleMouseDown = useCallback((e) => {
    if (e.button !== 0) return
    if (isEditMode || isDeleteConfirmMode || isTokenEraserActive) return
    
    if (e.target.closest('.token-gear, .token-trash, .token-edit-controls, .token-size-controls, .token-labels, .token-save-controls, .token-delete-controls, .token-delete-buttons')) {
      return
    }

    e.preventDefault()
    e.stopPropagation()
    
    dragStartPosRef.current = { x: e.clientX, y: e.clientY }
    dragStartTimeRef.current = Date.now()
    hasMovedRef.current = false
    
    const handleMouseMove = (moveEvent) => {
      if (dragStartPosRef.current) {
        const dx = Math.abs(moveEvent.clientX - dragStartPosRef.current.x)
        const dy = Math.abs(moveEvent.clientY - dragStartPosRef.current.y)
        if (dx > 3 || dy > 3) {
          hasMovedRef.current = true
          onDragStart(token, e)
          document.removeEventListener('mousemove', handleMouseMove)
          document.removeEventListener('mouseup', handleMouseUp)
        }
      }
    }
    
    const handleMouseUp = () => {
      document.removeEventListener('mousemove', handleMouseMove)
      document.removeEventListener('mouseup', handleMouseUp)
    }
    
    document.addEventListener('mousemove', handleMouseMove)
    document.addEventListener('mouseup', handleMouseUp)
  }, [token, onDragStart, isEditMode, isDeleteConfirmMode, isTokenEraserActive])

  const handleTouchStart = useCallback((e) => {
    if (isEditMode || isDeleteConfirmMode || isTokenEraserActive) return
    
    if (e.target.closest('.token-gear, .token-trash, .token-edit-controls, .token-size-controls, .token-labels, .token-save-controls, .token-delete-controls, .token-delete-buttons')) {
      return
    }

    e.preventDefault()
    e.stopPropagation()
    
    dragStartPosRef.current = { x: e.touches[0].clientX, y: e.touches[0].clientY }
    dragStartTimeRef.current = Date.now()
    hasMovedRef.current = false
    isLongPressRef.current = false
    
    longPressTimerRef.current = setTimeout(() => {
      if (!hasMovedRef.current) {
        isLongPressRef.current = true
        setIsEditMode(true)
        setIsDeleteConfirmMode(false)
        setIsHovered(false)
      }
    }, 500)
    
    const handleTouchMove = (moveEvent) => {
      if (dragStartPosRef.current && moveEvent.touches[0]) {
        const dx = Math.abs(moveEvent.touches[0].clientX - dragStartPosRef.current.x)
        const dy = Math.abs(moveEvent.touches[0].clientY - dragStartPosRef.current.y)
        if (dx > 10 || dy > 10) {
          hasMovedRef.current = true
          if (longPressTimerRef.current) {
            clearTimeout(longPressTimerRef.current)
            longPressTimerRef.current = null
          }
          if (!isLongPressRef.current) {
            onDragStart(token, e)
          }
        }
      }
    }
    
    const handleTouchEnd = () => {
      if (longPressTimerRef.current) {
        clearTimeout(longPressTimerRef.current)
        longPressTimerRef.current = null
      }
      document.removeEventListener('touchmove', handleTouchMove)
      document.removeEventListener('touchend', handleTouchEnd)
    }
    
    document.addEventListener('touchmove', handleTouchMove)
    document.addEventListener('touchend', handleTouchEnd)
  }, [token, onDragStart, isEditMode, isDeleteConfirmMode])

  const handleTokenClickInEraseMode = useCallback((e) => {
    if (!isTokenEraserActive || !onTokenErase) return
    e.preventDefault()
    e.stopPropagation()
    onTokenErase(token.id)
  }, [isTokenEraserActive, onTokenErase, token.id])

  useEffect(() => {
    return () => {
      if (longPressTimerRef.current) {
        clearTimeout(longPressTimerRef.current)
      }
    }
  }, [])

  const posX = isDragging && dragPosition 
    ? dragPosition.x 
    : token.x * cellSize + cellSize / 2
    
  const posY = isDragging && dragPosition 
    ? dragPosition.y 
    : token.y * cellSize + cellSize / 2

  const tokenSize = (token.size ?? 1) * 64
  const displaySize = isEditMode ? editSize * 64 : tokenSize

  return (
    <div
      className={`token ${isDragging ? 'dragging' : ''} ${(isEditMode || isDeleteConfirmMode) ? 'edit-mode' : ''}`}
      style={{
        left: posX,
        top: posY
      }}
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
      onMouseDown={handleMouseDown}
      onTouchStart={handleTouchStart}
      onClick={handleTokenClickInEraseMode}
    >
      {(isEditMode || token.upperLabel) && (
        <div className="token-label token-label-upper">
          {isEditMode ? (
            <input
              type="text"
              className="token-label-input"
              value={editUpperLabel}
              onChange={(e) => setEditUpperLabel(e.target.value)}
              placeholder={t('token.upperLabelPlaceholder')}
              onClick={(e) => e.stopPropagation()}
              onMouseDown={(e) => e.stopPropagation()}
              onTouchStart={(e) => e.stopPropagation()}
              autoFocus
            />
          ) : (
            <span>{token.upperLabel}</span>
          )}
        </div>
      )}

      {isHovered && !isEditMode && !isDeleteConfirmMode && !isDragging && (
        <button
          className="token-gear"
          onClick={handleGearClick}
          onMouseDown={(e) => {
            e.stopPropagation()
            e.preventDefault()
          }}
          onTouchStart={(e) => e.stopPropagation()}
          title={t('token.editTitle')}
        >
          ⚙️
        </button>
      )}

      {isHovered && !isEditMode && !isDeleteConfirmMode && !isDragging && (
        <button
          className="token-trash"
          onClick={handleTrashClick}
          onMouseDown={(e) => {
            e.stopPropagation()
            e.preventDefault()
          }}
          onTouchStart={(e) => e.stopPropagation()}
          title={t('token.deleteTitle')}
        >
          🗑️
        </button>
      )}

      <div className="token-content">
        {isEditMode && !isDeleteConfirmMode && (
          <div className="token-edit-controls">
            <div className="token-size-controls">
              <button
                className="token-size-btn token-size-decrease"
                onClick={handleDecreaseSize}
                onMouseDown={(e) => e.stopPropagation()}
                onTouchStart={(e) => e.stopPropagation()}
                title={t('token.sizeDecreaseTitle')}
              >
                −
              </button>
              <button
                className="token-size-btn token-size-increase"
                onClick={handleIncreaseSize}
                onMouseDown={(e) => e.stopPropagation()}
                onTouchStart={(e) => e.stopPropagation()}
                title={t('token.sizeIncreaseTitle')}
              >
                +
              </button>
            </div>

            <div className="token-save-controls">
              <button
                className="token-save-btn token-save-confirm"
                onClick={handleSave}
                onMouseDown={(e) => e.stopPropagation()}
                onTouchStart={(e) => e.stopPropagation()}
                title={t('token.saveTitle')}
              >
                ✓
              </button>
              <button
                className="token-save-btn token-save-cancel"
                onClick={handleCancel}
                onMouseDown={(e) => e.stopPropagation()}
                onTouchStart={(e) => e.stopPropagation()}
                title={t('token.cancelTitle')}
              >
                ✕
              </button>
            </div>
          </div>
        )}

        {isDeleteConfirmMode && (
          <div className="token-delete-controls" title={t('token.deleteConfirmTitle')}>
            <div className="token-delete-message">
              {t('token.deleteConfirmQuestion')}
            </div>
            <div className="token-delete-buttons">
              <button
                className="token-delete-btn token-delete-confirm"
                onClick={handleDeleteConfirm}
                onMouseDown={(e) => e.stopPropagation()}
                onTouchStart={(e) => e.stopPropagation()}
              >
                {t('token.deleteConfirm')}
              </button>
              <button
                className="token-delete-btn token-delete-cancel"
                onClick={handleDeleteCancel}
                onMouseDown={(e) => e.stopPropagation()}
                onTouchStart={(e) => e.stopPropagation()}
              >
                {t('token.deleteCancel')}
              </button>
            </div>
          </div>
        )}

        <img
          src={`${basePath}${token.src}`}
          alt=""
          draggable={false}
          style={{
            width: `${displaySize}px`,
            height: `${displaySize}px`
          }}
        />
      </div>

      {(isEditMode || token.lowerLabel) && (
        <div className="token-label token-label-lower">
          {isEditMode ? (
            <input
              type="text"
              className="token-label-input"
              value={editLowerLabel}
              onChange={(e) => setEditLowerLabel(e.target.value)}
              placeholder={t('token.lowerLabelPlaceholder')}
              onClick={(e) => e.stopPropagation()}
              onMouseDown={(e) => e.stopPropagation()}
              onTouchStart={(e) => e.stopPropagation()}
            />
          ) : (
            <span>{token.lowerLabel}</span>
          )}
        </div>
      )}
    </div>
  )
}

export default Token
