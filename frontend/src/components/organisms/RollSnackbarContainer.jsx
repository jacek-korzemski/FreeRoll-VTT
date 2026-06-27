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

function RollSnackbarContainer({ rollHistory, dicePanelOpen, rollsHydrated }) {
  const [active, setActive] = useState([])
  const seenIdsRef = useRef(new Set())
  const hydratedRef = useRef(false)

  useEffect(() => {
    if (!rollsHydrated || !Array.isArray(rollHistory)) return

    if (!hydratedRef.current) {
      rollHistory.forEach((roll, i) => seenIdsRef.current.add(getRollKey(roll, i)))
      hydratedRef.current = true
      return
    }

    const newOnes = []
    rollHistory.forEach((roll, i) => {
      const key = getRollKey(roll, i)
      if (!seenIdsRef.current.has(key)) {
        seenIdsRef.current.add(key)
        if (roll.snackbar) {
          newOnes.push({ ...roll, __key: key })
        }
      }
    })

    if (newOnes.length === 0) return
    if (dicePanelOpen) return

    setActive(prev => {
      const combined = [...newOnes, ...prev]
      if (combined.length <= MAX_STACK) return combined
      return combined.slice(0, MAX_STACK)
    })
  }, [rollHistory, dicePanelOpen, rollsHydrated])

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
