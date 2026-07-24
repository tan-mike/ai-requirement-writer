'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth'

export default function AuthCallbackPage() {
  const router = useRouter()
  const { setToken, setUser, refreshUser } = useAuth()

  useEffect(() => {
    const hash = window.location.hash.slice(1)
    const params = new URLSearchParams(hash)
    const token = params.get('token')
    const userJson = params.get('user')
    const error = params.get('error')

    if (token && userJson) {
      localStorage.setItem('token', token)
      localStorage.setItem('user', userJson)
      setToken(token)
      setUser(JSON.parse(userJson))
      refreshUser().then(() => router.push('/dashboard'))
    } else {
      router.push('/login?error=' + (error || 'Authentication failed'))
    }
  }, [])

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10 text-center">
      <p className="text-muted-foreground">Completing sign-in...</p>
    </div>
  )
}
