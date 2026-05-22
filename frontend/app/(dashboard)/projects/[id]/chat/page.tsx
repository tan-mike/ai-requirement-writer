'use client'
import { use, useEffect, useState, useRef } from 'react'
import { useRouter } from 'next/navigation'
import ReactMarkdown from 'react-markdown'
import Link from 'next/link'
import { apiClient } from '@/lib/api'

interface Message {
  id: number
  role: 'user' | 'assistant'
  content: string
}

export default function ChatPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params)
  const router = useRouter()
  const [project, setProject] = useState<{ status: string } | null>(null)
  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [streamingContent, setStreamingContent] = useState('')
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    let interval: NodeJS.Timeout

    const fetchData = () => {
      Promise.all([
        apiClient.get<{ data: { status: string } }>(`/projects/${id}`),
        apiClient.get<{ data: Message[] }>(`/projects/${id}/messages`)
      ]).then(([projRes, msgRes]) => {
        setProject(projRes.data)
        setMessages(msgRes.data)
        
        if (projRes.data.status === 'ready') {
          setLoading(false)
          if (msgRes.data.length === 0) {
            triggerInitialMessage()
          }
          if (interval) clearInterval(interval)
        } else if (projRes.data.status === 'error') {
          setLoading(false)
          if (interval) clearInterval(interval)
        } else {
          // Still processing, keep polling but allow UI to show
          setLoading(false)
        }
      })
      .catch(err => {
        console.error(err)
        setLoading(false)
      })
    }

    fetchData()

    // Poll if not ready or error
    interval = setInterval(() => {
      apiClient.get<{ data: { status: string } }>(`/projects/${id}`).then(res => {
        if (res.data.status === 'ready' || res.data.status === 'error') {
          clearInterval(interval)
          fetchData()
        }
      })
    }, 3000)

    return () => clearInterval(interval)
  }, [id])

  const [started, setStarted] = useState(false)

  async function triggerInitialMessage() {
    if (started) return
    setStarted(true)
    await handleSendInternal("Hello! I'm ready to start the discovery interview.")
  }

  async function handleSend(e: React.FormEvent) {
    e.preventDefault()
    if (!input.trim() || sending) return
    const msg = input
    setInput('')
    await handleSendInternal(msg)
  }

  async function handleSendInternal(userMsg: string) {
    if (project?.status !== 'ready') return
    setSending(true)
    setStreamingContent('')

    // Optimistically add user message if it's not the auto-start one 
    // (or just add it anyway for transparency)
    setMessages(prev => [...prev, { id: Date.now(), role: 'user', content: userMsg }])

    const token = localStorage.getItem('token')
    const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api'

    try {
      const res = await fetch(`${BASE_URL}/projects/${id}/chat`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
          Authorization: `Bearer ${token ?? ''}`,
        },
        body: JSON.stringify({ message: userMsg }),
      })

      if (!res.ok) {
        const errData = await res.json().catch(() => ({}))
        throw new Error(errData.message || 'Chat failed')
      }

      const reader = res.body!.getReader()
      const decoder = new TextDecoder()
      let accumulated = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        const chunk = decoder.decode(value, { stream: true })
        const lines = chunk.split('\n')

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue
          const payload = line.slice(6)
          
          if (payload === '[DONE]') {
            if (accumulated.includes('[READY_FOR_BRD]')) {
               router.push(`/projects/${id}/generate`)
               return
            }
            setMessages(prev => [...prev, { id: Date.now(), role: 'assistant', content: accumulated }])
            setStreamingContent('')
            setSending(false)
            return
          }

          try {
            const parsed = JSON.parse(payload)
            if (parsed.error) {
              throw new Error(parsed.error)
            }
            if (parsed.text) {
              accumulated += parsed.text
              setStreamingContent(prev => prev + parsed.text)
            }
          } catch (e) {
            if (e instanceof Error && e.message.includes('Chat failed')) throw e
            /* ignore JSON parse errors for non-json payloads */
          }
        }
      }
    } catch (err) {
      console.error(err)
      const errorMsg = err instanceof Error ? err.message : 'Connection failed'
      setMessages(prev => [...prev, { id: Date.now(), role: 'assistant', content: `Error: ${errorMsg}. Please try again.` }])
      setSending(false)
    }
  }

  if (loading) return (
    <div className="flex flex-col items-center justify-center py-20 text-muted">
      <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
      <p className="font-medium">Loading conversation...</p>
    </div>
  )

  if (project?.status === 'processing' || project?.status === 'draft') return (
    <div className="flex flex-col items-center justify-center py-20 text-muted">
      <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
      <p className="font-medium text-foreground">Preparing project context...</p>
      <p className="text-sm mt-2 text-muted-foreground text-center max-w-xs">We are analyzing your repository or files to provide a better interview experience. This usually takes less than a minute.</p>
    </div>
  )

  if (project?.status === 'error' || project?.status === 'failed') return (
    <div className="flex flex-col items-center justify-center py-20 text-red-500">
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mb-4"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
      <p className="font-medium">Failed to prepare project context.</p>
      <button onClick={() => window.location.reload()} className="btn-secondary mt-4">Try again</button>
    </div>
  )

  return (
    <div className="flex flex-col h-[calc(100vh-180px)] max-w-4xl mx-auto">
      <Link href="/dashboard" className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Dashboard
      </Link>

      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Discovery Interview</h1>
          <p className="text-sm text-muted-foreground mt-1">Answer the AI's questions to build your requirements.</p>
        </div>
        <div className="px-3 py-1 bg-primary/10 text-primary text-xs font-bold rounded-full uppercase tracking-widest border border-primary/20">
          AI Assistant Active
        </div>
      </div>
      
      <div className="flex-1 overflow-y-auto space-y-6 mb-6 pr-4 scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent">
        {messages.length === 0 && (
          <div className="text-center py-10 opacity-50">
            <p className="text-sm italic">The interview will begin shortly...</p>
          </div>
        )}
        
        {messages.map(m => (
          <div key={m.id} className={`flex animate-in fade-in slide-in-from-bottom-2 duration-300 ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[85%] rounded-2xl px-5 py-3 text-sm leading-relaxed shadow-sm ${
              m.role === 'user' 
                ? 'bg-primary text-white rounded-tr-none' 
                : 'bg-surface border border-border text-foreground rounded-tl-none prose prose-sm dark:prose-invert max-w-none prose-p:leading-relaxed prose-pre:bg-background/50'
            }`}>
              <ReactMarkdown>
                {m.content.replace('[READY_FOR_BRD]', '')}
              </ReactMarkdown>
            </div>
          </div>
        ))}
        {streamingContent && (
          <div className="flex justify-start animate-in fade-in duration-200">
            <div className="max-w-[85%] rounded-2xl px-5 py-3 text-sm bg-surface border border-border text-foreground rounded-tl-none leading-relaxed shadow-sm prose prose-sm dark:prose-invert max-w-none prose-p:leading-relaxed prose-pre:bg-background/50">
              <ReactMarkdown>
                {streamingContent.replace('[READY_FOR_BRD]', '')}
              </ReactMarkdown>
              <span className="inline-block w-1.5 h-4 bg-primary/40 ml-1 animate-pulse align-middle"></span>
            </div>
          </div>
        )}
        <div ref={scrollRef} className="h-4" />
      </div>

      <div className="card p-2 bg-surface/50 backdrop-blur-sm border-border shadow-lg">
        <form onSubmit={handleSend} className="flex gap-2">
          <input
            type="text"
            value={input}
            onChange={e => setInput(e.target.value)}
            placeholder="Type your message..."
            className="flex-1 bg-transparent border-none px-4 py-3 text-sm focus:outline-none focus:ring-0 placeholder:text-muted-foreground"
            disabled={sending}
          />
          <button
            type="submit"
            disabled={sending || !input.trim()}
            className="bg-primary text-white p-3 rounded-xl disabled:opacity-50 transition-all hover:scale-105 active:scale-95 shadow-md shadow-primary/20"
            aria-label="Send message"
          >
            {sending ? (
              <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            ) : (
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            )}
          </button>
        </form>
      </div>
    </div>
  )
}
