'use client'
import { useAuth } from '@/lib/auth'
import { useRouter, usePathname } from 'next/navigation'
import { useEffect } from 'react'
import Link from 'next/link'

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth()
  const router = useRouter()
  const pathname = usePathname()

  useEffect(() => {
    if (!loading && !user) router.push('/login')
  }, [user, loading, router])

  if (loading) return <div className="min-h-screen flex items-center justify-center text-gray-500">Loading…</div>
  if (!user) return null

  return (
    <div className="min-h-screen bg-background">
      <nav className="bg-surface border-b border-border sticky top-0 z-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16 items-center">
            <div className="flex items-center gap-8">
              <Link href="/dashboard" className="flex items-center gap-2">
                <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-white font-bold">A</div>
                <span className="font-bold text-lg tracking-tight text-foreground">AI Requirement Writer</span>
              </Link>
              <div className="hidden md:flex items-center gap-1">
                <Link 
                  href="/dashboard" 
                  className={`px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                    pathname === '/dashboard' 
                      ? 'text-primary bg-primary/5' 
                      : 'text-muted hover:text-foreground hover:bg-surface-hover'
                  }`}
                >
                  Projects
                </Link>
                <Link 
                  href="/settings" 
                  className={`px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                    pathname === '/settings' 
                      ? 'text-primary bg-primary/5' 
                      : 'text-muted hover:text-foreground hover:bg-surface-hover'
                  }`}
                >
                  Settings
                </Link>
              </div>
            </div>
            <div className="flex items-center gap-6">
              <div className="hidden sm:flex flex-col items-end">
                <span className="text-sm font-semibold text-foreground">{user.name}</span>
                <span className="text-xs text-muted-foreground">{user.email}</span>
              </div>
              <button 
                onClick={logout} 
                className="text-sm font-medium text-muted hover:text-primary transition-colors px-3 py-1.5 rounded-md hover:bg-surface-hover"
              >
                Sign out
              </button>
            </div>
          </div>
        </div>
      </nav>
      <main className="max-w-5xl mx-auto px-6 py-10">
        <div className="animate-in fade-in slide-in-from-bottom-2 duration-500">
          {children}
        </div>
      </main>
    </div>
  )
}
