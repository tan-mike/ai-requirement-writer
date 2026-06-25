'use client'

import { useEffect, useState, Suspense } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { useAuth } from '@/lib/auth'

function VerifyEmailContent() {
  const { verifyEmail } = useAuth()
  const router = useRouter()
  const searchParams = useSearchParams()
  const [error, setError] = useState('')
  const [verifying, setVerifying] = useState(true)

  useEffect(() => {
    const id = searchParams.get('id')
    const hash = searchParams.get('hash')
    const expires = searchParams.get('expires')
    const signature = searchParams.get('signature')

    if (!id || !hash || !expires || !signature) {
      setError('Invalid verification link.')
      setVerifying(false)
      return
    }

    verifyEmail(id, hash, expires, signature)
      .then(() => {
        sessionStorage.removeItem('pending_email')
        router.push('/login?verified=1')
      })
      .catch((err: Error) => {
        setError(err.message || 'Verification failed. The link may have expired.')
      })
      .finally(() => setVerifying(false))
  }, [searchParams])

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10 text-center">
      {verifying && (
        <>
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4" />
          <p className="text-muted-foreground">Verifying your email...</p>
        </>
      )}

      {!verifying && !error && (
        <>
          <div className="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mb-4">
            <svg className="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-foreground">Email verified!</h1>
          <p className="text-muted-foreground mt-2">Redirecting to login...</p>
        </>
      )}

      {!verifying && error && (
        <>
          <div className="mx-auto w-16 h-16 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-foreground">Verification failed</h1>
          <p className="text-red-600 dark:text-red-400 mt-2 text-sm">{error}</p>
        </>
      )}
    </div>
  )
}

export default function VerifyEmailPage() {
  return (
    <Suspense fallback={<div className="card p-10 text-center"><p>Loading...</p></div>}>
      <VerifyEmailContent />
    </Suspense>
  )
}
