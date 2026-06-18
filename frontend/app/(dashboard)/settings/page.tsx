'use client'
import { useEffect, useState } from 'react'
import { apiClient } from '@/lib/api'
import GuidedTour from '@/components/GuidedTour'
import { useNotifications } from '@/lib/notifications'

interface CustomSkill {
  id: number
  name: string
  role: 'lead' | 'reviewer'
  is_global: boolean
  created_at: string
}

export default function SettingsPage() {
  const { showAlert, showConfirm } = useNotifications()
  const [freeTier, setFreeTier] = useState(false)
  const [geminiApiKey, setGeminiApiKey] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')

  // Skill State
  const [skills, setPersonas] = useState<CustomSkill[]>([])

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

  async function handleDeleteSkill(id: number) {
    showConfirm(
      'Delete Skill',
      'Are you sure you want to delete this custom skill?',
      async () => {
        await apiClient.delete(`/user/skills/${id}`)
        setPersonas(skills.filter(s => s.id !== id))
      }
    )
  }

  async function handleResetTours() {
    showConfirm(
      'Reset Onboarding',
      'This will show all onboarding guides again. Continue?',
      async () => {
        setSaving(true)
        try {
          await apiClient.patch('/user/settings', { tour_flags: {} })
          showAlert('Success', 'Onboarding tours have been reset!')
        } catch {
          showAlert('Error', 'Failed to reset tours.')
        } finally {
          setSaving(false)
        }
      }
    )
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
        
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="text-sm font-bold uppercase tracking-widest text-muted-foreground">Expert Skills Library</h3>
            <p className="text-[10px] text-muted-foreground italic">Synced from Google Drive</p>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
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
                {/* <button 
                  onClick={() => handleDeleteSkill(skill.id)}
                  className="p-2 text-muted-foreground hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                </button> */}
              </div>
            ))}
            {skills.length === 0 && (
              <div className="col-span-full py-12 text-center border-2 border-dashed border-border rounded-2xl bg-muted/10">
                <p className="text-sm text-muted-foreground italic">No custom skills found in your library.</p>
              </div>
            )}
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
            content: "View your specialized expert agents. These are automatically synced from your Google Drive folder."
          }
        ]} 
      />
    </div>
  )
}
