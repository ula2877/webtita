import { createContext, useCallback, useContext, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

type ToastType = 'success' | 'error' | 'info'

interface ToastItem {
  id: number
  type: ToastType
  message: string
}

interface ToastContextValue {
  toast: (message: string, type?: ToastType) => void
  success: (message: string) => void
  error: (message: string) => void
  info: (message: string) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

let nextId = 1

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([])

  const remove = useCallback((id: number) => {
    setItems((prev) => prev.filter((item) => item.id !== id))
  }, [])

  const toast = useCallback(
    (message: string, type: ToastType = 'info') => {
      const id = nextId++
      setItems((prev) => [...prev, { id, type, message }])
      window.setTimeout(() => remove(id), 4500)
    },
    [remove],
  )

  const success = useCallback((message: string) => toast(message, 'success'), [toast])
  const error = useCallback((message: string) => toast(message, 'error'), [toast])
  const info = useCallback((message: string) => toast(message, 'info'), [toast])

  const value = useMemo<ToastContextValue>(
    () => ({ toast, success, error, info }),
    [toast, success, error, info],
  )

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div
        aria-live="polite"
        role="status"
        className="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4"
      >
        {items.map((item) => (
          <div
            key={item.id}
            role="alert"
            className={`pointer-events-auto w-full max-w-sm rounded-lg border px-4 py-3 text-sm shadow-sm ${
              item.type === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : item.type === 'error'
                  ? 'border-red-200 bg-red-50 text-red-800'
                  : 'border-sky-200 bg-sky-50 text-sky-800'
            }`}
          >
            {item.message}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext)
  if (!ctx) {
    throw new Error('useToast must be used within ToastProvider')
  }
  return ctx
}
