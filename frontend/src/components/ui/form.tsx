import type {
  InputHTMLAttributes,
  LabelHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react'
import { useState, useRef, useEffect } from 'react'
import { IconChevronDown, IconEye, IconEyeOff, IconX } from './icons'

export function Label({ className = '', ...rest }: LabelHTMLAttributes<HTMLLabelElement>) {
  return (
    <label
      className={`mb-1.5 block text-sm font-medium text-slate-700 ${className}`}
      {...rest}
    />
  )
}

export function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1 text-sm text-red-600">{message}</p>
}

const fieldClasses =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:bg-slate-100 disabled:text-slate-500'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  error?: boolean
  errorMessage?: string
  label?: string
  endAdornment?: ReactNode
}

export function Input({ error, errorMessage, label, className = '', id, endAdornment, ...rest }: InputProps) {
  const inputId = id || rest.name
  const hasEndAdornment = !!endAdornment
  return (
    <div className="relative">
      {label && <Label htmlFor={inputId}>{label}</Label>}
      <input
        id={inputId}
        className={`${fieldClasses} ${error ? 'border-red-500' : ''} ${className} ${hasEndAdornment ? 'pr-10' : ''}`}
        {...rest}
      />
      {hasEndAdornment && (
        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
          {endAdornment}
        </div>
      )}
      <FieldError message={errorMessage} />
    </div>
  )
}

interface PasswordInputProps extends Omit<InputProps, 'type' | 'endAdornment' | 'children'> {
  showPassword: boolean
  onToggleShowPassword: () => void
}

export function PasswordInput({
  error,
  errorMessage,
  label,
  className = '',
  id,
  showPassword,
  onToggleShowPassword,
  ...rest
}: PasswordInputProps) {
  const inputId = id || rest.name
  return (
    <div className="relative">
      {label && <Label htmlFor={inputId}>{label}</Label>}
      <input
        id={inputId}
        type={showPassword ? 'text' : 'password'}
        className={`${fieldClasses} ${error ? 'border-red-500' : ''} ${className} pr-10`}
        {...rest}
      />
      <button
        type="button"
        onClick={onToggleShowPassword}
        className="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center justify-center p-1 bg-transparent border-0 text-slate-400 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded"
        aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
      >
        {showPassword ? <IconEyeOff className="h-4 w-4" /> : <IconEye className="h-4 w-4" />}
      </button>
      <FieldError message={errorMessage} />
    </div>
  )
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  error?: boolean
  errorMessage?: string
  label?: string
  children?: ReactNode
}

export function Select({ error, errorMessage, label, className = '', id, children, ...rest }: SelectProps) {
  const selectId = id || rest.name
  return (
    <div>
      {label && <Label htmlFor={selectId}>{label}</Label>}
      <div className="relative">
        <select
          id={selectId}
          className={`${fieldClasses} appearance-none pr-9 ${error ? 'border-red-500' : ''} ${className}`}
          {...rest}
        >
          {children}
        </select>
        <IconChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      </div>
      <FieldError message={errorMessage} />
    </div>
  )
}

interface ComboboxOption {
  value: string | number
  label: string
}

interface ComboboxProps {
  label?: string
  placeholder?: string
  value: string | number | ''
  onChange: (value: string | number | '') => void
  options: ComboboxOption[]
  error?: boolean
  errorMessage?: string
  className?: string
  name?: string
  clearable?: boolean
  searchPlaceholder?: string
}

export function Combobox({
  label,
  placeholder = 'Pilih...',
  value,
  onChange,
  options,
  error,
  errorMessage,
  className = '',
  name,
  clearable = true,
  searchPlaceholder = 'Cari...',
}: ComboboxProps) {
  const [isOpen, setIsOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [highlightedIndex, setHighlightedIndex] = useState(-1)
  const containerRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const filteredOptions = options.filter((opt) =>
    opt.label.toLowerCase().includes(searchQuery.toLowerCase())
  )

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false)
        setSearchQuery('')
        setHighlightedIndex(-1)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  useEffect(() => {
    if (isOpen) {
      setSearchQuery('')
      setHighlightedIndex(-1)
      // Focus the search input when dropdown opens
      setTimeout(() => inputRef.current?.focus(), 0)
    }
  }, [isOpen])

  const handleKeyDown = (event: React.KeyboardEvent) => {
    const maxIndex = filteredOptions.length - 1
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        setHighlightedIndex((prev) => (prev < maxIndex ? prev + 1 : prev))
        break
      case 'ArrowUp':
        event.preventDefault()
        setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : prev))
        break
      case 'Enter':
        event.preventDefault()
        if (highlightedIndex >= 0 && highlightedIndex < filteredOptions.length) {
          onChange(filteredOptions[highlightedIndex].value)
          setIsOpen(false)
          setSearchQuery('')
          setHighlightedIndex(-1)
        }
        break
      case 'Escape':
        setIsOpen(false)
        setSearchQuery('')
        setHighlightedIndex(-1)
        break
    }
  }

  const handleOptionClick = (optionValue: string | number) => {
    onChange(optionValue)
    setIsOpen(false)
    setSearchQuery('')
    setHighlightedIndex(-1)
  }

  const handleClear = (event: React.MouseEvent) => {
    event.stopPropagation()
    onChange('')
    setSearchQuery('')
  }

  const displayValue = options.find((opt) => opt.value === value)?.label || placeholder

  return (
    <div className={className} ref={containerRef}>
      {label && <Label htmlFor={name}>{label}</Label>}
      <div className="relative">
        <div
          className={`${fieldClasses} ${error ? 'border-red-500' : ''} cursor-pointer flex items-center justify-between`}
          onClick={() => setIsOpen(!isOpen)}
          onKeyDown={handleKeyDown}
          tabIndex={0}
          role="combobox"
          aria-expanded={isOpen}
          aria-haspopup="listbox"
        >
          <span className={`flex-1 text-left ${value ? 'text-slate-900' : 'text-slate-400'}`}>
            {displayValue}
          </span>
          {clearable && value && (
            <button
              type="button"
              onClick={handleClear}
              className="ml-2 flex-shrink-0 p-1 text-slate-400 hover:text-slate-600 rounded"
              aria-label="Hapus pilihan"
            >
              <IconX className="h-4 w-4" />
            </button>
          )}
          <IconChevronDown className={`ml-2 flex-shrink-0 h-4 w-4 text-slate-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
        </div>

        {isOpen && (
          <div className="absolute z-50 w-full mt-1 max-h-64 overflow-auto rounded-lg border border-slate-300 bg-white shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <div className="p-2 border-b border-slate-100">
              <input
                ref={inputRef}
                type="text"
                placeholder={searchPlaceholder}
                className="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                onChange={(e) => {
                  setSearchQuery(e.target.value)
                  setHighlightedIndex(-1)
                }}
                onClick={(e) => e.stopPropagation()}
                onKeyDown={(e) => e.stopPropagation()}
                autoFocus
              />
            </div>
            <ul className="py-1 max-h-[300px] overflow-auto" role="listbox">
              {filteredOptions.length === 0 ? (
                <li className="px-3 py-2 text-sm text-slate-500 text-center" role="option" aria-disabled="true">
                  Tidak ada wilayah ditemukan
                </li>
              ) : (
                filteredOptions.map((option, index) => (
                  <li
                    key={option.value}
                    role="option"
                    aria-selected={index === highlightedIndex}
                    onClick={() => handleOptionClick(option.value)}
                    onMouseEnter={() => setHighlightedIndex(index)}
                    className={`px-3 py-2 text-sm cursor-pointer ${index === highlightedIndex ? 'bg-primary-50 text-primary-700' : 'text-slate-700 hover:bg-slate-50'}`}
                  >
                    {option.label}
                  </li>
                ))
              )}
            </ul>
          </div>
        )}
      </div>
      <FieldError message={errorMessage} />
    </div>
  )
}

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  error?: boolean
  errorMessage?: string
  label?: string
}

export function Textarea({ error, errorMessage, label, className = '', id, ...rest }: TextareaProps) {
  const textareaId = id || rest.name
  return (
    <div>
      {label && <Label htmlFor={textareaId}>{label}</Label>}
      <textarea
        id={textareaId}
        className={`${fieldClasses} ${error ? 'border-red-500' : ''} ${className}`}
        {...rest}
      />
      <FieldError message={errorMessage} />
    </div>
  )
}
