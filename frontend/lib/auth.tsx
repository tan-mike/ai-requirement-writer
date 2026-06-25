'use client'

import { createContext, useContext, useEffect, useState, ReactNode } from 'react'
import { apiClient } from './api'

interface User {
  id: number
  name: string
  email: string
  role: string
  current_team_id: number | null
  email_verified_at: string | null
}

interface AuthContextType {
  user: User | null
  token: string | null
  settings: { creation_disabled: boolean } | null
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string) => Promise<string>
  logout: () => Promise<void>
  refreshUser: () => Promise<void>
  switchTeam: (teamId: number | null) => Promise<void>
  resendVerification: (email: string) => Promise<void>
  verifyEmail: (id: string, hash: string, expires: string, signature: string) => Promise<void>
  loading: boolean
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(null)
  const [settings, setSettings] = useState<{ creation_disabled: boolean } | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const safeParse = (val: string | null) => {
      if (!val || val === 'undefined') return null
      try {
        return JSON.parse(val)
      } catch (e) {
        return null
      }
    }

    const stored = localStorage.getItem('token')
    const storedUser = localStorage.getItem('user')
    const storedSettings = localStorage.getItem('settings')
    
    if (stored && storedUser && storedUser !== 'undefined') {
      const parsedUser = safeParse(storedUser)
      if (parsedUser) {
        setToken(stored)
        setUser(parsedUser)
        setSettings(safeParse(storedSettings))
      }
    }
    setLoading(false)
  }, [])

  async function login(email: string, password: string) {
    try {
      const res = await apiClient.post<{ data: { user: User; token: string } }>(
        '/auth/login',
        { email, password }
      )
      localStorage.setItem('token', res.data.token)
      localStorage.setItem('user', JSON.stringify(res.data.user))
      setToken(res.data.token)
      setUser(res.data.user)
      // Refresh user after login to get settings
      await refreshUser()
    } catch (err: unknown) {
      if (err instanceof Error && (err as Error & { status: number }).status === 409) {
        sessionStorage.setItem('pending_email', email)
        throw { needsVerification: true, email }
      }
      throw err
    }
  }

  async function register(name: string, email: string, password: string): Promise<string> {
    const res = await apiClient.post<{ message: string }>(
      '/auth/register',
      { name, email, password, password_confirmation: password }
    )
    sessionStorage.setItem('pending_email', email)
    return email
  }

  async function logout() {
    await apiClient.post('/auth/logout', {})
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('settings')
    setToken(null)
    setUser(null)
    setSettings(null)
  }

  async function refreshUser() {
    try {
      const res = await apiClient.get<{ data: User; settings: { creation_disabled: boolean } }>('/user/me')
      const { data, settings: newSettings } = res;
      const updatedUser = { ...(user || {}), ...data } as User;
      
      if (updatedUser) localStorage.setItem('user', JSON.stringify(updatedUser))
      if (newSettings) localStorage.setItem('settings', JSON.stringify(newSettings))
      
      setUser(updatedUser);
      if (newSettings) setSettings(newSettings);
    } catch (err) {
      // ignore
    }
  }

  async function switchTeam(teamId: number | null) {
    await apiClient.patch('/user/current-team', { team_id: teamId })
    await refreshUser()
    window.location.reload()
  }

  async function resendVerification(email: string) {
    await apiClient.post('/auth/email/resend', { email })
  }

  async function verifyEmail(id: string, hash: string, expires: string, signature: string) {
    await apiClient.post('/auth/email/verify', { id, hash, expires, signature })
  }

  return (
    <AuthContext.Provider value={{ user, token, settings, login, register, logout, refreshUser, switchTeam, resendVerification, verifyEmail, loading }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
