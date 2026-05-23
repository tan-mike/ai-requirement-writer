'use client'
import { useState } from 'react'

interface Props {
  text: string
}

export default function InfoTooltip({ text }: Props) {
  const [show, setShow] = useState(false)

  return (
    <div className="relative inline-block ml-1.5 align-middle group">
      <button
        type="button"
        onMouseEnter={() => setShow(true)}
        onMouseLeave={() => setShow(false)}
        onClick={() => setShow(!show)}
        className="text-muted-foreground hover:text-primary transition-colors focus:outline-none"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      </button>

      {show && (
        <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-3 bg-foreground text-background text-[10px] leading-relaxed rounded-lg shadow-xl z-50 animate-in fade-in zoom-in-95 duration-200 pointer-events-none font-medium">
          {text}
          <div className="absolute top-full left-1/2 -translate-x-1/2 border-8 border-transparent border-t-foreground" />
        </div>
      )}
    </div>
  )
}
