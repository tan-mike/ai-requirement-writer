'use client'
import { useState, useEffect } from 'react'
import api from '@/lib/api'

type Project = {
  id: number
  name: string
  description?: string
  category?: string
  is_curated: boolean
}

export default function AdminProjectsPage() {
  const [projects, setProjects] = useState<Project[]>([])
  const [loading, setLoading] = useState(true)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editCategory, setEditCategory] = useState('')

  useEffect(() => {
    fetchProjects()
  }, [])

  const fetchProjects = async () => {
    try {
      const res = await api.get<{ data: Project[] }>('/admin/projects')
      setProjects(res.data)
    } catch (e) {
      console.error(e)
    } finally {
      setLoading(false)
    }
  }

  const toggleCurated = async (project: Project) => {
    try {
      await api.patch(`/admin/projects/${project.id}/curate`, {
        is_curated: !project.is_curated,
      })
      fetchProjects()
    } catch (e) {
      console.error(e)
    }
  }

  const saveCategory = async (project: Project) => {
    try {
      await api.patch(`/admin/projects/${project.id}/curate`, {
        is_curated: project.is_curated,
        category: editCategory
      })
      setEditingId(null)
      fetchProjects()
    } catch (e) {
      console.error(e)
    }
  }

  if (loading) return <div>Loading products...</div>

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground">Products</h1>
          <p className="text-sm text-muted-foreground mt-1">Manage public template products</p>
        </div>
      </div>

      <div className="border border-border rounded-lg overflow-hidden bg-background">
        <table className="w-full text-sm text-left">
          <thead className="bg-surface border-b border-border text-muted-foreground">
            <tr>
              <th className="px-4 py-3 font-medium">Name</th>
              <th className="px-4 py-3 font-medium">Category</th>
              <th className="px-4 py-3 font-medium">Default</th>
              <th className="px-4 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {projects.map(p => (
              <tr key={p.id} className="hover:bg-surface/50">
                <td className="px-4 py-3 font-medium text-foreground">{p.name}</td>
                <td className="px-4 py-3 text-muted-foreground">
                  {editingId === p.id ? (
                    <div className="flex gap-2">
                      <input 
                        type="text" 
                        value={editCategory} 
                        onChange={e => setEditCategory(e.target.value)}
                        className="px-2 py-1 border border-border rounded text-sm bg-background"
                        placeholder="e.g. Web App"
                      />
                      <button onClick={() => saveCategory(p)} className="text-primary font-medium hover:underline">Save</button>
                      <button onClick={() => setEditingId(null)} className="text-muted hover:underline">Cancel</button>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2">
                      {p.category || '-'}
                      <button 
                        onClick={() => { setEditingId(p.id); setEditCategory(p.category || '') }}
                        className="text-xs text-primary hover:underline ml-2"
                      >
                        Edit
                      </button>
                    </div>
                  )}
                </td>
                <td className="px-4 py-3">
                  <button 
                    onClick={() => toggleCurated(p)}
                    className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${p.is_curated ? 'bg-primary' : 'bg-muted/30'}`}
                  >
                    <span className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform ${p.is_curated ? 'translate-x-4.5' : 'translate-x-1'}`} />
                  </button>
                </td>
                <td className="px-4 py-3">
                  <a href={`/projects/${p.id}`} className="text-primary hover:underline font-medium">View</a>
                </td>
              </tr>
            ))}
            {projects.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">No products found.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
