'use client'
import { use, useCallback, useEffect, useState } from 'react'
import Link from 'next/link'
import { apiClient } from '@/lib/api'
import StreamingOutput from '@/components/StreamingOutput'
import DraftEditor from '@/components/DraftEditor'
import GenerationStrategy, { GenerationConfig } from '@/components/GenerationStrategy'
import GuidedTour from '@/components/GuidedTour'
import CommitteeNotes from '@/components/CommitteeNotes'

interface Critique {
  id: number
  persona: { name: string }
  content: string
  status: string
}

interface Draft {
  id: number
  type: 'brd' | 'stories' | 'spec'
  version: number
  content: string | null
  status: 'drafting' | 'reviewing' | 'refining' | 'approved' | 'failed'
  critiques?: Critique[]
  reviewer_persona_ids?: number[]
}

interface DraftsResponse {
  data: {
    brd?: Draft[]
    stories?: Draft[]
    spec?: Draft[]
  }
}

interface ProjectResponse {
  data: {
    id: number
    name: string
    status: string
    lead_persona_id: number
    lead_persona?: { id: number; name: string }
  }
}

type Step = 'brd' | 'stories' | 'spec'

const STEPS: { key: Step; label: string; title: string }[] = [
  { key: 'brd', label: 'Business Requirements', title: 'Step 1 — BRD' },
  { key: 'stories', label: 'User Stories', title: 'Step 2 — User Stories' },
  { key: 'spec', label: 'Technical Spec', title: 'Step 3 — Technical Spec' },
]

export default function GeneratePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params)
  const [project, setProject] = useState<ProjectResponse['data'] | null>(null)
  const [drafts, setDrafts] = useState<Record<Step, Draft[]>>({ brd: [], stories: [], spec: [] })
  const [generating, setGenerating] = useState<Step | null>(null)
  const [synthesizing, setSynthesizing] = useState<number | null>(null)
  const [config, setConfig] = useState<GenerationConfig | null>(null)
  const [error, setError] = useState('')

  const loadData = useCallback(async () => {
    try {
      const [draftsRes, projectRes] = await Promise.all([
        apiClient.get<DraftsResponse>(`/projects/${id}/drafts`),
        apiClient.get<ProjectResponse>(`/projects/${id}`)
      ])
      setDrafts({
        brd: draftsRes.data.brd ?? [],
        stories: draftsRes.data.stories ?? [],
        spec: draftsRes.data.spec ?? [],
      })
      setProject(projectRes.data)
    } catch {
      setError('Failed to load project data. Please refresh.')
    }
  }, [id])

  useEffect(() => { loadData() }, [loadData])

  // Polling for reviewing drafts
  useEffect(() => {
    const hasReviewing = Object.values(drafts).flat().some(d => d.status === 'reviewing')
    if (hasReviewing) {
      const interval = setInterval(loadData, 5000)
      return () => clearInterval(interval)
    }
  }, [drafts, loadData])

  function latestDraft(step: Step): Draft | undefined {
    return drafts[step][0]
  }

  function isApproved(step: Step): boolean {
    return latestDraft(step)?.status === 'approved'
  }

  function canGenerate(step: Step): boolean {
    if (step === 'brd') return true
    if (step === 'stories') return isApproved('brd')
    if (step === 'spec') return isApproved('stories')
    return false
  }

  function startGeneration(step: Step, config: GenerationConfig) {
    setConfig(config)
    setGenerating(step)
    setError('')
  }

  async function handleComplete() {
    try {
      await apiClient.post(`/projects/${id}/complete`, {})
      loadData()
    } catch {
      setError('Failed to mark project as complete')
    }
  }

  function generateBody(step: Step): Record<string, unknown> {
    const brd = latestDraft('brd')
    const stories = latestDraft('stories')
    const baseBody = config ? { ...config } : {}
    if (step === 'stories' && brd) return { ...baseBody, brd_draft_id: brd.id }
    if (step === 'spec' && brd && stories) return { ...baseBody, brd_draft_id: brd.id, stories_draft_id: stories.id }
    return baseBody
  }

  return (
    <div className="max-w-4xl space-y-12 pb-20">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-2 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>

      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
        <div>
          <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground">Generate Requirements</h1>
          <p className="text-muted-foreground mt-2 text-lg">Build high-quality documentation with AI experts.</p>
        </div>

        <div className="flex-shrink-0">
          {project?.status === 'complete' ? (
            <div className="bg-emerald-100 text-emerald-700 px-6 py-3 rounded-xl font-bold border-2 border-emerald-200 flex items-center gap-2 shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Project Completed
            </div>
          ) : isApproved('brd') && (
            <button
              onClick={handleComplete}
              className="btn-primary flex items-center gap-2 w-full sm:w-auto justify-center"
            >
              Mark as Completed
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </button>
          )}
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded shadow-sm">
          <p className="text-red-700 text-sm font-medium">{error}</p>
        </div>
      )}

      <div className="space-y-16" id="tour-generation-steps">
        {STEPS.map((step) => {
          const draft = latestDraft(step.key)
          const locked = !canGenerate(step.key)
          const isGenerating = generating === step.key
          const isSynthesizing = synthesizing === draft?.id

          return (
            <section key={step.key} className={`relative w-full min-w-0 ${locked ? 'opacity-40 grayscale pointer-events-none' : ''}`}>
              <div className="flex items-center justify-between mb-6">
                <div className="space-y-1">
                  <h2 className="text-2xl font-bold">{step.title}</h2>
                  <p className="text-sm text-muted-foreground">{step.label}</p>
                </div>
                {isApproved(step.key) && (
                  <div className="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold border border-green-200">
                    Approved
                  </div>
                )}
              </div>

              {!isGenerating && !isSynthesizing && (!draft || draft.status === 'failed') && (
                <div id="tour-generation-strategy">
                  <GenerationStrategy 
                    label={`Start Generating ${step.label}`}
                    onStart={(cfg) => startGeneration(step.key, cfg)}
                    disabled={locked}
                    leadPersonaName={project?.lead_persona?.name}
                  />
                </div>
              )}

              {isGenerating && (
                <StreamingOutput
                  projectId={id}
                  endpoint={`/projects/${id}/generate/${step.key}`}
                  body={generateBody(step.key)}
                  onComplete={() => {
                    setGenerating(null)
                    loadData()
                  }}
                  onError={msg => {
                    setError(msg)
                    setGenerating(null)
                  }}
                />
              )}

              {isSynthesizing && draft && (
                <div className="space-y-4">
                  <div className="bg-blue-50 border border-blue-200 p-3 rounded text-sm text-blue-700 flex items-center gap-2">
                    <div className="w-4 h-4 border-2 border-blue-700 border-t-transparent rounded-full animate-spin"></div>
                    Synthesizing draft with committee feedback...
                  </div>
                  <StreamingOutput
                    projectId={id}
                    endpoint={`/projects/${id}/drafts/${draft.id}/synthesize`}
                    body={{}}
                    onComplete={() => {
                      setSynthesizing(null)
                      loadData()
                    }}
                    onError={msg => {
                      setError(msg)
                      setSynthesizing(null)
                    }}
                  />
                </div>
              )}

              {!isGenerating && !isSynthesizing && draft && draft.status !== 'failed' && (
                <div className="space-y-6">
                  {draft.status === 'reviewing' && (
                    <div className="bg-yellow-50 border border-yellow-200 p-4 rounded-lg flex items-center justify-between animate-pulse">
                      <div className="flex items-center gap-3">
                        <div className="w-5 h-5 border-2 border-yellow-600 border-t-transparent rounded-full animate-spin"></div>
                        <span className="text-sm font-medium text-yellow-800">Reviewers are auditing the draft...</span>
                      </div>
                      <span className="text-xs text-yellow-700 font-mono">
                        {(draft.critiques ?? []).length} / {draft.reviewer_persona_ids?.length ?? 0} Done
                      </span>
                    </div>
                  )}

                  {draft.status === 'refining' && (draft.critiques ?? []).length > 0 && (
                    <div className="bg-indigo-50 border border-indigo-200 p-4 rounded-lg flex items-center justify-between">
                      <div className="flex items-center gap-3 text-indigo-800">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/></svg>
                        <span className="text-sm font-bold">
                          Committee Review Complete!
                        </span>
                      </div>
                      <button 
                        onClick={() => setSynthesizing(draft.id)}
                        className="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-indigo-700 shadow-sm transition-all active:scale-95"
                      >
                        Synthesize & Finalize
                      </button>
                    </div>
                  )}

                  <div className="space-y-6">
                    {draft.critiques && draft.critiques.length > 0 && (
                      <CommitteeNotes critiques={draft.critiques} />
                    )}

                    <div className="min-w-0">
                      <DraftEditor
                        projectId={id}
                        draft={draft}
                        onApproved={loadData}
                      />
                    </div>
                  </div>

                  {draft.status === 'approved' && (
                    <button
                      onClick={() => setGenerating(step.key)}
                      className="text-xs text-muted-foreground hover:text-primary underline transition-colors"
                    >
                      Regenerate from scratch
                    </button>
                  )}
                </div>
              )}
            </section>
          )
        })}
      </div>

      {drafts.brd.length === 0 && (
        <GuidedTour 
          pageKey="generation" 
          mode="sequential"
          steps={[
            {
              targetId: "tour-generation-strategy",
              title: "Configure Agents",
              content: "Pick reviewers and add context before starting the generation."
            },
            {
              targetId: "tour-generation-steps",
              title: "Build the Backlog",
              content: "Follow the 3-step process: Requirements (BRD), User Stories, and then the Technical Spec."
            }
          ]} 
        />
      )}
    </div>
  )
}
