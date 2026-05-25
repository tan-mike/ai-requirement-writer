'use client'
import { useEffect, useState, useRef } from 'react'
import { apiClient } from '@/lib/api'
import GuidedTour from '@/components/GuidedTour'

interface CustomSkill {
  id: number
  name: string
  role: 'lead' | 'reviewer'
  is_global: boolean
  created_at: string
}

export default function SettingsPage() {
  const [freeTier, setFreeTier] = useState(false)
  const [geminiApiKey, setGeminiApiKey] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')

  // Skill State
  const [skills, setPersonas] = useState<CustomSkill[]>([])
  const [uploading, setUploading] = useState(false)
  const [isGlobal, setIsGlobal] = useState(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    Promise.all([
      apiClient.get<{ data: { free_tier: boolean, gemini_api_key?: string } }>('/user/settings'),
      apiClient.get<{ data: CustomSkill[] }>('/user/skills')
    ]).then(([settingsRes, skillsRes]) => {
      setFreeTier(settingsRes.data.free_tier)
      setGeminiApiKey(settingsRes.data.gemini_api_key ?? '')
      setPersonas(skillsRes.data)
      setLoading(false)
    })
  }, [])

  async function handleSaveSettings() {
    setSaving(true)
    setMessage('')
    try {
      await apiClient.patch('/user/settings', { 
        free_tier: freeTier,
        gemini_api_key: geminiApiKey || null
      })
      setMessage('Settings saved successfully.')
    } catch {
      setMessage('Failed to save settings.')
    } finally {
      setSaving(false)
    }
  }

  async function handleUploadSkill(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return

    setUploading(true)
    const formData = new FormData()
    formData.append('file', file)
    formData.append('is_global', isGlobal ? '1' : '0')

    try {
      const token = localStorage.getItem('token')
      const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api'
      const res = await fetch(`${BASE_URL}/user/skills/upload`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        body: formData,
      })

      if (!res.ok) throw new Error('Upload failed')
      
      const updatedSkills = await apiClient.get<{ data: CustomSkill[] }>('/user/skills')
      setPersonas(updatedSkills.data)
      alert('Skill uploaded successfully!')
    } catch (err) {
      alert('Error uploading skill. Please ensure JSON format is correct.')
    } finally {
      setUploading(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }

  async function handleDeleteSkill(id: number) {
    if (!confirm('Are you sure you want to delete this custom skill?')) return
    await apiClient.delete(`/user/skills/${id}`)
    setPersonas(skills.filter(s => s.id !== id))
  }

  async function handleResetTours() {
    if (!confirm('This will show all onboarding guides again. Continue?')) return
    setSaving(true)
    try {
      await apiClient.patch('/user/settings', { tour_flags: {} })
      alert('Onboarding tours have been reset!')
    } catch {
      alert('Failed to reset tours.')
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <div className="animate-pulse">Loading settings...</div>

  return (
    <div className="max-w-4xl space-y-12 pb-20">
      <div>
        <h1 className="text-4xl font-extrabold tracking-tight">Settings</h1>
        <p className="text-muted-foreground mt-2 text-lg">Manage your account preferences and expert skills.</p>
      </div>

      {/* Preferences Section */}
      <section className="space-y-6" id="tour-preferences">
        <h2 className="text-xl font-bold border-b pb-2">AI Credentials & Quotas</h2>
        <div className="bg-card border border-border rounded-2xl p-8 space-y-8 shadow-sm">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="space-y-2" id="tour-api-keys">
              <label className="text-xs font-bold uppercase tracking-widest text-muted-foreground">Personal Gemini API Key</label>
              <input 
                type="password" 
                className="input" 
                placeholder="AIzaSy..." 
                value={geminiApiKey}
                onChange={e => setGeminiApiKey(e.target.value)}
              />
              <p className="text-[10px] text-muted-foreground leading-relaxed">
                Optional. If blank, the platform's shared API key will be used. 
                Your key is stored with AES-256 encryption.
              </p>
            </div>

            <div className="flex items-center justify-between p-4 bg-muted/20 rounded-xl border border-border/50">
              <div className="space-y-0.5">
                <h3 className="text-sm font-bold">Personal Free-Tier Mode</h3>
                <p className="text-[10px] text-muted-foreground max-w-[200px]">
                  Introduce delays to respect rate limits of your personal API key. 
                  Ignored when using platform key.
                </p>
              </div>
              <button 
                onClick={() => setFreeTier(!freeTier)}
                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none ${freeTier ? 'bg-primary' : 'bg-muted'}`}
              >
                <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${freeTier ? 'translate-x-6' : 'translate-x-1'}`} />
              </button>
            </div>
          </div>

          <div className="pt-6 border-t border-border flex items-center justify-between">
            <p className="text-sm text-emerald-600 font-medium">{message}</p>
            <div className="flex gap-4">
              <button 
                onClick={handleResetTours} 
                disabled={saving}
                className="px-6 py-2.5 rounded-xl text-xs font-bold text-muted-foreground hover:text-foreground hover:bg-muted transition-all"
              >
                Reset Onboarding Tours
              </button>
              <button onClick={handleSaveSettings} disabled={saving} className="btn-primary px-10 shadow-lg shadow-primary/20">
                {saving ? 'Saving...' : 'Save AI Settings'}
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Skills Management Section */}
      <section className="space-y-6" id="tour-custom-skills">
        <h2 className="text-xl font-bold border-b pb-2">Expert Skills Management</h2>
        
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Upload Panel */}
          <div className="lg:col-span-1 space-y-4">
            <div className="bg-surface border-2 border-dashed border-border rounded-2xl p-6 text-center space-y-4">
              <div className="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              </div>
              <div className="space-y-1">
                <p className="text-sm font-bold">Upload Custom Skill</p>
                <p className="text-xs text-muted-foreground">JSON files only</p>
              </div>
              
              <div className="flex items-center justify-center gap-4 py-2 border-y border-border/50">
                 <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Visibility</span>
                 <button 
                  onClick={() => setIsGlobal(!isGlobal)}
                  className={`px-3 py-1 rounded-full text-[10px] font-bold transition-colors ${isGlobal ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'}`}
                 >
                   {isGlobal ? 'Global' : 'Private'}
                 </button>
              </div>

              <input 
                type="file" 
                ref={fileInputRef}
                onChange={handleUploadSkill}
                accept=".json"
                className="hidden"
                id="skill-upload"
              />
              <label 
                htmlFor="skill-upload" 
                className={`block w-full py-2.5 rounded-xl text-sm font-bold cursor-pointer transition-all ${uploading ? 'bg-muted text-muted-foreground' : 'bg-primary text-white hover:bg-primary/90 shadow-md'}`}
              >
                {uploading ? 'Uploading...' : 'Select JSON File'}
              </label>
            </div>

            <div className="bg-muted/30 rounded-xl p-4 space-y-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">JSON Structure Guide</p>
              <pre className="text-[9px] font-mono bg-background p-2 rounded border border-border overflow-x-auto">
{`{
  "name": "Expert Name",
  "role": "lead", // or "reviewer"
  "system_prompt": "You are a...",
  "cost_multiplier": 1.5
}`}
              </pre>
            </div>
          </div>

          {/* List Panel */}
          <div className="lg:col-span-2 space-y-4">
            <h3 className="text-sm font-bold uppercase tracking-widest text-muted-foreground">My Uploaded Skills</h3>
            <div className="grid gap-4">
              {skills.map(skill => (
                <div key={skill.id} className="bg-card border border-border rounded-xl p-4 flex items-center justify-between group hover:shadow-sm transition-shadow">
                  <div className="flex items-center gap-4">
                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center font-bold ${skill.role === 'lead' ? 'bg-amber-50 text-amber-600' : 'bg-purple-50 text-purple-600'}`}>
                      {skill.role === 'lead' ? 'L' : 'R'}
                    </div>
                    <div>
                      <h4 className="font-bold text-sm">{skill.name}</h4>
                      <div className="flex items-center gap-2 mt-0.5">
                        <span className="text-[10px] font-bold px-1.5 py-0.5 bg-muted rounded uppercase tracking-wider">{skill.role}</span>
                        <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider ${skill.is_global ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-600'}`}>
                          {skill.is_global ? 'Global' : 'Private'}
                        </span>
                      </div>
                    </div>
                  </div>
                  <button 
                    onClick={() => handleDeleteSkill(skill.id)}
                    className="p-2 text-muted-foreground hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                  </button>
                </div>
              ))}
              {skills.length === 0 && (
                <div className="py-12 text-center border-2 border-dashed border-border rounded-2xl bg-muted/10">
                  <p className="text-sm text-muted-foreground italic">You haven't uploaded any custom skills yet.</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      <GuidedTour 
        pageKey="settings" 
        mode="hotspots"
        steps={[
          {
            targetId: "tour-api-keys",
            title: "Manage Keys",
            content: "Add your personal Gemini API key here to use your own quota."
          },
          {
            targetId: "tour-custom-skills",
            title: "Custom Personas",
            content: "Upload JSON definitions to create your own specialized expert agents."
          }
        ]} 
      />
    </div>
  )
}
