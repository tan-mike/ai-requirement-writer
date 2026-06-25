'use client'

import { useState } from 'react'
import { useAuth } from '@/lib/auth'

export default function CheckEmailPage() {
  const { resendVerification } = useAuth()
  const [resent, setResent] = useState(false)
  const [resendError, setResendError] = useState('')

  const email = typeof window !== 'undefined'
    ? sessionStorage.getItem('pending_email') || ''
    : ''

  async function handleResend() {
    if (!email) {
      setResendError('No email address found. Please try registering or logging in again.')
      return
    }
    try {
      setResendError('')
      await resendVerification(email)
      setResent(true)
    } catch {
      setResendError('Failed to resend verification email.')
    }
  }

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10 text-center">
      <div className="mb-6">
        <div className="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-4">
          <svg className="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <h1 className="text-2xl font-bold text-foreground">Check your email</h1>
        <p className="text-muted-foreground mt-2">
          A verification link has been sent to your email address. Please check your inbox and click the link to verify your account.
        </p>
      </div>

      {resent && (
        <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-3 mb-4">
          <p className="text-green-700 dark:text-green-400 text-sm font-medium">
            Verification email resent! Check your inbox.
          </p>
        </div>
      )}

      {resendError && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mb-4">
          <p className="text-red-700 dark:text-red-400 text-sm font-medium">{resendError}</p>
        </div>
      )}

      <button
        onClick={handleResend}
        className="text-primary font-bold hover:underline underline-offset-4 text-sm"
      >
        Didn&apos;t receive the email? Resend verification link
      </button>
    </div>
  )
}
