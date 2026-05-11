import { useCallback, useEffect, useRef, useState } from 'react'
import RollSnackbar from '../molecules/RollSnackbar'

const MAX_STACK = 5

function getRollKey(roll, index) {
  if (!roll) return `idx-${index}`
  if (roll.timestamp != null) {
    const player = roll.player ?? ''
    const total = roll.total ?? ''
    return `sig-${roll.timestamp}-${player}-${total}`
  }
  if (roll.id != null) return `id-${roll.id}`
  return `idx-${index}`
}

function RollSnackbarContainer({ rollHistory, dicePanelOpen }) {
  const [active, setActive] = useState([])
  const seenIdsRef = useRef(null)

  if (seenIdsRef.current === null) {
    const initial = new Set()
    ;(rollHistory || []).forEach((roll, i) => initial.add(getRollKey(roll, i)))
    seenIdsRef.current = initial
  }

  useEffect(() => {
    if (!Array.isArray(rollHistory) || rollHistory.length === 0) return

    const newOnes = []
    rollHistory.forEach((roll, i) => {
      const key = getRollKey(roll, i)
      if (!seenIdsRef.current.has(key)) {
        seenIdsRef.current.add(key)
        newOnes.push({ ...roll, __key: key })
      }
    })

    if (newOnes.length === 0) return
    if (dicePanelOpen) return

    setActive(prev => {
      const combined = [...newOnes.reverse(), ...prev]
      if (combined.length <= MAX_STACK) return combined
      return combined.slice(0, MAX_STACK)
    })
  }, [rollHistory, dicePanelOpen])

  useEffect(() => {
    if (dicePanelOpen) {
      setActive([])
    }
  }, [dicePanelOpen])

  const handleClose = useCallback((key) => {
    setActive(prev => prev.filter(r => r.__key !== key))
  }, [])

  if (active.length === 0) return null

  return (
    <div className="roll-snackbar-container" aria-live="polite">
      {active.map(roll => (
        <RollSnackbar
          key={roll.__key}
          roll={roll}
          onClose={() => handleClose(roll.__key)}
        />
      ))}
    </div>
  )
}

export default RollSnackbarContainer
