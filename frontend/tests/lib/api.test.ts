import { describe, it, expect, vi, beforeEach } from 'vitest'
import { apiClient } from '@/lib/api'

describe('apiClient', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
    localStorage.clear()
  })

  const mockJsonSuccess = (data: any) => ({
    ok: true,
    status: 200,
    headers: { get: (name: string) => name === 'Content-Type' ? 'application/json' : null },
    json: async () => data,
  })

  const mockJsonError = (status: number, data: any) => ({
    ok: false,
    status,
    headers: { get: (name: string) => name === 'Content-Type' ? 'application/json' : null },
    json: async () => data,
  })

  it('sends Authorization header when token exists in localStorage', async () => {
    localStorage.setItem('token', 'test-token-123')
    vi.mocked(fetch).mockResolvedValue(mockJsonSuccess({ data: [] }) as Response)

    await apiClient.get('/projects')

    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/projects'),
      expect.objectContaining({
        headers: expect.objectContaining({
          Authorization: 'Bearer test-token-123',
        }),
      })
    )
  })

  it('does not send Authorization header when no token', async () => {
    vi.mocked(fetch).mockResolvedValue(mockJsonSuccess({}) as Response)

    await apiClient.post('/auth/login', { email: 'a@b.com', password: 'pw' })

    const callArgs = vi.mocked(fetch).mock.calls[0][1] as RequestInit
    expect((callArgs.headers as Record<string, string>)['Authorization']).toBeUndefined()
  })

  it('throws an error when response is not ok', async () => {
    vi.mocked(fetch).mockResolvedValue(mockJsonError(401, { message: 'Unauthorized' }) as Response)

    await expect(apiClient.get('/projects')).rejects.toThrow('Unauthorized')
  })

  it('extracts first error from Laravel validation errors object', async () => {
    const laravelError = {
      message: 'The given data was invalid.',
      errors: {
        name: ['The name field is required.'],
        type: ['The type field is required.']
      }
    }
    vi.mocked(fetch).mockResolvedValue(mockJsonError(422, laravelError) as Response)

    await expect(apiClient.post('/projects', {})).rejects.toThrow('The name field is required.')
  })

  it('handles FormData without setting Content-Type', async () => {
    vi.mocked(fetch).mockResolvedValue(mockJsonSuccess({ id: 1 }) as Response)
    
    const formData = new FormData()
    formData.append('name', 'Test')
    
    await apiClient.post('/projects', formData)
    
    const callArgs = vi.mocked(fetch).mock.calls[0][1] as RequestInit
    expect((callArgs.headers as Record<string, string>)['Content-Type']).toBeUndefined()
    expect(callArgs.body).toBeInstanceOf(FormData)
  })
})
