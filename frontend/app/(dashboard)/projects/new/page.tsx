'use client'
import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { apiClient } from '@/lib/api'

interface Template { id: number; name: string; type: string }

interface ProjectPayload {
  name: string
  type: string
  template_id: number
  mode: string
  repository_url?: string
  repository_path?: string
}

export default function NewProjectPage() {
  const router = useRouter()
  const [templates, setTemplates] = useState<Template[]>([])
  const [name, setName] = useState('')
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null)
  const [mode, setMode] = useState<'template' | 'conversational'>('template')
  const [contextSource, setContextSource] = useState<'url' | 'path' | 'file' | 'none'>('none')
  const [repoUrl, setRepoUrl] = useState('')
  const [repoPath, setRepoPath] = useState('')
  const [contextFile, setContextFile] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    apiClient.get<{ data: Template[] }>('/templates').then(res => setTemplates(res.data)).catch(console.error)
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!name || !selectedTemplate) { setError('Select a template and enter a name'); return }
    setSubmitting(true)
    setError('')

    try {
      const payload: ProjectPayload = {
        name,
        type: selectedTemplate.type,
        template_id: selectedTemplate.id,
        mode,
      }

      if (contextSource === 'url') payload.repository_url = repoUrl
      if (contextSource === 'path') payload.repository_path = repoPath
      
      let body: ProjectPayload | FormData = payload
      if (contextSource === 'file' && contextFile) {
        const formData = new FormData()
        Object.entries(payload).forEach(([key, value]) => {
          formData.append(key, String(value))
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

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-semibold mb-6">New project</h1>
      {error && <p role="alert" aria-live="polite" className="text-red-600 text-sm mb-4">{error}</p>}
      <form onSubmit={handleSubmit} className="space-y-6">
        <div>
          <label htmlFor="project-name" className="block text-sm font-medium mb-2">Project name</label>
          <input id="project-name" type="text" value={name} onChange={e => setName(e.target.value)}
            placeholder="e.g. Customer Portal" className="w-full border rounded px-3 py-2 text-sm" required />
        </div>
        
        <fieldset>
          <legend className="block text-sm font-medium mb-2">Discovery Mode</legend>
          <div className="flex gap-4">
            <button type="button" onClick={() => setMode('template')}
              aria-pressed={mode === 'template'}
              className={`px-4 py-2 rounded text-sm font-medium border focus:ring-2 focus:ring-blue-500 outline-none ${mode === 'template' ? 'bg-blue-50 border-blue-900 text-blue-900' : 'bg-white border-gray-200 hover:border-gray-400'}`}>
              Template-based
            </button>
            <button type="button" onClick={() => setMode('conversational')}
              aria-pressed={mode === 'conversational'}
              className={`px-4 py-2 rounded text-sm font-medium border focus:ring-2 focus:ring-blue-500 outline-none ${mode === 'conversational' ? 'bg-blue-50 border-blue-900 text-blue-900' : 'bg-white border-gray-200 hover:border-gray-400'}`}>
              Conversational (AI Interview)
            </button>
          </div>
        </fieldset>

        <fieldset>
          <legend className="block text-sm font-medium mb-2">Project type</legend>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {templates.map(t => (
              <button key={t.id} type="button" onClick={() => setSelectedTemplate(t)}
                aria-pressed={selectedTemplate?.id === t.id}
                className={`border rounded-lg p-3 text-sm font-medium text-left transition-colors focus:ring-2 focus:ring-blue-500 outline-none ${
                  selectedTemplate?.id === t.id ? 'border-blue-900 bg-blue-50 text-blue-900' : 'border-gray-200 hover:border-gray-400'
                }`}>{t.name}</button>
            ))}
          </div>
        </fieldset>

        <fieldset className="pt-4 border-t space-y-4">
          <legend className="text-sm font-medium text-gray-700 mb-2">Add Context (Optional)</legend>
          <div className="flex gap-2 mb-4">
            {(['none', 'url', 'path', 'file'] as const).map(s => (
              <button key={s} type="button" onClick={() => setContextSource(s)}
                aria-pressed={contextSource === s}
                className={`px-3 py-1 rounded-full text-xs font-medium border transition-colors focus:ring-2 focus:ring-gray-900 outline-none ${
                  contextSource === s ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'
                }`}>
                {s === 'none' ? 'None' : s === 'url' ? 'GitHub URL' : s === 'path' ? 'Local Path' : 'File Upload'}
              </button>
            ))}
          </div>

          {contextSource === 'url' && (
            <div>
              <label htmlFor="repo-url" className="block text-xs text-gray-500 mb-1">GitHub Repository URL</label>
              <input id="repo-url" type="url" value={repoUrl} onChange={e => setRepoUrl(e.target.value)}
                placeholder="https://github.com/org/repo" className="w-full border rounded px-3 py-2 text-sm" />
            </div>
          )}

          {contextSource === 'path' && (
            <div>
              <label htmlFor="repo-path" className="block text-xs text-gray-500 mb-1">Local Repository Path</label>
              <input id="repo-path" type="text" value={repoPath} onChange={e => setRepoPath(e.target.value)}
                placeholder="/Users/mike/projects/my-app" className="w-full border rounded px-3 py-2 text-sm" />
            </div>
          )}

          {contextSource === 'file' && (
            <div>
              <label htmlFor="context-file" className="block text-xs text-gray-500 mb-1">Context File (.txt, .json, .md, .xml)</label>
              <input id="context-file" type="file" onChange={e => setContextFile(e.target.files?.[0] || null)}
                accept=".txt,.json,.md,.xml" className="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            </div>
          )}
        </fieldset>

        <button type="submit" disabled={submitting}
          className="bg-blue-900 text-white px-6 py-2 rounded text-sm font-medium disabled:opacity-50 focus:ring-2 focus:ring-blue-500 outline-none">
          {submitting ? 'Creating…' : 'Create project'}
        </button>
      </form>
    </div>
  )
}
