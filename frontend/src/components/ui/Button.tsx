import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { IconRefresh } from './icons'

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost'
type Size = 'sm' | 'md' | 'lg'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
  loading?: boolean
  children?: ReactNode
}

const variantClasses: Record<Variant, string> = {
  primary:
    'bg-primary-600 text-white hover:bg-primary-700 focus-visible:ring-primary-500 disabled:bg-primary-300',
  secondary:
    'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-slate-400 disabled:text-slate-400',
  danger:
    'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500 disabled:bg-red-300',
  ghost:
    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-slate-400',
}

const sizeClasses: Record<Size, string> = {
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2 text-sm',
  lg: 'px-5 py-2.5 text-sm',
}

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled,
  children,
  className = '',
  type = 'button',
  ...rest
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      className={`inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
      {...rest}
    >
      {loading ? (
        <>
          <IconRefresh className="h-4 w-4 animate-spin" />
          <span>{children}</span>
        </>
      ) : (
        children
      )}
    </button>
  )
}
