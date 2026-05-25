'use client'
import { useEffect, useRef, useState } from 'react'
import ReactMarkdown from 'react-markdown'

interface Props {
  projectId: string
  endpoint: string
  body?: Record<string, unknown>
  onComplete: (content: string, status?: string) => void
  onError: (msg: string) => void
}

export default function StreamingOutput({ projectId, endpoint, body, onComplete, onError }: Props) {
  const [text, setText] = useState('')
  const [streaming, setStreaming] = useState(false)
  const [done, setDone] = useState(false)
  const [lastStatus, setLastStatus] = useState<string | undefined>()
  const accumulatedRef = useRef('')

  useEffect(() => {
    const controller = new AbortController()
    const token = localStorage.getItem('token')
    setStreaming(true)
    setText('')
    accumulatedRef.current = ''
    setLastStatus(undefined)

    const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api'

    fetch(`${BASE_URL}${endpoint}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream',
        Authorization: `Bearer ${token ?? ''}`,
      },
      body: JSON.stringify(body ?? {}),
      signal: controller.signal,
    }).then(async res => {
      if (!res.ok) {
        const err = await res.json()
        onError(err.message ?? 'Generation failed')
        setStreaming(false)
        return
      }

      const reader = res.body!.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      while (true) {
        const { done: streamDone, value } = await reader.read()
        if (streamDone) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() ?? ''

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue
          const payload = line.slice(6)
          if (payload === '[DONE]') {
            setDone(true)
            setStreaming(false)
            onComplete(accumulatedRef.current, lastStatus)
            return
          }
          try {
            const parsed = JSON.parse(payload)
            if (parsed.error) {
              onError(parsed.error)
              setStreaming(false)
              return
            }
            if (parsed.text) {
              accumulatedRef.current += parsed.text
              setText(prev => prev + parsed.text)
            }
            if (parsed.status) {
              setLastStatus(parsed.status)
            }
          } catch {
            // malformed chunk, skip
          }
        }
      }
    }).catch(err => {
      if (err.name === 'AbortError') return
      onError(err.message ?? 'Network error')
      setStreaming(false)
    })

    return () => controller.abort()
  }, [endpoint, JSON.stringify(body), onComplete, onError]) // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="relative group">
      <div className="border border-border rounded-xl p-6 min-h-[300px] bg-surface-hover/50 overflow-auto max-h-[500px] shadow-inner">
        {text ? (
          <div className="animate-in fade-in duration-300 prose prose-sm dark:prose-invert max-w-none break-words break-all">
            <ReactMarkdown>{text}</ReactMarkdown>
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center py-20 text-muted">
            {streaming ? (
              <>
                <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin mb-3"></div>
                <p className="font-medium">AI is writing requirements...</p>
              </>
            ) : (
              <p>Waiting for generation to start...</p>
            )}
          </div>
        )}
      </div>
      {streaming && (
        <div className="absolute top-4 right-4 flex items-center gap-2 bg-surface border border-border px-2 py-1 rounded-md shadow-sm">
          <span className="relative flex h-2 w-2">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
            <span className="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
          </span>
          <span className="text-[10px] font-bold text-muted uppercase tracking-widest">Streaming</span>
        </div>
      )}
      {done && (
        <div className="absolute top-4 right-4 flex items-center gap-2 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 px-2 py-1 rounded-md shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" className="text-emerald-600 dark:text-emerald-400"><polyline points="20 6 9 17 4 12"/></svg>
          <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">
            {lastStatus === 'reviewing' ? 'Draft Complete — Reviewing' : 'Complete'}
          </span>
        </div>
      )}
    </div>
  )
}
