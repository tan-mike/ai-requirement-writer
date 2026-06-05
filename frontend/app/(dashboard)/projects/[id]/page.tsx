'use client'
import { use, useEffect, useState, useCallback } from 'react'
import Link from 'next/link'
import { apiClient } from '@/lib/api'

interface Project {
  id: number
  name: string
  description?: string
  type: string
  status: string
  project_contexts?: { id: number, name: string }[]
}

interface Feature {
  id: number
  name: string
  description?: string
  status: string
  created_at: string
}

interface ProjectContext {
  id: number
  name: string
}

export default function ProjectDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params)
  const [project, setProject] = useState<Project | null>(null)
  const [features, setFeatures] = useState<Feature[]>([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  // Context Management State
  const [allContexts, setAllContexts] = useState<ProjectContext[]>([])
  const [selectedContextIds, setSelectedContextIds] = useState<number[]>([])
  const [isManagingContexts, setIsManagingContexts] = useState(false)
  const [savingContexts, setSavingContexts] = useState(false)

  const loadData = useCallback(async () => {
    try {
      const [projRes, featRes, ctxRes] = await Promise.all([
        apiClient.get<{ data: Project }>(`/projects/${id}`),
        apiClient.get<{ data: Feature[] }>(`/projects/${id}/features`),
        apiClient.get<ProjectContext[]>('/contexts')
      ])
      setProject(projRes.data)
      setFeatures(featRes.data)
      setAllContexts(ctxRes)
      setSelectedContextIds(projRes.data.project_contexts?.map(c => c.id) ?? [])
      setLoading(false)
    } catch {
      setLoadError('Failed to load project details')
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    let interval: NodeJS.Timeout
    
    if (project?.status === 'processing') {
      interval = setInterval(loadData, 5000)
    }

    return () => {
      if (interval) clearInterval(interval)
    }
  }, [project?.status, loadData])

  useEffect(() => {
    loadData()
  }, [loadData])

  const toggleContextId = (ctxId: number) => {
    setSelectedContextIds(prev => prev.includes(ctxId) ? prev.filter(i => i !== ctxId) : [...prev, ctxId])
  }

  const handleUpdateContexts = async () => {
    setSavingContexts(true)
    try {
      await apiClient.patch(`/projects/${id}`, { context_ids: selectedContextIds })
      setIsManagingContexts(false)
      await loadData()
    } catch (err) {
      console.error(err)
      alert('Failed to update project contexts')
    } finally {
      setSavingContexts(false)
    }
  }

  if (loadError) return (
    <div className="card border-red-200 bg-red-50 dark:bg-red-900/10 dark:border-red-800 p-6 text-center">
      <p className="text-red-700 dark:text-red-400 font-medium">{loadError}</p>
      <Link href="/dashboard" className="text-primary hover:underline mt-4 inline-block font-semibold">Back to Dashboard</Link>
    </div>
  )
  
  if (loading || !project) return (
    <div className="flex flex-col items-center justify-center py-20 text-muted">
      <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
      <p className="font-medium">Loading project details...</p>
    </div>
  )

  const isProcessing = project.status === 'processing'

  return (
    <div className="max-w-4xl pb-20">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>
      
      <div className="flex justify-between items-start mb-8">
        <div className="space-y-1">
          <h1 className="text-4xl font-extrabold tracking-tight text-foreground">{project.name}</h1>
          <p className="text-muted-foreground max-w-2xl">{project.description || 'No description provided.'}</p>
          
          <div className="flex flex-wrap items-center gap-2 pt-4">
              <span className="text-[10px] bg-muted border border-border rounded px-2 py-0.5 font-black uppercase tracking-widest text-muted-foreground">
                  {project.type}
              </span>
              {(project.project_contexts ?? []).map(ctx => (
                  <span key={ctx.id} className="text-[10px] bg-primary/5 border border-primary/20 rounded px-2 py-0.5 font-bold text-primary flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                      {ctx.name}
                  </span>
              ))}
              <button 
                onClick={() => setIsManagingContexts(true)}
                className="text-[10px] font-bold text-muted-foreground hover:text-primary underline transition-colors ml-1"
              >
                {project.project_contexts?.length ? 'Manage Context' : 'Add Project Context'}
              </button>
          </div>
        </div>
        <Link 
            href={`/projects/${id}/features/new`}
            className={`btn-primary flex items-center gap-2 ${isProcessing ? 'opacity-50 pointer-events-none' : ''}`}
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Add Feature
        </Link>
      </div>

      {isManagingContexts && (
          <div className="fixed inset-0 bg-background/80 backdrop-blur-sm z-50 flex items-center justify-center p-6 animate-in fade-in duration-200">
              <div className="bg-card border border-border rounded-2xl p-8 max-w-xl w-full shadow-2xl animate-in zoom-in-95 duration-200">
                  <h3 className="text-xl font-bold mb-2">Manage Project Context</h3>
                  <p className="text-sm text-muted-foreground mb-6">Select baseline documentation that applies to all features in this project.</p>
                  
                  <div className="grid gap-2 max-h-60 overflow-y-auto pr-2 custom-scrollbar mb-8">
                    {allContexts.length > 0 ? allContexts.map(ctx => (
                        <label key={ctx.id} className="flex items-center gap-3 p-4 rounded-xl border border-border bg-surface hover:bg-surface-hover cursor-pointer transition-all group">
                            <input 
                                type="checkbox" 
                                checked={selectedContextIds.includes(ctx.id)} 
                                onChange={() => toggleContextId(ctx.id)} 
                                className="w-5 h-5 rounded text-primary focus:ring-primary border-border bg-background transition-all" 
                            />
                            <span className="text-sm font-bold text-foreground group-hover:text-primary transition-colors">{ctx.name}</span>
                        </label>
                    )) : (
                        <p className="text-sm text-muted-foreground italic text-center py-4">No contexts found. Sync from Google Drive first.</p>
                    )}
                  </div>

                  <div className="flex gap-3">
                      <button onClick={() => setIsManagingContexts(false)} className="btn-secondary flex-1 py-3">Cancel</button>
                      <button 
                        onClick={handleUpdateContexts} 
                        disabled={savingContexts}
                        className="btn-primary flex-1 py-3 flex items-center justify-center gap-2"
                      >
                          {savingContexts ? (
                              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                          ) : 'Save Changes'}
                      </button>
                  </div>
              </div>
          </div>
      )}

      {isProcessing && (
          <div className="card mb-12 bg-primary/5 border-primary/20 p-8 text-center animate-pulse">
              <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
              <h3 className="text-lg font-bold text-foreground mb-1">Analyzing Project Context</h3>
              <p className="text-sm text-muted-foreground max-w-md mx-auto leading-relaxed">
                  We are currently analyzing your repository or files to provide baseline knowledge for all features. This usually takes about a minute.
              </p>
          </div>
      )}

      <div className={`space-y-6 ${isProcessing ? 'opacity-50 pointer-events-none grayscale select-none' : ''}`}>
        <h2 className="text-sm font-bold uppercase tracking-widest text-muted-foreground">Product Features & Enhancements</h2>
        
        {features.length === 0 ? (
            <div className="card flex flex-col items-center justify-center py-20 text-center border-dashed">
                <div className="w-16 h-16 bg-surface-hover rounded-full flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-muted-foreground"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3 className="text-lg font-semibold text-foreground">No features yet</h3>
                <p className="text-muted-foreground mt-1 max-w-xs mx-auto">Start by adding your first feature or enhancement to this project.</p>
                <Link href={`/projects/${id}/features/new`} className="btn-primary mt-6">Add first feature</Link>
            </div>
        ) : (
            <div className="grid gap-4">
                {features.map(feature => (
                    <Link 
                        key={feature.id}
                        href={`/projects/${id}/features/${feature.id}`}
                        className="flex items-center justify-between p-6 rounded-2xl border border-border bg-card hover:border-primary/30 hover:shadow-lg transition-all group"
                    >
                        <div className="flex items-center gap-6">
                            <div className={`w-12 h-12 rounded-xl flex items-center justify-center font-bold text-lg ${
                                feature.status === 'ready' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-primary/10 text-primary'
                            }`}>
                                {feature.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 className="font-bold text-lg text-foreground group-hover:text-primary transition-colors">{feature.name}</h4>
                                <div className="flex items-center gap-3 mt-1">
                                    <span className="text-[10px] bg-surface-hover border border-border rounded px-1.5 py-0.5 font-bold uppercase tracking-wider text-muted-foreground">
                                        {feature.status}
                                    </span>
                                    <span className="text-xs text-muted-foreground">Created {new Date(feature.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-4">
                            <span className="text-sm font-medium text-primary opacity-0 group-hover:opacity-100 transition-opacity">View Requirements</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="text-muted group-hover:translate-x-1 transition-transform"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </div>
                    </Link>
                ))}
            </div>
        )}
      </div>
    </div>
  )
}
