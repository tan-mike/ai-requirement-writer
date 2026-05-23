'use client'
import { use, useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { apiClient } from '@/lib/api'
import InfoTooltip from '@/components/InfoTooltip'

interface TemplateField {
  key: string
  label: string
  type: 'text' | 'textarea'
  required: boolean
  tooltip?: string
}

interface Project {
  id: number
  name: string
  template: {
    fields: TemplateField[]
  }
}

export default function IntakePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params)
  const router = useRouter()
  const [project, setProject] = useState<Project | null>(null)
  const [formValues, setFormValues] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [loadError, setLoadError] = useState('')

  useEffect(() => {
    apiClient.get<{ data: Project }>(`/projects/${id}`)
      .then(res => setProject(res.data))
      .catch(() => setLoadError('Failed to load project'))
  }, [id])

  function handleChange(key: string, value: string) {
    setFormValues(prev => ({ ...prev, [key]: value }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const fields = project?.template?.fields ?? []
    const missing = fields.filter(f => f.required && !formValues[f.key]?.trim())
    if (missing.length > 0) {
      setError(`Please fill in all required fields: ${missing.map(f => f.label).join(', ')}`)
      return
    }
    setSubmitting(true)
    try {
      await apiClient.post(`/projects/${id}/intake`, { fields: formValues })
      router.push(`/projects/${id}/generate`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save')
    } finally {
      setSubmitting(false)
    }
  }

  if (loadError) return (
    <div className="card border-red-200 bg-red-50 dark:bg-red-900/10 dark:border-red-800 p-6 text-center">
      <p className="text-red-700 dark:text-red-400 font-medium">{loadError}</p>
      <Link href="/dashboard" className="text-primary hover:underline mt-4 inline-block font-semibold">Back to Dashboard</Link>
    </div>
  )
  
  if (!project) return (
    <div className="flex flex-col items-center justify-center py-20 text-muted">
      <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
      <p className="font-medium">Loading project intake details...</p>
    </div>
  )

  const fields = project.template?.fields ?? []

  return (
    <div className="max-w-3xl mx-auto">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>

      <div className="card p-10">
        <div className="mb-10 text-center">
          <h1 className="text-3xl font-bold tracking-tight text-foreground">{project.name}</h1>
          <p className="text-muted-foreground mt-2">Fill in the project details to provide context for the AI agents.</p>
        </div>

        {error && (
          <div role="alert" aria-live="polite" className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 mb-8">
            <p className="text-red-700 dark:text-red-400 text-sm font-semibold">{error}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-8">
          {fields.map(field => (
            <div key={field.key} className="space-y-1.5">
              <div className="flex items-center gap-2 mb-1.5">
                <label className="text-xs font-bold text-muted-foreground uppercase tracking-widest block">
                  {field.label}
                  {field.required && <span className="text-red-500 ml-1">*</span>}
                </label>
                {field.tooltip && <InfoTooltip text={field.tooltip} />}
              </div>
              {field.type === 'textarea' ? (
                <textarea
                  value={formValues[field.key] ?? ''}
                  onChange={e => handleChange(field.key, e.target.value)}
                  rows={5}
                  className="w-full bg-surface border-2 border-border rounded-xl px-4 py-3 text-sm focus:border-primary transition-all outline-none resize-y leading-relaxed"
                  placeholder={`Enter ${field.label.toLowerCase()}...`}
                />
              ) : (
                <input
                  type="text"
                  value={formValues[field.key] ?? ''}
                  onChange={e => handleChange(field.key, e.target.value)}
                  className="input text-base"
                  placeholder={`Enter ${field.label.toLowerCase()}...`}
                />
              )}
            </div>
          ))}

          <div className="pt-4">
            <button
              type="submit"
              disabled={submitting}
              className="btn-primary w-full py-4 text-base shadow-lg shadow-primary/20"
            >
              {submitting ? (
                <span className="flex items-center justify-center gap-2">
                  <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  Saving & Generating...
                </span>
              ) : 'Save & Generate Requirements'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
