import Link from 'next/link'

interface Project {
  id: number
  name: string
  type: string
  status: string
  mode: string
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
      className="card block group"
    >
      <div className="flex items-start justify-between">
        <h3 className="font-semibold text-foreground group-hover:text-primary transition-colors">{project.name}</h3>
        <span className={`text-xs px-2 py-1 rounded-full font-semibold uppercase tracking-wider ${statusColors[project.status] ?? 'bg-muted/10 text-muted'}`}>
          {project.status.replace('_', ' ')}
        </span>
      </div>
      <div className="mt-3 flex items-center gap-3">
        <p className="text-sm text-muted font-medium capitalize bg-background px-2 py-0.5 rounded border border-border">{project.type}</p>
        <span className="text-xs text-muted-foreground">{new Date(project.created_at).toLocaleDateString()}</span>
      </div>
    </Link>
  )
}
