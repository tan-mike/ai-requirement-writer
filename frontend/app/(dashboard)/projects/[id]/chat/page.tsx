'use client'
import { use, useEffect, useState, useRef } from 'react'
import { useRouter } from 'next/navigation'
import { apiClient } from '@/lib/api'

interface Message {
  id: number
  role: 'user' | 'assistant'
  content: string
}

export default function ChatPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params)
  const router = useRouter()
  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [streamingContent, setStreamingContent] = useState('')
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    apiClient.get<{ data: Message[] }>(`/projects/${id}/messages`)
      .then(res => setMessages(res.data))
      .catch(console.error)
      .finally(() => setLoading(false))
  }, [id])

  useEffect(() => {
    scrollRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, streamingContent])

  async function handleSend(e: React.FormEvent) {
    e.preventDefault()
    if (!input.trim() || sending) return

    const userMsg = input
    setInput('')
    setSending(true)
    setStreamingContent('')

    // Optimistically add user message
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

      if (!res.ok) throw new Error('Chat failed')

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
            if (parsed.text) {
              accumulated += parsed.text
              setStreamingContent(prev => prev + parsed.text)
            }
          } catch { /* ignore */ }
        }
      }
    } catch (err) {
      console.error(err)
      setSending(false)
    }
  }

  if (loading) return <p className="text-gray-500">Loading chat…</p>

  return (
    <div className="flex flex-col h-[calc(100vh-120px)] max-w-3xl">
      <h1 className="text-xl font-semibold mb-4">Discovery Interview</h1>
      
      <div className="flex-1 overflow-y-auto space-y-4 mb-4 pr-2">
        {messages.map(m => (
          <div key={m.id} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[80%] rounded-lg p-3 text-sm ${
              m.role === 'user' ? 'bg-blue-900 text-white' : 'bg-gray-100 text-gray-800'
            }`}>
              {m.content.replace('[READY_FOR_BRD]', '')}
            </div>
          </div>
        ))}
        {streamingContent && (
          <div className="flex justify-start">
            <div className="max-w-[80%] rounded-lg p-3 text-sm bg-gray-100 text-gray-800">
              {streamingContent.replace('[READY_FOR_BRD]', '')}
              <span className="animate-pulse">|</span>
            </div>
          </div>
        )}
        <div ref={scrollRef} />
      </div>

      <form onSubmit={handleSend} className="flex gap-2">
        <input
          type="text"
          value={input}
          onChange={e => setInput(e.target.value)}
          placeholder="Answer the AI's question..."
          className="flex-1 border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-900"
          disabled={sending}
        />
        <button
          type="submit"
          disabled={sending || !input.trim()}
          className="bg-blue-900 text-white px-4 py-2 rounded text-sm font-medium disabled:opacity-50"
        >
          Send
        </button>
      </form>
    </div>
  )
}
