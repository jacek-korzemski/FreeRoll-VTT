import './polyfills'
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './App.css'
import { COLOR_TEMPLATE } from '../config'
import { applyTheme } from './themes/applyTheme'
import { getOrCreateClientId } from './utils/clientId'

applyTheme(COLOR_TEMPLATE)

const origFetch = window.fetch
window.fetch = function (url, opts) {
  let finalOpts = opts || {}
  const urlStr = typeof url === 'string' ? url : (url && url.url) || ''
  if (urlStr.includes('api.php')) {
    const headers = { ...(finalOpts.headers || {}) }
    headers['X-VTT-Client-Id'] = getOrCreateClientId()
    try {
      const name = localStorage.getItem('vtt_player_name')
      if (name) {
        headers['X-VTT-Player-Name'] = String(name).slice(0, 80)
      }
    } catch {
      /* ignore */
    }
    if (import.meta.env.DEV && localStorage.getItem('dev_gm') === '1') {
      headers['X-Dev-GM'] = '1'
    }
    finalOpts = { ...finalOpts, headers }
  }
  return origFetch.call(this, url, finalOpts)
}

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
