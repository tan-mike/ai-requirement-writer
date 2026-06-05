import type { Metadata } from 'next'
import { Inter } from 'next/font/google'
import './globals.css'
import { AuthProvider } from '@/lib/auth'
import { NotificationProvider } from '@/lib/notifications'

const inter = Inter({ subsets: ['latin'] })

export const metadata: Metadata = {
  title: 'AI Requirement Writer',
  description: 'Generate requirements from raw ideas',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className={`${inter.className} overflow-x-hidden`}>
        <NotificationProvider>
          <AuthProvider>{children}</AuthProvider>
        </NotificationProvider>
      </body>
    </html>
  )
}
