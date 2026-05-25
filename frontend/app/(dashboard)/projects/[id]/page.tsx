'use client'
import { use, useEffect, useState } from 'react'
import Link from 'next/link'
import { apiClient } from '@/lib/api'

interface Project {
  id: number
  name: string
  type: string
  status: string
  mode: string
  template?: { name: string }
}

interface Draft {
  id: number
  type: 'brd' | 'stories' | 'spec'
  status: string
  updated_at: string
}

export default function ProjectDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params)
  const [project, setProject] = useState<Project | null>(null)
  const [drafts, setDrafts] = useState<Record<string, Draft[]>>({})
  const [loadError, setLoadError] = useState('')

  useEffect(() => {
    Promise.all([
      apiClient.get<{ data: Project }>(`/projects/${id}`),
      apiClient.get<{ data: Record<string, Draft[]> }>(`/projects/${id}/drafts`)
    ]).then(([projRes, draftRes]) => {
      setProject(projRes.data)
      setDrafts(draftRes.data)
    }).catch(() => setLoadError('Failed to load project details'))
  }, [id])

  if (loadError) return (
    <div className="card border-red-200 bg-red-50 dark:bg-red-900/10 dark:border-red-800 p-6 text-center">
      <p className="text-red-700 dark:text-red-400 font-medium">{loadError}</p>
      <Link href="/dashboard" className="text-primary hover:underline mt-4 inline-block font-semibold">Back to Dashboard</Link>
    </div>
  )
  
  if (!project) return (
    <div className="flex flex-col items-center justify-center py-20 text-muted">
      <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
      <p className="font-medium">Loading project details...</p>
    </div>
  )

  return (
    <div className="max-w-3xl">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>
      
      <div className="card p-8">
        <div className="flex justify-between items-start mb-6">
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">{project.name}</h1>
            <div className="flex items-center gap-3 mt-2">
              <span className="text-sm font-medium text-muted uppercase tracking-wider">{project.type}</span>
              <span className="w-1 h-1 bg-muted-foreground/30 rounded-full"></span>
              <span className="text-sm text-muted-foreground">{project.template?.name ?? 'Standard Template'}</span>
            </div>
          </div>
          <span className="px-3 py-1 bg-surface-hover border border-border rounded-full text-xs font-bold uppercase tracking-widest text-muted">
            {project.status.replace('_', ' ')}
          </span>
        </div>

        <div className="bg-background/50 border border-border rounded-xl p-6 mt-8">
          <h2 className="text-lg font-bold text-foreground mb-3">Requirement Generation</h2>
          <p className="text-muted-foreground mb-6 text-balance leading-relaxed">
            {project.mode === 'conversational' 
              ? "Use our interactive AI interview to extract deep requirements from your raw ideas. The AI will guide you through the process."
              : "Quickly fill out a structured intake form to provide the necessary context for requirement generation."}
          </p>
          
          <div className="flex flex-wrap gap-4">
            {project.mode === 'conversational' ? (
              <Link
                href={`/projects/${id}/chat`}
                className="btn-primary inline-flex items-center gap-2 px-8"
              >
                Start AI Interview
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M7 7l5 5-5 5"/><path d="M13 7l5 5-5 5"/></svg>
              </Link>
            ) : (
              <Link
                href={`/projects/${id}/intake`}
                className="btn-primary inline-flex items-center gap-2 px-8"
              >
                Fill Intake Form
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              </Link>
            )}

            {Object.keys(drafts).length > 0 && (
              <Link
                href={`/projects/${id}/generate`}
                className="btn-secondary inline-flex items-center gap-2 px-8"
              >
                View Requirements
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
              </Link>
            )}
          </div>
        </div>

        {Object.keys(drafts).length > 0 && (
          <div className="mt-12">
            <h2 className="text-sm font-bold text-foreground uppercase tracking-wider mb-6">Generated Content</h2>
            <div className="grid gap-4">
              {(['brd', 'stories', 'spec'] as const).map(type => {
                const latest = drafts[type]?.at(0)
                if (!latest) return null

                return (
                  <Link 
                    key={type}
                    href={`/projects/${id}/generate`}
                    className="flex items-center justify-between p-4 rounded-xl border border-border bg-surface hover:bg-surface-hover transition-colors group"
                  >
                    <div className="flex items-center gap-4">
                      <div className={`p-2 rounded-lg ${
                        latest.status === 'approved' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-primary/10 text-primary'
                      }`}>
                        {type === 'brd' && <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>}
                        {type === 'stories' && <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>}
                        {type === 'spec' && <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>}
                      </div>
                      <div>
                        <div className="font-bold text-foreground flex items-center gap-2">
                          <span className="uppercase">{type}</span>
                          {latest.status === 'approved' && (
                            <span className="text-[10px] bg-emerald-500/10 text-emerald-600 px-1.5 py-0.5 rounded-md border border-emerald-500/20">Approved</span>
                          )}
                        </div>
                        <p className="text-xs text-muted-foreground mt-0.5">Last updated {new Date(latest.updated_at).toLocaleDateString()}</p>
                      </div>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="text-muted opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                  </Link>
                )
              })}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
