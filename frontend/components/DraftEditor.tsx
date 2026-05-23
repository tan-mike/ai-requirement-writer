'use client'
import { useState } from 'react'
import ReactMarkdown from 'react-markdown'
import { apiClient } from '@/lib/api'

interface Props {
  projectId: string
  draft: { id: number; content: string | null; status: string }
  onApproved: () => void
}

export default function DraftEditor({ projectId, draft, onApproved }: Props) {
  const [content, setContent] = useState(draft.content ?? '')
  const [saving, setSaving] = useState(false)
  const [approving, setApproving] = useState(false)
  const [error, setError] = useState('')

  async function handleSave() {
    setSaving(true)
    try {
      await apiClient.patch(`/projects/${projectId}/drafts/${draft.id}`, { content })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  async function handleApprove() {
    setApproving(true)
    try {
      await apiClient.post(`/projects/${projectId}/drafts/${draft.id}/approve`, {})
      onApproved()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Approve failed')
    } finally {
      setApproving(false)
    }
  }

  if (draft.status === 'approved') {
    return (
      <div className="card border-emerald-200 bg-emerald-50/50 dark:bg-emerald-900/10 dark:border-emerald-800 p-6">
        <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 font-bold mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Approved Requirement Draft
        </div>
        <div className="prose prose-sm dark:prose-invert max-w-none break-words break-all bg-surface/50 p-6 rounded-xl border border-emerald-200/50 dark:border-emerald-800/50 shadow-inner">
          <ReactMarkdown>{content}</ReactMarkdown>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {error && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3">
          <p className="text-red-700 dark:text-red-400 text-sm font-medium">{error}</p>
        </div>
      )}
      
      <div className="relative group">
        <textarea
          value={content}
          onChange={e => setContent(e.target.value)}
          rows={15}
          className="w-full max-w-full bg-surface-hover/30 border border-border rounded-xl px-4 py-4 text-sm font-mono resize-y focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all leading-relaxed"
          placeholder="Requirement content..."
        />
        <div className="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
          <span className="text-[10px] font-bold text-muted uppercase tracking-widest bg-surface/80 px-2 py-1 rounded border border-border">Editor</span>
        </div>
      </div>
      
      <div className="flex gap-4">
        <button onClick={handleSave} disabled={saving}
          className="flex-1 px-4 py-2.5 rounded-lg text-sm font-bold border border-border bg-surface hover:bg-surface-hover transition-colors disabled:opacity-50"
        >
          {saving ? 'Saving changes...' : 'Save Draft'}
        </button>
        <button onClick={handleApprove} disabled={approving}
          className="btn-primary flex-1 py-2.5 shadow-lg shadow-primary/20"
        >
          {approving ? 'Approving...' : 'Approve & Finalize'}
        </button>
      </div>
    </div>
  )
}
