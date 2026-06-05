'use client'
import { useState, useEffect } from 'react'
import { apiClient } from '@/lib/api'
import { useAuth } from '@/lib/auth'

interface ProjectContext {
  id: number
  name: string
  content: string
  type: string
  user_id: number
  team_id: number | null
  created_at: string
}

interface Audit {
  id: number
  user: { name: string }
  event: string
  old_values: any
  new_values: any
  created_at: string
}

interface Team {
  id: number
  name: string
}

export default function ProjectContextManager() {
  const { user } = useAuth()
  const [contexts, setContexts] = useState<ProjectContext[]>([])
  const [teams, setTeams] = useState<Team[]>([])
  const [loading, setLoading] = useState(false)
  
  // Edit State
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editName, setEditName] = useState('')
  const [editContent, setEditContent] = useState('')
  const [editIsTeamShared, setEditIsTeamShared] = useState(false)
  const [editTeamId, setEditTeamId] = useState<number | null>(null)

  // History State
  const [history, setHistory] = useState<Audit[]>([])
  const [showHistory, setShowHistory] = useState<number | null>(null)

  const loadContexts = async () => {
    const data = await apiClient.get<ProjectContext[]>('/contexts')
    setContexts(data)
  }

  const loadTeams = async () => {
    try {
      const res = await apiClient.get<{ data: Team[] }>('/teams')
      setTeams(res.data)
    } catch (err) {
      console.error('Failed to load teams', err)
    }
  }

  useEffect(() => { 
    loadContexts()
    loadTeams()
  }, [])

  const handleUpdate = async (id: number) => {
    setLoading(true)
    try {
      const teamId = editIsTeamShared ? (editTeamId || user?.current_team_id) : null
      await apiClient.patch(`/contexts/${id}`, { 
        name: editName,
        content: editContent,
        team_id: teamId,
        is_team_shared: !!teamId
      })
      setEditingId(null)
      await loadContexts()
    } finally {
      setLoading(false)
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this context?')) return
    await apiClient.delete(`/contexts/${id}`)
    await loadContexts()
  }

  const startEditing = (ctx: ProjectContext) => {
    setEditingId(ctx.id)
    setEditName(ctx.name)
    setEditContent(ctx.content)
    setEditIsTeamShared(!!ctx.team_id)
    setEditTeamId(ctx.team_id)
  }

  const loadHistory = async (ctx: ProjectContext) => {
    setShowHistory(ctx.id)
    const res = await apiClient.get<{ data: Audit[] }>(`/contexts/${ctx.id}/history`)
    setHistory(res.data)
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold">Project Contexts</h2>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {contexts.map(ctx => (
          <div key={ctx.id} className="border border-border rounded-xl p-5 bg-card hover:shadow-md transition-shadow group relative">
            <div className="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
              {ctx.team_id && (
                <button onClick={() => loadHistory(ctx)} className="text-muted-foreground hover:text-indigo-600 p-1 rounded hover:bg-muted" title="Audit History">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
                </button>
              )}
              {(ctx.user_id === user?.id || ctx.team_id === user?.current_team_id) && (
                <button onClick={() => startEditing(ctx)} className="text-muted-foreground hover:text-blue-600 p-1 rounded hover:bg-muted" title="Edit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
              )}
              {ctx.user_id === user?.id && (
                <button onClick={() => handleDelete(ctx.id)} className="text-muted-foreground hover:text-red-600 p-1 rounded hover:bg-muted" title="Delete">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                </button>
              )}
            </div>
            <div className="flex items-center gap-2 mb-1">
                <h3 className="font-bold pr-16">{ctx.name}</h3>
                {ctx.team_id ? (
                    <span className="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded border border-indigo-100 font-bold uppercase tracking-wider">Team Shared</span>
                ) : (
                    <span className="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200 font-bold uppercase tracking-wider">Private</span>
                )}
            </div>
            <p className="text-[10px] uppercase font-bold tracking-widest text-muted-foreground mb-4">Added {new Date(ctx.created_at).toLocaleDateString()}</p>
            
            {editingId === ctx.id ? (
              <div className="space-y-3 animate-in fade-in zoom-in-95 duration-200">
                <div>
                    <label className="block text-[10px] font-black uppercase tracking-widest text-muted-foreground mb-1">Context Name</label>
                    <input className="input text-xs" value={editName} onChange={e => setEditName(e.target.value)} />
                </div>
                <div>
                    <label className="block text-[10px] font-black uppercase tracking-widest text-muted-foreground mb-1">Content</label>
                    <textarea className="w-full bg-background border border-input rounded-lg px-3 py-3 text-xs font-mono focus:ring-2 focus:ring-primary/20 outline-none" rows={8} value={editContent} onChange={e => setEditContent(e.target.value)} />
                </div>

                {ctx.user_id === user?.id && teams.length > 0 && (
                    <div className="flex items-center justify-between p-2 bg-muted/50 rounded-lg">
                        <div className="flex flex-col">
                            <span className="text-[10px] font-black text-muted-foreground uppercase tracking-widest">Team Visibility</span>
                            {editIsTeamShared && teams.length > 1 ? (
                                <select 
                                    value={editTeamId || user?.current_team_id || ''} 
                                    onChange={e => setEditTeamId(Number(e.target.value))}
                                    className="text-[9px] font-bold bg-transparent border-none p-0 outline-none text-indigo-600"
                                >
                                    {teams.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                                </select>
                            ) : (
                                <span className="text-[9px] text-indigo-600 font-bold">
                                    {editIsTeamShared ? (teams.find(t => t.id === (editTeamId || user?.current_team_id))?.name || 'Shared') : 'Private'}
                                </span>
                            )}
                        </div>
                        <button 
                            type="button"
                            onClick={() => setEditIsTeamShared(!editIsTeamShared)}
                            className={`relative inline-flex h-5 w-10 items-center rounded-full transition-colors focus:outline-none ${editIsTeamShared ? 'bg-indigo-600' : 'bg-slate-400'}`}
                        >
                            <span className={`inline-block h-3 w-3 transform rounded-full bg-white transition-transform ${editIsTeamShared ? 'translate-x-6' : 'translate-x-1'}`} />
                        </button>
                    </div>
                )}

                <div className="flex gap-2">
                  <button onClick={() => handleUpdate(ctx.id)} disabled={loading} className="bg-blue-600 text-white px-3 py-1.5 rounded text-xs font-bold hover:bg-blue-700 disabled:opacity-50">Save Changes</button>
                  <button onClick={() => setEditingId(null)} className="bg-muted text-muted-foreground px-3 py-1.5 rounded text-xs font-bold hover:bg-muted-foreground hover:text-white">Cancel</button>
                </div>
              </div>
            ) : (
              <div className="text-xs text-muted-foreground bg-surface-hover/50 p-3 rounded-lg line-clamp-4 font-mono leading-relaxed border border-border/50">
                {ctx.content}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* History Modal */}
      {showHistory && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-6 animate-in fade-in duration-300">
          <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={() => setShowHistory(null)} />
          <div className="relative w-full max-w-lg bg-surface border border-primary/20 rounded-3xl shadow-2xl p-8 space-y-6 overflow-hidden max-h-[80vh] flex flex-col">
            <h2 className="text-2xl font-black tracking-tight">Change History</h2>
            <div className="flex-1 overflow-y-auto pr-2 space-y-6">
                {history.length > 0 ? history.map(audit => (
                    <div key={audit.id} className="relative pl-6 border-l-2 border-border pb-6 last:pb-0">
                        <div className="absolute -left-[9px] top-0 w-4 h-4 bg-background border-2 border-primary rounded-full" />
                        <p className="text-sm font-bold">{audit.user.name}</p>
                        <p className="text-xs text-muted-foreground uppercase font-bold tracking-widest mt-0.5">{new Date(audit.created_at).toLocaleString()}</p>
                        <div className="mt-3 p-3 bg-muted rounded-xl text-[11px] font-mono whitespace-pre-wrap">
                            {audit.new_values.content ? 'Content updated' : audit.new_values.team_id !== undefined ? 'Visibility changed' : 'Resource modified'}
                        </div>
                    </div>
                )) : (
                    <p className="text-sm text-muted-foreground italic text-center py-10">No history found for this resource.</p>
                )}
            </div>
            <button onClick={() => setShowHistory(null)} className="btn-primary w-full py-4">Close</button>
          </div>
        </div>
      )}
    </div>
  )
}
