'use client'
import { use, useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { apiClient } from '@/lib/api'

interface TemplateField {
  key: string
  label: string
  type: 'text' | 'textarea'
  required: boolean
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
      setError(`Required: ${missing.map(f => f.label).join(', ')}`)
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

  if (loadError) return <p className="text-sm text-red-600">{loadError}</p>
  if (!project) return <p className="text-sm text-gray-500">Loading…</p>

  const fields = project.template?.fields ?? []

  return (
    <div className="max-w-2xl">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>

      <h1 className="text-3xl font-bold tracking-tight text-foreground">{project.name}</h1>
      <p className="text-muted-foreground mt-2 mb-8">Fill in the project details to start generating requirements.</p>
      {error && <p className="text-red-600 text-sm mb-4">{error}</p>}
      <form onSubmit={handleSubmit} className="space-y-5">
        {fields.map(field => (
          <div key={field.key}>
            <label className="block text-sm font-medium mb-1">
              {field.label}
              {field.required && <span className="text-red-500 ml-1">*</span>}
            </label>
            {field.type === 'textarea' ? (
              <textarea
                value={formValues[field.key] ?? ''}
                onChange={e => handleChange(field.key, e.target.value)}
                rows={4}
                className="w-full border rounded px-3 py-2 text-sm resize-y"
              />
            ) : (
              <input
                type="text"
                value={formValues[field.key] ?? ''}
                onChange={e => handleChange(field.key, e.target.value)}
                className="w-full border rounded px-3 py-2 text-sm"
              />
            )}
          </div>
        ))}
        <button
          type="submit"
          disabled={submitting}
          className="bg-blue-900 text-white px-6 py-2 rounded text-sm font-medium disabled:opacity-50"
        >
          {submitting ? 'Saving…' : 'Save & generate'}
        </button>
      </form>
    </div>
  )
}
