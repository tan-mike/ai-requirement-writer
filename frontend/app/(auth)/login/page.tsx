'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { useAuth } from '@/lib/auth'

export default function LoginPage() {
  const { login } = useAuth()
  const router = useRouter()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const newErrors: Record<string, string> = {}
    if (!email) newErrors.email = 'Email is required'
    if (!password) newErrors.password = 'Password is required'
    if (Object.keys(newErrors).length) { setErrors(newErrors); return }

    setSubmitting(true)
    try {
      await login(email, password)
      router.push('/dashboard')
    } catch (err) {
      setErrors({ general: err instanceof Error ? err.message : 'Login failed' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground">Sign in</h1>
        <p className="text-muted-foreground mt-1">Welcome back! Please enter your details.</p>
      </div>
      
      {errors.general && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mb-6">
          <p className="text-red-700 dark:text-red-400 text-sm font-medium">{errors.general}</p>
        </div>
      )}
      
      <form onSubmit={handleSubmit} className="space-y-5">
        <div>
          <label htmlFor="email" className="block text-sm font-semibold text-foreground mb-1.5">Email</label>
          <input
            id="email" type="email" value={email}
            onChange={e => setEmail(e.target.value)}
            className="input"
            placeholder="name@example.com"
          />
          {errors.email && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.email}</p>}
        </div>
        <div>
          <div className="flex justify-between items-center mb-1.5">
            <label htmlFor="password" className="block text-sm font-semibold text-foreground">Password</label>
          </div>
          <input
            id="password" type="password" value={password}
            onChange={e => setPassword(e.target.value)}
            className="input"
            placeholder="••••••••"
          />
          {errors.password && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.password}</p>}
        </div>
        <button
          type="submit" disabled={submitting}
          className="btn-primary w-full py-2.5 mt-2"
        >
          {submitting ? 'Signing in...' : 'Sign in'}
        </button>
      </form>
      
      <div className="mt-8 pt-6 border-t border-border text-center">
        <p className="text-sm text-muted-foreground">
          Don't have an account?{' '}
          <Link href="/register" className="text-primary font-bold hover:underline underline-offset-4">
            Create an account
          </Link>
        </p>
      </div>
    </div>
  )
}
