import { useLayoutEffect } from 'react'

const VIEWPORT_PAD = 8

/**
 * Keeps a dropdown inside the viewport. Uses fixed positioning so parent
 * overflow:hidden does not clip the menu. Re-runs when deps change (menu items).
 */
export function useAnchoredMenuPosition(anchorRef, menuRef, isOpen, deps = []) {
  useLayoutEffect(() => {
    if (!isOpen) return

    const anchor = anchorRef.current
    const menu = menuRef.current
    if (!anchor || !menu) return

    const place = () => {
      menu.classList.add('note-load-menu--floating')

      const anchorRect = anchor.getBoundingClientRect()
      const menuWidth = menu.offsetWidth
      const menuHeight = menu.offsetHeight
      const vw = window.innerWidth
      const vh = window.innerHeight

      // Right-align with the trigger; menu grows to the left.
      let left = anchorRect.right - menuWidth
      let top = anchorRect.bottom + 2

      if (left < VIEWPORT_PAD) left = VIEWPORT_PAD
      if (left + menuWidth > vw - VIEWPORT_PAD) {
        left = Math.max(VIEWPORT_PAD, vw - VIEWPORT_PAD - menuWidth)
      }
      if (top + menuHeight > vh - VIEWPORT_PAD) {
        top = anchorRect.top - menuHeight - 2
      }
      if (top < VIEWPORT_PAD) top = VIEWPORT_PAD

      menu.style.left = `${Math.round(left)}px`
      menu.style.top = `${Math.round(top)}px`
    }

    place()

    const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(place) : null
    ro?.observe(menu)
    window.addEventListener('resize', place)
    window.addEventListener('scroll', place, true)

    return () => {
      ro?.disconnect()
      window.removeEventListener('resize', place)
      window.removeEventListener('scroll', place, true)
      menu.classList.remove('note-load-menu--floating')
      menu.style.left = ''
      menu.style.top = ''
    }
  }, [isOpen, anchorRef, menuRef, ...deps])
}
