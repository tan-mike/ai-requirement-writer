import Link from 'next/link'

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center p-6">
      <Link href="/" className="flex items-center gap-2 mb-8 animate-in fade-in slide-in-from-top-4 duration-700 hover:opacity-80 transition-opacity">
        <div className="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-primary/20">A</div>
        <span className="font-bold text-2xl tracking-tight text-foreground">AI Requirement Writer</span>
      </Link>
      <div className="w-full max-w-md animate-in fade-in zoom-in-95 duration-500 delay-100">
        {children}
      </div>
    </div>
  )
}
