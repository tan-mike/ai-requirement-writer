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

export default function ChatPage({ params }: { params: Promise<{ id: string, feature_id: string }> }) {
  const { id: projectId, feature_id: featureId } = use(params)
  const router = useRouter()
  const [feature, setFeature] = useState<{ status: string, name: string } | null>(null)
  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [streamingContent, setStreamingContent] = useState('')
  const [readyForBrd, setReadyForBrd] = useState(false)
  const [hasError, setHasError] = useState(false)
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollIntoView({ behavior: 'smooth' })
    }
  }, [messages, streamingContent])

  useEffect(() => {
    let interval: NodeJS.Timeout

    const fetchData = () => {
      Promise.all([
        apiClient.get<{ data: { status: string, name: string } }>(`/features/${featureId}`),
        apiClient.get<{ data: Message[] }>(`/features/${featureId}/messages`)
      ]).then(([featRes, msgRes]) => {
        setFeature(featRes.data)
        setMessages(msgRes.data)
        
        // Features are ready once created, unlike legacy projects with repo ingestion
        setLoading(false)
        if (msgRes.data.length === 0) {
          triggerInitialMessage()
        } else {
          // Check if we need to resume (last message is from user)
          const last = msgRes.data[msgRes.data.length - 1]
          if (last.role === 'user' && !readyForBrd) {
            // We could auto-resume here, but let's just make sure 
            // handleSendInternal can handle an empty message if we want to 'retry'
            // or just allow user to type something else.
          }
        }
      })
      .catch(err => {
        console.error(err)
        setLoading(false)
      })
    }

    fetchData()

    return () => clearInterval(interval)
  }, [featureId])

  const [started, setStarted] = useState(false)

  async function triggerInitialMessage() {
    if (started) return
    setStarted(true)
    await handleSendInternal("Hello! I'm ready to start the discovery interview.")
  }

  async function handleRetry() {
    const lastUserMsg = [...messages].reverse().find(m => m.role === 'user')?.content
    if (!lastUserMsg) return
    setHasError(false)
    await handleSendInternal(lastUserMsg, true)
  }

  async function handleRestart() {
    if (!confirm('Are you sure you want to restart? This will clear the entire conversation history.')) return
    setLoading(true)
    try {
      await apiClient.delete(`/features/${featureId}/messages`)
      setMessages([])
      setReadyForBrd(false)
      setStarted(false)
      triggerInitialMessage()
    } catch (err) {
      console.error(err)
      alert('Failed to restart interview')
    } finally {
      setLoading(false)
    }
  }

  async function handleSend(e: React.FormEvent) {
    e.preventDefault()
    if (!input.trim() || sending) return
    const msg = input
    setInput('')
    await handleSendInternal(msg)
  }

  async function handleSendInternal(userMsg: string, isResume = false) {
    setSending(true)
    setStreamingContent('')
    setHasError(false)

    if (!isResume) {
      setMessages(prev => [...prev, { id: Date.now(), role: 'user', content: userMsg }])
    }

    const token = localStorage.getItem('token')
    const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api'

    try {
      const res = await fetch(`${BASE_URL}/features/${featureId}/chat`, {
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
            const hasReadyToken = accumulated.includes('[READY_FOR_BRD]')
            setMessages(prev => [...prev, { id: Date.now(), role: 'assistant', content: accumulated }])
            setStreamingContent('')
            setSending(false)
            if (hasReadyToken) {
              setReadyForBrd(true)
            }
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
          }
        }
      }
    } catch (err) {
      console.error(err)
      const errorMsg = err instanceof Error ? err.message : 'Connection failed'
      setMessages(prev => [...prev, { id: Date.now(), role: 'assistant', content: `Error: ${errorMsg}. Please try again.` }])
      setSending(false)
      setHasError(true)
    }
  }

  if (loading) return (
    <div className="flex flex-col items-center justify-center py-20 text-muted">
      <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
      <p className="font-medium">Loading conversation...</p>
    </div>
  )

  return (
    <div className="flex flex-col h-[calc(100vh-180px)] max-w-4xl mx-auto">
      <Link href={`/projects/${projectId}/features/${featureId}`} className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors mb-6 group w-fit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 transition-transform group-hover:-translate-x-1"><path d="m15 18-6-6 6-6"/></svg>
        Back to Feature
      </Link>

      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Discovery Interview: {feature?.name}</h1>
          <p className="text-sm text-muted-foreground mt-1">Answer the AI's questions to build your requirements.</p>
        </div>
        <div className="flex items-center gap-3">
            {messages.length > 0 && !readyForBrd && (
                <button 
                    onClick={handleRestart}
                    className="text-xs font-bold text-muted-foreground hover:text-red-500 transition-colors flex items-center gap-1"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    Restart
                </button>
            )}
            <div className="px-3 py-1 bg-primary/10 text-primary text-xs font-bold rounded-full uppercase tracking-widest border border-primary/20">
                AI Assistant Active
            </div>
        </div>
      </div>
      
      <div className="flex-1 overflow-y-auto space-y-6 mb-6 pr-4 scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent">
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

        {(!sending && !readyForBrd && (hasError || (messages.length > 0 && messages[messages.length - 1].role === 'user'))) && (
            <div className="flex justify-center py-4 animate-in slide-in-from-bottom-2">
                <button 
                    onClick={handleRetry}
                    className="bg-indigo-50 text-indigo-600 border border-indigo-200 px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-indigo-100 transition-all flex items-center gap-2 shadow-sm"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    {hasError ? 'Retry last message' : 'Resume Interview'}
                </button>
            </div>
        )}
        
        {readyForBrd && (
          <div className="flex flex-col items-center gap-4 py-8 animate-in zoom-in duration-500">
            <div className="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-6 text-center max-w-md shadow-lg shadow-emerald-500/5">
                <div className="w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-md shadow-emerald-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3 className="text-lg font-bold text-foreground mb-2">Discovery Complete!</h3>
                <p className="text-sm text-muted-foreground mb-6">I have all the details I need to construct the requirements for this feature.</p>
                <Link 
                    href={`/projects/${projectId}/features/${featureId}`}
                    className="btn-primary w-full py-3 flex items-center justify-center gap-2 shadow-lg shadow-primary/20"
                >
                    <span>Proceed to Requirements</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </Link>
            </div>
            <button 
                onClick={() => setReadyForBrd(false)}
                className="text-xs text-muted-foreground hover:text-foreground underline transition-colors"
            >
                Wait, I have more questions
            </button>
          </div>
        )}

        <div ref={scrollRef} className="h-4" />
      </div>

      <div className={`card p-2 bg-surface/50 backdrop-blur-sm border-border shadow-lg transition-opacity duration-300 ${readyForBrd ? 'opacity-30 pointer-events-none grayscale' : ''}`}>
        <form onSubmit={handleSend} className="flex gap-2">
          <input
            type="text"
            value={input}
            onChange={e => setInput(e.target.value)}
            placeholder={readyForBrd ? "Interview complete" : "Type your message..."}
            className="flex-1 bg-transparent border-none px-4 py-3 text-sm focus:outline-none focus:ring-0 placeholder:text-muted-foreground"
            disabled={sending || readyForBrd}
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
