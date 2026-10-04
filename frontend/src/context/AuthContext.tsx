import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { clearToken, getToken, setToken } from '../lib/api'
import { login as loginRequest, logout as logoutRequest, me } from '../services/authService'
import type { User } from '../types'

interface AuthContextValue {
  user: User | null
  loading: boolean
  login: (userId: number, password: string) => Promise<User>
  logout: () => Promise<void>
  updateUser: (user: User) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true

    async function restore() {
      if (!getToken()) {
        setLoading(false)
        return
      }
      try {
        const currentUser = await me()
        if (active) {
          setUser(currentUser)
        }
      } catch {
        if (active) {
          clearToken()
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    restore()

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    function handleUnauthorized() {
      setUser(null)
    }
    window.addEventListener('tita:unauthorized', handleUnauthorized)
    return () => {
      window.removeEventListener('tita:unauthorized', handleUnauthorized)
    }
  }, [])

  const login = useCallback(async (userId: number, password: string) => {
    const response = await loginRequest(userId, password)
    setToken(response.token)
    setUser(response.user)
    return response.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await logoutRequest()
    } catch {
      // token may already be invalid; clear local state anyway
    }
    clearToken()
    setUser(null)
  }, [])

  const updateUser = useCallback((updatedUser: User) => {
    setUser(updatedUser)
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({ user, loading, login, logout, updateUser }),
    [user, loading, login, logout, updateUser],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return ctx
}
