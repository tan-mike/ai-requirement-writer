import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import ScoringModal from '@/components/ScoringModal'
import { apiClient } from '@/lib/api'

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
  default: { get: vi.fn(), post: vi.fn() },
}))

const configResponse = {
  data: {
    low_score_threshold: 2,
    criteria: {
      domain_accuracy: {
        label: 'Domain Accuracy',
        question: 'How accurate?',
        anchors: { '1': 'bad', '5': 'good' },
        tags: { hallucinated_feature: 'Hallucinated feature' },
      },
      structural_completeness: {
        label: 'Structural Completeness',
        question: 'How complete?',
        anchors: { '1': 'bad', '5': 'good' },
        tags: { missing_section: 'Missing section' },
      },
    },
  },
}

beforeEach(() => {
  vi.clearAllMocks()
  ;(apiClient.get as ReturnType<typeof vi.fn>).mockResolvedValue(configResponse)
  ;(apiClient.post as ReturnType<typeof vi.fn>).mockResolvedValue({ data: {} })
})

function renderModal() {
  return render(
    <ScoringModal featureId="1" draftId={42} onClose={() => {}} onApproved={() => {}} />
  )
}

describe('ScoringModal', () => {
  it('renders criteria from config', async () => {
    renderModal()
    expect(await screen.findByText('Domain Accuracy')).toBeInTheDocument()
    expect(screen.getByText('Structural Completeness')).toBeInTheDocument()
  })

  it('disables submit until every criterion is scored', async () => {
    renderModal()
    await screen.findByText('Domain Accuracy')

    const submit = screen.getByRole('button', { name: /score & approve/i })
    expect(submit).toBeDisabled()

    // Score only one criterion — still disabled
    fireEvent.click(screen.getAllByRole('radio', { name: '4' })[0])
    expect(submit).toBeDisabled()
  })

  it('requires a tag when a criterion scores at or below the threshold', async () => {
    renderModal()
    await screen.findByText('Domain Accuracy')

    // Give domain_accuracy a low score (1) and structural a high score (5)
    fireEvent.click(screen.getAllByRole('radio', { name: '1' })[0])
    fireEvent.click(screen.getAllByRole('radio', { name: '5' })[1])

    const submit = screen.getByRole('button', { name: /score & approve/i })
    expect(screen.getByText(/select at least one/i)).toBeInTheDocument()
    expect(submit).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: 'Hallucinated feature' }))
    expect(submit).toBeEnabled()
  })

  it('submits the scores payload on approve', async () => {
    const onApproved = vi.fn()
    render(
      <ScoringModal featureId="1" draftId={42} onClose={() => {}} onApproved={onApproved} />
    )
    await screen.findByText('Domain Accuracy')

    fireEvent.click(screen.getAllByRole('radio', { name: '4' })[0])
    fireEvent.click(screen.getAllByRole('radio', { name: '5' })[1])
    fireEvent.click(screen.getByRole('button', { name: /score & approve/i }))

    await waitFor(() => {
      expect(apiClient.post).toHaveBeenCalledWith(
        '/features/1/drafts/42/approve',
        {
          scores: {
            domain_accuracy: { score: 4, tags: [] },
            structural_completeness: { score: 5, tags: [] },
          },
        }
      )
    })
    expect(onApproved).toHaveBeenCalled()
  })
})
