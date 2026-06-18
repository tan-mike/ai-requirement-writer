import Link from 'next/link'

export interface Project {
  id: number
  name: string
  description?: string
  category?: string
  type: string
  status: string
  mode: string
  is_curated?: boolean
  created_at: string
}

const statusColors: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
  in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  complete: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
}

export function ProjectCard({ project }: { project: Project }) {
  return (
    <Link
      href={`/projects/${project.id}`}
      className="card block group hover:border-primary/30"
    >
      <div className="flex items-start justify-between mb-2">
        <h3 className="font-semibold text-foreground group-hover:text-primary transition-colors">{project.name}</h3>
        {/* {project.is_curated ? (
          <span className="text-xs px-2 py-1 rounded-full font-semibold uppercase tracking-wider bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
            Curated
          </span>
        ) : (
          <span className={`text-xs px-2 py-1 rounded-full font-semibold uppercase tracking-wider ${statusColors[project.status] ?? 'bg-muted/10 text-muted'}`}>
            {project.status.replace('_', ' ')}
          </span>
        )} */}
      </div>
      
      {project.description && (
        <p className="text-sm text-muted-foreground mb-3 line-clamp-2">{project.description}</p>
      )}

      <div className="flex items-center gap-3">
        {project.category && (
          <span className="text-xs font-medium text-foreground bg-surface-hover px-2 py-0.5 rounded border border-border">
            {project.category}
          </span>
        )}
        <p className="text-sm text-muted font-medium capitalize bg-background px-2 py-0.5 rounded border border-border">{project.type}</p>
        <span className="text-xs text-muted-foreground ml-auto">{new Date(project.created_at).toLocaleDateString()}</span>
      </div>
      
      {project.is_curated && (
        <div className="mt-4 pt-3 border-t border-border flex justify-end">
          <span className="text-sm font-medium text-primary flex items-center gap-1 group-hover:underline">
            Start Requirement <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </span>
        </div>
      )}
    </Link>
  )
}
