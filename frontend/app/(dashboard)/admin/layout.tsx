'use client'
import { useAuth } from '@/lib/auth'
import { useRouter, usePathname } from 'next/navigation'
import { useEffect } from 'react'
import Link from 'next/link'

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  const router = useRouter()
  const pathname = usePathname()

  useEffect(() => {
    if (!loading && (!user || user.role !== 'admin')) {
      router.push('/dashboard')
    }
  }, [user, loading, router])

  if (loading || !user || user.role !== 'admin') return null

  return (
    <div className="flex gap-8">
      <aside className="w-64 flex-shrink-0">
        <nav className="flex flex-col gap-2">
          <Link 
            href="/admin/projects" 
            className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
              pathname.startsWith('/admin/projects') || pathname === '/admin'
                ? 'bg-primary/10 text-primary' 
                : 'text-muted hover:bg-surface-hover hover:text-foreground'
            }`}
          >
            Products
          </Link>
          <Link 
            href="/admin/settings" 
            className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
              pathname.startsWith('/admin/settings') 
                ? 'bg-primary/10 text-primary' 
                : 'text-muted hover:bg-surface-hover hover:text-foreground'
            }`}
          >
            Global Settings
          </Link>
        </nav>
      </aside>
      <div className="flex-1 bg-surface border border-border rounded-xl shadow-sm p-8 min-h-[500px]">
        {children}
      </div>
    </div>
  )
}
