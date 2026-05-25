'use client'
import { useEffect, useState } from 'react'
import { apiClient } from '@/lib/api'

interface Persona {
  id: number
  slug: string
  name: string
  role: 'lead' | 'reviewer'
  cost_multiplier: number
}

interface ProjectContext {
  id: number
  name: string
}

interface GenerationStrategyProps {
  onStart: (config: GenerationConfig) => void
  disabled?: boolean
  label: string
  leadPersonaName?: string
}

export interface GenerationConfig {
  reviewer_persona_ids: number[]
  context_ids: number[]
}

export default function GenerationStrategy({ onStart, disabled, label, leadPersonaName }: GenerationStrategyProps) {
  const [personas, setPersonas] = useState<Persona[]>([])
  const [contexts, setContexts] = useState<ProjectContext[]>([])
  const [config, setConfig] = useState<GenerationConfig>({
    reviewer_persona_ids: [],
    context_ids: [],
  })
  const [expanded, setExpanded] = useState(false)

  useEffect(() => {
    apiClient.get<Persona[]>('/personas').then(setPersonas)
    apiClient.get<ProjectContext[]>('/contexts').then(setContexts)
  }, [])

  const reviewers = personas.filter(p => p.role === 'reviewer')

  const totalMultiplier = 1.0 + // Base lead cost is implicitly 1.0 or handled on backend
    config.reviewer_persona_ids.reduce((sum, id) => sum + (personas.find(p => p.id === id)?.cost_multiplier ?? 0), 0)

  return (
    <div className="border border-border rounded-lg p-4 space-y-4 bg-card shadow-sm transition-all duration-300">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <h3 className="font-medium text-sm text-foreground">Generation Strategy</h3>
          {leadPersonaName && (
            <span className="bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border border-primary/20">
              Lead: {leadPersonaName}
            </span>
          )}
        </div>
        <button 
          onClick={() => setExpanded(!expanded)}
          className="text-xs text-muted-foreground hover:text-primary underline"
        >
          {expanded ? 'Hide Settings' : 'Advanced Settings'}
        </button>
      </div>

      {expanded && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 animate-in fade-in slide-in-from-top-2 duration-300">
          <div className="space-y-4">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                Review Committee
              </label>
              <div className="space-y-2 max-h-48 overflow-y-auto p-2 border border-input rounded bg-muted/30">
                {reviewers.map(p => (
                  <label key={p.id} className="flex items-center space-x-2 text-sm cursor-pointer hover:bg-muted p-1 rounded transition-colors">
                    <input 
                      type="checkbox"
                      className="rounded border-input text-primary focus:ring-primary"
                      checked={config.reviewer_persona_ids.includes(p.id)}
                      onChange={e => {
                        const ids = e.target.checked 
                          ? [...config.reviewer_persona_ids, p.id]
                          : config.reviewer_persona_ids.filter(id => id !== p.id)
                        setConfig({ ...config, reviewer_persona_ids: ids })
                      }}
                    />
                    <span>{p.name}</span>
                  </label>
                ))}
              </div>
              <p className="text-[10px] text-muted-foreground mt-1.5 italic">Reviewers audit the draft before final synthesis.</p>
            </div>
          </div>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                Additional Contexts
              </label>
              <div className="space-y-2 max-h-48 overflow-y-auto p-2 border border-input rounded bg-muted/30">
                {contexts.length === 0 && <p className="text-xs text-muted-foreground p-1">No saved contexts.</p>}
                {contexts.map(ctx => (
                  <label key={ctx.id} className="flex items-center space-x-2 text-sm cursor-pointer hover:bg-muted p-1 rounded transition-colors">
                    <input 
                      type="checkbox"
                      className="rounded border-input text-primary focus:ring-primary"
                      checked={config.context_ids.includes(ctx.id)}
                      onChange={e => {
                        const ids = e.target.checked 
                          ? [...config.context_ids, ctx.id]
                          : config.context_ids.filter(id => id !== ctx.id)
                        setConfig({ ...config, context_ids: ids })
                      }}
                    />
                    <span>{ctx.name}</span>
                  </label>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="pt-2 border-t border-border flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="text-xs text-muted-foreground max-w-sm">
          {config.reviewer_persona_ids.length > 0 ? (
            <p>
              <span className="text-yellow-600 font-semibold mr-1">⚠️ Quality-focused:</span> 
              Draft will go through {config.reviewer_persona_ids.length} review cycles. 
              Estimated time/cost multiplier: <span className="text-foreground font-mono font-bold bg-muted px-1.5 py-0.5 rounded">{totalMultiplier.toFixed(1)}x</span>
            </p>
          ) : (
            <p className="italic">Single-pass generation via {leadPersonaName}. Faster, but less rigorous.</p>
          )}
        </div>
        <button
          onClick={() => onStart(config)}
          disabled={disabled}
          className="bg-primary text-primary-foreground hover:bg-primary/90 px-6 py-2.5 rounded-md text-sm font-semibold shadow-md active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
        >
          {label}
        </button>
      </div>
    </div>
  )
}
