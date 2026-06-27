import { createPortal } from 'react-dom'
import { t } from '../../lang'

function ConfirmModal({
  title,
  message,
  confirmLabel,
  cancelLabel,
  onConfirm,
  onCancel,
  danger = false,
  children,
}) {
  return createPortal(
    <div className="note-template-modal confirm-modal" role="dialog" aria-modal="true">
      <div className="note-template-modal-content confirm-modal-content">
        <h3>{title}</h3>
        {message && <p className="note-mismatch-message">{message}</p>}
        {children}
        <div className="note-template-modal-footer">
          <button type="button" onClick={onCancel} className="note-template-cancel">
            {cancelLabel || t('notes.templateCancel')}
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className={danger ? 'note-mismatch-confirm confirm-modal-danger' : 'note-mismatch-confirm'}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>,
    document.body
  )
}

export default ConfirmModal
