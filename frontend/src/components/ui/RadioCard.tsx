import type { ReactNode } from 'react'
import { IconCheck } from './icons'

export function RadioCard({
  name,
  value,
  label,
  description,
  checked,
  onChange,
}: {
  name: string
  value: string
  label: string
  description?: string
  checked: boolean
  onChange: (value: string) => void
}) {
  return (
    <label
      className={`flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-1 ${
        checked
          ? 'border-primary-500 bg-primary-50'
          : 'border-slate-200 bg-white hover:border-slate-300'
      }`}
    >
      <input
        type="radio"
        name={name}
        value={value}
        checked={checked}
        onChange={() => onChange(value)}
        className="sr-only"
      />
      <span
        aria-hidden="true"
        className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 ${
          checked ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-300 bg-white'
        }`}
      >
        {checked && <IconCheck className="h-3 w-3" />}
      </span>
      <span className="flex flex-col">
        <span className="text-sm font-medium text-slate-900">{label}</span>
        {description && <span className="text-xs text-slate-500">{description}</span>}
      </span>
    </label>
  )
}

export function RadioCardGroup({
  name,
  value,
  onChange,
  options,
}: {
  name: string
  value: string
  onChange: (value: string) => void
  options: { value: string; label: string; description?: string }[]
}) {
  return (
    <div role="radiogroup" className="grid gap-2">
      {options.map((option) => (
        <RadioCard
          key={option.value}
          name={name}
          value={option.value}
          label={option.label}
          description={option.description}
          checked={value === option.value}
          onChange={onChange}
        />
      ))}
    </div>
  )
}

export function FormError({ children }: { children: ReactNode }) {
  if (!children) return null
  return (
    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {children}
    </div>
  )
}
