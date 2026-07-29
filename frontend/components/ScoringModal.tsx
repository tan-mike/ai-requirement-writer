'use client'
import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { apiClient } from '@/lib/api'

interface CriterionConfig {
  label: string
  question: string
  anchors: Record<string, string>
  tags: Record<string, string>
}

interface ScoringConfig {
  low_score_threshold: number
  criteria: Record<string, CriterionConfig>
}

interface CriterionScore {
  score: number | null
  tags: string[]
}

interface Props {
  featureId: string
  draftId: number
  onClose: () => void
  onApproved: () => void
}

export default function ScoringModal({ featureId, draftId, onClose, onApproved }: Props) {
  const [config, setConfig] = useState<ScoringConfig | null>(null)
  const [loadError, setLoadError] = useState('')
  const [scores, setScores] = useState<Record<string, CriterionScore>>({})
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')

  useEffect(() => {
    apiClient.get<{ data: ScoringConfig }>('/scoring-config')
      .then(res => {
        setConfig(res.data)
        const init: Record<string, CriterionScore> = {}
        for (const key of Object.keys(res.data.criteria)) init[key] = { score: null, tags: [] }
        setScores(init)
      })
      .catch(() => setLoadError('Could not load the scoring rubric. Please retry.'))
  }, [])

  const threshold = config?.low_score_threshold ?? 2

  function setScore(key: string, value: number) {
    setScores(prev => ({
      ...prev,
      // Clear any previously selected issue tags when the score is raised above
      // the threshold — the backend rejects tags on high scores, and the tag
      // picker is hidden so the user could not remove them manually.
      [key]: { score: value, tags: value <= threshold ? (prev[key]?.tags ?? []) : [] },
    }))
  }

  function toggleTag(key: string, tag: string) {
    setScores(prev => {
      const cur = prev[key]?.tags ?? []
      const tags = cur.includes(tag) ? cur.filter(t => t !== tag) : [...cur, tag]
      return { ...prev, [key]: { ...prev[key], tags } }
    })
  }

  const needsTags = (s: CriterionScore) => s.score !== null && s.score <= threshold

  const isValid = config !== null && Object.keys(config.criteria).every(key => {
    const s = scores[key]
    if (!s || s.score === null) return false
    if (needsTags(s) && s.tags.length === 0) return false
    return true
  })

  async function handleSubmit() {
    if (!config || !isValid) return
    setSubmitting(true)
    setSubmitError('')
    try {
      const payload = {
        scores: Object.fromEntries(
          Object.entries(scores).map(([k, v]) => [k, { score: v.score, tags: v.tags }])
        ),
      }
      await apiClient.post(`/features/${featureId}/drafts/${draftId}/approve`, payload)
      onApproved()
    } catch (err) {
      setSubmitError(err instanceof Error ? err.message : 'Approval failed')
    } finally {
      setSubmitting(false)
    }
  }

  const modal = (
    <div className="fixed inset-0 bg-background/95 backdrop-blur-sm z-[100] flex items-center justify-center p-6 animate-in fade-in duration-200">
      <div className="bg-card border border-border rounded-2xl p-8 max-w-lg w-full shadow-2xl animate-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
        <h3 className="text-xl font-bold mb-1">Score this draft</h3>
        <p className="text-sm text-muted-foreground mb-6">
          Your rating is required before approval and feeds future quality improvements.
        </p>

        {loadError && (
          <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mb-4">
            <p className="text-red-700 dark:text-red-400 text-sm font-medium">{loadError}</p>
          </div>
        )}

        {config && (
          <div className="space-y-6">
            {Object.entries(config.criteria).map(([key, criterion]) => {
              const s = scores[key] ?? { score: null, tags: [] }
              return (
                <div key={key} className="border border-border rounded-xl p-4">
                  <p className="text-sm font-bold mb-1">{criterion.label}</p>
                  <p className="text-xs text-muted-foreground mb-1">{criterion.question}</p>
                  <p className="text-[10px] text-muted-foreground/70 mb-3">
                    1 = {criterion.anchors['1']} · 5 = {criterion.anchors['5']}
                  </p>

                  <div className="flex gap-1.5" role="radiogroup" aria-label={criterion.label}>
                    {[1, 2, 3, 4, 5].map(n => (
                      <button
                        key={n}
                        type="button"
                        role="radio"
                        aria-checked={s.score === n}
                        aria-label={String(n)}
                        onClick={() => setScore(key, n)}
                        className={`w-9 h-9 rounded-lg text-sm font-bold border transition-all ${
                          s.score === n
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-surface border-border hover:border-primary/50'
                        }`}
                      >
                        {n}
                      </button>
                    ))}
                  </div>

                  {needsTags(s) && (
                    <div className="mt-3">
                      <p className="text-xs font-semibold text-muted-foreground mb-2">
                        What went wrong? <span className="text-red-500">Select at least one.</span>
                      </p>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(criterion.tags).map(([tagKey, tagLabel]) => {
                          const active = s.tags.includes(tagKey)
                          return (
                            <button
                              key={tagKey}
                              type="button"
                              aria-pressed={active}
                              onClick={() => toggleTag(key, tagKey)}
                              className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-all ${
                                active
                                  ? 'bg-primary/10 border-primary text-primary'
                                  : 'bg-surface border-border text-muted-foreground hover:border-primary/50'
                              }`}
                            >
                              {tagLabel}
                            </button>
                          )
                        })}
                      </div>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {submitError && (
          <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mt-4">
            <p className="text-red-700 dark:text-red-400 text-sm font-medium">{submitError}</p>
          </div>
        )}

        <div className="flex gap-3 mt-8">
          <button onClick={onClose} type="button" className="btn-secondary flex-1 py-3">Cancel</button>
          <button
            onClick={handleSubmit}
            type="button"
            disabled={!isValid || submitting || !!loadError}
            className="btn-primary flex-1 py-3 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitting ? 'Approving...' : 'Score & Approve'}
          </button>
        </div>
      </div>
    </div>
  )

  return createPortal(modal, document.body)
}
