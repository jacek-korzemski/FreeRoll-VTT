import { useState, useCallback } from 'react'
import { API_BASE } from '../../../config'
import { t } from '../../lang'

const TYPE_TOKEN = 'token'
const TYPE_MAP = 'map'
const TYPE_BACKGROUND = 'background'
const TYPE_TEMPLATE = 'template'
const TYPE_PAPER = 'paper'

function UploadSection({
  onUploadedImages,
  onUploadedBackgrounds,
  onUploadedTemplates,
  onUploadedPapers,
}) {
  const [selectedType, setSelectedType] = useState(TYPE_TOKEN)
  const [files, setFiles] = useState([])
  const [isUploading, setIsUploading] = useState(false)
  const [message, setMessage] = useState(null)
  const [error, setError] = useState(null)
  const [details, setDetails] = useState(null)

  const handleTypeChange = useCallback((event) => {
    setSelectedType(event.target.value)
    setFiles([])
    setMessage(null)
    setError(null)
    setDetails(null)
  }, [])

  const handleFileChange = useCallback((event) => {
    const list = Array.from(event.target.files || [])
    setFiles(list)
    setMessage(null)
    setError(null)
    setDetails(null)
  }, [])

  const handleRemoveFile = useCallback((index) => {
    setFiles(prev => prev.filter((_, i) => i !== index))
    setMessage(null)
    setError(null)
    setDetails(null)
  }, [])

  const handleReset = useCallback(() => {
    setSelectedType(TYPE_TOKEN)
    setFiles([])
    setMessage(null)
    setError(null)
    setDetails(null)
  }, [])

  const validateFiles = useCallback(() => {
    if (files.length === 0) {
      setError(t('upload.noFiles') || 'No files selected')
      return false
    }

    const lowerExt = (name) => {
      const idx = name.lastIndexOf('.')
      return idx !== -1 ? name.slice(idx + 1).toLowerCase() : ''
    }

    if (selectedType === TYPE_TEMPLATE || selectedType === TYPE_PAPER) {
      if (files.length > 1) {
        setError(
          t('upload.singleFileOnly') ||
            'Only a single file is allowed for this type.'
        )
        return false
      }
    }

    if ([TYPE_TOKEN, TYPE_MAP, TYPE_BACKGROUND].includes(selectedType)) {
      const allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp']
      const invalid = files.filter(
        (f) => !allowed.includes(lowerExt(f.name))
      )
      if (invalid.length > 0) {
        setError(
          t('upload.invalidImageExtensions') ||
            'Only image files are allowed for this type.'
        )
        return false
      }
    }

    if (selectedType === TYPE_TEMPLATE) {
      const ext = lowerExt(files[0].name)
      if (!['html', 'htm'].includes(ext)) {
        setError(
          t('upload.invalidTemplateExtension') ||
            'Template must be a .html or .htm file.'
        )
        return false
      }
    }

    if (selectedType === TYPE_PAPER) {
      const ext = lowerExt(files[0].name)
      if (ext !== 'pdf') {
        setError(
          t('upload.invalidPdfExtension') || 'File must be a .pdf.'
        )
        return false
      }
    }

    setError(null)
    return true
  }, [files, selectedType])

  const handleUpload = useCallback(async () => {
    if (!validateFiles()) return

    setIsUploading(true)
    setMessage(null)
    setError(null)
    setDetails(null)

    try {
      const formData = new FormData()
      formData.append('type', selectedType)

      if ([TYPE_TOKEN, TYPE_MAP, TYPE_BACKGROUND].includes(selectedType)) {
        files.forEach((file) => formData.append('files[]', file))
      } else {
        formData.append('file', files[0])
      }

      const res = await fetch(`${API_BASE}?action=upload-asset`, {
        method: 'POST',
        credentials: 'include',
        body: formData,
      })

      const data = await res.json().catch(() => ({}))

      if (!data.success) {
        // Specjalne traktowanie zbyt dużych PDF
        if (data.code === 'file_too_large') {
          setError(
            t('upload.tooBigPdf') ||
              'File is too large for server limits – please upload manually on the server.'
          )
        } else if (data.error) {
          setError(data.error)
        } else {
          setError(
            t('upload.genericError') ||
              'Upload failed. Please try again.'
          )
        }
        setDetails(data.errors || null)
        return
      }

      const uploaded = data.uploaded || []

      if (uploaded.length === files.length && uploaded.length > 0) {
        setMessage(
          t('upload.success') || 'Files uploaded successfully.'
        )
      } else if (uploaded.length > 0) {
        setMessage(
          t('upload.partialSuccess') ||
            'Some files were uploaded, some failed.'
        )
      } else {
        setError(
          t('upload.genericError') ||
            'Upload failed. Please try again.'
        )
      }

      setDetails(data.errors || null)

      // Wywołania callbacków do odświeżenia list po stronie rodzica
      if (uploaded.length > 0) {
        if ([TYPE_TOKEN, TYPE_MAP].includes(selectedType)) {
          onUploadedImages && onUploadedImages()
        } else if (selectedType === TYPE_BACKGROUND) {
          onUploadedBackgrounds && onUploadedBackgrounds()
        } else if (selectedType === TYPE_TEMPLATE) {
          onUploadedTemplates && onUploadedTemplates()
        } else if (selectedType === TYPE_PAPER) {
          onUploadedPapers && onUploadedPapers()
        }
      }
    } catch (err) {
      console.error('Upload failed:', err)
      setError(
        t('upload.genericError') ||
          'Upload failed. Please try again.'
      )
    } finally {
      setIsUploading(false)
    }
  }, [
    files,
    onUploadedBackgrounds,
    onUploadedImages,
    onUploadedPapers,
    onUploadedTemplates,
    selectedType,
    validateFiles,
  ])

  const renderFileInput = () => {
    if ([TYPE_TOKEN, TYPE_MAP, TYPE_BACKGROUND].includes(selectedType)) {
      return (
        <input
          type="file"
          accept="image/*"
          multiple
          onChange={handleFileChange}
        />
      )
    }

    if (selectedType === TYPE_TEMPLATE) {
      return (
        <input
          type="file"
          accept=".html,.htm"
          onChange={handleFileChange}
        />
      )
    }

    if (selectedType === TYPE_PAPER) {
      return (
        <input
          type="file"
          accept=".pdf"
          onChange={handleFileChange}
        />
      )
    }

    return null
  }

  return (
    <div className="upload-section">
      <div className="upload-row">
        <label className="upload-label">
          {t('upload.typeLabel') || 'Material type'}
        </label>
        <select
          className="upload-select"
          value={selectedType}
          onChange={handleTypeChange}
        >
          <option value={TYPE_TOKEN}>
            {t('upload.types.tokens') || 'Tokens'}
          </option>
          <option value={TYPE_MAP}>
            {t('upload.types.map') || 'Map elements'}
          </option>
          <option value={TYPE_BACKGROUND}>
            {t('upload.types.backgrounds') || 'Backgrounds'}
          </option>
          <option value={TYPE_TEMPLATE}>
            {t('upload.types.templates') || 'Templates (HTML)'}
          </option>
          <option value={TYPE_PAPER}>
            {t('upload.types.papers') || 'PDF materials'}
          </option>
        </select>
      </div>

      <div className="upload-row">
        <label className="upload-label">
          {t('upload.selectFiles') || 'Select files'}
        </label>
        {renderFileInput()}
        {files.length > 0 && (
          <div className="upload-file-list">
            {files.map((f, index) => (
              <div key={`${f.name}-${index}`} className="upload-file-item">
                <button
                  type="button"
                  className="upload-file-remove"
                  onClick={() => handleRemoveFile(index)}
                  title={t('upload.removeFile') || 'Remove file from list'}
                >
                  ❌
                </button>
                <span className="upload-file-name">{f.name}</span>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="upload-actions">
        <button
          className="upload-button"
          onClick={handleUpload}
          disabled={isUploading || files.length === 0}
        >
          {isUploading
            ? t('upload.uploading') || 'Uploading...'
            : t('upload.uploadButton') || 'Upload'}
        </button>
        <button
          type="button"
          className="upload-reset-button"
          onClick={handleReset}
          disabled={isUploading && files.length === 0}
        >
          {t('upload.reset') || 'Reset'}
        </button>
      </div>

      {message && (
        <div className="upload-message upload-message-success">
          {message}
        </div>
      )}
      {error && (
        <div className="upload-message upload-message-error">
          {error}
        </div>
      )}
      {details && details.length > 0 && (
        <ul className="upload-details">
          {details.map((item, idx) => (
            <li key={idx}>
              {item.name ? `${item.name}: ` : ''}
              {item.message || item.error || String(item)}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

export default UploadSection

