import axios from 'axios'

/**
 * Single axios instance for the whole app.
 *
 * - Attaches the Sanctum bearer token to every request.
 * - Normalises every failure into an ApiError { status, message, errors }, so
 *   components never have to dig through axios' error shape.
 * - On 401 with a stored token (expired/revoked), drops the token and notifies
 *   listeners so AuthContext can log the user out.
 */

const TOKEN_KEY = 'foodapp.token'

// localStorage can throw (private mode, blocked storage) — never let that crash the app.
export const tokenStorage = {
  get() {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token) {
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      /* storage unavailable: session lasts until reload */
    }
  },
  clear() {
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      /* ignore */
    }
  },
}

export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    // Laravel validation errors: { field: ["message", ...] }
    this.errors = errors
  }

  /** First validation message for a field, if any. */
  fieldError(field) {
    return this.errors?.[field]?.[0]
  }
}

const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api/v1',
  headers: { Accept: 'application/json' },
  timeout: 15000,
})

client.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

const unauthorizedListeners = new Set()

/** Subscribe to "the stored token stopped working". Returns an unsubscribe fn. */
export function onUnauthorized(listener) {
  unauthorizedListeners.add(listener)
  return () => unauthorizedListeners.delete(listener)
}

client.interceptors.response.use(
  (response) => response,
  (error) => {
    // Aborted requests (component unmounted) are not errors worth reporting.
    if (axios.isCancel(error)) {
      return Promise.reject(error)
    }

    const status = error.response?.status ?? 0
    const body = error.response?.data ?? {}

    if (status === 401 && tokenStorage.get()) {
      tokenStorage.clear()
      unauthorizedListeners.forEach((listener) => listener())
    }

    const message =
      status === 0
        ? 'Cannot reach the server. Please check that the API is running.'
        : body.message || `Request failed with status ${status}.`

    return Promise.reject(new ApiError(message, status, body.errors ?? {}))
  },
)

export default client
