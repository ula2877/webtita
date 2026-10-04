import { useEffect, useRef, useState } from 'react'
import type { ChangeEvent } from 'react'
import { IconSearch, IconX } from './icons'

export function SearchInput({
  value,
  onChange,
  placeholder,
  debounce = 300,
  name,
  label,
}: {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  debounce?: number
  name?: string
  label?: string
}) {
  const [text, setText] = useState(value)
  const timerRef = useRef<number | null>(null)

  useEffect(() => {
    setText(value)
  }, [value])

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    const next = event.target.value
    setText(next)
    if (timerRef.current) window.clearTimeout(timerRef.current)
    timerRef.current = window.setTimeout(() => onChange(next), debounce)
  }

  function clear() {
    setText('')
    if (timerRef.current) window.clearTimeout(timerRef.current)
    onChange('')
  }

  return (
    <div className="w-full">
      {label && (
        <label htmlFor={name} className="mb-1.5 block text-sm font-medium text-slate-700">
          {label}
        </label>
      )}
      <div className="relative w-full">
        <IconSearch className="pointer-events-none absolute left-4 top-1/2 z-10 h-5 w-5 -translate-y-1/2 text-slate-400" />
        <input
          id={name}
          name={name}
          type="search"
          value={text}
          onChange={handleChange}
          placeholder={placeholder}
          className="w-full rounded-lg border border-slate-300 bg-white py-2 pl-12 pr-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
        />
        {text && (
          <button
            type="button"
            onClick={clear}
            aria-label="Bersihkan pencarian"
            className="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          >
            <IconX className="h-4 w-4" />
          </button>
        )}
      </div>
    </div>
  )
}
