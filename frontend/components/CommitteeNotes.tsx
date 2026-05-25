'use client'
import { useState, useEffect } from 'react'
import { createPortal } from 'react-dom'
import ReactMarkdown from 'react-markdown'

interface CommitteeNotesProps {
  critiques: {
    id: number
    persona: { name: string }
    content: string
    status: string
  }[]
}

export default function CommitteeNotes({ critiques }: CommitteeNotesProps) {
  const [isOpen, setIsOpen] = useState(false)
  const roles = critiques.map(c => c.persona.name)
  const [activeTab, setActiveTab] = useState(roles[0])
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
    return () => setMounted(false)
  }, [])

  if (roles.length === 0) return null

  const modalContent = (
    <div className="fixed inset-0 z-[1000] bg-background/80 backdrop-blur-sm animate-in fade-in duration-200">
      <div 
        className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-surface border border-border rounded-xl shadow-2xl w-[95vw] max-w-4xl h-[90vh] flex flex-col overflow-hidden animate-in fade-in duration-300"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-6 border-b flex items-center justify-between bg-surface shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary text-primary-foreground rounded-lg">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/></svg>
            </div>
            <div>
              <h3 className="text-xl font-bold text-foreground">Committee Review Notes</h3>
              <p className="text-sm text-muted-foreground">Expert feedback to refine and finalize your requirements.</p>
            </div>
          </div>
          <button
            onClick={() => setIsOpen(false)}
            className="p-2 hover:bg-surface-hover rounded-full transition-colors text-muted-foreground hover:text-foreground"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>

        {/* Tabs - Fixed height and styling to prevent overlap */}
        <div className="flex border-b overflow-x-auto no-scrollbar bg-surface-hover/30 shrink-0 sticky top-0 z-10 shadow-sm">
          {roles.map((role) => (
            <button
              key={role}
              onClick={() => setActiveTab(role)}
              className={`px-6 py-4 text-sm font-bold whitespace-nowrap transition-all border-b-2 relative ${
                activeTab === role
                  ? 'border-primary text-primary bg-surface shadow-[inset_0_-2px_0_0_var(--color-primary)]'
                  : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-surface-hover/50'
              }`}
            >
              {role}
            </button>
          ))}
        </div>

        {/* Content - Ensure it stays below the tabs */}
        <div className="flex-1 overflow-y-auto p-8 prose dark:prose-invert max-w-none bg-surface">
           <ReactMarkdown>{critiques.find(c => c.persona.name === activeTab)?.content || ''}</ReactMarkdown>
        </div>

        {/* Footer */}
        <div className="p-4 border-t bg-surface flex justify-end shrink-0">
          <button
            onClick={() => setIsOpen(false)}
            className="btn-primary"
          >
            Close Notes
          </button>
        </div>
      </div>
    </div>
  )

  return (
    <div className="w-full">
      <button
        onClick={() => setIsOpen(true)}
        className="w-full flex items-center justify-between p-4 bg-surface border border-border rounded-lg hover:bg-surface-hover transition-colors group shadow-sm"
      >
        <div className="flex items-center gap-3">
          <div className="flex -space-x-2">
            {roles.slice(0, 3).map((role) => (
              <div
                key={role}
                className="w-8 h-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[10px] font-bold border-2 border-surface"
                title={role}
              >
                {role.substring(0, 2).toUpperCase()}
              </div>
            ))}
            {roles.length > 3 && (
              <div className="w-8 h-8 rounded-full bg-muted-foreground text-primary-foreground flex items-center justify-center text-[10px] font-bold border-2 border-surface">
                +{roles.length - 3}
              </div>
            )}
          </div>
          <div className="text-left">
            <p className="text-sm font-bold text-foreground">Committee Notes</p>
            <p className="text-xs text-muted-foreground">{roles.length} experts provided feedback</p>
          </div>
        </div>
        <div className="flex items-center gap-2 text-primary font-medium text-xs bg-primary/5 px-2 py-1 rounded border border-primary/10 group-hover:bg-primary group-hover:text-primary-foreground transition-all">
          View Notes
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
            <polyline points="15 3 21 3 21 9" />
            <line x1="10" y1="14" x2="21" y2="3" />
          </svg>
        </div>
      </button>

      {isOpen && mounted && createPortal(modalContent, document.body)}
    </div>
  )
}
