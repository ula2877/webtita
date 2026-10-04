import { useEffect } from 'react'
import type { ReactNode } from 'react'
import { IconX } from './icons'

export function Modal({
  open,
  title,
  onClose,
  children,
  size = 'md',
}: {
  open: boolean
  title: string
  onClose: () => void
  children: ReactNode
  size?: 'sm' | 'md' | 'lg'
}) {
  useEffect(() => {
    if (!open) return

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose()
      }
    }
    document.addEventListener('keydown', onKeyDown)
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', onKeyDown)
      document.body.style.overflow = ''
    }
  }, [open, onClose])

  if (!open) return null

  const sizeClasses = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      <div className="absolute inset-0 bg-slate-900/50" onClick={onClose} />
      <div
        className={`relative w-full ${sizeClasses[size]} rounded-xl bg-white shadow-lg`}
      >
        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 className="text-base font-semibold text-slate-900">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Tutup"
            className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
          >
            <IconX className="h-5 w-5" />
          </button>
        </div>
        <div className="max-h-[70vh] overflow-y-auto px-5 py-4">{children}</div>
      </div>
    </div>
  )
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Ya',
  cancelLabel = 'Batal',
  tone = 'danger',
  loading = false,
  onConfirm,
  onCancel,
}: {
  open: boolean
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  tone?: 'danger' | 'primary'
  loading?: boolean
  onConfirm: () => void
  onCancel: () => void
}) {
  return (
    <Modal open={open} title={title} onClose={onCancel} size="sm">
      <p className="text-sm text-slate-600">{message}</p>
      <div className="mt-5 flex justify-end gap-2">
        <button
          type="button"
          onClick={onCancel}
          className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
        >
          {cancelLabel}
        </button>
        <button
          type="button"
          onClick={onConfirm}
          disabled={loading}
          className={`rounded-lg px-4 py-2 text-sm font-medium text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 ${
            tone === 'danger'
              ? 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-500'
              : 'bg-primary-600 hover:bg-primary-700 focus-visible:ring-primary-500'
          }`}
        >
          {loading ? 'Memproses...' : confirmLabel}
        </button>
      </div>
    </Modal>
  )
}
