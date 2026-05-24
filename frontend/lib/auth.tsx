'use client'

import { createContext, useContext, useEffect, useState, ReactNode } from 'react'
import { apiClient } from './api'

interface User {
  id: number
  name: string
  email: string
  role: string
  current_team_id: number | null
}

interface AuthContextType {
  user: User | null
  token: string | null
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  refreshUser: () => Promise<void>
  switchTeam: (teamId: number | null) => Promise<void>
  loading: boolean
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const stored = localStorage.getItem('token')
    const storedUser = localStorage.getItem('user')
    if (stored && storedUser) {
      setToken(stored)
      setUser(JSON.parse(storedUser))
    }
    setLoading(false)
  }, [])

  async function login(email: string, password: string) {
    const res = await apiClient.post<{ data: { user: User; token: string } }>(
      '/auth/login',
      { email, password }
    )
    localStorage.setItem('token', res.data.token)
    localStorage.setItem('user', JSON.stringify(res.data.user))
    setToken(res.data.token)
    setUser(res.data.user)
  }

  async function register(name: string, email: string, password: string) {
    const res = await apiClient.post<{ data: { user: User; token: string } }>(
      '/auth/register',
      { name, email, password, password_confirmation: password }
    )
    localStorage.setItem('token', res.data.token)
    localStorage.setItem('user', JSON.stringify(res.data.user))
    setToken(res.data.token)
    setUser(res.data.user)
  }

  async function logout() {
    await apiClient.post('/auth/logout', {})
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setToken(null)
    setUser(null)
  }

  async function refreshUser() {
    try {
      const res = await apiClient.get<{ data: User }>('/user/me')
      const data = res.data;
      const updatedUser = { ...user, ...data } as User;
      localStorage.setItem('user', JSON.stringify(updatedUser))
      setUser(updatedUser);
    } catch (err) {
      // ignore
    }
  }

  async function switchTeam(teamId: number | null) {
    await apiClient.patch('/user/current-team', { team_id: teamId })
    await refreshUser()
    window.location.reload()
  }

  return (
    <AuthContext.Provider value={{ user, token, login, register, logout, refreshUser, switchTeam, loading }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
