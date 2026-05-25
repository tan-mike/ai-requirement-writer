'use client'
import { useEffect, useState, useCallback, useRef } from 'react'
import { createPortal } from 'react-dom'
import { apiClient } from '@/lib/api'

interface TourStep {
  targetId: string
  title: string
  content: string
  placement?: 'top' | 'bottom' | 'left' | 'right'
}

interface Props {
  pageKey: string
  mode: 'hotspots' | 'sequential'
  steps: TourStep[]
}

interface Rect {
  top: number
  left: number
  width: number
  height: number
}

export default function GuidedTour({ pageKey, mode, steps }: Props) {
  const [visible, setVisible] = useState(false)
  const [activeStep, setActiveStep] = useState(0)
  const [activeHotspot, setActiveHotspot] = useState<number | null>(null)
  const [targetRects, setTargetRects] = useState<Record<string, Rect>>({})
  const [dialogStyles, setDialogStyles] = useState<React.CSSProperties>({})
  const [loading, setLoading] = useState(true)
  const [mounted, setMounted] = useState(false)
  
  // Track if we've already manually dismissed in this session
  const wasDismissedManually = useRef(false)

  const updateRects = useCallback(() => {
    const newRects: Record<string, Rect> = {}
    steps.forEach(step => {
      const el = document.getElementById(step.targetId)
      if (el) {
        const r = el.getBoundingClientRect()
        newRects[step.targetId] = {
          top: r.top + window.scrollY,
          left: r.left + window.scrollX,
          width: r.width,
          height: r.height,
        }
      }
    })
    setTargetRects(newRects)
  }, [steps])

  const calculateDialogPosition = useCallback(() => {
    if (mode !== 'sequential' || !visible) return

    const currentStep = steps[activeStep]
    const el = document.getElementById(currentStep.targetId)
    if (!el) return

    const rect = el.getBoundingClientRect()
    const gap = 20
    const dialogWidth = 320
    const dialogHeight = 250 // Rough estimate
    
    // Relative to viewport for initial check, but styles are absolute (relative to document)
    let top = rect.bottom + gap + window.scrollY
    let left = rect.left + (rect.width / 2) - (dialogWidth / 2)

    // Viewport boundaries
    const padding = 20
    const viewportHeight = window.innerHeight
    const viewportWidth = window.innerWidth

    // Horizontal containment
    left = Math.max(padding, Math.min(left, viewportWidth - dialogWidth - padding))

    // Vertical flipping (if it falls off the bottom of the viewport, flip to top)
    // We check relative to viewport bottom
    if (rect.bottom + dialogHeight + gap > viewportHeight) {
      top = rect.top - dialogHeight - gap + window.scrollY
    }

    setDialogStyles({
      top: `${top}px`,
      left: `${left}px`,
      width: `${dialogWidth}px`,
    })
  }, [activeStep, mode, steps, visible])

  // SCROLL LOCK EFFECT
  useEffect(() => {
    if (visible) {
      const originalStyle = window.getComputedStyle(document.body).overflow
      document.body.style.overflow = 'hidden'
      return () => {
        document.body.style.overflow = originalStyle
      }
    }
  }, [visible])

  // AUTO-SCROLL EFFECT (Sequential Mode)
  useEffect(() => {
    if (visible && mode === 'sequential') {
      const currentStep = steps[activeStep]
      const el = document.getElementById(currentStep.targetId)
      if (el) {
        const rect = el.getBoundingClientRect()
        const viewportHeight = window.innerHeight
        
        // If center of highlighted area is off screen or too close to edges
        const centerY = rect.top + rect.height / 2
        if (centerY < 100 || centerY > viewportHeight - 100) {
          // Scroll so target is in top third
          const targetScrollY = window.scrollY + rect.top - (viewportHeight / 3)
          window.scrollTo({ top: Math.max(0, targetScrollY), behavior: 'smooth' })
          
          // Wait for smooth scroll to finish before updating measurements
          const timer = setTimeout(updateRects, 500)
          return () => clearTimeout(timer)
        }
      }
    }
  }, [activeStep, visible, mode, steps, updateRects])

  // INITIAL LOAD EFFECT
  useEffect(() => {
    setMounted(true)
    apiClient.get<{ data: { tour_flags: Record<string, boolean> } }>('/user/settings')
      .then(res => {
        if (!res.data.tour_flags?.[pageKey] && !wasDismissedManually.current) {
          setVisible(true)
          setTimeout(updateRects, 500)
        }
      })
      .finally(() => setLoading(false))
  }, [pageKey, updateRects])

  // LAYOUT LISTENERS (Only resize, since scroll is locked)
  useEffect(() => {
    if (!visible) return
    const handleResize = () => {
      updateRects()
      calculateDialogPosition()
    }
    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [visible, updateRects, calculateDialogPosition])

  useEffect(() => {
    if (visible) calculateDialogPosition()
  }, [activeStep, visible, calculateDialogPosition])

  const handleDismiss = async () => {
    if (!visible) return
    wasDismissedManually.current = true
    setVisible(false)
    setActiveStep(0)
    try {
      const res = await apiClient.get<{ data: { tour_flags: Record<string, boolean> } }>('/user/settings')
      const currentFlags = res.data.tour_flags ?? {}
      await apiClient.patch('/user/settings', {
        tour_flags: { ...currentFlags, [pageKey]: true }
      })
    } catch (err) {
      console.error('Failed to save tour progress', err)
    }
  }

  if (loading || !visible || !mounted) return null

  const currentStep = steps[activeStep]
  const currentRect = targetRects[currentStep.targetId]

  const tourContent = (
    <div className="fixed inset-0 z-[9999] pointer-events-none overflow-hidden">
      {/* Overlay Backdrop - Slightly more transparent (35% -> 30%) */}
      <div 
        className={`absolute inset-0 bg-slate-950/30 backdrop-blur-[2px] pointer-events-auto transition-opacity duration-500 ${visible ? 'opacity-100' : 'opacity-0'}`}
        onClick={handleDismiss} 
      />

      {mode === 'sequential' && currentRect && (
        <div className="absolute inset-0 pointer-events-none">
          {/* Spotlight Cutout */}
          <div 
            className="absolute bg-transparent transition-all duration-300 ease-in-out rounded-2xl shadow-[0_0_0_9999px_rgba(15,23,42,0.6)] z-10"
            style={{
              top: currentRect.top - 12,
              left: currentRect.left - 12,
              width: currentRect.width + 24,
              height: currentRect.height + 24,
            }}
          />

          {/* Dialog Box */}
          <div 
            className="absolute bg-surface border border-primary/20 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] p-8 pointer-events-auto z-20 animate-in fade-in zoom-in-95 slide-in-from-bottom-4 duration-300"
            style={dialogStyles}
          >
            <div className="space-y-5">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-black uppercase tracking-[0.2em] text-primary bg-primary/10 px-3 py-1 rounded-full">
                  Step {activeStep + 1} / {steps.length}
                </span>
                <button onClick={handleDismiss} className="text-muted-foreground hover:text-foreground transition-colors p-1">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
              </div>
              <div className="space-y-2">
                <h3 className="font-black text-lg tracking-tight text-foreground">{currentStep.title}</h3>
                <p className="text-sm leading-relaxed text-muted-foreground font-medium">{currentStep.content}</p>
              </div>
              <div className="flex gap-4 pt-2">
                <button 
                  onClick={handleDismiss}
                  className="flex-1 px-4 py-3 rounded-2xl text-xs font-bold text-muted-foreground hover:bg-muted transition-all"
                >
                  Skip
                </button>
                <button 
                  onClick={() => {
                    if (activeStep < steps.length - 1) {
                      setActiveStep(activeStep + 1)
                    } else {
                      handleDismiss()
                    }
                  }}
                  className="flex-1 bg-primary text-white px-4 py-3 rounded-2xl text-xs font-bold shadow-lg shadow-primary/25 transition-all hover:bg-primary/90 hover:scale-[1.02] active:scale-[0.98]"
                >
                  {activeStep < steps.length - 1 ? 'Next' : 'Finish'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {mode === 'hotspots' && (
        <div className="absolute inset-0 pointer-events-none">
          {steps.map((step, i) => {
            const r = targetRects[step.targetId]
            if (!r) return null
            const isOpen = activeHotspot === i

            return (
              <div key={i} className="absolute z-20 pointer-events-auto" style={{ top: r.top + (r.height / 2), left: r.left + (r.width / 2) }}>
                {/* Pulsing Dot */}
                <button 
                  onClick={(e) => {
                    e.stopPropagation()
                    setActiveHotspot(isOpen ? null : i)
                  }}
                  className="relative flex items-center justify-center w-10 h-10 -translate-x-1/2 -translate-y-1/2 focus:outline-none group"
                >
                  <span className="absolute inset-0 rounded-full bg-primary animate-ping opacity-30 group-hover:opacity-50"></span>
                  <span className="relative w-5 h-5 rounded-full bg-primary border-4 border-white shadow-xl group-hover:scale-110 transition-transform"></span>
                </button>

                {/* Popover */}
                {isOpen && (
                  <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-5 w-72 bg-surface border border-primary/20 rounded-3xl shadow-[0_15px_40px_rgba(0,0,0,0.2)] p-6 z-30 animate-in fade-in zoom-in-95 duration-200">
                    <h4 className="font-black text-sm mb-2 text-foreground">{step.title}</h4>
                    <p className="text-xs leading-relaxed text-muted-foreground font-medium">{step.content}</p>
                    <div className="absolute top-full left-1/2 -translate-x-1/2 border-[10px] border-transparent border-t-surface" />
                  </div>
                )}
              </div>
            )
          })}

          <div className="absolute bottom-10 right-10 z-50 pointer-events-auto">
            <button 
              onClick={(e) => {
                e.stopPropagation()
                handleDismiss()
              }}
              className="bg-primary text-white px-10 py-4 rounded-full text-xs font-black shadow-2xl hover:bg-primary/90 hover:scale-105 active:scale-95 transition-all flex items-center gap-3 group"
            >
              <span className="tracking-widest uppercase">Finish Discovery</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" className="group-hover:translate-x-1 transition-transform"><path d="m5 12 14 0"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      )}
    </div>
  )

  return createPortal(tourContent, document.body)
}
