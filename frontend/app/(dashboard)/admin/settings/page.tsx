'use client'
import { useState, useEffect } from 'react'
import api from '@/lib/api'
import { useNotifications } from '@/lib/notifications'

export default function AdminSettingsPage() {
  const { showAlert } = useNotifications()
  const [settings, setSettings] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    fetchSettings()
  }, [])

  const fetchSettings = async () => {
    try {
      const res = await api.get<{ data: Record<string, string> }>('/admin/settings')
      setSettings(res.data)
    } catch (e) {
      console.error(e)
    } finally {
      setLoading(false)
    }
  }

  const saveSettings = async () => {
    setSaving(true)
    try {
      await api.patch('/admin/settings', { settings })
      showAlert('Success', 'Settings saved successfully.')
    } catch (e) {
      console.error(e)
      showAlert('Error', 'Failed to save settings.')
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <div>Loading settings...</div>

  const creationDisabled = settings.creation_disabled === 'true' || settings.creation_disabled === '1'

  return (
    <div className="space-y-6 max-w-xl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-foreground">Global Settings</h1>
        <p className="text-sm text-muted-foreground mt-1">Manage platform-wide configuration</p>
      </div>

      <div className="space-y-4">
        <div className="flex items-center justify-between p-4 border border-border rounded-lg bg-surface">
          <div>
            <h3 className="font-medium text-foreground">Disable User Project Creation</h3>
            <p className="text-sm text-muted-foreground mt-1">
              If enabled, regular users cannot create their own projects. They can only create requirements inside Curated Projects.
            </p>
          </div>
          <button 
            onClick={() => setSettings({ ...settings, creation_disabled: creationDisabled ? 'false' : 'true' })}
            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${creationDisabled ? 'bg-primary' : 'bg-muted/30'}`}
          >
            <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${creationDisabled ? 'translate-x-6' : 'translate-x-1'}`} />
          </button>
        </div>

        <button 
          onClick={saveSettings}
          disabled={saving}
          className="px-4 py-2 bg-primary text-primary-foreground rounded-lg font-medium hover:bg-primary/90 disabled:opacity-50 transition-colors"
        >
          {saving ? 'Saving...' : 'Save Changes'}
        </button>
      </div>
    </div>
  )
}
