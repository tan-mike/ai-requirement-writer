const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api'

function getToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem('token')
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const token = getToken()
  const headers: Record<string, string> = {
    Accept: 'application/json',
  }
  if (token) headers['Authorization'] = `Bearer ${token}`
  
  let fetchBody: BodyInit | undefined | null = undefined
  if (body !== undefined) {
    if (body instanceof FormData) {
      // Don't set Content-Type, browser will set it with boundary
      fetchBody = body
    } else {
      headers['Content-Type'] = 'application/json'
      fetchBody = JSON.stringify(body)
    }
  }

  const controller = new AbortController()
  const timeoutId = setTimeout(() => controller.abort(), 60000)

  try {
    const res = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: fetchBody,
      signal: controller.signal,
    })

    let data: any = {}
    const contentType = res.headers?.get('Content-Type')
    if (contentType && contentType.includes('application/json')) {
      data = await res.json()
    } else {
      const text = await res.text()
      data = { message: text || `Request failed with status ${res.status}` }
    }

    if (!res.ok) {
      if (res.status === 401) {
        if (typeof window !== 'undefined') {
          localStorage.removeItem('token')
          localStorage.removeItem('user')
          window.location.href = '/login'
        }
      }
      // Extract Laravel validation errors if present
      let message = data.message || `Request failed with status ${res.status}`
      if (data.errors) {
        const firstError = Object.values(data.errors)[0]
        if (Array.isArray(firstError)) message = firstError[0]
      }
      throw new Error(message)
    }
    return data as T
  } finally {
    clearTimeout(timeoutId)
  }
}

export const apiClient = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body: unknown) => request<T>('POST', path, body),
  patch: <T>(path: string, body: unknown) => request<T>('PATCH', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
