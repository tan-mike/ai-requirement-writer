'use client'
import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { apiClient } from '@/lib/api'
import { useAuth } from '@/lib/auth'

interface ProjectContext { id: number; name: string }

interface ProjectPayload {
  name: string
  description?: string
  type: string
  is_team_shared: boolean
  repository_url?: string
  repository_path?: string
  context_ids: number[]
}

export default function NewProjectPage() {
  const { user, settings, loading: authLoading } = useAuth()
  const router = useRouter()
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [type, setType] = useState('webapp')
  const [isTeamShared, setIsTeamShared] = useState(false)
  const [repoUrl, setRepoUrl] = useState('')
  const [repoPath, setRepoPath] = useState('')
  const [savedContexts, setSavedContexts] = useState<ProjectContext[]>([])
  const [selectedContextIds, setSelectedContextIds] = useState<number[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!authLoading && user && settings) {
      if (settings.creation_disabled && user.role !== 'admin') {
        router.push('/dashboard')
      }
    }
  }, [user, settings, authLoading, router])

  useEffect(() => {
    apiClient.get<ProjectContext[]>('/contexts').then(setSavedContexts).catch(console.error)
  }, [])

  if (authLoading || (user?.role !== 'admin' && !settings)) {
    return <div className="min-h-[50vh] flex items-center justify-center text-muted">Checking permissions...</div>
  }

  const toggleContextId = (id: number) => {
    setSelectedContextIds(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id])
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!name) { setError('Enter a project name'); return }
    setSubmitting(true)
    setError('')

    try {
      const payload: ProjectPayload = {
        name,
        description,
        type,
        is_team_shared: isTeamShared,
        repository_url: repoUrl || undefined,
        repository_path: repoPath || undefined,
        context_ids: selectedContextIds,
      }

      const res = await apiClient.post<{ data: { id: number } }>('/projects', payload)
      router.push(`/projects/${res.data.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create project')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="max-w-3xl mx-auto">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>

      <div className="card p-10">
        <div className="mb-10 text-center">
          <h1 className="text-3xl font-bold tracking-tight text-foreground">Create New Product</h1>
          <p className="text-muted-foreground mt-2">Create a container to manage your product requirements and features.</p>
        </div>

        {error && (
          <div role="alert" aria-live="polite" className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-8">
            <p className="text-red-700 dark:text-red-400 text-sm font-semibold">{error}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-8">
          <div className="space-y-6">
            <div className="space-y-1">
              <label htmlFor="project-name" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Product Name</label>
              <input id="project-name" type="text" value={name} onChange={e => setName(e.target.value)}
                placeholder="e.g. Customer Portal" className="input text-base" required />
            </div>

            <div className="space-y-1">
              <label htmlFor="project-description" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Description (Optional)</label>
              <textarea id="project-description" value={description} onChange={e => setDescription(e.target.value)}
                placeholder="What is this product about?" className="w-full bg-surface border-2 border-border rounded-xl px-4 py-3 text-sm focus:border-primary transition-all outline-none" rows={3} />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div className="space-y-1">
                    <label htmlFor="project-type" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Primary Type</label>
                    <select 
                        id="project-type"
                        value={type}
                        onChange={e => setType(e.target.value)}
                        className="w-full bg-surface border-2 border-border rounded-xl px-4 py-2.5 text-sm font-bold focus:border-primary transition-all outline-none"
                    >
                        <option value="webapp">Web Application</option>
                        <option value="mobile">Mobile App</option>
                        <option value="api">API Service</option>
                        <option value="data">Data Platform</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>

                <div className="flex items-center justify-between p-4 bg-muted/20 rounded-xl border border-border/50">
                    <div className="space-y-0.5">
                        <h3 className="text-sm font-bold">Share with Team</h3>
                        <p className="text-[10px] text-muted-foreground">Allow team members to view and add features.</p>
                    </div>
                    <button 
                        type="button"
                        onClick={() => setIsTeamShared(!isTeamShared)}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none ${isTeamShared ? 'bg-primary' : 'bg-muted'}`}
                    >
                        <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${isTeamShared ? 'translate-x-6' : 'translate-x-1'}`} />
                    </button>
                </div>
            </div>
          </div>

          <div className="pt-6 border-t border-border space-y-6">
            <h3 className="text-sm font-bold uppercase tracking-widest text-foreground">Codebase Context (Optional)</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-1">
                    <label htmlFor="repo-url" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">GitHub Repository URL</label>
                    <input id="repo-url" type="url" value={repoUrl} onChange={e => setRepoUrl(e.target.value)}
                        placeholder="https://github.com/org/repo" className="input" />
                </div>
                <div className="space-y-1">
                    <label htmlFor="repo-path" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Local Path</label>
                    <input id="repo-path" type="text" value={repoPath} onChange={e => setRepoPath(e.target.value)}
                        placeholder="/Users/mike/projects/app" className="input" />
                </div>
            </div>
            <p className="text-[10px] text-muted-foreground italic leading-relaxed">
                If provided, we will analyze your existing codebase to provide better requirement context.
            </p>
          </div>

          <div className="pt-6 border-t border-border space-y-6">
            <h3 className="text-sm font-bold uppercase tracking-widest text-foreground">Product Context (Optional)</h3>
            <div className="grid gap-3 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                {savedContexts.length > 0 ? savedContexts.map(ctx => (
                    <label key={ctx.id} className="flex items-center gap-3 p-4 rounded-xl border border-border bg-surface hover:bg-surface-hover cursor-pointer transition-all group">
                        <input 
                            type="checkbox" 
                            checked={selectedContextIds.includes(ctx.id)} 
                            onChange={() => toggleContextId(ctx.id)} 
                            className="w-5 h-5 rounded text-primary focus:ring-primary border-border bg-background transition-all" 
                        />
                        <div className="flex flex-col">
                            <span className="text-sm font-bold text-foreground group-hover:text-primary transition-colors">{ctx.name}</span>
                        </div>
                    </label>
                )) : (
                    <div className="py-8 text-center border-2 border-dashed border-border rounded-2xl bg-muted/10">
                        <p className="text-sm text-muted-foreground italic">No saved contexts found. Contexts are synced from Google Drive.</p>
                    </div>
                )}
            </div>
            <p className="text-[10px] text-muted-foreground italic leading-relaxed">
                Select baseline documentation or specifications that should apply to all features in this product.
            </p>
          </div>

          <div className="pt-6">
            <button type="submit" disabled={submitting}
              className="btn-primary w-full py-4 text-base shadow-lg shadow-primary/20">
              {submitting ? 'Creating container...' : 'Create product'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
