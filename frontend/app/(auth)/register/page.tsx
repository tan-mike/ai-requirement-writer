'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { useAuth } from '@/lib/auth'

export default function RegisterPage() {
  const { register, loginWithGoogle, googleError, setGoogleError } = useAuth()
  const router = useRouter()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setGoogleError(null)
    const newErrors: Record<string, string> = {}
    if (!name) newErrors.name = 'Name is required'
    if (!email) newErrors.email = 'Email is required'
    if (password.length < 8) newErrors.password = 'Password must be at least 8 characters'
    if (Object.keys(newErrors).length) { setErrors(newErrors); return }

    setSubmitting(true)
    try {
      await register(name, email, password)
      router.push('/check-email')
    } catch (err) {
      setErrors({ general: err instanceof Error ? err.message : 'Registration failed' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground">Create account</h1>
        <p className="text-muted-foreground mt-1">Join us to start writing requirements efficiently.</p>
      </div>

      {(errors.general || googleError) && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mb-6">
          <p className="text-red-700 dark:text-red-400 text-sm font-medium">{errors.general || googleError}</p>
        </div>
      )}

      <button
        type="button"
        onClick={loginWithGoogle}
        className="w-full flex items-center justify-center gap-3 px-4 py-2.5 border border-border rounded-md hover:bg-muted transition-colors"
      >
        <svg className="w-5 h-5" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        <span className="font-medium">Sign up with Google</span>
      </button>

      <div className="relative my-6">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-border"></div>
        </div>
        <div className="relative flex justify-center text-sm">
          <span className="px-2 text-muted-foreground bg-background">or register with email</span>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5">
        <div>
          <label htmlFor="name" className="block text-sm font-semibold text-foreground mb-1.5">Full Name</label>
          <input
            id="name" type="text" value={name}
            onChange={e => { setName(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="John Doe"
          />
          {errors.name && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.name}</p>}
        </div>
        <div>
          <label htmlFor="email" className="block text-sm font-semibold text-foreground mb-1.5">Email</label>
          <input
            id="email" type="email" value={email}
            onChange={e => { setEmail(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="name@example.com"
          />
          {errors.email && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.email}</p>}
        </div>
        <div>
          <label htmlFor="password" className="block text-sm font-semibold text-foreground mb-1.5">Password</label>
          <input
            id="password" type="password" value={password}
            onChange={e => { setPassword(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="••••••••"
          />
          {errors.password && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.password}</p>}
        </div>
        <button
          type="submit" disabled={submitting}
          className="btn-primary w-full py-2.5 mt-2"
        >
          {submitting ? 'Creating account...' : 'Create account'}
        </button>
      </form>

      <div className="mt-8 pt-6 border-t border-border text-center">
        <p className="text-sm text-muted-foreground">
          Already have an account?{' '}
          <Link href="/login" className="text-primary font-bold hover:underline underline-offset-4">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  )
}
