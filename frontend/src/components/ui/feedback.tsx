import type { ReactNode } from 'react'
import { IconAlert, IconInbox, IconRefresh } from './icons'
import { Button } from './Button'

export function Spinner({ className = 'h-5 w-5' }: { className?: string }) {
  return <IconRefresh className={`animate-spin ${className}`} aria-hidden="true" />
}

export function LoadingState({ label = 'Memuat data...' }: { label?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-slate-500">
      <Spinner className="h-6 w-6" />
      <p className="text-sm">{label}</p>
    </div>
  )
}

export function SkeletonRow() {
  return (
    <div className="animate-pulse space-y-3">
      {[0, 1, 2, 3, 4].map((i) => (
        <div key={i} className="h-12 rounded-lg bg-slate-100" />
      ))}
    </div>
  )
}

export function EmptyState({
  title,
  description,
  icon,
  action,
}: {
  title: string
  description?: string
  icon?: ReactNode
  action?: ReactNode
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
      <div className="mb-1 text-slate-300">
        {icon || <IconInbox className="h-10 w-10" />}
      </div>
      <h3 className="text-sm font-semibold text-slate-700">{title}</h3>
      {description && <p className="max-w-sm text-sm text-slate-500">{description}</p>}
      {action && <div className="mt-3">{action}</div>}
    </div>
  )
}

export function ErrorState({
  message,
  onRetry,
}: {
  message: string
  onRetry?: () => void
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-red-200 bg-red-50 py-10 text-center">
      <IconAlert className="h-8 w-8 text-red-400" />
      <p className="max-w-md px-4 text-sm text-red-700">{message}</p>
      {onRetry && (
        <Button variant="secondary" size="sm" onClick={onRetry}>
          Coba Lagi
        </Button>
      )}
    </div>
  )
}
