'use client'
import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { apiClient } from '@/lib/api'
import GuidedTour from '@/components/GuidedTour'
import InfoTooltip from '@/components/InfoTooltip'

interface Template { id: number; name: string; type: string }
interface Persona { id: number; name: string; slug: string }
interface ProjectContext { id: number; name: string }

interface ProjectPayload {
  name: string
  type: string
  template_id: number
  mode: string
  lead_persona_id: number
  repository_url?: string
  repository_path?: string
  context_ids?: number[]
}

export default function NewProjectPage() {
  const router = useRouter()
  const [templates, setTemplates] = useState<Template[]>([])
  const [personas, setPersonas] = useState<Persona[]>([])
  const [savedContexts, setSavedContexts] = useState<ProjectContext[]>([])
  const [name, setName] = useState('')
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null)
  const [selectedPersonaId, setSelectedPersonaId] = useState<number>(0)
  const [mode, setMode] = useState<'template' | 'conversational'>('template')
  const [contextSource, setContextSource] = useState<'url' | 'path' | 'file' | 'saved' | 'none'>('none')
  const [repoUrl, setRepoUrl] = useState('')
  const [repoPath, setRepoPath] = useState('')
  const [contextFile, setContextFile] = useState<File | null>(null)
  const [selectedContextIds, setSelectedContextIds] = useState<number[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    apiClient.get<{ data: Template[] }>('/templates').then(res => setTemplates(res.data)).catch(console.error)
    apiClient.get<Persona[]>('/personas?role=lead').then(res => {
      setPersonas(res)
      if (res.length > 0) setSelectedPersonaId(res[0].id)
    }).catch(console.error)
    apiClient.get<ProjectContext[]>('/contexts').then(setSavedContexts).catch(console.error)
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!name || !selectedTemplate || !selectedPersonaId) { setError('Select a template, lead expert, and enter a name'); return }
    setSubmitting(true)
    setError('')

    try {
      const payload: ProjectPayload = {
        name,
        type: selectedTemplate.type,
        template_id: selectedTemplate.id,
        mode,
        lead_persona_id: selectedPersonaId,
      }

      if (contextSource === 'url') payload.repository_url = repoUrl
      if (contextSource === 'path') payload.repository_path = repoPath
      if (contextSource === 'saved') payload.context_ids = selectedContextIds
      
      let body: ProjectPayload | FormData = payload
      if (contextSource === 'file' && contextFile) {
        const formData = new FormData()
        Object.entries(payload).forEach(([key, value]) => {
          if (Array.isArray(value)) {
            value.forEach(v => formData.append(`${key}[]`, String(v)))
          } else {
            formData.append(key, String(value))
          }
        })
        formData.append('context_file', contextFile)
        body = formData
      }

      const res = await apiClient.post<{ data: { id: number } }>('/projects', body)
      router.push(`/projects/${res.data.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create project')
    } finally {
      setSubmitting(false)
    }
  }

  const toggleContextId = (id: number) => {
    setSelectedContextIds(prev => 
      prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
    )
  }

  return (
    <div className="max-w-3xl mx-auto">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>

      <div className="card p-10">
        <div className="mb-10 text-center">
          <h1 className="text-3xl font-bold tracking-tight text-foreground">New project</h1>
          <p className="text-muted-foreground mt-2">Set up your project and choose how you want to discover requirements.</p>
        </div>

        {error && (
          <div role="alert" aria-live="polite" className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-8">
            <p className="text-red-700 dark:text-red-400 text-sm font-semibold">{error}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-10">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="space-y-1">
              <label htmlFor="project-name" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Project Name</label>
              <input id="project-name" type="text" value={name} onChange={e => setName(e.target.value)}
                placeholder="e.g. Customer Portal" className="input text-base" required />
            </div>

            <div className="space-y-1" id="tour-lead-persona">
              <label htmlFor="lead-persona" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Lead Expert Persona</label>
              <select 
                id="lead-persona"
                value={selectedPersonaId}
                onChange={e => setSelectedPersonaId(Number(e.target.value))}
                className="w-full bg-surface border-2 border-border rounded-xl px-4 py-2.5 text-sm font-bold focus:border-primary transition-all outline-none"
                required
              >
                {personas.map(p => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
            </div>
          </div>
          
          <div className="space-y-4" id="tour-discovery-mode">
            <div className="flex items-center gap-2">
              <label className="block text-sm font-bold text-foreground uppercase tracking-wider">Discovery Mode</label>
              <InfoTooltip text="Template mode uses a structured form. Conversational mode uses an interactive AI interview to extract requirements." />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <button type="button" onClick={() => setMode('template')}
                className={`p-4 rounded-xl text-left border-2 transition-all outline-none ${
                  mode === 'template' 
                    ? 'border-primary bg-primary/5 ring-1 ring-primary' 
                    : 'border-border bg-surface hover:border-muted-foreground/30'
                }`}>
                <div className="flex justify-between items-start mb-2">
                  <div className={`p-2 rounded-lg ${mode === 'template' ? 'bg-primary text-white' : 'bg-surface-hover text-muted'}`}>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
                  </div>
                  {mode === 'template' && <div className="w-5 h-5 bg-primary rounded-full flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>}
                </div>
                <div className="font-bold text-foreground">Template-based</div>
                <div className="text-xs text-muted-foreground mt-1">Structured intake form</div>
              </button>
              
              <button type="button" onClick={() => setMode('conversational')}
                className={`p-4 rounded-xl text-left border-2 transition-all outline-none ${
                  mode === 'conversational' 
                    ? 'border-primary bg-primary/5 ring-1 ring-primary' 
                    : 'border-border bg-surface hover:border-muted-foreground/30'
                }`}>
                <div className="flex justify-between items-start mb-2">
                  <div className={`p-2 rounded-lg ${mode === 'conversational' ? 'bg-primary text-white' : 'bg-surface-hover text-muted'}`}>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  </div>
                  {mode === 'conversational' && <div className="w-5 h-5 bg-primary rounded-full flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>}
                </div>
                <div className="font-bold text-foreground">Conversational</div>
                <div className="text-xs text-muted-foreground mt-1">Interactive AI Interview</div>
              </button>
            </div>
          </div>

          <div className="space-y-4">
            <label className="block text-sm font-bold text-foreground uppercase tracking-wider">Project type</label>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {templates.map(t => (
                <button key={t.id} type="button" onClick={() => setSelectedTemplate(t)}
                  className={`border-2 rounded-xl p-4 text-sm font-bold transition-all text-left outline-none ${
                    selectedTemplate?.id === t.id 
                      ? 'border-primary bg-primary/5 text-primary' 
                      : 'border-border bg-surface hover:border-muted-foreground/30 text-muted'
                  }`}>
                  {t.name}
                </button>
              ))}
            </div>
          </div>

          <div className="pt-8 border-t border-border space-y-6" id="tour-add-context">
            <div>
              <div className="flex items-center gap-2 mb-4">
                <label className="block text-sm font-bold text-foreground uppercase tracking-wider">Add Context (Optional)</label>
                <InfoTooltip text="Provide your existing documentation or codebase for the AI to analyze. This helps maintain system integrity." />
              </div>
              <div className="flex flex-wrap gap-2">
                {(['none', 'saved', 'url', 'path', 'file'] as const).map(s => (
                  <button key={s} type="button" onClick={() => setContextSource(s)}
                    className={`px-4 py-1.5 rounded-full text-xs font-bold border transition-all outline-none ${
                      contextSource === s 
                        ? 'bg-foreground text-background border-foreground shadow-lg shadow-foreground/10' 
                        : 'bg-surface text-muted border-border hover:border-muted-foreground/30'
                    }`}>
                    {s === 'none' ? 'None' : s === 'saved' ? 'Saved Contexts' : s === 'url' ? 'GitHub URL' : s === 'path' ? 'Local Path' : 'File Upload'}
                  </button>
                ))}
              </div>
            </div>

            <div className="bg-surface-hover rounded-xl p-6 min-h-[100px] flex flex-col justify-center border border-border/50">
              {contextSource === 'none' && <p className="text-sm text-muted-foreground text-center italic">No extra context will be provided. Starting from scratch.</p>}
              
              {contextSource === 'saved' && (
                <div className="animate-in fade-in slide-in-from-top-1 duration-300">
                  <p className="text-xs font-bold text-muted uppercase tracking-wider mb-3">Your Saved Contexts</p>
                  <div className="grid gap-2 max-h-48 overflow-y-auto pr-2">
                    {savedContexts.length > 0 ? savedContexts.map(ctx => (
                      <label key={ctx.id} className="flex items-center gap-3 p-3 rounded-lg border border-border bg-surface hover:bg-muted/50 cursor-pointer transition-colors">
                        <input type="checkbox" checked={selectedContextIds.includes(ctx.id)} onChange={() => toggleContextId(ctx.id)} className="w-4 h-4 rounded text-primary focus:ring-primary border-input" />
                        <span className="text-sm font-medium">{ctx.name}</span>
                      </label>
                    )) : (
                      <p className="text-xs text-muted-foreground italic">No saved contexts found. Go to Dashboard to add some.</p>
                    )}
                  </div>
                </div>
              )}

              {contextSource === 'url' && (
                <div className="animate-in fade-in slide-in-from-top-1 duration-300">
                  <label htmlFor="repo-url" className="block text-xs font-bold text-muted uppercase tracking-wider mb-2">GitHub Repository URL</label>
                  <input id="repo-url" type="url" value={repoUrl} onChange={e => setRepoUrl(e.target.value)}
                    placeholder="https://github.com/org/repo" className="input" />
                </div>
              )}

              {contextSource === 'path' && (
                <div className="animate-in fade-in slide-in-from-top-1 duration-300">
                  <label htmlFor="repo-path" className="block text-xs font-bold text-muted uppercase tracking-wider mb-2">Local Repository Path</label>
                  <input id="repo-path" type="text" value={repoPath} onChange={e => setRepoPath(e.target.value)}
                    placeholder="/Users/mike/projects/my-app" className="input" />
                </div>
              )}

              {contextSource === 'file' && (
                <div className="animate-in fade-in slide-in-from-top-1 duration-300">
                  <label htmlFor="context-file" className="block text-xs font-bold text-muted uppercase tracking-wider mb-2">Context File (.txt, .json, .md, .xml)</label>
                  <input id="context-file" type="file" onChange={e => setContextFile(e.target.files?.[0] || null)}
                    accept=".txt,.json,.md,.xml" className="w-full text-sm file:mr-4 file:py-2.5 file:px-6 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white file:transition-colors hover:file:bg-primary/90 cursor-pointer" />
                </div>
              )}
            </div>
          </div>

          <div className="pt-6">
            <button type="submit" disabled={submitting}
              className="btn-primary w-full py-4 text-base shadow-lg shadow-primary/20">
              {submitting ? (
                <span className="flex items-center justify-center gap-2">
                  <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  Creating project...
                </span>
              ) : 'Create project'}
            </button>
          </div>
        </form>
      </div>

      <GuidedTour 
        pageKey="new_project" 
        mode="sequential"
        steps={[
          {
            targetId: "tour-lead-persona",
            title: "Pick Your Partner",
            content: "Select an expert (e.g., Fintech, Architect) to conduct your interview and draft your documents."
          },
          {
            targetId: "tour-discovery-mode",
            title: "Discovery Approach",
            content: "Choose 'Template' for a structured form, or 'Conversational' to be interviewed by the AI."
          },
          {
            targetId: "tour-add-context",
            title: "System Awareness",
            content: "Optionally select from your Saved Contexts, or provide GitHub/file links to inform the AI."
          }
        ]} 
      />
    </div>
  )
}
