'use client'
import { useEffect, useState } from 'react'
import Link from 'next/link'
import { apiClient } from '@/lib/api'
import { ProjectCard } from '@/components/ProjectCard'

interface Project {
  id: number; name: string; type: string; status: string; mode: string; created_at: string
}

export default function DashboardPage() {
  const [projects, setProjects] = useState<Project[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    apiClient.get<{ data: Project[] }>('/projects')
      .then(res => setProjects(res.data))
      .catch(console.error)
      .finally(() => setLoading(false))
  }, [])

  return (
    <div>
      <div className="flex justify-between items-end mb-8">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-foreground">Projects</h1>
          <p className="text-muted-foreground mt-1">Manage and view your requirement generation projects.</p>
        </div>
        <Link href="/projects/new" className="btn-primary flex items-center gap-2">
          <span>New project</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        </Link>
      </div>
      
      {loading && (
        <div className="flex flex-col items-center justify-center py-20 text-muted">
          <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
          <p className="font-medium">Loading projects...</p>
        </div>
      )}
      
      {!loading && projects.length === 0 && (
        <div className="card flex flex-col items-center justify-center py-20 text-center">
          <div className="w-16 h-16 bg-surface-hover rounded-full flex items-center justify-center mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-muted-foreground"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
          </div>
          <h2 className="text-xl font-semibold text-foreground">No projects yet</h2>
          <p className="text-muted-foreground mt-2 max-w-xs mx-auto">Create your first project to start generating high-quality requirements.</p>
          <Link href="/projects/new" className="btn-primary mt-6">Create first project</Link>
        </div>
      )}
      
      <div className="grid gap-6 md:grid-cols-2">
        {projects.map(p => <ProjectCard key={p.id} project={p} />)}
      </div>
    </div>
  )
}
