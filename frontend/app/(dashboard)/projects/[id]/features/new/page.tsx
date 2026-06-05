'use client'
import { use, useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { apiClient } from '@/lib/api'
import InfoTooltip from '@/components/InfoTooltip'

interface Template { id: number; name: string; type: string }
interface Persona { id: number; name: string; slug: string }

export default function NewFeaturePage({ params }: { params: Promise<{ id: string }> }) {
  const { id: projectId } = use(params)
  const router = useRouter()
  const [templates, setTemplates] = useState<Template[]>([])
  const [personas, setPersonas] = useState<Persona[]>([])
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null)
  const [selectedPersonaId, setSelectedPersonaId] = useState<number>(0)
  const [mode, setMode] = useState<'template' | 'conversational'>('template')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    apiClient.get<{ data: Template[] }>('/templates').then(res => setTemplates(res.data)).catch(console.error)
    apiClient.get<Persona[]>('/personas?role=lead').then(res => {
      setPersonas(res)
      if (res.length > 0) setSelectedPersonaId(res[0].id)
    }).catch(console.error)
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!name || !selectedTemplate || !selectedPersonaId) { setError('Select a template, lead expert, and enter a name'); return }
    setSubmitting(true)
    setError('')

    try {
      const payload = {
        name,
        description,
        template_id: selectedTemplate.id,
        mode,
        lead_persona_id: selectedPersonaId,
      }

      const res = await apiClient.post<{ data: { id: number } }>(`/projects/${projectId}/features`, payload)
      
      // Redirect to feature intake or chat
      if (mode === 'conversational') {
        router.push(`/projects/${projectId}/features/${res.data.id}/chat`)
      } else {
        router.push(`/projects/${projectId}/features/${res.data.id}/intake`)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create feature')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="max-w-3xl mx-auto">
      <Link href={`/projects/${projectId}`} className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Project
      </Link>

      <div className="card p-10">
        <div className="mb-10 text-center">
          <h1 className="text-3xl font-bold tracking-tight text-foreground">Add new feature</h1>
          <p className="text-muted-foreground mt-2">Define what you want to add or enhance in this project.</p>
        </div>

        {error && (
          <div role="alert" aria-live="polite" className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-8">
            <p className="text-red-700 dark:text-red-400 text-sm font-semibold">{error}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-10">
          <div className="space-y-6">
            <div className="space-y-1">
              <label htmlFor="feature-name" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Feature Name</label>
              <input id="feature-name" type="text" value={name} onChange={e => setName(e.target.value)}
                placeholder="e.g. User Authentication" className="input text-base" required />
            </div>

            <div className="space-y-1">
              <label htmlFor="feature-description" className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">What's the goal?</label>
              <textarea id="feature-description" value={description} onChange={e => setDescription(e.target.value)}
                placeholder="Briefly describe the purpose of this enhancement..." className="w-full bg-surface border-2 border-border rounded-xl px-4 py-3 text-sm focus:border-primary transition-all outline-none" rows={2} />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="space-y-1">
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

            <div className="space-y-1">
                <label className="text-xs font-bold text-muted-foreground uppercase tracking-widest mb-1.5 block">Discovery Approach</label>
                <div className="flex gap-2 p-1 bg-muted rounded-xl">
                    <button type="button" onClick={() => setMode('template')}
                        className={`flex-1 py-2 text-xs font-bold rounded-lg transition-all ${mode === 'template' ? 'bg-surface shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'}`}>
                        Template
                    </button>
                    <button type="button" onClick={() => setMode('conversational')}
                        className={`flex-1 py-2 text-xs font-bold rounded-lg transition-all ${mode === 'conversational' ? 'bg-surface shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'}`}>
                        AI Interview
                    </button>
                </div>
            </div>
          </div>
          
          <div className="space-y-4">
            <label className="block text-sm font-bold text-foreground uppercase tracking-wider">Requirement Framework</label>
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

          <div className="pt-6">
            <button type="submit" disabled={submitting}
              className="btn-primary w-full py-4 text-base shadow-lg shadow-primary/20">
              {submitting ? 'Initializing discovery...' : 'Start discovery'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
