'use client'
import { useEffect, useState } from 'react'
import { useAuth } from '@/lib/auth'
import { apiClient } from '@/lib/api'

interface Team {
  id: number
  name: string
  code: string
  owner_id: number
}

export default function TeamSwitcher() {
  const { user, switchTeam } = useAuth()
  const [teams, setTeams] = useState<Team[]>([])
  const [showMenu, setShowMenu] = useState(false)
  const [showCreate, setShowCreate] = useState(false)
  const [showJoin, setShowJoin] = useState(false)
  const [newTeamName, setNewName] = useState('')
  const [joinCode, setJoinCode] = useState('')
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (user) {
      apiClient.get<{ data: Team[] }>('/teams').then(res => setTeams(res.data))
    }
  }, [user])

  const currentTeam = teams.find(t => t.id === user?.current_team_id)

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    try {
      const res = await apiClient.post<{ data: Team }>('/teams', { name: newTeamName })
      setTeams([...teams, res.data])
      setShowCreate(false)
      setNewName('')
      switchTeam(res.data.id)
    } finally {
      setLoading(false)
    }
  }

  const handleJoin = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    try {
      const res = await apiClient.post<{ data: Team }>('/teams/join', { code: joinCode })
      setTeams([...teams, res.data])
      setShowJoin(false)
      setJoinCode('')
      switchTeam(res.data.id)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="relative">
      <button 
        onClick={() => setShowMenu(!showMenu)}
        className="flex items-center gap-2 px-4 py-2 bg-surface-hover rounded-xl border border-border hover:border-primary transition-all text-sm font-bold"
      >
        <div className="w-5 h-5 bg-primary/10 text-primary rounded-md flex items-center justify-center text-[10px]">
          {currentTeam ? 'T' : 'P'}
        </div>
        <span className="truncate max-w-[120px]">{currentTeam ? currentTeam.name : 'Personal'}</span>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </button>

      {showMenu && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setShowMenu(false)} />
          <div className="absolute top-full left-0 mt-2 w-64 bg-surface border border-border rounded-2xl shadow-2xl z-50 p-2 animate-in fade-in zoom-in-95 duration-200">
            <button 
              onClick={() => { switchTeam(null); setShowMenu(false); }}
              className={`w-full flex items-center justify-between p-3 rounded-xl text-sm font-bold transition-all ${!currentTeam ? 'bg-primary/5 text-primary' : 'hover:bg-surface-hover'}`}
            >
              <span>Personal Space</span>
              {!currentTeam && <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" className="text-primary"><polyline points="20 6 9 17 4 12"/></svg>}
            </button>
            
            <div className="my-2 border-t border-border" />
            
            <div className="px-3 py-1 text-[10px] font-black text-muted-foreground uppercase tracking-widest">Joined Teams</div>
            {teams.map(team => (
              <button 
                key={team.id}
                onClick={() => { switchTeam(team.id); setShowMenu(false); }}
                className={`w-full flex items-center justify-between p-3 rounded-xl text-sm font-bold transition-all ${currentTeam?.id === team.id ? 'bg-primary/5 text-primary' : 'hover:bg-surface-hover'}`}
              >
                <div className="flex items-center gap-2">
                   <span className="truncate max-w-[150px]">{team.name}</span>
                   {team.owner_id === user?.id && <span className="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded border border-indigo-100">Owner</span>}
                </div>
                {currentTeam?.id === team.id && <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" className="text-primary"><polyline points="20 6 9 17 4 12"/></svg>}
              </button>
            ))}

            <div className="my-2 border-t border-border" />
            
            <div className="grid grid-cols-2 gap-2 p-1">
              <button 
                onClick={() => { setShowCreate(true); setShowMenu(false); }}
                className="flex flex-col items-center justify-center p-3 rounded-xl border border-border bg-surface-hover hover:border-primary transition-all group"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="text-muted-foreground group-hover:text-primary mb-1"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                <span className="text-[10px] font-bold uppercase tracking-wider">Create</span>
              </button>
              <button 
                onClick={() => { setShowJoin(true); setShowMenu(false); }}
                className="flex flex-col items-center justify-center p-3 rounded-xl border border-border bg-surface-hover hover:border-primary transition-all group"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="text-muted-foreground group-hover:text-primary mb-1"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span className="text-[10px] font-bold uppercase tracking-wider">Join</span>
              </button>
            </div>
          </div>
        </>
      )}

      {/* Create Modal */}
      {showCreate && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-6 animate-in fade-in duration-300">
          <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={() => setShowCreate(false)} />
          <form onSubmit={handleCreate} className="relative w-full max-w-sm bg-surface border border-primary/20 rounded-3xl shadow-2xl p-8 space-y-6 animate-in zoom-in-95 duration-300">
            <h2 className="text-2xl font-black tracking-tight">Create a Team</h2>
            <div>
              <label className="text-xs font-bold text-muted-foreground uppercase tracking-widest block mb-2">Team Name</label>
              <input required value={newTeamName} onChange={e => setNewName(e.target.value)} className="input text-base" placeholder="e.g. Acme Engineering" />
            </div>
            <button disabled={loading} type="submit" className="btn-primary w-full py-4 shadow-lg shadow-primary/20">
              {loading ? 'Creating...' : 'Create Team'}
            </button>
          </form>
        </div>
      )}

      {/* Join Modal */}
      {showJoin && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-6 animate-in fade-in duration-300">
          <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={() => setShowJoin(false)} />
          <form onSubmit={handleJoin} className="relative w-full max-w-sm bg-surface border border-primary/20 rounded-3xl shadow-2xl p-8 space-y-6 animate-in zoom-in-95 duration-300">
            <h2 className="text-2xl font-black tracking-tight">Join a Team</h2>
            <div>
              <label className="text-xs font-bold text-muted-foreground uppercase tracking-widest block mb-2">6-Character Team Code</label>
              <input required value={joinCode} onChange={e => setJoinCode(e.target.value.toUpperCase())} maxLength={6} className="input text-center text-3xl font-black tracking-[0.3em]" placeholder="ABC123" />
            </div>
            <button disabled={loading} type="submit" className="btn-primary w-full py-4 shadow-lg shadow-primary/20">
              {loading ? 'Joining...' : 'Join Team'}
            </button>
          </form>
        </div>
      )}

      {/* Display code if just created/current owner */}
      {currentTeam && currentTeam.owner_id === user?.id && showMenu && (
         <div className="mt-2 p-3 bg-indigo-50 border border-indigo-100 rounded-xl">
            <p className="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-1">Share this Code</p>
            <div className="text-lg font-black tracking-widest text-indigo-900">{currentTeam.code}</div>
         </div>
      )}
    </div>
  )
}
