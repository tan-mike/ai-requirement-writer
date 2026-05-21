'use client'
import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { apiClient } from '@/lib/api'

interface Template { id: number; name: string; type: string }

export default function NewProjectPage() {
  const router = useRouter()
  const [templates, setTemplates] = useState<Template[]>([])
  const [name, setName] = useState('')
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null)
  const [mode, setMode] = useState<'template' | 'conversational'>('template')
  const [repoUrl, setRepoUrl] = useState('')
  const [repoPath, setRepoPath] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    apiClient.get<{ data: Template[] }>('/templates').then(res => setTemplates(res.data)).catch(console.error)
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!name || !selectedTemplate) { setError('Select a template and enter a name'); return }
    setSubmitting(true)
    try {
      const res = await apiClient.post<{ data: { id: number } }>('/projects', {
        name, 
        type: selectedTemplate.type, 
        template_id: selectedTemplate.id,
        mode,
        repository_url: repoUrl || null,
        repository_path: repoPath || null,
      })
      router.push(`/projects/${res.data.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create project')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-semibold mb-6">New project</h1>
      {error && <p className="text-red-600 text-sm mb-4">{error}</p>}
      <form onSubmit={handleSubmit} className="space-y-6">
        <div>
          <label className="block text-sm font-medium mb-2">Project name</label>
          <input type="text" value={name} onChange={e => setName(e.target.value)}
            placeholder="e.g. Customer Portal" className="w-full border rounded px-3 py-2 text-sm" />
        </div>
        
        <div>
          <label className="block text-sm font-medium mb-2">Discovery Mode</label>
          <div className="flex gap-4">
            <button type="button" onClick={() => setMode('template')}
              className={`px-4 py-2 rounded text-sm font-medium border ${mode === 'template' ? 'bg-blue-50 border-blue-900 text-blue-900' : 'bg-white border-gray-200'}`}>
              Template-based
            </button>
            <button type="button" onClick={() => setMode('conversational')}
              className={`px-4 py-2 rounded text-sm font-medium border ${mode === 'conversational' ? 'bg-blue-50 border-blue-900 text-blue-900' : 'bg-white border-gray-200'}`}>
              Conversational (AI Interview)
            </button>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium mb-2">Project type</label>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {templates.map(t => (
              <button key={t.id} type="button" onClick={() => setSelectedTemplate(t)}
                className={`border rounded-lg p-3 text-sm font-medium text-left transition-colors ${
                  selectedTemplate?.id === t.id ? 'border-blue-900 bg-blue-50 text-blue-900' : 'border-gray-200 hover:border-gray-400'
                }`}>{t.name}</button>
            ))}
          </div>
        </div>

        <div className="space-y-4 pt-4 border-t">
          <h3 className="text-sm font-medium text-gray-700">Existing Codebase (Optional)</h3>
          <div>
            <label className="block text-xs text-gray-500 mb-1">GitHub Repository URL</label>
            <input type="text" value={repoUrl} onChange={e => setRepoUrl(e.target.value)}
              placeholder="https://github.com/org/repo" className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-xs text-gray-500 mb-1">Local Repository Path</label>
            <input type="text" value={repoPath} onChange={e => setRepoPath(e.target.value)}
              placeholder="/Users/mike/projects/my-app" className="w-full border rounded px-3 py-2 text-sm" />
          </div>
        </div>

        <button type="submit" disabled={submitting}
          className="bg-blue-900 text-white px-6 py-2 rounded text-sm font-medium disabled:opacity-50">
          {submitting ? 'Creating…' : 'Create project'}
        </button>
      </form>
    </div>
  )
}
