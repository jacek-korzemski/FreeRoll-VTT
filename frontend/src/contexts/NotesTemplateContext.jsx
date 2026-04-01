import React, { createContext, useContext, useState, useRef, useCallback, useMemo } from 'react'
import {
  readNoteStorageData,
  getTemplateFieldMetaFromHtml,
  getStoredTemplateFieldValue,
} from '../utils/noteTemplateMeta'

const NotesTemplateContext = createContext(null)

export function useNotesTemplate() {
  const ctx = useContext(NotesTemplateContext)
  return ctx
}

export function NotesTemplateProvider({ children }) {
  const [noteOrder, setNoteOrderState] = useState([])
  const [sourcesRevision, setSourcesRevision] = useState(0)
  const liveGettersRef = useRef({})
  /** Mounted template editors: latest title / field meta (DOM-derived). */
  const liveMetaRef = useRef({})

  const setNoteOrder = useCallback((ids) => {
    setNoteOrderState(Array.isArray(ids) ? [...ids] : [])
  }, [])

  const refreshNoteSources = useCallback(() => {
    setSourcesRevision((r) => r + 1)
  }, [])

  const registerNoteTemplate = useCallback((noteId, { title, fieldNames, fieldLabels, getFieldValue }) => {
    liveGettersRef.current[noteId] = getFieldValue
    liveMetaRef.current[noteId] = {
      title: title ?? '',
      fieldNames: fieldNames || [],
      fieldLabels: fieldLabels || {},
    }
    setSourcesRevision((r) => r + 1)
  }, [])

  const unregisterNoteTemplate = useCallback((noteId) => {
    delete liveGettersRef.current[noteId]
    delete liveMetaRef.current[noteId]
    setSourcesRevision((r) => r + 1)
  }, [])

  const getFieldValue = useCallback((noteId, fieldName) => {
    const live = liveGettersRef.current[noteId]
    if (live) {
      const v = live(fieldName)
      return v ?? ''
    }
    const data = readNoteStorageData(noteId)
    return getStoredTemplateFieldValue(data, fieldName)
  }, [])

  const sources = useMemo(() => {
    void sourcesRevision
    return noteOrder.map((id, idx) => {
      const noteIndex = idx + 1
      const stored = readNoteStorageData(id)
      const liveM = liveMetaRef.current[id]
      const titleFromStorage = stored?.title ?? ''
      const templateMeta =
        stored?.mode === 'template' && stored?.templateHtml
          ? getTemplateFieldMetaFromHtml(stored.templateHtml)
          : { fieldNames: [], fieldLabels: {} }

      const title = liveM?.title ?? titleFromStorage
      const fieldNames =
        liveM?.fieldNames && liveM.fieldNames.length > 0 ? liveM.fieldNames : templateMeta.fieldNames
      const fieldLabels =
        liveM?.fieldLabels && Object.keys(liveM.fieldLabels).length > 0
          ? liveM.fieldLabels
          : templateMeta.fieldLabels

      return {
        noteId: id,
        noteIndex,
        title,
        fieldNames,
        fieldLabels,
      }
    })
  }, [noteOrder, sourcesRevision])

  const value = {
    sources,
    getFieldValue,
    registerNoteTemplate,
    unregisterNoteTemplate,
    setNoteOrder,
    refreshNoteSources,
  }

  return (
    <NotesTemplateContext.Provider value={value}>
      {children}
    </NotesTemplateContext.Provider>
  )
}
