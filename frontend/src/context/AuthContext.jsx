import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import * as authApi from '../api/auth'
import { onUnauthorized, tokenStorage } from '../api/client'

const AuthContext = createContext(null)

/**
 * Holds the signed-in user. The Sanctum token lives in localStorage so a page
 * refresh keeps the session; on start-up we exchange it for the user via /me.
 *
 * Trade-off (documented in the README): a token in localStorage is readable by
 * any XSS on the page. Sanctum's cookie-based SPA mode avoids that but needs
 * same-site domains and CSRF handling — overkill for this demo.
 */
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  // Only "initialising" if there is a token to validate.
  const [initialising, setInitialising] = useState(() => Boolean(tokenStorage.get()))

  useEffect(() => {
    if (!tokenStorage.get()) return undefined

    const controller = new AbortController()
    authApi
      .me({ signal: controller.signal })
      .then((me) => setUser(me))
      // A 401 is handled by the client interceptor (token cleared); on a network
      // error we simply stay signed out for now.
      .catch(() => {})
      .finally(() => {
        if (!controller.signal.aborted) setInitialising(false)
      })

    return () => controller.abort()
  }, [])

  // Token expired or revoked mid-session -> reflect it in the UI immediately.
  useEffect(() => onUnauthorized(() => setUser(null)), [])

  const startSession = useCallback(({ user: nextUser, token }) => {
    tokenStorage.set(token)
    setUser(nextUser)
    return nextUser
  }, [])

  const login = useCallback((credentials) => authApi.login(credentials).then(startSession), [startSession])
  const register = useCallback((payload) => authApi.register(payload).then(startSession), [startSession])
  const registerAdmin = useCallback((payload) => authApi.registerAdmin(payload).then(startSession), [startSession])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Even if revoking fails (network, already expired), sign out locally.
    } finally {
      tokenStorage.clear()
      setUser(null)
    }
  }, [])

  const value = useMemo(
    () => ({
      user,
      isAuthenticated: Boolean(user),
      isAdmin: Boolean(user?.is_admin),
      initialising,
      login,
      register,
      registerAdmin,
      logout,
    }),
    [user, initialising, login, register, registerAdmin, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>')
  return context
}
