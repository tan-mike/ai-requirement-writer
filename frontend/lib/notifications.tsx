'use client'
import { createContext, useContext, useState, ReactNode, useCallback } from 'react'

interface NotificationContextType {
  showAlert: (title: string, message: string) => void
  showConfirm: (title: string, message: string, onConfirm: () => void) => void
}

const NotificationContext = createContext<NotificationContextType | null>(null)

export function NotificationProvider({ children }: { children: ReactNode }) {
  const [alert, setAlert] = useState<{ title: string; message: string } | null>(null)
  const [confirm, setConfirm] = useState<{ title: string; message: string; onConfirm: () => void } | null>(null)

  const showAlert = useCallback((title: string, message: string) => {
    setAlert({ title, message })
  }, [])

  const showConfirm = useCallback((title: string, message: string, onConfirm: () => void) => {
    setConfirm({ title, message, onConfirm })
  }, [])

  return (
    <NotificationContext.Provider value={{ showAlert, showConfirm }}>
      {children}
      
      {/* Alert Modal */}
      {alert && (
        <div className="fixed inset-0 bg-background/80 backdrop-blur-sm z-[100] flex items-center justify-center p-6 animate-in fade-in duration-200">
          <div className="bg-card border border-border rounded-2xl p-8 max-w-sm w-full shadow-2xl animate-in zoom-in-95 duration-200">
            <h3 className="text-xl font-bold mb-2">{alert.title}</h3>
            <p className="text-sm text-muted-foreground mb-8 leading-relaxed">{alert.message}</p>
            <button 
              onClick={() => setAlert(null)}
              className="btn-primary w-full py-3"
            >
              Dismiss
            </button>
          </div>
        </div>
      )}

      {/* Confirm Modal */}
      {confirm && (
        <div className="fixed inset-0 bg-background/80 backdrop-blur-sm z-[100] flex items-center justify-center p-6 animate-in fade-in duration-200">
          <div className="bg-card border border-border rounded-2xl p-8 max-w-sm w-full shadow-2xl animate-in zoom-in-95 duration-200">
            <h3 className="text-xl font-bold mb-2">{confirm.title}</h3>
            <p className="text-sm text-muted-foreground mb-8 leading-relaxed">{confirm.message}</p>
            <div className="flex gap-3">
              <button 
                onClick={() => setConfirm(null)}
                className="btn-secondary flex-1 py-3"
              >
                Cancel
              </button>
              <button 
                onClick={() => {
                  confirm.onConfirm()
                  setConfirm(null)
                }}
                className="btn-primary flex-1 py-3"
              >
                Confirm
              </button>
            </div>
          </div>
        </div>
      )}
    </NotificationContext.Provider>
  )
}

export function useNotifications() {
  const ctx = useContext(NotificationContext)
  if (!ctx) throw new Error('useNotifications must be used inside NotificationProvider')
  return ctx
}
