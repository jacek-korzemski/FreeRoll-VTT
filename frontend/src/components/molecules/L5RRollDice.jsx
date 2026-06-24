import React from 'react'
import { RING_DICE_IMAGES, SKILL_DICE_IMAGES } from '../../assets/l5r'

function dieImageSrc(die) {
  const faceId = Number(die?.faceId)
  if (!Number.isFinite(faceId) || faceId < 1) return null
  if (die?.type === 'skill') return SKILL_DICE_IMAGES[faceId] || null
  return RING_DICE_IMAGES[faceId] || null
}

function dieTitle(die) {
  const parts = []
  if (die.success) parts.push(`✓${die.success}`)
  if (die.opportunity) parts.push(`🌀${die.opportunity}`)
  if (die.strife) parts.push('💢')
  if (die.exploded) parts.push('★')
  return parts.join(' ') || (die.type === 'skill' ? 'Skill' : 'Ring')
}

/** Kept dice from a finished L5R roll (ring + skill face images). */
function L5RRollDice({ dice = [], className = '' }) {
  return (
    <div className={`l5r-roll-dice-row ${className}`.trim()}>
      {dice.map((die, i) => {
        const src = dieImageSrc(die)
        if (!src) return null
        return (
          <span
            key={i}
            className={`l5r-roll-die ${die.type === 'skill' ? 'skill' : 'ring'}`}
            title={dieTitle(die)}
          >
            <img src={src} alt="" className="l5r-roll-die-img" draggable={false} />
          </span>
        )
      })}
    </div>
  )
}

export default L5RRollDice
