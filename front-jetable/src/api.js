/** Base URL unique — modifier ici si besoin. */
export const API_BASE = 'http://127.0.0.1:8080/api/v1'

const TOKEN_KEY = 'ostravel_jetable_token'

export function loadToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function saveToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

function extractErrorMessage(body) {
  const err = body?.error ?? body
  if (!err) return 'Erreur inconnue'
  if (Array.isArray(err.violations) && err.violations.length > 0) {
    return err.violations
      .map((v) => {
        if (typeof v === 'string') return v
        const field = v.field || v.propertyPath
        const msg = v.message || JSON.stringify(v)
        return field ? `${field}: ${msg}` : msg
      })
      .join(' ; ')
  }
  if (err.message) return err.message
  return JSON.stringify(body)
}

/**
 * Fetch centralisé.
 * @param {string} path - chemin relatif à API_BASE
 * @param {object} options
 * @param {string|null} options.token
 * @param {() => void} [options.onUnauthorized] - appelé sur 401 (appel authentifié)
 */
export async function apiFetch(path, { method = 'GET', body, token, onUnauthorized } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = `Bearer ${token}`

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  const requestId = res.headers.get('X-Request-Id')
  let data = null
  const text = await res.text()
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = { raw: text }
    }
  }

  if (res.status === 401 && token && onUnauthorized) {
    onUnauthorized()
  }

  if (res.status >= 400) {
    const message = extractErrorMessage(data)
    const err = new Error(message)
    err.status = res.status
    err.data = data
    err.requestId = requestId
    throw err
  }

  return { data, status: res.status, requestId }
}
